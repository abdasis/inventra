<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset\Concerns;

use DateTimeImmutable;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Enums\AcquisitionSource;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Normalisasi dan validasi payload aset. Dipakai bersama oleh CreateAssetAction
 * dan UpdateAssetAction supaya aturan bentuk input tidak ditulis dua kali, dan
 * supaya kegagalan validasi tetap terhitung sebagai 422 per field.
 */
trait BuildsAssetFromPayload
{
    /**
     * @param array<string, mixed> $payload
     * @param bool $isRegistration Registrasi memaksa status tersedia dan pemegang kosong.
     * @return array<string, mixed> Input ternormalisasi siap diserahkan ke AssetDTO.
     */
    private function assetFromPayload(
        AssetRepository $assets,
        array $payload,
        ?int $exceptId = null,
        bool $isRegistration = false
    ): array {
        $input = [
            'asset_code' => $this->assetText($payload['asset_code'] ?? ''),
            'name' => $this->assetText($payload['name'] ?? ''),
            'category_id' => $this->assetInt($payload['category_id'] ?? null),
            'location_id' => $this->assetInt($payload['location_id'] ?? null),
            'item_id' => $this->assetOptionalInt($payload['item_id'] ?? null),
            'serial_number' => $this->assetOptionalText($payload['serial_number'] ?? null),
            'purchase_date' => $this->assetOptionalText($payload['purchase_date'] ?? null),
            'purchase_cost' => $this->assetCost($payload['purchase_cost'] ?? null),
            'acquisition_source' => $this->assetText($payload['acquisition_source'] ?? '') ?: AcquisitionSource::Purchase->value,
            'purchase_reference' => $this->assetOptionalText($payload['purchase_reference'] ?? null),
            'warranty_expiry' => $this->assetOptionalText($payload['warranty_expiry'] ?? null),
            'photo_path' => $this->assetOptionalText($payload['photo_path'] ?? null),
            'document_path' => $this->assetOptionalText($payload['document_path'] ?? null),
            'condition_level' => $this->assetText($payload['condition_level'] ?? '') ?: AssetCondition::Good->value,
            'notes' => $this->assetOptionalText($payload['notes'] ?? null),
            'quantity' => $this->assetQuantity($payload['quantity'] ?? null),
            'status' => $isRegistration
                ? AssetStatus::Available->value
                : $this->assetText($payload['status'] ?? ''),
            'assigned_to' => $isRegistration ? null : ($payload['assigned_to'] ?? null),
        ];

        $item = $input['item_id'] !== null ? $assets->item($input['item_id']) : null;
        $errors = $this->assetErrors($input, $assets, $item, $exceptId);

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        // Jenis barang adalah master yang membawa kategorinya sendiri, jadi saat
        // aset ditautkan ke jenis barang kategorinya diambil dari sana.
        if ($item !== null) {
            $input['category_id'] = (int)$item['category_id'];
        }

        return $input;
    }

