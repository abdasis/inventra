<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Loan\CreateLoanAction;
use Inventra\Core\Actions\Loan\ReturnLoanAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetLoanDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi use case peminjaman aset: memanggil Action, memegang transaksi —
 * satu peminjaman menyentuh baris pinjaman, aset, dan pergerakan aset — lalu
 * menarasikan activity log. Controller hanya meneruskan hasilnya ke Response.
 */
final class LoanService
{
    public function __construct(
        private readonly AssetLoanRepository $loans,
        private readonly CreateLoanAction $createLoan,
        private readonly ReturnLoanAction $returnLoan,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload, string $actorName): array
    {
        $id = Connection::transaction(fn (): int => $this->createLoan->execute($payload, $actorName));
        $data = $this->present($this->row($id));

        ActivityLogger::log(
            'create',
            'Meminjamkan aset ' . $this->assetLabel($data) . ' kepada ' . $this->borrower($data)
                . $this->dueSuffix($data),
            'asset_loan',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Peminjaman aset berhasil dicatat', 'data' => $data];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function return(int $id, array $payload, string $actor): array
    {
        $row = Connection::transaction(fn (): array => $this->returnLoan->execute($id, $payload, $actor));
        $data = $this->present($row);

        ActivityLogger::log(
            'return',
            'Menerima kembali aset ' . $this->assetLabel($data) . ' dari peminjaman ' . $this->borrower($data)
                . ' (kondisi: ' . (string)$data['condition_in_label'] . ')',
            'asset_loan',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Pengembalian aset berhasil dicatat', 'data' => $data];
    }

    /**
     * @return array{data: array<string, mixed>}
     */
    public function detail(int $id): array
    {
        return ['data' => $this->present($this->row($id))];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id): array
    {
        return $this->loans->find($id) ?? throw new NotFoundException('Peminjaman tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return AssetLoanDTO::fromArray($row)->toArray();
    }

    /**
     * Peminjam ditampilkan dari nama karyawan bila ada, selain itu dari nama
     * penerima yang tersimpan di barisnya.
     *
     * @param array<string, mixed> $data Hasil present().
     */
    private function borrower(array $data): string
    {
        return (string)($data['employee_name'] ?? $data['received_by_name'] ?? '');
    }

    /**
     * @param array<string, mixed> $data Hasil present().
     */
    private function dueSuffix(array $data): string
    {
        return empty($data['due_at']) ? '' : ', jatuh tempo ' . (string)$data['due_at'];
    }

    /**
     * @param array<string, mixed> $data Hasil present().
     */
    private function assetLabel(array $data): string
    {
        return (string)$data['asset_name'] . ' (' . (string)$data['asset_code'] . ')';
    }
}
