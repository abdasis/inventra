<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Disposal\CreateDisposalAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetDisposalDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetDisposalRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi use case penghapusan aset: memanggil Action dan memegang transaksi
 * karena pencatatan penghapusan sekaligus menandai status aset dan mencatat
 * pergerakannya, lalu menarasikan activity log. Controller hanya meneruskan
 * hasilnya ke Response.
 */
final class DisposalService
{
    public function __construct(
        private readonly AssetDisposalRepository $disposals,
        private readonly CreateDisposalAction $createDisposal,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload, string $actorName): array
    {
        $id = Connection::transaction(fn (): int => $this->createDisposal->execute($payload, $actorName));
        $data = $this->present($this->row($id));

        ActivityLogger::log(
            'delete',
            'Menghapus aset ' . $this->assetLabel($data) . ' dengan metode '
                . (string)$data['method_label'] . ' (alasan: ' . (string)$data['reason'] . ')',
            'asset_disposal',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Penghapusan aset berhasil dicatat', 'data' => $data];
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
        return $this->disposals->find($id) ?? throw new NotFoundException('Penghapusan tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return AssetDisposalDTO::fromArray($row)->toArray();
    }

    /**
     * @param array<string, mixed> $data Hasil present().
     */
    private function assetLabel(array $data): string
    {
        return (string)$data['asset_name'] . ' (' . (string)$data['asset_code'] . ')';
    }
}
