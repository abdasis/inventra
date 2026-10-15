<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Item\Concerns;

use Inventra\Core\Enums\ItemType;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ItemRepository;

/**
 * Normalisasi dan validasi payload barang. Dipakai bersama oleh Action
 * create/update supaya aturan bentuk input tidak ditulis dua kali.
 */
trait BuildsItemFromPayload
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function itemFromPayload(ItemRepository $items, array $payload, ?int $exceptId = null): array
    {
        $input = [
            'code' => $this->text($payload['code'] ?? ''),
            'name' => $this->text($payload['name'] ?? ''),
            'brand' => $this->optionalText($payload['brand'] ?? null),
            'model' => $this->optionalText($payload['model'] ?? null),
            'specification' => $this->optionalText($payload['specification'] ?? null),
            'category_id' => $this->intValue($payload['category_id'] ?? null),
            'unit_id' => $this->intValue($payload['unit_id'] ?? null),
            'item_type' => $this->text($payload['item_type'] ?? ''),
            'minimum_stock' => $this->stockValue($payload['minimum_stock'] ?? null),
            'description' => $this->optionalText($payload['description'] ?? null),
        ];

        $errors = $this->itemErrors($input, $items, $exceptId);

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        return $input;
    }

    /**
     * @param array<string, mixed> $input Hasil normalisasi.
     * @return array<string, string> Peta error per field, kosong bila valid.
     */
    private function itemErrors(array $input, ItemRepository $items, ?int $exceptId): array
    {
        $errors = [];

        if ($input['code'] === '') {
            $errors['code'] = 'Kode barang wajib diisi.';
        } elseif (mb_strlen($input['code']) > 32) {
            $errors['code'] = 'Kode barang maksimal 32 karakter.';
        } elseif ($items->codeExists($input['code'], $exceptId)) {
            $errors['code'] = 'Kode barang sudah dipakai.';
        }

        if ($input['name'] === '') {
            $errors['name'] = 'Nama barang wajib diisi.';
        } elseif (mb_strlen($input['name']) > 160) {
            $errors['name'] = 'Nama barang maksimal 160 karakter.';
        }

        if ($input['brand'] !== null && mb_strlen($input['brand']) > 128) {
            $errors['brand'] = 'Merek maksimal 128 karakter.';
        }

        if ($input['model'] !== null && mb_strlen($input['model']) > 128) {
            $errors['model'] = 'Model maksimal 128 karakter.';
        }

        if ($input['category_id'] <= 0) {
            $errors['category_id'] = 'Kategori wajib diisi.';
        } elseif (!$items->categoryExists($input['category_id'])) {
            $errors['category_id'] = 'Kategori tidak ditemukan.';
        }

        if ($input['unit_id'] <= 0) {
            $errors['unit_id'] = 'Satuan wajib diisi.';
        } elseif (!$items->unitExists($input['unit_id'])) {
            $errors['unit_id'] = 'Satuan tidak ditemukan.';
        }

        if (ItemType::tryFrom($input['item_type']) === null) {
            $errors['item_type'] = 'Jenis barang tidak valid.';
        }

        if ($input['minimum_stock'] === null) {
            $errors['minimum_stock'] = 'Stok minimum harus berupa angka.';
        } elseif ($input['minimum_stock'] < 0) {
            $errors['minimum_stock'] = 'Stok minimum tidak boleh negatif.';
        }

        return $errors;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function optionalText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === '' ? null : $text;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Kosong berarti default 0; null dikembalikan hanya bila nilainya bukan angka.
     */
    private function stockValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return is_numeric($value) ? (int)$value : null;
    }
}
