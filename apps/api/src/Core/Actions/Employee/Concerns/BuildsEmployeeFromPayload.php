<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Employee\Concerns;

use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Support\BooleanInput;

/**
 * Normalisasi dan validasi payload karyawan untuk Create dan Update.
 * Trait membaca $this->employees (EmployeeRepository) milik Action pemakainya.
 */
trait BuildsEmployeeFromPayload
{
    private const NUMBER_MAX_LENGTH = 32;
    private const NAME_MAX_LENGTH = 128;
    private const TEXT_MAX_LENGTH = 128;
    private const EMAIL_MAX_LENGTH = 190;
    private const PHONE_MAX_LENGTH = 32;

    /**
     * @param array<string, mixed> $payload
     * @param int|null $exceptId Id karyawan yang sedang diperbarui, null saat create.
     * @return array<string, mixed>
     */
    private function employeeFromPayload(array $payload, ?int $exceptId): array
    {
        $input = [
            'employee_number' => $this->text($payload['employee_number'] ?? ''),
            'name' => $this->text($payload['name'] ?? ''),
            'department' => $this->nullableText($payload['department'] ?? ''),
            'position' => $this->nullableText($payload['position'] ?? ''),
            'email' => $this->nullableText($payload['email'] ?? ''),
            'phone' => $this->nullableText($payload['phone'] ?? ''),
            'is_active' => BooleanInput::value($payload['is_active'] ?? true),
        ];

        $errors = [];

        if ($input['employee_number'] === '') {
            $errors['employee_number'] = 'Nomor karyawan wajib diisi.';
        } elseif (strlen($input['employee_number']) > self::NUMBER_MAX_LENGTH) {
            $errors['employee_number'] = 'Nomor karyawan maksimal ' . self::NUMBER_MAX_LENGTH . ' karakter.';
        } elseif ($this->employees->employeeNumberExists($input['employee_number'], $exceptId)) {
            $errors['employee_number'] = 'Nomor karyawan sudah dipakai.';
        }

        if ($input['name'] === '') {
            $errors['name'] = 'Nama karyawan wajib diisi.';
        } elseif (strlen($input['name']) > self::NAME_MAX_LENGTH) {
            $errors['name'] = 'Nama karyawan maksimal ' . self::NAME_MAX_LENGTH . ' karakter.';
        }

        if ($input['department'] !== null && strlen($input['department']) > self::TEXT_MAX_LENGTH) {
            $errors['department'] = 'Departemen maksimal ' . self::TEXT_MAX_LENGTH . ' karakter.';
        }

        if ($input['position'] !== null && strlen($input['position']) > self::TEXT_MAX_LENGTH) {
            $errors['position'] = 'Jabatan maksimal ' . self::TEXT_MAX_LENGTH . ' karakter.';
        }

        if ($input['email'] !== null) {
            if (filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'Format email tidak valid.';
            } elseif (strlen($input['email']) > self::EMAIL_MAX_LENGTH) {
                $errors['email'] = 'Email maksimal ' . self::EMAIL_MAX_LENGTH . ' karakter.';
            }
        }

        if ($input['phone'] !== null && strlen($input['phone']) > self::PHONE_MAX_LENGTH) {
            $errors['phone'] = 'Nomor telepon maksimal ' . self::PHONE_MAX_LENGTH . ' karakter.';
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
