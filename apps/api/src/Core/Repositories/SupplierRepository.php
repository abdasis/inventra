<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use PDO;

final class SupplierRepository
{
    private const COLUMNS = 'id, code, name, contact_person, phone, email, address, is_active, created_at, updated_at';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $filters Filter opsional: search.
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM suppliers WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (code LIKE ? OR name LIKE ? OR contact_person LIKE ? OR email LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data code, name, contact_person, phone, email, address, is_active.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare('
            INSERT INTO suppliers (
                code, name, contact_person, phone, email, address, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            !empty($data['is_active']) ? 1 : 0,
            $data['created_at'] ?? $now,
            $data['updated_at'] ?? $now,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data code, name, contact_person, phone, email, address, is_active.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE suppliers SET
                code = ?, name = ?, contact_person = ?, phone = ?, email = ?, address = ?,
                is_active = ?, updated_at = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['code'],
            $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            !empty($data['is_active']) ? 1 : 0,
            $data['updated_at'] ?? date('Y-m-d H:i:s'),
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM suppliers WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM suppliers WHERE code = ?';
        $params = [$code];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}
