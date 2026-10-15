<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\CustodyStatus;
use PDO;

/**
 * Akses data peminjaman aset beserta nama aset, karyawan, dan lokasi pemakaian.
 * Peminjaman lama tidak pernah ditimpa: baris baru dibuat untuk peminjaman
 * berikutnya dan yang lama ditutup lewat close().
 */
final class AssetLoanRepository
{
    /**
     * Kolom yang boleh dibaca klien. SELECT * dilarang, jadi daftarnya eksplisit
     * dan kolom baru wajib ditambahkan di sini.
     */
    private const COLUMNS = '
        l.id, l.asset_id, l.employee_id, l.location_id, l.loaned_at, l.due_at,
        l.returned_at, l.condition_out, l.condition_in, l.handed_by_name,
        l.received_by_name, l.purpose, l.status, l.notes, l.created_at, l.updated_at,
        ast.asset_code, ast.name AS asset_name,
        e.name AS employee_name,
        loc.name AS location_name
    ';

    private const JOINS = '
        JOIN assets ast ON ast.id = l.asset_id
        LEFT JOIN employees e ON e.id = l.employee_id
        LEFT JOIN locations loc ON loc.id = l.location_id
    ';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $filters Filter opsional: search, status, overdue.
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM asset_loans l ' . self::JOINS . ' WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (ast.asset_code LIKE ? OR ast.name LIKE ? OR e.name LIKE ? OR l.received_by_name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND l.status = ?';
            $params[] = $filters['status'];
        }

        // Terlambat hanya berlaku untuk pinjaman yang masih aktif dan sudah
        // melewati jatuh temponya.
        if (!empty($filters['overdue'])) {
            $sql .= ' AND l.status = ? AND l.due_at IS NOT NULL AND l.due_at < ?';
            $params[] = CustodyStatus::Active->value;
            $params[] = date('Y-m-d');
        }

        $sql .= ' ORDER BY l.loaned_at DESC, l.id DESC';

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
            'SELECT ' . self::COLUMNS . ' FROM asset_loans l ' . self::JOINS . ' WHERE l.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Peminjaman yang belum dikembalikan untuk satu aset. Dipakai Action untuk
     * menegakkan "satu aset tidak boleh punya lebih dari satu peminjaman aktif".
     *
     * @return array<string, mixed>|null
     */
    public function activeForAsset(int $assetId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ' FROM asset_loans l ' . self::JOINS
            . ' WHERE l.asset_id = ? AND l.status = ? ORDER BY l.id DESC LIMIT 1'
        );
        $stmt->execute([$assetId, CustodyStatus::Active->value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data asset_id, loaned_at, handed_by_name wajib.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            INSERT INTO asset_loans (
                asset_id, employee_id, location_id, loaned_at, due_at, condition_out,
                handed_by_name, received_by_name, purpose, status, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['asset_id'],
            $data['employee_id'] ?? null,
            $data['location_id'] ?? null,
            $data['loaned_at'],
            $data['due_at'] ?? null,
            $data['condition_out'] ?? AssetCondition::Good->value,
            $data['handed_by_name'],
            $data['received_by_name'] ?? null,
            $data['purpose'] ?? null,
            $data['status'] ?? CustodyStatus::Active->value,
            $data['notes'] ?? null,
            $data['created_at'] ?? $now,
            $data['updated_at'] ?? $now,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Menutup peminjaman: mengisi tanggal kembali, kondisi, dan penerima, lalu
     * memindahkan statusnya ke returned. Barisnya tetap ada sebagai histori.
     *
     * @param array<string, mixed> $data returned_at, condition_in, received_by_name.
     */
    public function close(int $id, array $data): bool
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            UPDATE asset_loans
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
