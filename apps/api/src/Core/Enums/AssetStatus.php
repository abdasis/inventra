<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case Disposed = 'disposed';
    case OnLoan = 'on_loan';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::InUse => 'Dipakai',
            self::Maintenance => 'Perbaikan',
            self::Disposed => 'Dihapus/Afkir',
            self::OnLoan => 'Sedang Dipinjam',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'bg-success-subtle text-success border border-success-subtle',
            self::InUse => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::Maintenance => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Disposed => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            self::OnLoan => 'bg-info-subtle text-info border border-info-subtle',
        };
    }
}
