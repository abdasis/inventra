<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\MaintenanceStatus;
use Inventra\Core\Enums\MaintenanceType;
use PDO;

/**
 * Akses data pemeliharaan aset beserta nama asetnya. Barisnya adalah histori
 * perawatan: yang lama tidak pernah ditimpa, hanya ditutup lewat update().
 * Repository tidak memuat aturan transisi status aset maupun transaksi.
 */
final class AssetMaintenanceRepository
{
    /**
     * Kolom yang boleh dibaca klien. SELECT * dilarang, jadi daftarnya eksplisit
     * dan kolom baru wajib ditambahkan di sini.
     */
    private const COLUMNS = '
        m.id, m.asset_id, m.maintenance_type, m.maintenance_date, m.vendor, m.cost,
        m.damage_description, m.work_result, m.next_schedule, m.status,
        m.performed_by_name, m.completed_at, m.notes, m.created_at, m.updated_at,
        ast.asset_code, ast.name AS asset_name
    ';

    /**
     * Kolom yang boleh diubah lewat update(): hasil pekerjaan, biaya, jadwal
     * berikutnya, dan penutupan status.
     */
    private const UPDATABLE = [
        'maintenance_type', 'maintenance_date', 'vendor', 'cost', 'damage_description',
        'work_result', 'next_schedule', 'status', 'completed_at', 'notes',
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
        $sql = 'SELECT ' . self::COLUMNS . ' FROM asset_maintenances m'
            . ' JOIN assets ast ON ast.id = m.asset_id WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (ast.asset_code LIKE ? OR ast.name LIKE ? OR m.vendor LIKE ?'
                . ' OR m.performed_by_name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND m.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['asset_id'])) {
            $sql .= ' AND m.asset_id = ?';
            $params[] = (int)$filters['asset_id'];
        }

        $sql .= ' ORDER BY m.maintenance_date DESC, m.id DESC';

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
            'SELECT ' . self::COLUMNS . ' FROM asset_maintenances m'
            . ' JOIN assets ast ON ast.id = m.asset_id WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Pemeliharaan yang belum ditutup untuk satu aset. Invarian "satu aset hanya
     * dipelihara sekali pada satu waktu" dijaga Action lewat method ini.
     *
     * @return array<string, mixed>|null
     */
    public function ongoingForAsset(int $assetId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ' FROM asset_maintenances m'
            . ' JOIN assets ast ON ast.id = m.asset_id'
            . ' WHERE m.asset_id = ? AND m.status = ? ORDER BY m.id DESC LIMIT 1'
        );
        $stmt->execute([$assetId, MaintenanceStatus::Ongoing->value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data asset_id, maintenance_date, performed_by_name wajib.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            INSERT INTO asset_maintenances (
                asset_id, maintenance_type, maintenance_date, vendor, cost,
                damage_description, work_result, next_schedule, status,
                performed_by_name, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['asset_id'],
            $data['maintenance_type'] ?? MaintenanceType::Corrective->value,
            $data['maintenance_date'],
            $data['vendor'] ?? null,
            $data['cost'] ?? 0,
            $data['damage_description'] ?? null,
            $data['work_result'] ?? null,
            $data['next_schedule'] ?? null,
            $data['status'] ?? MaintenanceStatus::Ongoing->value,
            $data['performed_by_name'],
            $data['notes'] ?? null,
            $data['created_at'] ?? $now,
            $data['updated_at'] ?? $now,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Melengkapi atau menutup pemeliharaan. Kolom di luar daftar putih diabaikan,
     * dan updated_at selalu diisi sendiri.
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

        $stmt = $this->db->prepare('UPDATE asset_maintenances SET ' . implode(', ', $sets) . ' WHERE id = ?');

        return $stmt->execute($params);
    }
}
