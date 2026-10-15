<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\DisposalMethod;
use PDO;

/**
 * Akses data penghapusan aset beserta nama aset dan kategorinya. Barisnya adalah
 * histori: asetnya sendiri tetap ada di tabel assets dengan status disposed.
 * Repository tidak memuat aturan kelayakan penghapusan maupun transaksi.
 */
final class AssetDisposalRepository
{
    /**
     * Kolom yang boleh dibaca klien. SELECT * dilarang, jadi daftarnya eksplisit
     * dan kolom baru wajib ditambahkan di sini.
     */
    private const COLUMNS = '
        d.id, d.asset_id, d.disposal_date, d.reason, d.method, d.book_value,
        d.document_reference, d.approved_by_name, d.executed_by_name, d.notes,
        d.created_at, d.updated_at,
        ast.asset_code, ast.name AS asset_name,
        c.name AS category_name
    ';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Daftar penghapusan. Penghapusan tidak punya kolom status — satu aset paling
     * banyak dihapus sekali — jadi penyaringnya search, asset_id, dan method
     * (padanan status pada modul lain).
     *
     * @param array<string, mixed> $filters Filter opsional: search, asset_id, method.
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM asset_disposals d'
            . ' JOIN assets ast ON ast.id = d.asset_id'
            . ' JOIN categories c ON c.id = ast.category_id WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (ast.asset_code LIKE ? OR ast.name LIKE ? OR d.document_reference LIKE ?'
                . ' OR d.executed_by_name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['method'])) {
            $sql .= ' AND d.method = ?';
            $params[] = $filters['method'];
        }

        if (!empty($filters['asset_id'])) {
            $sql .= ' AND d.asset_id = ?';
            $params[] = (int)$filters['asset_id'];
        }

        $sql .= ' ORDER BY d.disposal_date DESC, d.id DESC';

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
            'SELECT ' . self::COLUMNS . ' FROM asset_disposals d'
            . ' JOIN assets ast ON ast.id = d.asset_id'
            . ' JOIN categories c ON c.id = ast.category_id WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Baris penghapusan satu aset, bila ada. Dipakai Action untuk menolak
     * penghapusan kedua dengan pesan yang ramah sebelum UNIQUE asset_id menolaknya
     * sebagai error SQL.
     *
     * @return array<string, mixed>|null
     */
    public function findByAsset(int $assetId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ' FROM asset_disposals d'
            . ' JOIN assets ast ON ast.id = d.asset_id'
            . ' JOIN categories c ON c.id = ast.category_id WHERE d.asset_id = ?'
        );
        $stmt->execute([$assetId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data asset_id, disposal_date, reason, executed_by_name wajib.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            INSERT INTO asset_disposals (
                asset_id, disposal_date, reason, method, book_value, document_reference,
                approved_by_name, executed_by_name, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['asset_id'],
            $data['disposal_date'],
            $data['reason'],
            $data['method'] ?? DisposalMethod::WrittenOff->value,
            $data['book_value'] ?? null,
            $data['document_reference'] ?? null,
            $data['approved_by_name'] ?? null,
            $data['executed_by_name'],
            $data['notes'] ?? null,
            $data['created_at'] ?? $now,
            $data['updated_at'] ?? $now,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
