<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Services\AssetService;

/**
 * Transport endpoint aset: menerjemahkan Request menjadi pemanggilan Service
 * (operasi tulis) atau Repository (baca murni), lalu mengirim respons. Tidak ada
 * aturan bisnis, validasi, maupun narasi log di sini.
 *
 * Daftar kategori dan lokasi kini dilayani CategoryController dan
 * LocationController pada modul master data.
 */
final class AssetController
{
    public function __construct(
        private readonly AssetService $service,
        private readonly AssetRepository $assets,
        private readonly MovementRepository $movements,
    ) {
    }

    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'status' => $request->query('status'),
            'location_id' => $request->query('location_id'),
            'item_id' => $request->query('item_id'),
        ];

        $assets = $this->presentMany($this->assets->all($filters));
        Response::success($assets, count($assets));
    }

    public function show(Request $request): void
    {
        $id = $this->assetId($request);
        $asset = $this->assets->find($id);

        if ($asset === null) {
            throw new NotFoundException('Aset tidak ditemukan');
        }

        $asset = $this->present($asset);
        $asset['movements'] = $this->movements->forAsset($id);

        Response::success($asset);
    }

    /**
     * Registrasi bisa massal, jadi data respons selalu berupa daftar aset dan
     * jumlah unitnya disebut di pesan.
     */
    public function store(Request $request): void
    {
        $result = $this->service->create($request->json());
        Response::message($result['message'], 201, $result['data']);
    }

    public function update(Request $request): void
    {
        $result = $this->service->update($this->assetId($request), $request->json());
        Response::message($result['message'], 200, $result['data']);
    }

    public function destroy(Request $request): void
    {
        $result = $this->service->delete($this->assetId($request), Auth::isAdmin());
        Response::message($result['message']);
    }

    public function checkOut(Request $request): void
    {
        $result = $this->service->checkOut(
            $this->assetId($request),
            $request->json(),
            $this->actorName()
        );

        Response::message($result['message'], 200, $result['data']);
    }

    public function checkIn(Request $request): void
    {
        $result = $this->service->checkIn(
            $this->assetId($request),
            $request->json(),
            $this->actorName()
        );

        Response::message($result['message'], 200, $result['data']);
    }

    public function stats(): void
    {
        Response::success([
            'stats' => $this->assets->getStats(),
            'recent_movements' => $this->movements->getRecent(5),
        ]);
    }

    /**
     * Identitas pelaku diambil dari sesi, bukan payload klien, supaya tidak bisa dipalsukan.
     */
    private function actorName(): string
    {
        $user = Auth::user();

        return (string)($user['name'] ?? 'Sistem');
    }

    private function assetId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }

    /**
     * Baris DB dipetakan lewat DTO supaya label status/kondisi/sumber perolehan
     * dan format rupiah sampai ke klien; frontend tidak menghitungnya sendiri.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return AssetDTO::fromArray($row)->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function presentMany(array $rows): array
    {
        return array_map(fn (array $row): array => $this->present($row), $rows);
    }
}
