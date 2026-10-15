<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Transfer\CancelTransferAction;
use Inventra\Core\Actions\Transfer\CompleteTransferAction;
use Inventra\Core\Actions\Transfer\CreateTransferAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetTransferDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetTransferRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi use case mutasi aset: memanggil Action dan memegang transaksi untuk
 * use case yang menulis lebih dari satu tabel — penyelesaian mutasi sekaligus
 * memindahkan aset dan mencatat pergerakannya — lalu menarasikan activity log.
 * Controller hanya meneruskan hasilnya ke Response.
 */
final class TransferService
{
    public function __construct(
        private readonly AssetTransferRepository $transfers,
        private readonly CreateTransferAction $createTransfer,
        private readonly CompleteTransferAction $completeTransfer,
        private readonly CancelTransferAction $cancelTransfer,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload, string $actorName): array
    {
        $id = Connection::transaction(fn (): int => $this->createTransfer->execute($payload, $actorName));
        $data = $this->present($this->row($id));

        ActivityLogger::log(
            'create',
            'Memindahkan aset ' . $this->assetLabel($data) . ' dari ' . $this->origin($data)
                . ' ke ' . (string)$data['to_location_name'],
            'asset_transfer',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Mutasi aset berhasil dicatat', 'data' => $data];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function complete(int $id, array $payload, string $actor): array
    {
        $row = Connection::transaction(fn (): array => $this->completeTransfer->execute($id, $payload, $actor));
        $data = $this->present($row);

        ActivityLogger::log(
            'complete',
            'Menyelesaikan mutasi aset ' . $this->assetLabel($data) . ' dari ' . $this->origin($data)
                . ' ke ' . (string)$data['to_location_name'],
            'asset_transfer',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Mutasi aset berhasil diselesaikan', 'data' => $data];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function cancel(int $id, array $payload): array
    {
        $row = $this->cancelTransfer->execute($id, $payload);
        $data = $this->present($row);

        ActivityLogger::log(
            'cancel',
            'Membatalkan mutasi aset ' . $this->assetLabel($data)
                . ' (alasan: ' . (string)$data['cancel_reason'] . ')',
            'asset_transfer',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Mutasi aset berhasil dibatalkan', 'data' => $data];
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
        return $this->transfers->find($id) ?? throw new NotFoundException('Mutasi tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return AssetTransferDTO::fromArray($row)->toArray();
    }

    /**
     * @param array<string, mixed> $data Hasil present().
     */
    private function assetLabel(array $data): string
    {
        return (string)$data['asset_name'] . ' (' . (string)$data['asset_code'] . ')';
    }

    /**
     * Lokasi asal mutasi lama bisa saja sudah dihapus, jadi namanya tidak selalu ada.
     *
     * @param array<string, mixed> $data Hasil present().
     */
    private function origin(array $data): string
    {
        return !empty($data['from_location_name']) ? (string)$data['from_location_name'] : 'lokasi tidak diketahui';
    }
}
