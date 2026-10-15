<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::InUse => 'Dipakai',
            self::Maintenance => 'Perbaikan',
            self::Disposed => 'Dihapus/Afkir',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'bg-success-subtle text-success border border-success-subtle',
            self::InUse => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::Maintenance => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Disposed => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
