<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Loan;

use Inventra\Core\Actions\Concerns\AssetCustodyInput;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\MovementType;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\MovementRepository;

/**
 * Mencatat peminjaman sementara aset. Satu aset tidak boleh punya lebih dari
 * satu peminjaman aktif pada waktu yang sama, dan aset yang sedang dipegang,
 * dalam perbaikan, atau sudah dihapus ditolak dengan pesan masing-masing supaya
 * penyebabnya jelas. Actor diserahkan pemanggil, bukan dibaca dari sesi.
 */
final class CreateLoanAction
{
    use AssetCustodyInput;

    public function __construct(
        private readonly AssetRepository $assets,
        private readonly AssetLoanRepository $loans,
        private readonly AssetAssignmentRepository $assignments,
        private readonly EmployeeRepository $employees,
        private readonly LocationRepository $locations,
        private readonly MovementRepository $movements,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $actorName Nama pelaku dari sesi, diserahkan Controller.
     * @return int Id baris peminjaman yang baru dibuat.
     */
    public function execute(array $payload, string $actorName): int
    {
        $assetId = $this->custodyInt($payload['asset_id'] ?? null);
        $employeeId = $this->custodyOptionalInt($payload['employee_id'] ?? null);
        $borrowerName = $this->custodyOptionalText($payload['borrower_name'] ?? null);
        $locationId = $this->custodyOptionalInt($payload['location_id'] ?? null);
        $loanedAt = $this->custodyDateTime($payload['loaned_at'] ?? null) ?? date('Y-m-d H:i:s');
        $dueAt = $this->custodyDate($payload['due_at'] ?? null);
        $purpose = $this->custodyOptionalText($payload['purpose'] ?? null);
        $notes = $this->custodyOptionalText($payload['notes'] ?? null);
        $errors = [];

        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        $assetStatus = $asset !== null ? AssetStatus::tryFrom((string)$asset['status']) : null;

        if ($asset === null) {
            $errors['asset_id'] = 'Aset tidak ditemukan.';
        } elseif ($assetStatus === AssetStatus::Disposed) {
            $errors['asset_id'] = 'Aset yang sudah dihapus tidak dapat dipakai transaksi baru.';
        } elseif ($this->loans->activeForAsset($assetId) !== null) {
            $errors['asset_id'] = 'Aset sedang dipinjam dan belum dikembalikan.';
        } elseif ($this->assignments->activeForAsset($assetId) !== null) {
            $errors['asset_id'] = 'Aset sedang berada pada pemegang lain.';
        } elseif ($assetStatus === AssetStatus::Maintenance) {
            $errors['asset_id'] = 'Aset sedang dalam perbaikan.';
        }

        $employee = $employeeId !== null ? $this->employees->find($employeeId) : null;
        if ($employeeId !== null && $employee === null) {
            $errors['employee_id'] = 'Karyawan tidak ditemukan.';
        }

        if ($locationId !== null && $this->locations->find($locationId) === null) {
            $errors['location_id'] = 'Lokasi tidak ditemukan.';
        }

        if ($this->custodyText($payload['due_at'] ?? null) !== '') {
            if ($dueAt === null) {
                $errors['due_at'] = 'Jatuh tempo tidak valid.';
            } elseif ($dueAt < substr($loanedAt, 0, 10)) {
                $errors['due_at'] = 'Jatuh tempo tidak boleh lebih awal dari tanggal pinjam.';
            }
        }

        [$conditionOut, $conditionValid] = $this->custodyCondition($payload['condition_out'] ?? null);
        if (!$conditionValid) {
            $errors['condition_out'] = 'Kondisi aset tidak valid.';
        }

        // Peminjam diambil dari nama karyawan; tanpa karyawan, nama peminjam yang
        // diisi klien dipakai supaya pinjaman tetap punya pemegang yang jelas.
        $holderName = $employee !== null ? (string)$employee['name'] : $borrowerName;
        if ($holderName === null || $holderName === '') {
            $errors['borrower_name'] = 'Nama peminjam wajib diisi.';
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        $id = $this->loans->create([
            'asset_id' => $assetId,
            'employee_id' => $employeeId,
            'location_id' => $locationId,
            'loaned_at' => $loanedAt,
            'due_at' => $dueAt,
            'condition_out' => $conditionOut->value,
            'handed_by_name' => $actorName,
            'received_by_name' => $holderName,
            'purpose' => $purpose,
            'notes' => $notes,
        ]);

        // Lokasi aset sengaja tidak diubah: peminjaman sementara tidak memindahkan
        // aset ke master lokasi, hanya menandai pemegangnya.
        $this->assets->setHolder($assetId, $employeeId, (string)$holderName, null, AssetStatus::OnLoan);

        $this->movements->record([
            'asset_id' => $assetId,
            'movement_type' => MovementType::CheckOut->value,
            'from_location_id' => (int)$asset['location_id'],
            'to_location_id' => $locationId,
            'actor_name' => $actorName,
            'recipient_name' => $holderName,
            'notes' => $purpose !== null ? 'Peminjaman sementara: ' . $purpose : 'Peminjaman sementara',
        ]);

        return $id;
    }
}
