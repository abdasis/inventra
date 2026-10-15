<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\CustodyStatus;
use PDO;

/**
 * Akses data penempatan aset beserta nama aset, karyawan, dan lokasinya. Baris
 * lama tidak pernah ditimpa: penempatan berikutnya selalu baris baru, dan yang
 * lama ditutup lewat close(). Repository tidak memuat aturan transisi status.
 */
final class AssetAssignmentRepository
{
    /**
     * Kolom yang boleh dibaca klien. SELECT * dilarang, jadi daftarnya eksplisit
     * dan kolom baru wajib ditambahkan di sini.
     */
    private const COLUMNS = '
        a.id, a.asset_id, a.employee_id, a.location_id, a.assigned_at, a.returned_at,
        a.condition_out, a.condition_in, a.handed_by_name, a.received_by_name,
        a.status, a.notes, a.created_at, a.updated_at,
        ast.asset_code, ast.name AS asset_name,
        e.name AS employee_name,
        l.name AS location_name
    ';

    private const JOINS = '
        JOIN assets ast ON ast.id = a.asset_id
        LEFT JOIN employees e ON e.id = a.employee_id
        LEFT JOIN locations l ON l.id = a.location_id
    ';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $filters Filter opsional: search, status, employee_id.
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM asset_assignments a ' . self::JOINS . ' WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (ast.asset_code LIKE ? OR ast.name LIKE ? OR e.name LIKE ? OR a.received_by_name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['employee_id'])) {
            $sql .= ' AND a.employee_id = ?';
            $params[] = (int)$filters['employee_id'];
        }

        $sql .= ' ORDER BY a.assigned_at DESC, a.id DESC';

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
            'SELECT ' . self::COLUMNS . ' FROM asset_assignments a ' . self::JOINS . ' WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Penempatan yang belum ditutup untuk satu aset. Invarian "satu aset hanya
     * punya satu penempatan aktif" dijaga Action dengan memeriksa hasil method ini.
     *
     * @return array<string, mixed>|null
     */
    public function activeForAsset(int $assetId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ' FROM asset_assignments a ' . self::JOINS
            . ' WHERE a.asset_id = ? AND a.status = ? ORDER BY a.id DESC LIMIT 1'
        );
        $stmt->execute([$assetId, CustodyStatus::Active->value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Seluruh penempatan satu aset, terbaru dulu, termasuk yang sudah dikembalikan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyForAsset(int $assetId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ' FROM asset_assignments a ' . self::JOINS
            . ' WHERE a.asset_id = ? ORDER BY a.assigned_at DESC, a.id DESC'
        );
        $stmt->execute([$assetId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data asset_id, location_id, assigned_at, handed_by_name wajib.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            INSERT INTO asset_assignments (
                asset_id, employee_id, location_id, assigned_at, condition_out,
                handed_by_name, received_by_name, status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['asset_id'],
            $data['employee_id'] ?? null,
            $data['location_id'],
            $data['assigned_at'],
            $data['condition_out'] ?? AssetCondition::Good->value,
            $data['handed_by_name'],
            $data['received_by_name'] ?? null,
            $data['status'] ?? CustodyStatus::Active->value,
            $data['notes'] ?? null,
            $data['created_at'] ?? $now,
            $data['updated_at'] ?? $now,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Menutup penempatan: mengisi hasil pengembalian dan memindahkan statusnya ke
     * returned. Barisnya tidak dihapus supaya histori pemegang tetap utuh.
     *
     * @param array<string, mixed> $data returned_at, condition_in, received_by_name.
     */
    public function close(int $id, array $data): bool
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            UPDATE asset_assignments
            SET returned_at = ?, condition_in = ?, received_by_name = ?, status = ?, updated_at = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['returned_at'] ?? $now,
            $data['condition_in'] ?? null,
            $data['received_by_name'] ?? null,
            CustodyStatus::Returned->value,
            $now,
            $id,
        ]);
    }
}
