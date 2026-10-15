<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Transfer;

use Inventra\Core\Actions\Concerns\AssetCustodyInput;
use Inventra\Core\Enums\TransferStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetTransferRepository;

/**
 * Membatalkan mutasi yang belum diselesaikan. Asetnya sengaja tidak disentuh:
 * lokasi aktif hanya berubah lewat penyelesaian, jadi pembatalan cukup menutup
 * barisnya beserta alasannya. Barisnya tetap ada sebagai histori.
 */
final class CancelTransferAction
{
    use AssetCustodyInput;

    public function __construct(
        private readonly AssetTransferRepository $transfers,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Baris mutasi sesudah dibatalkan.
     */
    public function execute(int $id, array $payload): array
    {
        $transfer = $this->transfers->find($id)
            ?? throw new NotFoundException('Mutasi tidak ditemukan.');

        if (TransferStatus::tryFrom((string)$transfer['status']) !== TransferStatus::Pending) {
            throw new ValidationException(
                'Mutasi ini sudah diselesaikan atau dibatalkan.',
                ['status' => 'Mutasi ini sudah diselesaikan atau dibatalkan.']
            );
        }

        $reason = $this->custodyOptionalText($payload['reason'] ?? null);
        if ($reason === null) {
            throw new ValidationException(
                'Alasan pembatalan wajib diisi.',
                ['reason' => 'Alasan pembatalan wajib diisi.']
            );
        }

        $this->transfers->update($id, [
            'status' => TransferStatus::Cancelled->value,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancel_reason' => $reason,
        ]);

        return $this->transfers->find($id) ?? $transfer;
    }
}
