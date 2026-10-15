<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\ItemType;
use InvalidArgumentException;

final readonly class ItemDTO
{
    public function __construct(
        public ?int $id,
        public string $code,
        public string $name,
        public ?string $brand,
        public ?string $model,
        public ?string $specification,
        public int $categoryId,
        public int $unitId,
        public ItemType $itemType,
        public int $minimumStock,
        public ?string $description,
        public ?string $categoryName = null,
        public ?string $unitName = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Nama barang wajib diisi.');
        }

        $code = trim((string)($data['code'] ?? ''));
        if ($code === '') {
            throw new InvalidArgumentException('Kode barang wajib diisi.');
        }

        $typeStr = (string)($data['item_type'] ?? ItemType::Asset->value);
        $itemType = ItemType::tryFrom($typeStr);
        if ($itemType === null) {
            throw new InvalidArgumentException('Jenis barang tidak valid.');
        }

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            code: $code,
            name: $name,
            brand: !empty($data['brand']) ? (string)$data['brand'] : null,
            model: !empty($data['model']) ? (string)$data['model'] : null,
            specification: !empty($data['specification']) ? (string)$data['specification'] : null,
            categoryId: (int)($data['category_id'] ?? 0),
            unitId: (int)($data['unit_id'] ?? 0),
            itemType: $itemType,
            minimumStock: (int)($data['minimum_stock'] ?? 0),
            description: !empty($data['description']) ? (string)$data['description'] : null,
            categoryName: isset($data['category_name']) ? (string)$data['category_name'] : null,
            unitName: isset($data['unit_name']) ? (string)$data['unit_name'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'brand' => $this->brand,
            'model' => $this->model,
            'specification' => $this->specification,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
            'unit_id' => $this->unitId,
            'unit_name' => $this->unitName,
            'item_type' => $this->itemType->value,
            'item_type_label' => $this->itemType->label(),
            'item_type_badge' => $this->itemType->badgeClass(),
            'minimum_stock' => $this->minimumStock,
            'description' => $this->description,
        ];
    }
}
