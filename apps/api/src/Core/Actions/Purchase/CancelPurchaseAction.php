<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Purchase;

use Inventra\Core\Enums\PurchaseStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\PurchaseRepository;

/**
 * Membatalkan pembelian yang masih draf beserta alasannya. Pembelian yang sudah
 * menerima barang tidak boleh dibatalkan: stok dan unit aset yang lahir dari
 * penerimaan itu harus dikoreksi lewat transaksi, bukan dihapus diam-diam.
 */
final class CancelPurchaseAction
{
    public function __construct(private readonly PurchaseRepository $purchases)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Baris pembelian sesudah dibatalkan.
     */
    public function execute(int $id, array $payload): array
    {
        $existing = $this->purchases->find($id)
            ?? throw new NotFoundException('Pembelian tidak ditemukan.');

        if (PurchaseStatus::tryFrom((string)$existing['status']) !== PurchaseStatus::Draft) {
            throw new ValidationException(
                'Pembelian yang sudah menerima barang tidak dapat dibatalkan. '
                . 'Koreksi lewat transaksi stok atau penghapusan aset.'
            );
        }

        $reason = trim(is_scalar($payload['reason'] ?? null) ? (string)$payload['reason'] : '');
        if ($reason === '') {
            throw new ValidationException('Alasan pembatalan wajib diisi.', ['reason' => 'Alasan pembatalan wajib diisi.']);
        }

        $this->purchases->markStatus($id, PurchaseStatus::Cancelled, [
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancel_reason' => $reason,
        ]);

        return $this->purchases->find($id) ?? $existing;
    }
}
