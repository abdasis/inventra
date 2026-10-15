<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Purchase;

use Inventra\Core\Actions\Asset\CreateAssetAction;
use Inventra\Core\Enums\AcquisitionSource;
use Inventra\Core\Enums\ItemType;
use Inventra\Core\Enums\PurchaseStatus;
use Inventra\Core\Enums\StockMovementType;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\PurchaseRepository;
use Inventra\Core\Repositories\StockRepository;

/**
 * Mencatat penerimaan barang, sebagian maupun seluruhnya.
 *
 * Penerimaan dihitung sebagai tambahan terhadap jumlah yang sudah diterima dan
 * ditolak, lalu divalidasi terhadap sisa pesanan — jadi mengirim ulang baris yang
 * sama akan ditolak, bukan menggandakan stok. Barang habis pakai menambah saldo
 * dan satu catatan pergerakan bertipe "in"; barang berjenis aset melahirkan unit
 * aset baru lewat CreateAssetAction supaya aturan registrasi aset tidak ditulis
 * ulang di sini. Action ini tidak memegang transaksi: Service yang membungkusnya.
 */
final class ReceivePurchaseAction
{
    /** Lokasi gudang default untuk unit aset hasil penerimaan. */
    private const WAREHOUSE_CODE = 'LOC-GDG';

    /** CreateAssetAction membatasi satu panggilan maksimal 50 unit. */
    private const ASSET_BATCH = 50;

    public function __construct(
        private readonly PurchaseRepository $purchases,
        private readonly ItemRepository $items,
        private readonly LocationRepository $locations,
        private readonly StockRepository $stocks,
        private readonly AssetRepository $assets,
        private readonly CreateAssetAction $createAsset,
    ) {
    }

    /**
     * @param array<string, mixed> $payload details berisi {id, quantity_received, quantity_rejected?}.
     * @param string $actor Nama pelaku dari sesi, dipakai pada catatan pergerakan stok.
     * @return array{purchase: array<string, mixed>|null, details: array<int, array<string, mixed>>, lines: array<int, array<string, mixed>>}
     */
    public function execute(int $purchaseId, array $payload, string $actor): array
    {
        $purchase = $this->purchases->find($purchaseId)
            ?? throw new NotFoundException('Pembelian tidak ditemukan.');

        $status = PurchaseStatus::tryFrom((string)$purchase['status']) ?? PurchaseStatus::Draft;
        if ($status === PurchaseStatus::Cancelled) {
            throw new ValidationException('Pembelian yang dibatalkan tidak dapat diterima.');
        }
        if ($status === PurchaseStatus::Received) {
            throw new ValidationException('Pembelian sudah diterima seluruhnya.');
        }

        $lines = $this->receiveLines($payload, $this->detailsById($this->purchases->details($purchaseId)));

        if ($lines === []) {
            throw new ValidationException('Tidak ada baris penerimaan yang diproses.');
        }

        $locationId = null;
        foreach ($lines as $line) {
            $detail = $line['detail'];
            $received = $line['received'];

            $this->purchases->receive(
                (int)$detail['id'],
                (int)$detail['quantity_received'] + $received,
                (int)$detail['quantity_rejected'] + $line['rejected']
            );

            if ($received === 0) {
                continue;
            }

            if (($detail['item_type'] ?? '') === ItemType::Consumable->value) {
                $this->addStock($purchase, $detail, $received, $actor);
                continue;
            }

            // Lokasi gudang dicari sekali saja, dan hanya bila memang ada unit aset.
            $locationId ??= $this->warehouseLocationId();
            $this->registerAssets($purchase, $detail, $received, $locationId);
        }

        $this->settleStatus($purchaseId);

        return [
            'purchase' => $this->purchases->find($purchaseId),
            'details' => $this->purchases->details($purchaseId),
            'lines' => $this->narrationLines($lines),
        ];
    }

    /**
     * Menyaring baris yang benar-benar diproses dan memvalidasinya terhadap sisa
     * pesanan. Baris tanpa penerimaan maupun penolakan diabaikan, dan pengiriman
     * ganda atas baris yang sama dijumlahkan lebih dulu supaya sisa pesanan tetap
     * terjaga.
     *
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $details Baris pembelian, berkunci id.
     * @return array<int, array{detail: array<string, mixed>, received: int, rejected: int}>
     */
    private function receiveLines(array $payload, array $details): array
    {
        $rows = is_array($payload['details'] ?? null) ? array_values($payload['details']) : [];
        $errors = [];
        $pending = [];

        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $key = 'details.' . $index;

            $detailId = $this->intValue($row['id'] ?? null);
            if ($detailId <= 0 || !isset($details[$detailId])) {
                $errors[$key . '.id'] = 'Baris pembelian tidak ditemukan.';
                continue;
            }

            $received = $this->quantityValue($row['quantity_received'] ?? null);
            if ($received === null || $received < 0) {
                $errors[$key . '.quantity_received'] = 'Jumlah diterima tidak valid.';
                continue;
            }

            $rejected = $this->quantityValue($row['quantity_rejected'] ?? null);
            if ($rejected === null || $rejected < 0) {
                $errors[$key . '.quantity_rejected'] = 'Jumlah ditolak tidak valid.';
                continue;
            }

            $pending[$detailId]['received'] = ($pending[$detailId]['received'] ?? 0) + $received;
            $pending[$detailId]['rejected'] = ($pending[$detailId]['rejected'] ?? 0) + $rejected;
            $pending[$detailId]['key'] = $key;
        }

