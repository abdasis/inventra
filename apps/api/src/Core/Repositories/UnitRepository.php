<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use PDO;

final class UnitRepository
{
    /**
     * Kolom publik satuan. Tidak memilih SELECT * supaya penambahan kolom internal
     * di masa depan tidak otomatis bocor ke frontend.
     */
    private const COLUMNS = 'id, code, name, description, created_at';

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
        $sql = 'SELECT ' . self::COLUMNS . ' FROM units WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (code LIKE ? OR name LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
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
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM units WHERE id = ?');
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
            INSERT INTO units (code, name, description)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([
            $data['code'],
            $data['name'],
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
            UPDATE units SET code = ?, name = ?, description = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['code'],
            $data['name'],
            $data['description'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM units WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM units WHERE code = ?';
        $params = [$code];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Dipakai penjaga hapus: satuan yang masih dirujuk barang tidak boleh dihapus.
     */
    public function countItemReferences(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM items WHERE unit_id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }
}
