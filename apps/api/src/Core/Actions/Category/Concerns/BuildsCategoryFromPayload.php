<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Category\Concerns;

use Inventra\Core\Exceptions\ValidationException;

/**
 * Normalisasi dan validasi payload kategori untuk Create dan Update. Dipakai
 * trait supaya kedua Action berbagi aturan yang sama persis.
 *
 * Trait membaca $this->categories (CategoryRepository) milik Action pemakainya.
 */
trait BuildsCategoryFromPayload
{
    private const CODE_MAX_LENGTH = 32;
    private const NAME_MAX_LENGTH = 128;

    /**
     * @param array<string, mixed> $payload
     * @param int|null $exceptId Id kategori yang sedang diperbarui, null saat create.
     * @return array<string, mixed>
     */
    private function categoryFromPayload(array $payload, ?int $exceptId): array
    {
        $input = [
            'code' => $this->text($payload['code'] ?? ''),
            'name' => $this->text($payload['name'] ?? ''),
            'description' => $this->nullableText($payload['description'] ?? ''),
            'parent_id' => !empty($payload['parent_id']) ? (int)$payload['parent_id'] : null,
        ];

        $errors = [];

        if ($input['code'] === '') {
            $errors['code'] = 'Kode kategori wajib diisi.';
        } elseif (strlen($input['code']) > self::CODE_MAX_LENGTH) {
            $errors['code'] = 'Kode kategori maksimal ' . self::CODE_MAX_LENGTH . ' karakter.';
        } elseif ($this->categories->codeExists($input['code'], $exceptId)) {
            $errors['code'] = 'Kode kategori sudah dipakai.';
        }

        if ($input['name'] === '') {
            $errors['name'] = 'Nama kategori wajib diisi.';
        } elseif (strlen($input['name']) > self::NAME_MAX_LENGTH) {
            $errors['name'] = 'Nama kategori maksimal ' . self::NAME_MAX_LENGTH . ' karakter.';
        }

        if ($input['parent_id'] !== null) {
            if ($input['parent_id'] === $exceptId) {
                $errors['parent_id'] = 'Kategori tidak dapat menjadi induk dirinya sendiri.';
            } elseif ($this->categories->find($input['parent_id']) === null) {
                $errors['parent_id'] = 'Kategori induk tidak ditemukan.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        return $input;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function nullableText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === '' ? null : $text;
    }
}
