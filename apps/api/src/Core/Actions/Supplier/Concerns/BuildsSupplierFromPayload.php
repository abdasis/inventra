<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Supplier\Concerns;

use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Support\BooleanInput;

/**
 * Normalisasi dan validasi payload supplier untuk Create dan Update.
 * Trait membaca $this->suppliers (SupplierRepository) milik Action pemakainya.
 */
trait BuildsSupplierFromPayload
{
    private const CODE_MAX_LENGTH = 32;
    private const NAME_MAX_LENGTH = 128;
    private const CONTACT_MAX_LENGTH = 128;
    private const EMAIL_MAX_LENGTH = 190;
    private const PHONE_MAX_LENGTH = 32;

    /**
     * @param array<string, mixed> $payload
     * @param int|null $exceptId Id supplier yang sedang diperbarui, null saat create.
     * @return array<string, mixed>
     */
    private function supplierFromPayload(array $payload, ?int $exceptId): array
    {
        $input = [
            'code' => $this->text($payload['code'] ?? ''),
            'name' => $this->text($payload['name'] ?? ''),
            'contact_person' => $this->nullableText($payload['contact_person'] ?? ''),
            'phone' => $this->nullableText($payload['phone'] ?? ''),
            'email' => $this->nullableText($payload['email'] ?? ''),
            'address' => $this->nullableText($payload['address'] ?? ''),
            'is_active' => BooleanInput::value($payload['is_active'] ?? true),
        ];

        $errors = [];

        if ($input['code'] === '') {
            $errors['code'] = 'Kode supplier wajib diisi.';
        } elseif (strlen($input['code']) > self::CODE_MAX_LENGTH) {
            $errors['code'] = 'Kode supplier maksimal ' . self::CODE_MAX_LENGTH . ' karakter.';
        } elseif ($this->suppliers->codeExists($input['code'], $exceptId)) {
            $errors['code'] = 'Kode supplier sudah dipakai.';
        }

        if ($input['name'] === '') {
            $errors['name'] = 'Nama supplier wajib diisi.';
        } elseif (strlen($input['name']) > self::NAME_MAX_LENGTH) {
            $errors['name'] = 'Nama supplier maksimal ' . self::NAME_MAX_LENGTH . ' karakter.';
        }

        if ($input['contact_person'] !== null && strlen($input['contact_person']) > self::CONTACT_MAX_LENGTH) {
            $errors['contact_person'] = 'Nama kontak maksimal ' . self::CONTACT_MAX_LENGTH . ' karakter.';
        }

        if ($input['phone'] !== null && strlen($input['phone']) > self::PHONE_MAX_LENGTH) {
            $errors['phone'] = 'Nomor telepon maksimal ' . self::PHONE_MAX_LENGTH . ' karakter.';
        }

        if ($input['email'] !== null) {
            if (filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'Format email tidak valid.';
            } elseif (strlen($input['email']) > self::EMAIL_MAX_LENGTH) {
                $errors['email'] = 'Email maksimal ' . self::EMAIL_MAX_LENGTH . ' karakter.';
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
