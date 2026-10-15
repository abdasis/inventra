<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Stock;

use Inventra\Core\Enums\ItemType;
use Inventra\Core\Enums\StockMovementType;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\StockRepository;

/**
 * Mencatat satu pergerakan stok barang habis pakai dan menyesuaikan saldonya.
 *
 * Kuantitas pada payload SELALU bilangan bulat positif; arahnya ditentukan
 * movement_type, dan khusus untuk koreksi (adjustment) ditentukan pula oleh field
 * direction: "increase" (bawaan) menambah saldo, "decrease" menguranginya. Saldo
 * tidak pernah boleh negatif — pengeluaran atau koreksi turun yang melebihi saldo
 * ditolak, jadi angka saldo selalu bisa direkonsiliasi dari riwayat pergerakan.
 * Action ini tidak memegang transaksi: Service yang membungkusnya.
 */
final class RecordStockMovementAction
{
    private const DIRECTION_INCREASE = 'increase';
    private const DIRECTION_DECREASE = 'decrease';

    public function __construct(
        private readonly StockRepository $stocks,
        private readonly ItemRepository $items,
        private readonly EmployeeRepository $employees,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $actorName Nama pelaku dari sesi, diserahkan Controller.
     * @return array{movement: array<string, mixed>|null, balance: array<string, mixed>|null}
     */
    public function execute(array $payload, string $actorName): array
    {
        $errors = [];

        $itemId = $this->intValue($payload['item_id'] ?? null);
        $item = $itemId > 0 ? $this->items->find($itemId) : null;

        if ($item === null) {
            $errors['item_id'] = 'Barang tidak ditemukan.';
        } elseif (($item['item_type'] ?? '') !== ItemType::Consumable->value) {
            $errors['item_id'] = 'Hanya barang habis pakai yang memiliki saldo stok.';
        }

        $type = StockMovementType::tryFrom($this->text($payload['movement_type'] ?? ''));
        if ($type === null) {
            $errors['movement_type'] = 'Jenis pergerakan tidak valid.';
        }

        $quantity = $this->quantityValue($payload['quantity'] ?? null);
        if ($quantity === null || $quantity <= 0) {
            $errors['quantity'] = 'Jumlah harus lebih dari 0.';
        }

        $direction = self::DIRECTION_INCREASE;
        if ($type === StockMovementType::Adjustment) {
            $direction = $this->text($payload['direction'] ?? '') ?: self::DIRECTION_INCREASE;
            if (!in_array($direction, [self::DIRECTION_INCREASE, self::DIRECTION_DECREASE], true)) {
                $errors['direction'] = 'Arah koreksi tidak valid.';
            }
        }

        $reason = $this->optionalText($payload['reason'] ?? null);
        if ($type === StockMovementType::Adjustment && $reason === null) {
            $errors['reason'] = 'Alasan koreksi wajib diisi.';
        }

        $employeeId = $this->intValue($payload['employee_id'] ?? null);
        $employee = $employeeId > 0 ? $this->employees->find($employeeId) : null;
        if ($employeeId > 0 && $employee === null) {
            $errors['employee_id'] = 'Karyawan tidak ditemukan.';
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        /** @var StockMovementType $type */
        /** @var int $quantity */
        $current = $this->currentQuantity($itemId);
        $balance = $this->nextBalance($type, $direction, $current, $quantity);

        if ($balance < 0) {
            throw new ValidationException(
                'Stok tidak mencukupi. Sisa ' . $current . ' unit.',
                ['quantity' => 'Stok tidak mencukupi. Sisa ' . $current . ' unit.']
            );
        }

        $this->stocks->setBalance($itemId, $balance);

        $movementId = $this->stocks->recordMovement([
            'item_id' => $itemId,
            'movement_type' => $type->value,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'department' => $this->optionalText($payload['department'] ?? null)
                ?? (!empty($employee['department']) ? (string)$employee['department'] : null),
            'employee_id' => $employee !== null ? (int)$employee['id'] : null,
            'reason' => $reason,
            'notes' => $this->optionalText($payload['notes'] ?? null),
            'actor_name' => $actorName,
        ]);

        return [
            'movement' => $this->stocks->findMovement($movementId),
            'balance' => $this->stocks->balance($itemId),
        ];
    }

    private function nextBalance(StockMovementType $type, string $direction, int $current, int $quantity): int
    {
        return match ($type) {
            StockMovementType::In => $current + $quantity,
            StockMovementType::Out => $current - $quantity,
            StockMovementType::Adjustment => $direction === self::DIRECTION_DECREASE
                ? $current - $quantity
                : $current + $quantity,
        };
    }

    private function currentQuantity(int $itemId): int
    {
        $balance = $this->stocks->balance($itemId);

        return $balance !== null ? (int)$balance['quantity'] : 0;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function optionalText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === '' ? null : $text;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Kosong berarti nol; null dikembalikan hanya bila nilainya bukan angka.
     */
    private function quantityValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return is_numeric($value) ? (int)$value : null;
    }
}
