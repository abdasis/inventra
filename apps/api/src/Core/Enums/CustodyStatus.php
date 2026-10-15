<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

/**
 * Status satu transaksi penitipan: penempatan (asset_assignments) dan peminjaman
 * (asset_loans) memakai kosakata yang sama, active selama aset masih dipegang dan
 * returned sesudah dikembalikan.
 */
enum CustodyStatus: string
{
    case Active = 'active';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Returned => 'Dikembalikan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::Returned => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
