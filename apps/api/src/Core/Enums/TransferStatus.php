<?php

declare(strict_types=1);

namespace Inventra\Core\Enums;

/**
 * Status satu mutasi aset. Rencana perpindahan disimpan sebagai pending lebih
 * dulu; hanya baris yang completed yang memindahkan lokasi aktif aset, sedangkan
 * cancelled menutup rencana tanpa menyentuh asetnya.
 */
enum TransferStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Completed => 'bg-success-subtle text-success border border-success-subtle',
            self::Cancelled => 'bg-danger-subtle text-danger border border-danger-subtle',
        };
    }
}
