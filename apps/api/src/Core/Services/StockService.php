<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Stock\RecordStockMovementAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\StockMovementDTO;
use Inventra\Core\Enums\StockMovementType;
use Inventra\Core\Repositories\StockRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi pencatatan pergerakan stok: menulis saldo dan catatan pergerakan
 * dalam satu transaksi — saldo tanpa jejak mutasi tidak bisa direkonsiliasi —
 * lalu menarasikan activity log.
 */
final class StockService
{
    public function __construct(
        private readonly StockRepository $stocks,
        private readonly RecordStockMovementAction $recordMovementAction,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $actorName Nama pelaku dari sesi, diserahkan Controller.
     * @return array{message: string, data: array<string, mixed>}
     */
    public function recordMovement(array $payload, string $actorName): array
    {
        $result = Connection::transaction(
            fn (): array => $this->recordMovementAction->execute($payload, $actorName)
        );

        $movement = $result['movement'] ?? [];
        $type = StockMovementType::tryFrom((string)($movement['movement_type'] ?? ''));

        ActivityLogger::log(
            $type === StockMovementType::In ? 'stock_in' : ($type === StockMovementType::Out ? 'stock_out' : 'stock_adjustment'),
            $this->description($movement, $type),
            'stock_movement',
            (int)($movement['id'] ?? 0),
            $this->ipAddress
        );

        return [
            'message' => match ($type) {
                StockMovementType::In => 'Stok masuk berhasil dicatat',
                StockMovementType::Out => 'Stok keluar berhasil dicatat',
                default => 'Koreksi stok berhasil dicatat',
            },
            'data' => [
                'movement' => StockMovementDTO::fromArray($movement)->toArray(),
                'balance' => $result['balance'],
            ],
        ];
    }

    /**
     * Narasi menyebut arah, jumlah, satuan, dan nama barangnya; pengeluaran dan
     * penerimaan menyebut departemen penerima bila ada, koreksi menyebut alasannya.
     *
     * @param array<string, mixed> $movement
     */
    private function description(array $movement, ?StockMovementType $type): string
    {
        $itemName = trim((string)($movement['item_name'] ?? ''));
        $quantity = $this->quantityWithUnit(
            (int)($movement['quantity'] ?? 0),
            $movement['unit_name'] ?? null
        );

        if ($type === StockMovementType::Adjustment) {
            return 'Mencatat koreksi stok ' . $itemName
                . ' (sebesar ' . $quantity . ', alasan: ' . (string)($movement['reason'] ?? '') . ')';
        }

        $verb = $type === StockMovementType::In ? 'penerimaan' : 'pengeluaran';
        $department = trim((string)($movement['department'] ?? ''));

        return 'Mencatat ' . $verb . ' ' . $quantity . ' ' . $itemName
            . ($department === '' ? '' : ' untuk departemen ' . $department);
    }

    /**
     * "5 Rim" — satuan dilewati bila barangnya tidak punya satuan.
     */
    private function quantityWithUnit(int $quantity, mixed $unitName): string
    {
        $unit = trim(is_scalar($unitName) ? (string)$unitName : '');

        return $quantity . ($unit === '' ? '' : ' ' . $unit);
    }
}
