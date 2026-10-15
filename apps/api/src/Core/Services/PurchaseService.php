<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Purchase\CancelPurchaseAction;
use Inventra\Core\Actions\Purchase\CreatePurchaseAction;
use Inventra\Core\Actions\Purchase\DeletePurchaseAction;
use Inventra\Core\Actions\Purchase\ReceivePurchaseAction;
use Inventra\Core\Actions\Purchase\UpdatePurchaseAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\PurchaseDTO;
use Inventra\Core\DTO\PurchaseDetailDTO;
use Inventra\Core\Enums\ItemType;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\PurchaseRepository;
use Inventra\Core\Support\ActivityLogger;
use Inventra\Core\Support\FieldChangeNarrator;

/**
 * Orkestrasi use case pengadaan: memanggil Action, memegang transaksi untuk use
 * case yang menulis lebih dari satu tabel — penerimaan sekaligus menyentuh baris
 * pesanan, saldo stok, pergerakan stok, dan unit aset — lalu menarasikan activity
 * log. Controller hanya meneruskan hasilnya ke Response.
 */
final class PurchaseService
{
    public function __construct(
        private readonly PurchaseRepository $purchases,
        private readonly CreatePurchaseAction $createPurchase,
        private readonly UpdatePurchaseAction $updatePurchase,
        private readonly ReceivePurchaseAction $receivePurchase,
        private readonly CancelPurchaseAction $cancelPurchase,
        private readonly DeletePurchaseAction $deletePurchase,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload, string $actorName): array
    {
        $id = Connection::transaction(fn (): int => $this->createPurchase->execute($payload, $actorName));
        $purchase = $this->row($id);
        $data = $this->present($purchase);

        ActivityLogger::log(
            'create',
            'Mencatat pembelian ' . $data['purchase_number'] . $this->supplierSuffix($data)
                . ' (' . count($data['details']) . ' baris barang, total ' . $data['total_amount_formatted'] . ')',
            'purchase',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Pembelian berhasil dicatat', 'data' => $data];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        $before = $this->purchases->find($id) ?? [];
        Connection::transaction(function () use ($id, $payload): void {
            $this->updatePurchase->execute($id, $payload);
        });
        $data = $this->present($this->row($id));

        ActivityLogger::log(
            'update',
            'Memperbarui pembelian ' . $data['purchase_number'] . $this->fieldChanges($before, $data),
            'purchase',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data pembelian berhasil diperbarui', 'data' => $data];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function detail(int $id): array
    {
        return ['data' => $this->present($this->row($id))];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function receive(int $id, array $payload, string $actorName): array
    {
        $result = Connection::transaction(fn (): array => $this->receivePurchase->execute($id, $payload, $actorName));
        $data = $this->present($result['purchase'] ?? $this->row($id));
        $number = (string)$data['purchase_number'];

        foreach ($result['lines'] as $line) {
            ActivityLogger::log(
                'receive',
                $this->receiveDescription($line, $number),
                'purchase',
                $id,
                $this->ipAddress
            );
        }

        return ['message' => 'Penerimaan barang berhasil dicatat', 'data' => $data];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string}
     */
    public function cancel(int $id, array $payload): array
    {
        $purchase = $this->cancelPurchase->execute($id, $payload);

        ActivityLogger::log(
            'cancel',
            'Membatalkan pembelian ' . $purchase['purchase_number']
                . ' (alasan: ' . (string)$purchase['cancel_reason'] . ')',
            'purchase',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Pembelian berhasil dibatalkan'];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id): array
    {
        $purchase = $this->deletePurchase->execute($id);

        ActivityLogger::log(
            'delete',
            'Menghapus pembelian ' . $purchase['purchase_number'],
            'purchase',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Pembelian berhasil dihapus'];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id): array
    {
        return $this->purchases->find($id) ?? throw new NotFoundException('Pembelian tidak ditemukan.');
    }

    /**
     * Header dipetakan lewat DTO supaya label status dan format rupiah sampai ke
     * klien, lalu baris pesanannya ditempelkan sebagai key "details".
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $data = PurchaseDTO::fromArray($row)->toArray();
        $data['details'] = array_map(
            static fn (array $detail): array => PurchaseDetailDTO::fromArray($detail)->toArray(),
            $this->purchases->details((int)$row['id'])
        );

        return $data;
    }

    /**
     * @param array<string, mixed> $data Hasil present().
     */
    private function supplierSuffix(array $data): string
    {
        return empty($data['supplier_name']) ? '' : ' dari ' . $data['supplier_name'];
    }

    /**
     * @param array<string, mixed> $line Ringkasan baris dari ReceivePurchaseAction.
     */
    private function receiveDescription(array $line, string $number): string
    {
        $type = ItemType::tryFrom((string)($line['item_type'] ?? ''));

        return 'Menerima ' . (int)$line['quantity'] . ' unit '
            . ($type === ItemType::Asset ? 'aset ' : '')
            . (string)$line['item_name'] . ' dari pembelian ' . $number;
    }

    /**
     * @param array<string, mixed> $before Baris sebelum update.
     * @param array<string, mixed> $after Data hasil present().
     */
    private function fieldChanges(array $before, array $after): string
    {
        $labels = [
            'supplier' => 'supplier',
            'purchase_date' => 'tanggal pembelian',
            'reference' => 'referensi',
        ];

        return FieldChangeNarrator::describe(
            [
                'supplier' => $before['supplier_name'] ?? null,
                'purchase_date' => $before['purchase_date'] ?? null,
                'reference' => $before['reference'] ?? null,
            ],
            [
                'supplier' => $after['supplier_name'] ?? null,
                'purchase_date' => $after['purchase_date'] ?? null,
                'reference' => $after['reference'] ?? null,
            ],
            $labels
        );
    }
}
