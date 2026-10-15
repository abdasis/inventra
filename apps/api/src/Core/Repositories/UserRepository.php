<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\UserRole;
use PDO;

final class UserRepository
{
    /**
     * Kolom publik user. password_hash sengaja tidak ikut supaya tidak bocor ke frontend.
     */
    private const PUBLIC_COLUMNS = 'id, name, email, role, is_active, last_login_at, created_at, updated_at';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users ORDER BY name ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Satu-satunya pembaca password_hash dan status throttle, dibutuhkan untuk verifikasi login.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('
            SELECT ' . self::PUBLIC_COLUMNS . ', password_hash, failed_login_attempts, locked_until
            FROM users
            WHERE email = ?
        ');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(
        string $name,
        string $email,
        string $passwordHash,
        UserRole $role,
        bool $isActive = true
    ): int {
        $stmt = $this->db->prepare('
            INSERT INTO users (name, email, password_hash, role, is_active)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$name, $email, $passwordHash, $role->value, $isActive ? 1 : 0]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $email, UserRole $role, bool $isActive): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users SET name = ?, email = ?, role = ?, is_active = ?
            WHERE id = ?
        ');
        return $stmt->execute([$name, $email, $role->value, $isActive ? 1 : 0, $id]);
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$passwordHash, $id]);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$email];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Timestamp dikirim dari PHP (bukan NOW()) supaya portabel ke SQLite saat test.
     */
    public function markLogin(int $id, string $at): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users SET last_login_at = ?, failed_login_attempts = 0, locked_until = NULL
            WHERE id = ?
        ');
        return $stmt->execute([$at, $id]);
    }

    public function recordFailedLogin(int $id, int $attempts, ?string $lockedUntil): bool
    {
        $stmt = $this->db->prepare('
            UPDATE users SET failed_login_attempts = ?, locked_until = ?
            WHERE id = ?
        ');
        return $stmt->execute([$attempts, $lockedUntil, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function countActiveAdmins(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1');
        $stmt->execute([UserRole::Admin->value]);
        return (int)$stmt->fetchColumn();
    }
}
