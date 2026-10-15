<?php

declare(strict_types=1);

namespace Inventra\Core\Models;

use Inventra\Core\Database\Connection;
use PDO;

final class MovementModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 10): array
    {
        $sql = '
            SELECT 
                m.*,
                a.asset_code,
                a.name AS asset_name,
                lf.name AS from_location_name,
                lt.name AS to_location_name
            FROM asset_movements m
            JOIN assets a ON m.asset_id = a.id
            LEFT JOIN locations lf ON m.from_location_id = lf.id
            LEFT JOIN locations lt ON m.to_location_id = lt.id
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT ?
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function record(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO asset_movements (
                asset_id, movement_type, from_location_id, to_location_id,
                actor_name, recipient_name, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['asset_id'],
            $data['movement_type'],
            $data['from_location_id'] ?? null,
            $data['to_location_id'] ?? null,
            $data['actor_name'] ?? 'Admin GA',
            $data['recipient_name'] ?? null,
            $data['notes'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }
}
