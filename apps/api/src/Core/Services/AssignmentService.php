<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Assignment\CreateAssignmentAction;
use Inventra\Core\Actions\Assignment\ReturnAssignmentAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetAssignmentDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi use case penempatan aset: memanggil Action, memegang transaksi —
 * satu penempatan menyentuh baris penempatan, aset, dan pergerakan aset — lalu
 * menarasikan activity log. Controller hanya meneruskan hasilnya ke Response.
 */
final class AssignmentService
{
    public function __construct(
        private readonly AssetAssignmentRepository $assignments,
        private readonly CreateAssignmentAction $createAssignment,
        private readonly ReturnAssignmentAction $returnAssignment,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload, string $actorName): array
    {
        $id = Connection::transaction(fn (): int => $this->createAssignment->execute($payload, $actorName));
        $data = $this->present($this->row($id));

        ActivityLogger::log(
            'create',
            'Menempatkan aset ' . $this->assetLabel($data) . ' kepada ' . $this->holder($data)
                . ' di ' . (string)$data['location_name'],
            'asset_assignment',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Penempatan aset berhasil dicatat', 'data' => $data];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function return(int $id, array $payload, string $actor): array
    {
        $row = Connection::transaction(fn (): array => $this->returnAssignment->execute($id, $payload, $actor));
        $data = $this->present($row);

        ActivityLogger::log(
            'return',
            'Menerima kembali aset ' . $this->assetLabel($data) . ' dari ' . $this->holder($data)
                . ' (kondisi: ' . (string)$data['condition_in_label'] . ')',
            'asset_assignment',
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
        return $this->assignments->find($id) ?? throw new NotFoundException('Penempatan tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return AssetAssignmentDTO::fromArray($row)->toArray();
    }

    /**
     * Pemegang ditampilkan dari nama karyawan bila ada, selain itu dari nama
     * penerima yang tersimpan di barisnya.
     *
     * @param array<string, mixed> $data Hasil present().
     */
    private function holder(array $data): string
    {
        return (string)($data['employee_name'] ?? $data['received_by_name'] ?? '');
    }

    /**
     * @param array<string, mixed> $data Hasil present().
     */
    private function assetLabel(array $data): string
    {
        return (string)$data['asset_name'] . ' (' . (string)$data['asset_code'] . ')';
    }
}
