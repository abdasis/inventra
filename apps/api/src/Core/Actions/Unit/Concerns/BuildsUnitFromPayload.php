<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Unit\Concerns;

use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\UnitRepository;

/**
 * Normalisasi dan validasi payload satuan. Dipakai bersama oleh Action
 * create/update supaya aturan bentuk input tidak ditulis dua kali.
 */
trait BuildsUnitFromPayload
{
    /**
     * @param array<string, mixed> $payload
     * @return array{code: string, name: string, description: ?string}
     */
    private function unitFromPayload(UnitRepository $units, array $payload, ?int $exceptId = null): array
    {
        $input = [
            'code' => $this->text($payload['code'] ?? ''),
            'name' => $this->text($payload['name'] ?? ''),
            'description' => $this->optionalText($payload['description'] ?? null),
        ];

        $errors = [];

        if ($input['code'] === '') {
            $errors['code'] = 'Kode satuan wajib diisi.';
        } elseif (mb_strlen($input['code']) > 32) {
            $errors['code'] = 'Kode satuan maksimal 32 karakter.';
        } elseif ($units->codeExists($input['code'], $exceptId)) {
            $errors['code'] = 'Kode satuan sudah dipakai.';
        }

        if ($input['name'] === '') {
            $errors['name'] = 'Nama satuan wajib diisi.';
        } elseif (mb_strlen($input['name']) > 128) {
            $errors['name'] = 'Nama satuan maksimal 128 karakter.';
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

    private function optionalText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === '' ? null : $text;
    }
}
