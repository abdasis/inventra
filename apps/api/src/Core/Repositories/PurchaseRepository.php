<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\PurchaseStatus;
use PDO;

/**
 * Akses data pengadaan: header pembelian beserta agregat barisnya, dan baris
 * pesanan. Repository tidak memuat aturan bisnis maupun transaksi: keputusan
 * status dan penulisan stok ada di Action, transaksi dipegang Service.
 */
final class PurchaseRepository
{
    /**
     * Kolom header yang boleh dibaca klien. SELECT * dilarang, jadi daftarnya
     * eksplisit dan kolom baru wajib ditambahkan di sini.
     */
    private const COLUMNS = '
        p.id, p.purchase_number, p.supplier_id, p.purchase_date, p.status, p.reference,
        p.notes, p.total_amount, p.created_by_name, p.received_at, p.cancelled_at,
        p.cancel_reason, p.created_at, p.updated_at
    ';

    /**
     * Agregat baris pesanan dipakai daftar dan detail ringkas supaya frontend
     * tidak perlu menjumlahkan sendiri.
     */
    private const AGGREGATE_JOIN = '
        LEFT JOIN (
            SELECT purchase_id,
                COUNT(*) AS item_count,
                SUM(quantity_ordered) AS quantity_ordered,
                SUM(quantity_received) AS quantity_received
            FROM purchase_details
            GROUP BY purchase_id
        ) d ON d.purchase_id = p.id
    ';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $filters Filter opsional: search, status.
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = '
            SELECT ' . self::COLUMNS . ',
                s.name AS supplier_name,
                COALESCE(d.item_count, 0) AS item_count,
                COALESCE(d.quantity_ordered, 0) AS quantity_ordered,
                COALESCE(d.quantity_received, 0) AS quantity_received
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            ' . self::AGGREGATE_JOIN . '
            WHERE 1=1
        ';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (p.purchase_number LIKE ? OR p.reference LIKE ? OR s.name LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = ?';
            $params[] = $filters['status'];
        }

        $sql .= ' ORDER BY p.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Header satu pembelian beserta nama supplier dan agregat barisnya, sehingga
     * pemetaan lewat PurchaseDTO selalu punya angka yang lengkap.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = '
            SELECT ' . self::COLUMNS . ',
                s.name AS supplier_name,
                COALESCE(d.item_count, 0) AS item_count,
                COALESCE(d.quantity_ordered, 0) AS quantity_ordered,
                COALESCE(d.quantity_received, 0) AS quantity_received
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            ' . self::AGGREGATE_JOIN . '
            WHERE p.id = ?
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Seluruh baris satu pembelian, terlama dulu supaya urutannya stabil.
     *
     * @return array<int, array<string, mixed>>
     */
    public function details(int $purchaseId): array
    {
        $stmt = $this->db->prepare('
            SELECT
                d.id, d.purchase_id, d.item_id, d.quantity_ordered, d.quantity_received,
                d.quantity_rejected, d.unit_price, d.notes, d.created_at, d.updated_at,
                i.code AS item_code, i.name AS item_name, i.item_type,
                u.name AS unit_name
            FROM purchase_details d
            JOIN items i ON d.item_id = i.id
            LEFT JOIN units u ON i.unit_id = u.id
            WHERE d.purchase_id = ?
            ORDER BY d.id ASC
        ');
        $stmt->execute([$purchaseId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $detailId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                d.id, d.purchase_id, d.item_id, d.quantity_ordered, d.quantity_received,
                d.quantity_rejected, d.unit_price, d.notes, d.created_at, d.updated_at,
                i.code AS item_code, i.name AS item_name, i.item_type,
                u.name AS unit_name
            FROM purchase_details d
            JOIN items i ON d.item_id = i.id
            LEFT JOIN units u ON i.unit_id = u.id
            WHERE d.id = ?
        ');
        $stmt->execute([$detailId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $header purchase_number, purchase_date, status, dst.
     * @param array<int, array<string, mixed>> $details item_id, quantity_ordered, unit_price, notes.
     */
    public function create(array $header, array $details): int
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            INSERT INTO purchases (
                purchase_number, supplier_id, purchase_date, status, reference, notes,
                total_amount, created_by_name, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $header['purchase_number'],
            $header['supplier_id'] ?? null,
            $header['purchase_date'],
            $header['status'] ?? PurchaseStatus::Draft->value,
            $header['reference'] ?? null,
            $header['notes'] ?? null,
            $header['total_amount'] ?? 0,
            $header['created_by_name'] ?? null,
            $header['created_at'] ?? $now,
            $header['updated_at'] ?? $now,
        ]);

        $purchaseId = (int)$this->db->lastInsertId();
        $this->insertDetails($purchaseId, $details, $now);

        return $purchaseId;
    }

    /**
     * Header dan seluruh baris diganti sekaligus. Status, penerimaan, dan
     * pembatalan sengaja tidak disentuh: ketiganya punya jalur sendiri.
     *
     * @param array<string, mixed> $header
     * @param array<int, array<string, mixed>> $details
     */
    public function update(int $id, array $header, array $details): bool
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare('
            UPDATE purchases SET
                purchase_number = ?, supplier_id = ?, purchase_date = ?, reference = ?,
                notes = ?, total_amount = ?, updated_at = ?
            WHERE id = ?
        ');
        $ok = $stmt->execute([
            $header['purchase_number'],
            $header['supplier_id'] ?? null,
            $header['purchase_date'],
            $header['reference'] ?? null,
            $header['notes'] ?? null,
            $header['total_amount'] ?? 0,
            $now,
            $id,
        ]);

        $this->db->prepare('DELETE FROM purchase_details WHERE purchase_id = ?')->execute([$id]);
        $this->insertDetails($id, $details, $now);

        return $ok;
    }

    /**
     * Menyimpan jumlah diterima dan ditolak kumulatif hasil keputusan Action —
     * bukan penambahan, supaya pemanggilan ulang tidak bisa menggandakan
     * penerimaan yang sudah divalidasi terhadap sisa pesanan.
     */
    public function receive(int $detailId, int $received, int $rejected): bool
    {
        $stmt = $this->db->prepare('
            UPDATE purchase_details
            SET quantity_received = ?, quantity_rejected = ?, updated_at = ?
            WHERE id = ?
        ');
        return $stmt->execute([$received, $rejected, date('Y-m-d H:i:s'), $detailId]);
    }

    /**
     * @param array<string, mixed> $extra Kolom tambahan yang dikenal: received_at,
     *        cancelled_at, cancel_reason. Kolom lain diabaikan supaya nama kolom
     *        tidak bisa datang dari payload.
     */
    public function markStatus(int $id, PurchaseStatus $status, array $extra = []): bool
    {
        $sets = ['status = ?', 'updated_at = ?'];
        $params = [$status->value, date('Y-m-d H:i:s')];

        foreach (['received_at', 'cancelled_at', 'cancel_reason'] as $column) {
            if (array_key_exists($column, $extra)) {
                $sets[] = $column . ' = ?';
                $params[] = $extra[$column];
            }
        }

        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE purchases SET ' . implode(', ', $sets) . ' WHERE id = ?');

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        // Baris pesanan dibuang lebih dulu karena skema test SQLite tidak memakai
        // foreign key cascade.
        $this->db->prepare('DELETE FROM purchase_details WHERE purchase_id = ?')->execute([$id]);
        $stmt = $this->db->prepare('DELETE FROM purchases WHERE id = ?');

        return $stmt->execute([$id]);
    }

    public function numberExists(string $number, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM purchases WHERE purchase_number = ?';
        $params = [$number];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Nomor berikutnya yang belum terpakai, berformat {prefix}-{tahun}-{4 digit}
     * mis. PO-2026-0001. Urutannya dilanjutkan dari jumlah nomor berawalan sama,
     * lalu digeser bila nomor hasilnya kebetulan sudah dipakai nomor manual.
     */
    public function nextNumber(string $prefix = 'PO'): string
    {
        $base = $prefix . '-' . date('Y') . '-';
        $sequence = $this->countByNumberPrefix($base) + 1;
        $number = sprintf('%s%04d', $base, $sequence);

        while ($this->numberExists($number)) {
            $number = sprintf('%s%04d', $base, ++$sequence);
        }

        return $number;
    }

    /**
     * Karakter wildcard LIKE di prefix di-escape supaya nomor jenis lain tidak
     * ikut terhitung.
     */
    public function countByNumberPrefix(string $prefix): int
    {
        $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM purchases WHERE purchase_number LIKE ? ESCAPE '\\'");
        $stmt->execute([$pattern]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function insertDetails(int $purchaseId, array $details, string $now): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO purchase_details (
                purchase_id, item_id, quantity_ordered, quantity_received,
                quantity_rejected, unit_price, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($details as $detail) {
            $stmt->execute([
                $purchaseId,
                $detail['item_id'],
                $detail['quantity_ordered'] ?? 0,
                $detail['quantity_received'] ?? 0,
                $detail['quantity_rejected'] ?? 0,
                $detail['unit_price'] ?? 0,
                $detail['notes'] ?? null,
                $now,
                $now,
            ]);
        }
    }
}
