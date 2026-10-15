<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\AcquisitionSource;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\AssetStatus;
use InvalidArgumentException;

final readonly class AssetDTO
{
    public function __construct(
        public ?int $id,
        public string $assetCode,
        public string $name,
        public int $categoryId,
        public int $locationId,
        public ?string $serialNumber,
        public ?string $purchaseDate,
        public float $purchaseCost,
        public AssetStatus $status,
        public AssetCondition $conditionLevel,
        public ?string $assignedTo,
        public ?string $notes,
        public ?string $categoryName = null,
        public ?string $locationName = null,
        public ?int $itemId = null,
        public ?string $itemName = null,
        public AcquisitionSource $acquisitionSource = AcquisitionSource::Purchase,
        public ?string $purchaseReference = null,
        public ?string $warrantyExpiry = null,
        public ?string $photoPath = null,
        public ?string $documentPath = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Nama aset wajib diisi.');
        }

        $code = trim((string)($data['asset_code'] ?? ''));
        if ($code === '') {
            // Auto generate fallback code if not provided
            $code = 'AST-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
        }

        $statusStr = (string)($data['status'] ?? 'available');
        $status = AssetStatus::tryFrom($statusStr) ?? AssetStatus::Available;

        $condStr = (string)($data['condition_level'] ?? 'good');
        $condition = AssetCondition::tryFrom($condStr) ?? AssetCondition::Good;

        // Baris lama bisa saja belum punya sumber perolehan; defaultnya pembelian.
        $sourceStr = (string)($data['acquisition_source'] ?? '');
        $source = AcquisitionSource::tryFrom($sourceStr) ?? AcquisitionSource::Purchase;

        $itemId = (int)($data['item_id'] ?? 0);

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            assetCode: $code,
            name: $name,
            categoryId: (int)($data['category_id'] ?? 1),
            locationId: (int)($data['location_id'] ?? 1),
            serialNumber: !empty($data['serial_number']) ? (string)$data['serial_number'] : null,
            purchaseDate: !empty($data['purchase_date']) ? (string)$data['purchase_date'] : null,
            purchaseCost: (float)($data['purchase_cost'] ?? 0.0),
            status: $status,
            conditionLevel: $condition,
            assignedTo: !empty($data['assigned_to']) ? (string)$data['assigned_to'] : null,
            notes: !empty($data['notes']) ? (string)$data['notes'] : null,
            categoryName: isset($data['category_name']) ? (string)$data['category_name'] : null,
            locationName: isset($data['location_name']) ? (string)$data['location_name'] : null,
            itemId: $itemId > 0 ? $itemId : null,
            itemName: isset($data['item_name']) ? (string)$data['item_name'] : null,
            acquisitionSource: $source,
            purchaseReference: !empty($data['purchase_reference']) ? (string)$data['purchase_reference'] : null,
            warrantyExpiry: !empty($data['warranty_expiry']) ? (string)$data['warranty_expiry'] : null,
            photoPath: !empty($data['photo_path']) ? (string)$data['photo_path'] : null,
            documentPath: !empty($data['document_path']) ? (string)$data['document_path'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'asset_code' => $this->assetCode,
            'name' => $this->name,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
            'location_id' => $this->locationId,
            'location_name' => $this->locationName,
            'serial_number' => $this->serialNumber,
            'purchase_date' => $this->purchaseDate,
            'purchase_cost' => $this->purchaseCost,
            'purchase_cost_formatted' => 'Rp ' . number_format($this->purchaseCost, 0, ',', '.'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badgeClass(),
            'condition_level' => $this->conditionLevel->value,
            'condition_label' => $this->conditionLevel->label(),
            'condition_badge' => $this->conditionLevel->badgeClass(),
            'assigned_to' => $this->assignedTo,
            'notes' => $this->notes,
            'item_id' => $this->itemId,
            'item_name' => $this->itemName,
            'acquisition_source' => $this->acquisitionSource->value,
            'acquisition_source_label' => $this->acquisitionSource->label(),
            'purchase_reference' => $this->purchaseReference,
            'warranty_expiry' => $this->warrantyExpiry,
            'photo_path' => $this->photoPath,
            'document_path' => $this->documentPath,
        ];
    }
}
