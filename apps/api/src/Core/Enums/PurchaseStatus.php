<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Partial = 'partial';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Partial => 'Diterima Sebagian',
            self::Received => 'Diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            self::Partial => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Received => 'bg-success-subtle text-success border border-success-subtle',
            self::Cancelled => 'bg-danger-subtle text-danger border border-danger-subtle',
        };
    }
}
