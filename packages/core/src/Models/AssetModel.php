<?php

declare(strict_types=1);

namespace Inventra\Core\Models;

use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\MovementType;
use PDO;

final class AssetModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::get();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = '
            SELECT 
                a.*,
                c.name AS category_name,
                c.code AS category_code,
                l.name AS location_name,
                l.code AS location_code
            FROM assets a
            JOIN categories c ON a.category_id = c.id
            JOIN locations l ON a.location_id = l.id
            WHERE 1=1
        ';
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= ' AND (a.name LIKE ? OR a.asset_code LIKE ? OR a.serial_number LIKE ? OR a.assigned_to LIKE ?)';
            $searchVal = '%' . $filters['search'] . '%';
            $params[] = $searchVal;
            $params[] = $searchVal;
            $params[] = $searchVal;
            $params[] = $searchVal;
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND a.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['location_id'])) {
            $sql .= ' AND a.location_id = ?';
            $params[] = (int)$filters['location_id'];
        }

        $sql .= ' ORDER BY a.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = '
            SELECT 
                a.*,
                c.name AS category_name,
                l.name AS location_name
            FROM assets a
            JOIN categories c ON a.category_id = c.id
            JOIN locations l ON a.location_id = l.id
            WHERE a.id = ?
        ';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(AssetDTO $dto): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO assets (
                asset_code, name, category_id, location_id, serial_number,
                purchase_date, purchase_cost, status, condition_level, assigned_to, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $dto->assetCode,
            $dto->name,
            $dto->categoryId,
            $dto->locationId,
            $dto->serialNumber,
            $dto->purchaseDate,
            $dto->purchaseCost,
            $dto->status->value,
            $dto->conditionLevel->value,
            $dto->assignedTo,
            $dto->notes,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, AssetDTO $dto): bool
    {
        $stmt = $this->db->prepare('
            UPDATE assets SET
                asset_code = ?,
                name = ?,
                category_id = ?,
                location_id = ?,
                serial_number = ?,
                purchase_date = ?,
                purchase_cost = ?,
                status = ?,
                condition_level = ?,
                assigned_to = ?,
                notes = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $dto->assetCode,
            $dto->name,
            $dto->categoryId,
            $dto->locationId,
            $dto->serialNumber,
            $dto->purchaseDate,
            $dto->purchaseCost,
            $dto->status->value,
            $dto->conditionLevel->value,
            $dto->assignedTo,
            $dto->notes,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM assets WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function checkOut(
        int $assetId,
        string $recipient,
        int $targetLocationId,
        string $actor = 'Admin GA',
        ?string $notes = null
    ): bool {
        $asset = $this->find($assetId);
        if (!$asset) {
            return false;
        }

        $fromLocationId = (int)$asset['location_id'];

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                UPDATE assets 
                SET status = ?, assigned_to = ?, location_id = ?
                WHERE id = ?
            ');
            $stmt->execute([AssetStatus::InUse->value, $recipient, $targetLocationId, $assetId]);

            $moveModel = new MovementModel($this->db);
            $moveModel->record([
                'asset_id' => $assetId,
                'movement_type' => MovementType::CheckOut->value,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $targetLocationId,
                'actor_name' => $actor,
                'recipient_name' => $recipient,
                'notes' => $notes,
            ]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function checkIn(
        int $assetId,
        int $returnLocationId,
        string $actor = 'Admin GA',
        ?string $notes = null
    ): bool {
        $asset = $this->find($assetId);
        if (!$asset) {
            return false;
        }

        $fromLocationId = (int)$asset['location_id'];
        $recipient = $asset['assigned_to'];

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('
                UPDATE assets 
                SET status = ?, assigned_to = NULL, location_id = ?
                WHERE id = ?
            ');
            $stmt->execute([AssetStatus::Available->value, $returnLocationId, $assetId]);

            $moveModel = new MovementModel($this->db);
            $moveModel->record([
                'asset_id' => $assetId,
                'movement_type' => MovementType::CheckIn->value,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $returnLocationId,
                'actor_name' => $actor,
                'recipient_name' => $recipient,
                'notes' => $notes,
            ]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $stmt = $this->db->query('
            SELECT 
                COUNT(*) AS total_assets,
                SUM(purchase_cost) AS total_valuation,
                SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) AS count_available,
                SUM(CASE WHEN status = "in_use" THEN 1 ELSE 0 END) AS count_in_use,
                SUM(CASE WHEN status = "maintenance" THEN 1 ELSE 0 END) AS count_maintenance,
                SUM(CASE WHEN status = "disposed" THEN 1 ELSE 0 END) AS count_disposed
            FROM assets
        ');
        $row = $stmt->fetch() ?: [];

        return [
            'total_assets' => (int)($row['total_assets'] ?? 0),
            'total_valuation' => (float)($row['total_valuation'] ?? 0),
            'total_valuation_formatted' => 'Rp ' . number_format((float)($row['total_valuation'] ?? 0), 0, ',', '.'),
            'count_available' => (int)($row['count_available'] ?? 0),
            'count_in_use' => (int)($row['count_in_use'] ?? 0),
            'count_maintenance' => (int)($row['count_maintenance'] ?? 0),
            'count_disposed' => (int)($row['count_disposed'] ?? 0),
        ];
    }
}
