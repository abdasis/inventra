<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

/**
 * Jenis pergerakan stok. Kuantitas pada catatan selalu positif; arahnya yang
 * ditentukan jenis ini, dan untuk koreksi ditentukan pula oleh arah koreksinya.
 */
enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stok Masuk',
            self::Out => 'Stok Keluar',
            self::Adjustment => 'Koreksi Stok',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::In => 'bg-success-subtle text-success border border-success-subtle',
            self::Out => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::Adjustment => 'bg-warning-subtle text-warning border border-warning-subtle',
        };
    }
}
