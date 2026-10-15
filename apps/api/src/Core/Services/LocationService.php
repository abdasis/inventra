<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Location\CreateLocationAction;
use Inventra\Core\Actions\Location\DeleteLocationAction;
use Inventra\Core\Actions\Location\UpdateLocationAction;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Support\ActivityLogger;
use Inventra\Core\Support\FieldChangeNarrator;

/**
 * Orkestrasi use case lokasi: memanggil Action lalu menarasikan activity log.
 * Penulisan hanya menyentuh satu tabel, jadi tidak ada transaksi di sini.
 */
final class LocationService
{
    /** @var array<string, string> */
    private const CHANGE_LABELS = [
        'name' => 'nama',
        'code' => 'kode',
        'building' => 'gedung',
        'floor' => 'lantai',
    ];

    public function __construct(
        private readonly LocationRepository $locations,
        private readonly CreateLocationAction $createLocation,
        private readonly UpdateLocationAction $updateLocation,
        private readonly DeleteLocationAction $deleteLocation,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $id = $this->createLocation->execute($payload);
        $location = $this->locations->find($id) ?? [];

        ActivityLogger::log(
            'location.create',
            'Menambahkan lokasi ' . $this->label($location),
            'location',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Lokasi berhasil ditambahkan.', 'data' => $location];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        // Urutan lama dipertahankan: 404 lebih dulu, baru 422 validasi.
        $before = $this->findOrFail($id);
        $this->updateLocation->execute($id, $payload);
        $after = $this->locations->find($id) ?? [];

        ActivityLogger::log(
            'location.update',
            'Memperbarui lokasi ' . $this->label($after)
                . FieldChangeNarrator::describe($this->narrated($before), $this->narrated($after), self::CHANGE_LABELS),
            'location',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data lokasi berhasil diperbarui.', 'data' => $after];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id): array
    {
        $location = $this->deleteLocation->execute($id);

        ActivityLogger::log(
            'location.delete',
            'Menghapus lokasi ' . $this->label($location),
            'location',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Lokasi berhasil dihapus.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        return $this->locations->find($id) ?? throw new NotFoundException('Lokasi tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $location
     */
    private function label(array $location): string
    {
        return trim((string)($location['name'] ?? '')) . ' (' . trim((string)($location['code'] ?? '')) . ')';
    }

    /**
     * @param array<string, mixed> $location
     * @return array<string, mixed>
     */
    private function narrated(array $location): array
    {
        return [
            'name' => $location['name'] ?? '',
            'code' => $location['code'] ?? '',
            'building' => $location['building'] ?? '',
            'floor' => $location['floor'] ?? '',
        ];
    }
}
