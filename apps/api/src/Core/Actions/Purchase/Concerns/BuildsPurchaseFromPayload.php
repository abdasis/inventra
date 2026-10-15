<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Purchase\Concerns;

use DateTimeImmutable;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\PurchaseRepository;
use Inventra\Core\Repositories\SupplierRepository;

/**
 * Normalisasi dan validasi payload pembelian, dipakai bersama Action create dan
 * update supaya aturan bentuk input tidak ditulis dua kali. Kesalahannya
 * dikumpulkan per field — termasuk per baris pesanan — lalu dilempar sekali
 * sebagai ValidationException.
 */
trait BuildsPurchaseFromPayload
{
    /**
     * @param array<string, mixed> $payload
     * @param int|null $exceptId Id pembelian yang dikecualikan saat memeriksa nomor.
     * @return array{header: array<string, mixed>, details: array<int, array<string, mixed>>}
     */
    private function purchaseFromPayload(
        PurchaseRepository $purchases,
        ItemRepository $items,
        SupplierRepository $suppliers,
        array $payload,
        ?int $exceptId = null
    ): array {
        $header = [
            'purchase_number' => $this->purchaseText($payload['purchase_number'] ?? ''),
            'supplier_id' => $this->purchaseOptionalInt($payload['supplier_id'] ?? null),
            'purchase_date' => $this->purchaseText($payload['purchase_date'] ?? ''),
            'reference' => $this->purchaseOptionalText($payload['reference'] ?? null),
            'notes' => $this->purchaseOptionalText($payload['notes'] ?? null),
        ];

        $rows = is_array($payload['details'] ?? null) ? array_values($payload['details']) : [];
        $errors = [];

        if (!$this->purchaseDate($header['purchase_date'])) {
            $errors['purchase_date'] = 'Tanggal pembelian tidak valid.';
        }

        if ($header['supplier_id'] !== null && $suppliers->find($header['supplier_id']) === null) {
            $errors['supplier_id'] = 'Supplier tidak ditemukan.';
        }

        if ($header['purchase_number'] !== '') {
            if (mb_strlen($header['purchase_number']) > 64) {
                $errors['purchase_number'] = 'Nomor pembelian maksimal 64 karakter.';
            } elseif ($purchases->numberExists($header['purchase_number'], $exceptId)) {
                $errors['purchase_number'] = 'Nomor pembelian sudah dipakai.';
            }
        }

        if ($header['reference'] !== null && mb_strlen($header['reference']) > 128) {
            $errors['reference'] = 'Referensi maksimal 128 karakter.';
        }

        if ($rows === []) {
            $errors['details'] = 'Pembelian harus memiliki minimal satu baris barang.';
        }

        $details = [];
        $total = 0.0;

        foreach ($rows as $index => $row) {
            [$detail, $detailErrors] = $this->purchaseDetail(
                $items,
                is_array($row) ? $row : [],
                'details.' . $index
            );

            foreach ($detailErrors as $field => $message) {
                $errors[$field] = $message;
            }

            $total += (int)$detail['quantity_ordered'] * (float)$detail['unit_price'];
            $details[] = $detail;
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        $header['total_amount'] = $total;

        return ['header' => $header, 'details' => $details];
    }

    /**
     * @param array<string, mixed> $row Baris payload mentah.
     * @param string $keyPrefix Awalan kunci error, mis. "details.0".
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function purchaseDetail(ItemRepository $items, array $row, string $keyPrefix): array
    {
        $itemId = $this->purchaseInt($row['item_id'] ?? null);
        $quantity = $this->purchaseQuantity($row['quantity_ordered'] ?? null);
        $unitPrice = $this->purchasePrice($row['unit_price'] ?? null);

        $detail = [
            'item_id' => $itemId,
            'quantity_ordered' => (int)$quantity,
            'unit_price' => (float)$unitPrice,
            'notes' => $this->purchaseOptionalText($row['notes'] ?? null),
        ];
        $errors = [];

        if ($itemId <= 0 || $items->find($itemId) === null) {
            $errors[$keyPrefix . '.item_id'] = 'Barang tidak ditemukan.';
        }

        if ($quantity === null || $quantity <= 0) {
            $errors[$keyPrefix . '.quantity_ordered'] = 'Jumlah dipesan harus lebih dari 0.';
        }

        if ($unitPrice === null) {
            $errors[$keyPrefix . '.unit_price'] = 'Harga satuan harus berupa angka.';
        } elseif ($unitPrice < 0) {
            $errors[$keyPrefix . '.unit_price'] = 'Harga satuan tidak boleh negatif.';
        }

        return [$detail, $errors];
    }

    /**
     * Format tanggal yang diterima hanya Y-m-d dan tanggalnya harus benar-benar
     * ada, supaya "2026-02-31" tidak lolos.
     */
    private function purchaseDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function purchaseText(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function purchaseOptionalText(mixed $value): ?string
    {
        $text = $this->purchaseText($value);

        return $text === '' ? null : $text;
    }

    private function purchaseInt(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    private function purchaseOptionalInt(mixed $value): ?int
    {
        $int = $this->purchaseInt($value);

        return $int > 0 ? $int : null;
    }

    /**
     * Kosong berarti 1; null dikembalikan hanya bila nilainya bukan angka.
     */
    private function purchaseQuantity(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 1;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * Kosong berarti 0; null dikembalikan hanya bila nilainya bukan angka.
     */
    private function purchasePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return is_numeric($value) ? (float)$value : null;
    }
}
