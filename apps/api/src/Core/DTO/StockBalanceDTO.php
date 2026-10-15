<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

/**
 * Saldo satu barang habis pakai. Barang yang belum pernah bergerak tetap
 * ditampilkan dengan saldo nol dan updated_at kosong, karena daftar saldo
 * berperan sebagai buku besar seluruh barang.
 */
final readonly class StockBalanceDTO
{
    public function __construct(
        public int $itemId,
        public ?string $itemCode,
        public ?string $itemName,
        public ?string $categoryName,
        public ?string $unitName,
        public int $quantity,
        public int $minimumStock,
        public bool $isLow,
        public ?string $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $quantity = (int)($data['quantity'] ?? 0);
        $minimumStock = (int)($data['minimum_stock'] ?? 0);

        return new self(
            itemId: (int)($data['item_id'] ?? 0),
            itemCode: !empty($data['item_code']) ? (string)$data['item_code'] : null,
            itemName: !empty($data['item_name']) ? (string)$data['item_name'] : null,
            categoryName: !empty($data['category_name']) ? (string)$data['category_name'] : null,
            unitName: !empty($data['unit_name']) ? (string)$data['unit_name'] : null,
            quantity: $quantity,
            minimumStock: $minimumStock,
            isLow: $quantity <= $minimumStock,
            updatedAt: !empty($data['updated_at']) ? (string)$data['updated_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'item_code' => $this->itemCode,
            'item_name' => $this->itemName,
            'category_name' => $this->categoryName,
            'unit_name' => $this->unitName,
            'quantity' => $this->quantity,
            'minimum_stock' => $this->minimumStock,
            'is_low' => $this->isLow,
            'updated_at' => $this->updatedAt,
        ];
    }
}
