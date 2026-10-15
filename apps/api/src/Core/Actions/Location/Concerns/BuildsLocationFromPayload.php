<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Location\Concerns;

use Inventra\Core\Exceptions\ValidationException;

/**
 * Normalisasi dan validasi payload lokasi untuk Create dan Update.
 * Trait membaca $this->locations (LocationRepository) milik Action pemakainya.
 */
trait BuildsLocationFromPayload
{
    private const CODE_MAX_LENGTH = 32;
    private const NAME_MAX_LENGTH = 128;
    private const BUILDING_MAX_LENGTH = 128;
    private const FLOOR_MAX_LENGTH = 32;

    /**
     * @param array<string, mixed> $payload
     * @param int|null $exceptId Id lokasi yang sedang diperbarui, null saat create.
     * @return array<string, mixed>
     */
    private function locationFromPayload(array $payload, ?int $exceptId): array
    {
        $input = [
            'code' => $this->text($payload['code'] ?? ''),
            'name' => $this->text($payload['name'] ?? ''),
            'building' => $this->nullableText($payload['building'] ?? ''),
            'floor' => $this->nullableText($payload['floor'] ?? ''),
        ];

        $errors = [];

        if ($input['code'] === '') {
            $errors['code'] = 'Kode lokasi wajib diisi.';
        } elseif (strlen($input['code']) > self::CODE_MAX_LENGTH) {
            $errors['code'] = 'Kode lokasi maksimal ' . self::CODE_MAX_LENGTH . ' karakter.';
        } elseif ($this->locations->codeExists($input['code'], $exceptId)) {
            $errors['code'] = 'Kode lokasi sudah dipakai.';
        }

        if ($input['name'] === '') {
            $errors['name'] = 'Nama lokasi wajib diisi.';
        } elseif (strlen($input['name']) > self::NAME_MAX_LENGTH) {
            $errors['name'] = 'Nama lokasi maksimal ' . self::NAME_MAX_LENGTH . ' karakter.';
        }

        if ($input['building'] !== null && strlen($input['building']) > self::BUILDING_MAX_LENGTH) {
            $errors['building'] = 'Gedung maksimal ' . self::BUILDING_MAX_LENGTH . ' karakter.';
        }

        if ($input['floor'] !== null && strlen($input['floor']) > self::FLOOR_MAX_LENGTH) {
            $errors['floor'] = 'Lantai maksimal ' . self::FLOOR_MAX_LENGTH . ' karakter.';
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
