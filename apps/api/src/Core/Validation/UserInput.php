<?php

declare(strict_types=1);

namespace Inventra\Core\Validation;

use Inventra\Core\Enums\UserRole;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\UserRepository;

/**
 * Normalisasi dan validasi payload pengguna. Dipisah dari Action supaya aturan
 * bentuk input tidak tercampur dengan unit kerja penulisan data.
 */
final class UserInput
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * Normalisasi lalu validasi, sekaligus mengubah kegagalan validasi menjadi
     * ValidationException (422). Pesan field pertama dipakai sebagai message,
     * sedangkan peta error per field ikut dibawa apa adanya.
     *
     * @param array<string, mixed> $payload
     * @return array{name: string, email: string, role: string, is_active: bool, password: string, password_confirmation: string}
     */
    public function validated(array $payload, ?int $exceptId): array
    {
        $input = $this->normalize($payload);
        $errors = $this->validate($input, $exceptId);

        if ($errors === []) {
            return $input;
        }

        $message = (string)reset($errors);

        throw new ValidationException(
            $message !== '' ? $message : 'Data yang dikirim tidak valid.',
            $errors
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{name: string, email: string, role: string, is_active: bool, password: string, password_confirmation: string}
     */
    public function normalize(array $payload): array
    {
        return [
            'name' => $this->stringValue($payload['name'] ?? ''),
            'email' => $this->stringValue($payload['email'] ?? ''),
            'role' => $this->stringValue($payload['role'] ?? ''),
            'is_active' => $this->boolValue($payload['is_active'] ?? true),
            // Kata sandi tidak di-trim: spasi bisa jadi bagian sah dari kata sandi.
            'password' => $this->rawString($payload['password'] ?? ''),
            'password_confirmation' => $this->rawString($payload['password_confirmation'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input Hasil normalize().
     * @return array<string, string> Peta error per field, kosong bila valid.
     */
    public function validate(array $input, ?int $exceptId): array
    {
        $errors = [];

        if ($input['name'] === '') {
            $errors['name'] = 'Nama pengguna wajib diisi.';
        }

        if ($input['email'] === '') {
            $errors['email'] = 'Email wajib diisi.';
        } elseif (filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Format email tidak valid.';
        } elseif ($this->users->emailExists($input['email'], $exceptId)) {
            $errors['email'] = 'Email tersebut sudah dipakai pengguna lain.';
        }

        if (UserRole::tryFrom($input['role']) === null) {
            $errors['role'] = 'Peran pengguna tidak valid.';
        }

        // Saat edit kata sandi opsional: hanya divalidasi bila diisi (admin sedang mereset).
        $password = $input['password'];
        if ($exceptId === null && $password === '') {
            $errors['password'] = 'Kata sandi wajib diisi.';
        } elseif ($password !== '' && strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Kata sandi minimal ' . self::MIN_PASSWORD_LENGTH . ' karakter.';
        } elseif ($password !== '' && $password !== $input['password_confirmation']) {
            $errors['password'] = 'Konfirmasi kata sandi tidak cocok.';
        }

        return $errors;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function rawString(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['', '0', 'false', 'off', 'no'], true);
        }

        return (bool)$value;
    }
}
