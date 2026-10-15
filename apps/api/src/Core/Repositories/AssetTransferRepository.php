<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\TransferStatus;
use PDO;

/**
 * Akses data mutasi aset beserta nama aset dan lokasi asal/tujuannya. Repository
 * tidak memuat aturan transisi status maupun transaksi: keputusan memindahkan
 * aset ada di Action, transaksi dipegang Service.
 */
final class AssetTransferRepository
{
    /**
     * Kolom yang boleh dibaca klien. SELECT * dilarang, jadi daftarnya eksplisit
     * dan kolom baru wajib ditambahkan di sini.
     */
    private const COLUMNS = '
        t.id, t.asset_id, t.from_location_id, t.to_location_id, t.from_holder_name,
        t.to_holder_name, t.transfer_date, t.reason, t.status, t.executed_by_name,
        t.completed_at, t.cancelled_at, t.cancel_reason, t.notes, t.created_at, t.updated_at,
        ast.asset_code, ast.name AS asset_name,
        lf.name AS from_location_name,
        lt.name AS to_location_name
    ';

    private const JOINS = '
        JOIN assets ast ON ast.id = t.asset_id
        LEFT JOIN locations lf ON lf.id = t.from_location_id
        JOIN locations lt ON lt.id = t.to_location_id
    ';

    /**
     * Kolom yang boleh diubah lewat update() supaya nama kolom tidak bisa datang
     * dari payload.
     */
    private const UPDATABLE = [
        'from_holder_name', 'to_holder_name', 'transfer_date', 'reason', 'status',
        'completed_at', 'cancelled_at', 'cancel_reason', 'notes',
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $filters Filter opsional: search, status, asset_id.
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM asset_transfers t ' . self::JOINS . ' WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (ast.asset_code LIKE ? OR ast.name LIKE ? OR lf.name LIKE ?'
                . ' OR lt.name LIKE ? OR t.to_holder_name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['asset_id'])) {
            $sql .= ' AND t.asset_id = ?';
            $params[] = (int)$filters['asset_id'];
        }

        $sql .= ' ORDER BY t.transfer_date DESC, t.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ' FROM asset_transfers t ' . self::JOINS . ' WHERE t.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Mutasi yang belum diselesaikan untuk satu aset. Invarian "satu aset hanya
     * punya satu mutasi berjalan" dijaga Action dengan memeriksa hasil method ini.
     *
     * @return array<string, mixed>|null
     */
    public function pendingForAsset(int $assetId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ' FROM asset_transfers t ' . self::JOINS
            . ' WHERE t.asset_id = ? AND t.status = ? ORDER BY t.id DESC LIMIT 1'
        );
        $stmt->execute([$assetId, TransferStatus::Pending->value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data asset_id, to_location_id, transfer_date, executed_by_name wajib.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            INSERT INTO asset_transfers (
                asset_id, from_location_id, to_location_id, from_holder_name, to_holder_name,
                transfer_date, reason, status, executed_by_name, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['asset_id'],
            $data['from_location_id'] ?? null,
            $data['to_location_id'],
            $data['from_holder_name'] ?? null,
            $data['to_holder_name'] ?? null,
            $data['transfer_date'],
            $data['reason'] ?? null,
            $data['status'] ?? TransferStatus::Pending->value,
            $data['executed_by_name'],
            $data['notes'] ?? null,
            $data['created_at'] ?? $now,
            $data['updated_at'] ?? $now,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Melengkapi atau menutup mutasi. Kolom di luar daftar putih diabaikan, dan
     * updated_at selalu diisi sendiri supaya klien tidak bisa memalsukan waktunya.
     *
     * @param array<string, mixed> $data Nilai kolom yang ingin diubah.
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];

        foreach (self::UPDATABLE as $column) {
            if (array_key_exists($column, $data)) {
                $sets[] = $column . ' = ?';
                $params[] = $data[$column];
            }
        }

        if ($sets === []) {
            return false;
        }

        $sets[] = 'updated_at = ?';
        $params[] = date('Y-m-d H:i:s');
        $params[] = $id;

        $stmt = $this->db->prepare('UPDATE asset_transfers SET ' . implode(', ', $sets) . ' WHERE id = ?');

        return $stmt->execute($params);
    }
}
