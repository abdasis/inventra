<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

/**
 * Status satu catatan pemeliharaan. Selama ongoing asetnya berstatus maintenance;
 * completed mengembalikan aset ke available, dan cancelled menutup catatan tanpa
 * mengubah aset.
 */
enum MaintenanceStatus: string
{
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Ongoing => 'Sedang Dikerjakan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Ongoing => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Completed => 'bg-success-subtle text-success border border-success-subtle',
            self::Cancelled => 'bg-danger-subtle text-danger border border-danger-subtle',
        };
    }
}
