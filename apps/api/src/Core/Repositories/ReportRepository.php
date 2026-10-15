<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\CustodyStatus;
use Inventra\Core\Enums\ItemType;
use PDO;

/**
 * Query agregat modul laporan: ringkasan statistik dan baris siap ekspor.
 * Hanya membaca; tidak ada aturan bisnis, transaksi, maupun penulisan. Nilainya
 * masih mentah (angka dan nilai enum), penyusunan label dan format rupiah ada di
 * ReportService supaya repository tetap jadi lapisan data saja.
 *
 * Semua batas waktu dihitung di PHP, bukan lewat NOW(), supaya tetap jalan di
 * SQLite saat test.
 */
final class ReportRepository
{
    /** Batas baris ekspor audit agar satu permintaan tidak menarik seluruh tabel. */
    private const ACTIVITY_LIMIT_DEFAULT = 200;
    private const ACTIVITY_LIMIT_MAX = 1000;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Ringkasan angka mentah untuk dashboard laporan.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $yearStart = date('Y-01-01');
        $nextYearStart = ((int)date('Y') + 1) . '-01-01';

        return [
            'assets_by_status' => $this->assetsByStatus(),
            'total_valuation' => (float)$this->scalar('SELECT COALESCE(SUM(purchase_cost), 0) FROM assets'),
            'top_categories' => $this->topCategories(),
            'low_stock_items' => (int)$this->scalar($this->lowStockSql(), [ItemType::Consumable->value]),
            'active_loans' => (int)$this->scalar(
                'SELECT COUNT(*) FROM asset_loans WHERE status = ?',
                [CustodyStatus::Active->value]
            ),
            'overdue_loans' => (int)$this->scalar(
                'SELECT COUNT(*) FROM asset_loans WHERE status = ? AND due_at IS NOT NULL AND due_at < ?',
                [CustodyStatus::Active->value, date('Y-m-d')]
            ),
            'maintenance_cost_this_year' => (float)$this->scalar(
                'SELECT COALESCE(SUM(cost), 0) FROM asset_maintenances WHERE maintenance_date >= ? AND maintenance_date < ?',
                [$yearStart, $nextYearStart]
            ),
            'disposals_this_year' => (int)$this->scalar(
                'SELECT COUNT(*) FROM asset_disposals WHERE disposal_date >= ? AND disposal_date < ?',
                [$yearStart, $nextYearStart]
            ),
            'active_users' => (int)$this->scalar('SELECT COUNT(*) FROM users WHERE is_active = 1'),
            'activity_last_7_days' => (int)$this->scalar(
                'SELECT COUNT(*) FROM activity_logs WHERE created_at >= ?',
                [date('Y-m-d H:i:s', strtotime('-7 days'))]
            ),
        ];
    }

    /**
     * @param array<string, mixed> $filters Filter opsional: status, category_id, location_id.
     * @return array<int, array<string, mixed>>
     */
    public function assetRows(array $filters = []): array
    {
        $sql = '
            SELECT
                a.asset_code, a.name, c.name AS category_name, l.name AS location_name,
                i.name AS item_name, a.serial_number, a.purchase_date, a.purchase_cost,
                a.status, a.condition_level, a.assigned_to
            FROM assets a
            JOIN categories c ON a.category_id = c.id
            JOIN locations l ON a.location_id = l.id
            LEFT JOIN items i ON a.item_id = i.id
            WHERE 1=1
        ';
        $params = [];

        foreach (['status', 'category_id', 'location_id'] as $filter) {
            if (empty($filters[$filter])) {
                continue;
            }

            $column = $filter === 'status' ? 'a.status' : 'a.' . $filter;
            $sql .= ' AND ' . $column . ' = ?';
            $params[] = $filter === 'status' ? $filters[$filter] : (int)$filters[$filter];
        }

        $sql .= ' ORDER BY a.name ASC, a.id ASC';

        return $this->rows($sql, $params);
    }

    /**
     * Saldo barang habis pakai beserta penanda menipis. Predikatnya sengaja sama
     * dengan StockRepository::balances supaya laporan dan halaman stok tidak
     * pernah berbeda angka.
     *
     * @param array<string, mixed> $filters Filter opsional: low_only.
     * @return array<int, array<string, mixed>>
     */
    public function stockRows(array $filters = []): array
    {
        $sql = '
            SELECT
                i.code AS item_code, i.name AS item_name, c.name AS category_name,
                u.name AS unit_name, COALESCE(sb.quantity, 0) AS quantity,
                i.minimum_stock
            FROM items i
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN units u ON i.unit_id = u.id
            LEFT JOIN stock_balances sb ON sb.item_id = i.id
            WHERE i.item_type = ?
        ';
        $params = [ItemType::Consumable->value];

        if (!empty($filters['low_only'])) {
            $sql .= ' AND COALESCE(sb.quantity, 0) <= i.minimum_stock';
        }

        $sql .= ' ORDER BY i.name ASC';

        $rows = $this->rows($sql, $params);

        foreach ($rows as $index => $row) {
            $rows[$index]['is_low'] = (int)$row['quantity'] <= (int)$row['minimum_stock'];
        }

        return $rows;
    }

    /**
     * Baris jejak audit, terbaru dulu, dengan batas baris agar satu permintaan
     * tidak menarik seluruh tabel.
     *
     * @param array<string, mixed> $filters Filter opsional: search, action, subject_type, date_from, date_to, limit.
     * @return array<int, array<string, mixed>>
     */
    public function activityRows(array $filters = []): array
    {
        $sql = 'SELECT id, user_id, user_name, action, subject_type, subject_id, description, ip_address, created_at
            FROM activity_logs WHERE 1=1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (description LIKE ? OR user_name LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        foreach (['action', 'subject_type'] as $filter) {
            if (empty($filters[$filter])) {
                continue;
            }

            $sql .= ' AND ' . $filter . ' = ?';
            $params[] = $filters[$filter];
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ?';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $this->activityLimit($filters['limit'] ?? null), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Nilai action yang pernah tercatat, untuk mengisi dropdown filter.
     *
     * @return array<int, string>
     */
    public function activityActions(): array
    {
        $stmt = $this->db->query('SELECT DISTINCT action FROM activity_logs ORDER BY action ASC');

        return array_column($stmt->fetchAll(), 'action');
    }

    /**
     * Seluruh status selalu muncul walau jumlahnya nol, supaya kunci hasilnya
     * stabil untuk frontend.
     *
     * @return array<string, int>
     */
    private function assetsByStatus(): array
    {
        $counts = [];
        foreach (AssetStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($this->rows('SELECT status, COUNT(*) AS count FROM assets GROUP BY status') as $row) {
            $status = (string)$row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int)$row['count'];
            }
        }

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topCategories(): array
    {
        return $this->rows('
            SELECT c.name AS name, COUNT(a.id) AS count
            FROM assets a
            JOIN categories c ON a.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY COUNT(a.id) DESC, c.name ASC
            LIMIT 10
        ');
    }

    private function lowStockSql(): string
    {
        return '
            SELECT COUNT(*) FROM items i
            LEFT JOIN stock_balances sb ON sb.item_id = i.id
            WHERE i.item_type = ? AND COALESCE(sb.quantity, 0) <= i.minimum_stock
        ';
    }

    /**
     * Batas baris jejak audit; di luar rentang yang masuk akal dikembalikan ke default.
     */
    private function activityLimit(mixed $limit): int
    {
        $value = (int)$limit;

        if ($value < 1 || $value > self::ACTIVITY_LIMIT_MAX) {
            return self::ACTIVITY_LIMIT_DEFAULT;
        }

        return $value;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * @param array<int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