        $lines = [];
        foreach ($pending as $detailId => $delta) {
            if ($delta['received'] === 0 && $delta['rejected'] === 0) {
                continue;
            }

            $detail = $details[$detailId];
            $remaining = (int)$detail['quantity_ordered']
                - (int)$detail['quantity_received']
                - (int)$detail['quantity_rejected'];

            if ($delta['received'] + $delta['rejected'] > $remaining) {
                $errors[$delta['key'] . '.quantity_received'] = 'Jumlah diterima melebihi sisa pesanan.';
                continue;
            }

            $lines[] = ['detail' => $detail, 'received' => $delta['received'], 'rejected' => $delta['rejected']];
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        return $lines;
    }

    /**
     * Menambah saldo barang habis pakai dan mencatat pergerakannya sebagai stok
     * masuk yang menunjuk ke pembelian ini.
     *
     * @param array<string, mixed> $purchase
     * @param array<string, mixed> $detail
     */
    private function addStock(array $purchase, array $detail, int $quantity, string $actor): void
    {
        $itemId = (int)$detail['item_id'];
        $balance = $this->currentQuantity($itemId) + $quantity;

        $this->stocks->setBalance($itemId, $balance);
        $this->stocks->recordMovement([
            'item_id' => $itemId,
            'movement_type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'reference_type' => 'purchase',
            'reference_id' => (int)$purchase['id'],
            'actor_name' => $actor,
        ]);
    }

    /**
     * Melahirkan satu unit aset per barang yang diterima. Registrasinya memakai
     * jalur yang sama dengan pendaftaran aset manual, lalu tiap unit ditautkan ke
     * baris pesanannya sebagai jejak asal-usul.
     *
     * @param array<string, mixed> $purchase
     * @param array<string, mixed> $detail
     */
    private function registerAssets(array $purchase, array $detail, int $quantity, int $locationId): void
    {
        $item = $this->items->find((int)$detail['item_id'])
            ?? throw new ValidationException('Barang tidak ditemukan.');

        $remaining = $quantity;
        while ($remaining > 0) {
            $batch = min(self::ASSET_BATCH, $remaining);

            $result = $this->createAsset->execute([
                'name' => (string)$item['name'],
                'item_id' => (int)$item['id'],
                'category_id' => (int)$item['category_id'],
                'location_id' => $locationId,
                'acquisition_source' => AcquisitionSource::Purchase->value,
                'purchase_reference' => (string)$purchase['purchase_number'],
                'purchase_date' => (string)$purchase['purchase_date'],
                'purchase_cost' => (float)$detail['unit_price'],
                'quantity' => $batch,
            ]);

            foreach ($result['ids'] as $assetId) {
                $this->assets->linkPurchase((int)$assetId, (int)$purchase['id'], (int)$detail['id']);
            }

            $remaining -= $batch;
        }
    }

    /**
     * Menetapkan status akhir: diterima seluruhnya bila tiap baris sudah terpenuhi
     * oleh penerimaan maupun penolakan, selain itu diterima sebagian.
     */
    private function settleStatus(int $purchaseId): PurchaseStatus
    {
        $details = $this->purchases->details($purchaseId);
        $complete = $details !== [];

        foreach ($details as $row) {
            if ((int)$row['quantity_received'] + (int)$row['quantity_rejected'] < (int)$row['quantity_ordered']) {
                $complete = false;
                break;
            }
        }

        if ($complete) {
            $this->purchases->markStatus($purchaseId, PurchaseStatus::Received, [
                'received_at' => date('Y-m-d H:i:s'),
            ]);

            return PurchaseStatus::Received;
        }

        $this->purchases->markStatus($purchaseId, PurchaseStatus::Partial);

        return PurchaseStatus::Partial;
    }

    /**
     * Lokasi gudang default, jatuh ke lokasi pertama bila kode bakunya belum ada.
     */
    private function warehouseLocationId(): int
    {
        $location = $this->locations->findByCode(self::WAREHOUSE_CODE)
            ?? ($this->locations->all()[0] ?? null);

        if ($location === null) {
            throw new ValidationException(
                'Lokasi penyimpanan belum tersedia. Daftarkan lokasi gudang terlebih dahulu.'
            );
        }

        return (int)$location['id'];
    }

    private function currentQuantity(int $itemId): int
    {
        $balance = $this->stocks->balance($itemId);

        return $balance !== null ? (int)$balance['quantity'] : 0;
    }

    /**
     * @param array<int, array<string, mixed>> $details
     * @return array<int, array<string, mixed>> Baris berkunci id-nya.
     */
    private function detailsById(array $details): array
    {
        $indexed = [];

        foreach ($details as $detail) {
            $indexed[(int)$detail['id']] = $detail;
        }

        return $indexed;
    }

    /**
     * Ringkasan baris yang diterima, dipakai Service untuk menarasikan activity log.
     *
     * @param array<int, array{detail: array<string, mixed>, received: int, rejected: int}> $lines
     * @return array<int, array<string, mixed>>
     */
    private function narrationLines(array $lines): array
    {
        $summary = [];

        foreach ($lines as $line) {
            if ($line['received'] === 0) {
                continue;
            }

            $summary[] = [
                'item_name' => (string)($line['detail']['item_name'] ?? ''),
                'item_type' => (string)($line['detail']['item_type'] ?? ''),
                'quantity' => $line['received'],
            ];
        }

        return $summary;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Kosong berarti nol; null dikembalikan hanya bila nilainya bukan angka.
     */
    private function quantityValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return is_numeric($value) ? (int)$value : null;
    }
}
