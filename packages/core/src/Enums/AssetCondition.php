<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

enum AssetCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Sangat Baik (Baru)',
            self::Good => 'Baik (Normal)',
            self::Fair => 'Cukup (Ada Aus)',
            self::Damaged => 'Rusak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'bg-info-subtle text-info',
            self::Good => 'bg-success-subtle text-success',
            self::Fair => 'bg-warning-subtle text-warning',
            self::Damaged => 'bg-danger-subtle text-danger',
        };
    }
}
