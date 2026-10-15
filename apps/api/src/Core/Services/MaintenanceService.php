<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Maintenance\CompleteMaintenanceAction;
use Inventra\Core\Actions\Maintenance\CreateMaintenanceAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetMaintenanceDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetMaintenanceRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi use case pemeliharaan aset: memanggil Action dan memegang transaksi
 * untuk use case yang menulis lebih dari satu tabel — pencatatan pemeliharaan
 * sekaligus menandai status aset dan mencatat pergerakannya — lalu menarasikan
 * activity log. Controller hanya meneruskan hasilnya ke Response.
 */
final class MaintenanceService
{
    public function __construct(
        private readonly AssetMaintenanceRepository $maintenances,
        private readonly CreateMaintenanceAction $createMaintenance,
        private readonly CompleteMaintenanceAction $completeMaintenance,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload, string $actorName): array
    {
        $id = Connection::transaction(fn (): int => $this->createMaintenance->execute($payload, $actorName));
        $data = $this->present($this->row($id));

        ActivityLogger::log(
            'create',
            'Memulai pemeliharaan aset ' . $this->assetLabel($data)
                . ' (' . (string)$data['maintenance_type_label'] . ')',
            'asset_maintenance',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Pemeliharaan aset berhasil dicatat', 'data' => $data];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function complete(int $id, array $payload, string $actor): array
    {
        $row = Connection::transaction(fn (): array => $this->completeMaintenance->execute($id, $payload, $actor));
        $data = $this->present($row);

        ActivityLogger::log(
            'complete',
            'Menutup pemeliharaan aset ' . $this->assetLabel($data)
                . ' (hasil: ' . (string)$data['work_result'] . ')',
            'asset_maintenance',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Pemeliharaan aset berhasil diselesaikan', 'data' => $data];
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
        return $this->maintenances->find($id) ?? throw new NotFoundException('Pemeliharaan tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return AssetMaintenanceDTO::fromArray($row)->toArray();
    }

    /**
     * @param array<string, mixed> $data Hasil present().
     */
    private function assetLabel(array $data): string
    {
        return (string)$data['asset_name'] . ' (' . (string)$data['asset_code'] . ')';
    }
}
