<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\ItemType;
use PDO;

/**
 * Akses data stok barang habis pakai: saldo per barang dan riwayat pergerakan.
 * Saldo hanya ditulis lewat Action; repository ini tidak memuat aturan bisnis
 * maupun transaksi.
 */
final class StockRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * Buku besar saldo: SELURUH barang habis pakai ikut terdaftar, bukan hanya
     * yang sudah punya baris saldo, supaya barang yang baru dibuat di master data
     * langsung muncul dengan saldo nol dan bisa segera dicatat pergerakannya.
     *
     * @param array<string, mixed> $filters Filter opsional: search, low_only.
     * @return array<int, array<string, mixed>>
     */
    public function balances(array $filters = []): array
    {
        $sql = '
            SELECT
                i.id AS item_id, i.code AS item_code, i.name AS item_name,
                c.name AS category_name, u.name AS unit_name,
                COALESCE(sb.quantity, 0) AS quantity,
                i.minimum_stock,
                sb.updated_at
            FROM items i
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN units u ON i.unit_id = u.id
            LEFT JOIN stock_balances sb ON sb.item_id = i.id
            WHERE i.item_type = ?
        ';
        $params = [ItemType::Consumable->value];

        if (!empty($filters['search'])) {
            $sql .= ' AND (i.code LIKE ? OR i.name LIKE ? OR c.name LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['low_only'])) {
            $sql .= ' AND COALESCE(sb.quantity, 0) <= i.minimum_stock';
        }

        $sql .= ' ORDER BY i.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Baris saldo apa adanya; null berarti barang itu belum pernah bergerak.
     *
     * @return array<string, mixed>|null
     */
    public function balance(int $itemId): ?array
    {
        $stmt = $this->db->prepare('SELECT item_id, quantity, updated_at FROM stock_balances WHERE item_id = ?');
        $stmt->execute([$itemId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters Filter opsional: item_id, movement_type.
     * @return array<int, array<string, mixed>>
     */
    public function movements(array $filters = []): array
    {
        $sql = '
            SELECT
                m.id, m.item_id, m.movement_type, m.quantity, m.balance_after,
                m.reference_type, m.reference_id, m.department, m.employee_id,
                m.reason, m.notes, m.actor_name, m.created_at,
                i.code AS item_code, i.name AS item_name, u.name AS unit_name,
                e.name AS employee_name
            FROM stock_movements m
            JOIN items i ON m.item_id = i.id
            LEFT JOIN units u ON i.unit_id = u.id
            LEFT JOIN employees e ON m.employee_id = e.id
            WHERE 1=1
        ';
        $params = [];

        if (!empty($filters['item_id'])) {
            $sql .= ' AND m.item_id = ?';
            $params[] = (int)$filters['item_id'];
        }

        if (!empty($filters['movement_type'])) {
            $sql .= ' AND m.movement_type = ?';
            $params[] = $filters['movement_type'];
        }

        $sql .= ' ORDER BY m.created_at DESC, m.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMovement(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT
                m.id, m.item_id, m.movement_type, m.quantity, m.balance_after,
                m.reference_type, m.reference_id, m.department, m.employee_id,
                m.reason, m.notes, m.actor_name, m.created_at,
                i.code AS item_code, i.name AS item_name, u.name AS unit_name,
                e.name AS employee_name
            FROM stock_movements m
            JOIN items i ON m.item_id = i.id
            LEFT JOIN units u ON i.unit_id = u.id
            LEFT JOIN employees e ON m.employee_id = e.id
            WHERE m.id = ?
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data item_id, movement_type, quantity, balance_after, actor_name.
     */
    public function recordMovement(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO stock_movements (
                item_id, movement_type, quantity, balance_after, reference_type,
                reference_id, department, employee_id, reason, notes, actor_name, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['item_id'],
            $data['movement_type'],
            $data['quantity'],
            $data['balance_after'],
            $data['reference_type'] ?? null,
            $data['reference_id'] ?? null,
            $data['department'] ?? null,
            $data['employee_id'] ?? null,
            $data['reason'] ?? null,
            $data['notes'] ?? null,
            $data['actor_name'],
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Menetapkan saldo barang. Cek-lalu-tulis dipakai supaya jalan di MySQL
     * maupun SQLite; seluruh pemanggilnya berada di dalam transaksi Service,
     * jadi tidak ada dua penulisan saldo yang beradu.
     */
    public function setBalance(int $itemId, int $quantity): bool
    {
        $now = date('Y-m-d H:i:s');

        if ($this->balance($itemId) !== null) {
            $stmt = $this->db->prepare('UPDATE stock_balances SET quantity = ?, updated_at = ? WHERE item_id = ?');
            return $stmt->execute([$quantity, $now, $itemId]);
        }

        $stmt = $this->db->prepare('INSERT INTO stock_balances (item_id, quantity, updated_at) VALUES (?, ?, ?)');
        return $stmt->execute([$itemId, $quantity, $now]);
    }
}
