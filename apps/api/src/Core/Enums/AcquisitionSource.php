<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

enum AcquisitionSource: string
{
    case Purchase = 'purchase';
    case Grant = 'grant';
    case Transfer = 'transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Pembelian',
            self::Grant => 'Hibah/Donasi',
            self::Transfer => 'Transfer Antar Unit',
            self::Other => 'Lainnya',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Purchase => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::Grant => 'bg-info-subtle text-info border border-info-subtle',
            self::Transfer => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Other => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
