<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

/**
 * Jenis perawatan pada satu catatan pemeliharaan: terencana, perbaikan atas
 * kerusakan, atau pemeriksaan rutin tanpa perbaikan.
 */
enum MaintenanceType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Inspection = 'inspection';

    public function label(): string
    {
        return match ($this) {
            self::Preventive => 'Preventif',
            self::Corrective => 'Korektif',
            self::Inspection => 'Inspeksi',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Preventive => 'bg-info-subtle text-info border border-info-subtle',
            self::Corrective => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Inspection => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
