<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

enum MovementType: string
{
    case CheckOut = 'check_out';
    case CheckIn = 'check_in';
    case Relocate = 'relocate';
    case Repair = 'repair';
    case Dispose = 'dispose';

    public function label(): string
    {
        return match ($this) {
            self::CheckOut => 'Peminjaman / Serah Terima',
            self::CheckIn => 'Pengembalian',
            self::Relocate => 'Mutasi Ruangan',
            self::Repair => 'Kirim Servis',
            self::Dispose => 'Pemusnahan / Afkir',
        };
    }
}
