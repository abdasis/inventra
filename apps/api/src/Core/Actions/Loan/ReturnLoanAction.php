<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Loan;

use Inventra\Core\Actions\Concerns\AssetCustodyInput;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\LocationRepository;

/**
 * Menutup peminjaman: aset kembali tersedia tanpa pemegang, dan barisnya
 * menyimpan kondisi saat kembali. Peminjaman yang sudah dikembalikan tidak boleh
 * ditutup dua kali.
 */
final class ReturnLoanAction
{
    use AssetCustodyInput;

    public function __construct(
        private readonly AssetRepository $assets,
        private readonly AssetLoanRepository $loans,
        private readonly LocationRepository $locations,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Baris peminjaman sesudah ditutup.
     */
    public function execute(int $id, array $payload, string $actor): array
    {
        $loan = $this->loans->find($id)
            ?? throw new NotFoundException('Peminjaman tidak ditemukan.');

        if ((string)$loan['status'] === 'returned') {
            throw new ValidationException(
                'Peminjaman ini sudah dikembalikan.',
                ['status' => 'Peminjaman ini sudah dikembalikan.']
            );
        }

        $assetId = (int)$loan['asset_id'];
        $asset = $this->assets->find($assetId)
            ?? throw new NotFoundException('Aset tidak ditemukan.');

        $returnLocationId = $this->custodyInt($payload['return_location_id'] ?? null);
        $notes = $this->custodyOptionalText($payload['notes'] ?? null);
        $conditionIn = null;
        $errors = [];

        if ($returnLocationId <= 0 || $this->locations->find($returnLocationId) === null) {
            $errors['return_location_id'] = 'Lokasi penyimpanan tidak ditemukan.';
        }

        $rawCondition = $this->custodyText($payload['condition_in'] ?? null);
        if ($rawCondition !== '') {
            $conditionIn = AssetCondition::tryFrom($rawCondition);
            if ($conditionIn === null) {
                $errors['condition_in'] = 'Kondisi aset tidak valid.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        $this->loans->close($id, [
            'returned_at' => date('Y-m-d H:i:s'),
            'condition_in' => ($conditionIn ?? $this->currentCondition($asset))->value,
            'received_by_name' => $actor,
        ]);

        $this->assets->checkIn(
            $assetId,
            $returnLocationId,
            $actor,
            AssetStatus::Available,
            (int)$asset['location_id'],
            $asset['assigned_to'] !== null ? (string)$asset['assigned_to'] : null,
            $notes
        );

        return $this->loans->find($id) ?? $loan;
    }

    /**
     * Kondisi aset saat ini dipakai bila pengembalian tidak menyebut kondisinya,
     * supaya kolom condition_in tidak pernah kosong tanpa alasan.
     *
     * @param array<string, mixed> $asset
     */
    private function currentCondition(array $asset): AssetCondition
    {
        return AssetCondition::tryFrom((string)($asset['condition_level'] ?? '')) ?? AssetCondition::Good;
    }
}
