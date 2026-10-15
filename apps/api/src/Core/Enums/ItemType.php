<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

enum ItemType: string
{
    case Asset = 'asset';
    case Consumable = 'consumable';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Aset',
            self::Consumable => 'Barang Habis Pakai',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Asset => 'bg-primary-subtle text-primary border border-primary-subtle',
            self::Consumable => 'bg-warning-subtle text-warning border border-warning-subtle',
        };
    }
}
