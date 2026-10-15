<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\DisposalMethod;

/**
 * Satu baris penghapusan aset siap tampil. Nama aset dan kategorinya diisi dari
 * join daftar; label metode dan format rupiah nilai buku dihitung di sini.
 */
final readonly class AssetDisposalDTO
{
    public function __construct(
        public ?int $id,
        public int $assetId,
        public ?string $assetCode,
        public ?string $assetName,
        public ?string $categoryName,
        public string $disposalDate,
        public string $reason,
        public DisposalMethod $method,
        public ?float $bookValue,
        public ?string $documentReference,
        public ?string $approvedByName,
        public string $executedByName,
        public ?string $notes,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            assetId: (int)($data['asset_id'] ?? 0),
            assetCode: !empty($data['asset_code']) ? (string)$data['asset_code'] : null,
            assetName: !empty($data['asset_name']) ? (string)$data['asset_name'] : null,
            categoryName: !empty($data['category_name']) ? (string)$data['category_name'] : null,
            disposalDate: (string)($data['disposal_date'] ?? ''),
            reason: (string)($data['reason'] ?? ''),
            method: DisposalMethod::tryFrom((string)($data['method'] ?? '')) ?? DisposalMethod::WrittenOff,
            bookValue: isset($data['book_value']) ? (float)$data['book_value'] : null,
            documentReference: !empty($data['document_reference']) ? (string)$data['document_reference'] : null,
            approvedByName: !empty($data['approved_by_name']) ? (string)$data['approved_by_name'] : null,
            executedByName: (string)($data['executed_by_name'] ?? ''),
            notes: !empty($data['notes']) ? (string)$data['notes'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->assetId,
            'asset_code' => $this->assetCode,
            'asset_name' => $this->assetName,
            'category_name' => $this->categoryName,
            'disposal_date' => $this->disposalDate,
            'reason' => $this->reason,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'book_value' => $this->bookValue,
            'book_value_formatted' => $this->bookValue === null
                ? null
                : 'Rp ' . number_format($this->bookValue, 0, ',', '.'),
            'document_reference' => $this->documentReference,
            'approved_by_name' => $this->approvedByName,
            'executed_by_name' => $this->executedByName,
            'notes' => $this->notes,
        ];
    }
}