    /**
     * @param array<string, mixed> $input Hasil normalisasi.
     * @param array<string, mixed>|null $item Baris jenis barang, null bila item_id kosong.
     * @return array<string, string> Peta error per field, kosong bila valid.
     */
    private function assetErrors(array $input, AssetRepository $assets, ?array $item, ?int $exceptId): array
    {
        $errors = [];

        if ($input['name'] === '') {
            $errors['name'] = 'Nama aset wajib diisi.';
        }

        if ($input['item_id'] === null) {
            if ($input['category_id'] <= 0) {
                $errors['category_id'] = 'Kategori wajib diisi.';
            } elseif (!$assets->categoryExists($input['category_id'])) {
                $errors['category_id'] = 'Kategori tidak ditemukan.';
            }
        } elseif ($item === null) {
            $errors['item_id'] = 'Barang tidak ditemukan.';
        }

        if ($input['location_id'] <= 0) {
            $errors['location_id'] = 'Lokasi wajib diisi.';
        } elseif (!$assets->locationExists($input['location_id'])) {
            $errors['location_id'] = 'Lokasi tidak ditemukan.';
        }

        if ($input['serial_number'] !== null && mb_strlen($input['serial_number']) > 128) {
            $errors['serial_number'] = 'Nomor seri maksimal 128 karakter.';
        }

        if ($input['purchase_date'] !== null && !$this->assetDate($input['purchase_date'])) {
            $errors['purchase_date'] = 'Tanggal perolehan tidak valid.';
        }

        if ($input['purchase_cost'] === null) {
            $errors['purchase_cost'] = 'Harga perolehan harus berupa angka.';
        } elseif ($input['purchase_cost'] < 0) {
            $errors['purchase_cost'] = 'Harga perolehan tidak boleh negatif.';
        }

        if (AcquisitionSource::tryFrom($input['acquisition_source']) === null) {
            $errors['acquisition_source'] = 'Sumber perolehan tidak valid.';
        }

        if ($input['purchase_reference'] !== null && mb_strlen($input['purchase_reference']) > 128) {
            $errors['purchase_reference'] = 'Referensi pengadaan maksimal 128 karakter.';
        }

        if ($input['warranty_expiry'] !== null && !$this->assetDate($input['warranty_expiry'])) {
            $errors['warranty_expiry'] = 'Masa garansi tidak valid.';
        } elseif (
            $input['warranty_expiry'] !== null
            && $input['purchase_date'] !== null
            && $this->assetDate($input['purchase_date'])
            && $this->assetDate($input['warranty_expiry'])
            && $input['warranty_expiry'] < $input['purchase_date']
        ) {
            $errors['warranty_expiry'] = 'Masa garansi tidak boleh lebih awal dari tanggal perolehan.';
        }

        if ($input['photo_path'] !== null && mb_strlen($input['photo_path']) > 255) {
            $errors['photo_path'] = 'Tautan foto maksimal 255 karakter.';
        }

        if ($input['document_path'] !== null && mb_strlen($input['document_path']) > 255) {
            $errors['document_path'] = 'Tautan dokumen maksimal 255 karakter.';
        }

        if (AssetCondition::tryFrom($input['condition_level']) === null) {
            $errors['condition_level'] = 'Kondisi fisik tidak valid.';
        }

        if ($input['quantity'] === null || $input['quantity'] < 1 || $input['quantity'] > 50) {
            $errors['quantity'] = 'Jumlah unit harus antara 1 dan 50.';
        }

        if ($input['asset_code'] !== '') {
            if (mb_strlen($input['asset_code']) > 64) {
                $errors['asset_code'] = 'Kode aset maksimal 64 karakter.';
            } elseif ($assets->codeExists($input['asset_code'], $exceptId)) {
                $errors['asset_code'] = 'Kode aset sudah dipakai.';
            } elseif ($input['quantity'] !== null && $input['quantity'] > 1) {
                $errors['asset_code'] = 'Kode aset manual hanya untuk satu unit. Kosongkan bila mendaftarkan beberapa unit.';
            }
        }

        return $errors;
    }

    /**
     * Format tanggal yang diterima hanya Y-m-d dan tanggalnya harus benar-benar
     * ada, supaya "2026-02-31" tidak lolos.
     */
    private function assetDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function assetText(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function assetOptionalText(mixed $value): ?string
    {
        $text = $this->assetText($value);

        return $text === '' ? null : $text;
    }

    private function assetInt(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    private function assetOptionalInt(mixed $value): ?int
    {
        $int = $this->assetInt($value);

        return $int > 0 ? $int : null;
    }

    /**
     * Kosong berarti 0; null dikembalikan hanya bila nilainya bukan angka.
     */
    private function assetCost(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * Kosong berarti 1 unit; null dikembalikan bila nilainya bukan angka.
     */
    private function assetQuantity(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 1;
        }

        return is_numeric($value) ? (int)$value : null;
    }
}
