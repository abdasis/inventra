<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use PDO;

final class CategoryRepository
{
    /**
     * Kolom publik kategori. parent_id dan created_at disertakan supaya daftar
     * bisa dipakai langsung oleh dropdown maupun tabel master.
     */
    private const COLUMNS = 'c.id, c.code, c.name, c.description, c.parent_id, c.created_at';

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
        $sql = 'SELECT ' . self::COLUMNS . ' FROM categories c WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (c.code LIKE ? OR c.name LIKE ? OR c.description LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY c.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT ' . self::COLUMNS . ', p.name AS parent_name
            FROM categories c
            LEFT JOIN categories p ON p.id = c.parent_id
            WHERE c.id = ?
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data code, name, description, parent_id.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO categories (code, name, description, parent_id, created_at)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'] ?? null,
            $data['parent_id'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data code, name, description, parent_id.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE categories SET code = ?, name = ?, description = ?, parent_id = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'] ?? null,
            $data['parent_id'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE code = ?';
        $params = [$code];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function countAssetReferences(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM assets WHERE category_id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    public function countItemReferences(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM items WHERE category_id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }
}
