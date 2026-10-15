<?php

declare(strict_types=1);

namespace Inventra\Core\Repositories;

use Inventra\Core\Database\Connection;
use PDO;

final class ActivityLogRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $data Wajib: action, description.
     */
    public function record(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO activity_logs (
                user_id, user_name, action, subject_type, subject_id, description, ip_address, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['user_name'] ?? null,
            $data['action'],
            $data['subject_type'] ?? null,
            $data['subject_id'] ?? null,
            $data['description'],
            $data['ip_address'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 20): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM activity_logs
            ORDER BY created_at DESC, id DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
