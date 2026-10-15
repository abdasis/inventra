<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use PDO;

final class LocationRepository
{
    private const COLUMNS = 'id, code, name, building, floor, created_at';

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
        $sql = 'SELECT ' . self::COLUMNS . ' FROM locations WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (code LIKE ? OR name LIKE ? OR building LIKE ? OR floor LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY building ASC, floor ASC, name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Dipakai penerimaan barang untuk menemukan lokasi gudang default berdasarkan
     * kodenya, tanpa menebak lewat urutan daftar.
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNS . ' FROM locations WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data code, name, building, floor.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO locations (code, name, building, floor, created_at)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['code'],
            $data['name'],
            $data['building'] ?? null,
            $data['floor'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data code, name, building, floor.
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE locations SET code = ?, name = ?, building = ?, floor = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['code'],
            $data['name'],
            $data['building'] ?? null,
            $data['floor'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM locations WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM locations WHERE code = ?';
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
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM assets WHERE location_id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }
}
