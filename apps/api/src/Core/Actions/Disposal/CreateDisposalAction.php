<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Disposal;

use Inventra\Core\Actions\Concerns\AssetCustodyInput;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\DisposalMethod;
use Inventra\Core\Enums\MovementType;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetDisposalRepository;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\MovementRepository;

/**
 * Menghapus aset dari daftar aktif. Asetnya tidak dibuang dari tabel assets —
 * hanya berstatus disposed supaya riwayatnya tetap utuh dan transaksi baru
 * menolaknya. Satu aset hanya boleh dihapus sekali, dan pemeriksaannya dilakukan
 * di sini supaya pesannya ramah, bukan error SQL dari UNIQUE asset_id.
 */
final class CreateDisposalAction
{
    use AssetCustodyInput;

    public function __construct(
        private readonly AssetRepository $assets,
        private readonly AssetDisposalRepository $disposals,
        private readonly AssetAssignmentRepository $assignments,
        private readonly AssetLoanRepository $loans,
        private readonly MovementRepository $movements,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $actorName Nama pelaku dari sesi, diserahkan Controller.
     * @return int Id baris penghapusan yang baru dibuat.
     */
    public function execute(array $payload, string $actorName): int
    {
        $assetId = $this->custodyInt($payload['asset_id'] ?? null);
        $disposalDate = $this->custodyDate($payload['disposal_date'] ?? null) ?? date('Y-m-d');
        $reason = $this->custodyOptionalText($payload['reason'] ?? null);
        $rawMethod = $this->custodyText($payload['method'] ?? null);
        $documentReference = $this->custodyOptionalText($payload['document_reference'] ?? null);
        $approvedByName = $this->custodyOptionalText($payload['approved_by_name'] ?? null);
        $notes = $this->custodyOptionalText($payload['notes'] ?? null);
        $errors = [];

        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        $assetStatus = $asset !== null ? AssetStatus::tryFrom((string)$asset['status']) : null;

        if ($asset === null) {
            $errors['asset_id'] = 'Aset tidak ditemukan.';
        } elseif ($assetStatus === AssetStatus::Disposed || $this->disposals->findByAsset($assetId) !== null) {
            $errors['asset_id'] = 'Aset ini sudah dihapus sebelumnya.';
        } elseif ($this->loans->activeForAsset($assetId) !== null
            || $this->assignments->activeForAsset($assetId) !== null
            || $assetStatus === AssetStatus::Maintenance) {
            $errors['asset_id'] = 'Tarik kembali aset dan selesaikan pemeliharaan sebelum menghapusnya.';
        }

        if ($this->custodyText($payload['disposal_date'] ?? null) !== ''
            && $this->custodyDate($payload['disposal_date'] ?? null) === null) {
            $errors['disposal_date'] = 'Tanggal penghapusan tidak valid.';
        }

        if ($reason === null) {
            $errors['reason'] = 'Alasan penghapusan wajib diisi.';
        }

        $method = DisposalMethod::tryFrom($rawMethod) ?? DisposalMethod::WrittenOff;
        if ($rawMethod !== '' && DisposalMethod::tryFrom($rawMethod) === null) {
            $errors['method'] = 'Metode penghapusan tidak valid.';
        }

        $bookValue = $this->bookValue($payload, $errors);

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        $id = $this->disposals->create([
            'asset_id' => $assetId,
            'disposal_date' => $disposalDate,
            'reason' => (string)$reason,
            'method' => $method->value,
            'book_value' => $bookValue,
            'document_reference' => $documentReference,
            'approved_by_name' => $approvedByName,
            'executed_by_name' => $actorName,
            'notes' => $notes,
        ]);

        // Pemegang dilepas dan statusnya disposed; lokasinya dibiarkan supaya
        // asal-usul aset terakhir tetap bisa ditelusuri dari histori.
        $this->assets->setHolder($assetId, null, null, null, AssetStatus::Disposed);

        $this->movements->record([
            'asset_id' => $assetId,
            'movement_type' => MovementType::Dispose->value,
            'from_location_id' => (int)$asset['location_id'],
            'to_location_id' => null,
            'actor_name' => $actorName,
            'recipient_name' => null,
            'notes' => 'Alasan: ' . (string)$reason,
        ]);

        return $id;
    }

    /**
     * Nilai buku boleh dikosongkan; bila diisi harus angka yang tidak negatif.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $errors Dilengkapi di tempat.
     * @return float|null Null berarti kolomnya dibiarkan kosong.
     */
    private function bookValue(array $payload, array &$errors): ?float
    {
        if (!array_key_exists('book_value', $payload) || $this->custodyText($payload['book_value']) === '') {
            return null;
        }

        $value = $this->custodyDecimal($payload['book_value']);
        if ($value === null) {
            $errors['book_value'] = 'Nilai buku tidak valid.';

            return null;
        }

        if ($value < 0) {
            $errors['book_value'] = 'Nilai buku tidak boleh negatif.';

            return null;
        }

        return $value;
    }
}
