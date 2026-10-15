<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

/**
 * Cara aset dikeluarkan dari daftar aktif: dijual, disumbangkan, dimusnahkan,
 * atau dihapusbukukan tanpa perpindahan fisik.
 */
enum DisposalMethod: string
{
    case Sold = 'sold';
    case Donated = 'donated';
    case Destroyed = 'destroyed';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Sold => 'Dijual',
            self::Donated => 'Disumbangkan',
            self::Destroyed => 'Dimusnahkan',
            self::WrittenOff => 'Dihapusbukukan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Sold => 'bg-info-subtle text-info border border-info-subtle',
            self::Donated => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::Destroyed => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::WrittenOff => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
