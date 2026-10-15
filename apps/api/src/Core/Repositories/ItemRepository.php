<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use PDO;

final class ItemRepository
{
    /**
     * Nama kategori dan satuan selalu ikut lewat JOIN supaya frontend tidak perlu
     * memanggil endpoint master terpisah hanya untuk menampilkan labelnya.
     */
    private const COLUMNS = '
        i.id, i.code, i.name, i.brand, i.model, i.specification,
        i.category_id, c.name AS category_name,
        i.unit_id, u.name AS unit_name,
        i.item_type, i.minimum_stock, i.description,
        i.created_at, i.updated_at
    ';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = '
            SELECT ' . self::COLUMNS . '
            FROM items i
            JOIN categories c ON i.category_id = c.id
            JOIN units u ON i.unit_id = u.id
            WHERE 1=1
        ';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (i.code LIKE ? OR i.name LIKE ? OR i.brand LIKE ? OR i.model LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= ' ORDER BY i.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = '
            SELECT ' . self::COLUMNS . '
            FROM items i
            JOIN categories c ON i.category_id = c.id
            JOIN units u ON i.unit_id = u.id
            WHERE i.id = ?
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO items (
                code, name, brand, model, specification,
                category_id, unit_id, item_type, minimum_stock, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['brand'] ?? null,
            $data['model'] ?? null,
            $data['specification'] ?? null,
            $data['category_id'],
            $data['unit_id'],
            $data['item_type'],
            $data['minimum_stock'] ?? 0,
            $data['description'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE items SET
                code = ?,
                name = ?,
                brand = ?,
                model = ?,
                specification = ?,
                category_id = ?,
                unit_id = ?,
                item_type = ?,
                minimum_stock = ?,
                description = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['code'],
            $data['name'],
            $data['brand'] ?? null,
            $data['model'] ?? null,
            $data['specification'] ?? null,
            $data['category_id'],
            $data['unit_id'],
            $data['item_type'],
            $data['minimum_stock'] ?? 0,
            $data['description'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM items WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM items WHERE code = ?';
        $params = [$code];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function categoryExists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function unitExists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM units WHERE id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
