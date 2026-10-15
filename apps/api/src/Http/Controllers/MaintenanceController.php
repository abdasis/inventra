<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\AssetMaintenanceDTO;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Repositories\AssetMaintenanceRepository;
use Inventra\Core\Services\MaintenanceService;

/**
 * Transport endpoint pemeliharaan aset: menerjemahkan Request menjadi pemanggilan
 * Service (operasi tulis) atau Repository (baca murni), lalu mengirim respons.
 * Tidak ada aturan bisnis, validasi, maupun narasi log di sini.
 */
final class MaintenanceController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola pemeliharaan aset.';

    public function __construct(
        private readonly MaintenanceService $service,
        private readonly AssetMaintenanceRepository $maintenances,
    ) {
    }

    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'asset_id' => $request->query('asset_id'),
        ];

        $rows = array_map(
            static fn (array $row): array => AssetMaintenanceDTO::fromArray($row)->toArray(),
            $this->maintenances->all($filters)
        );

        Response::success($rows, count($rows));
    }

    public function show(Request $request): void
    {
        $result = $this->service->detail($this->maintenanceId($request));
        Response::success($result['data']);
    }

    public function store(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->create($request->json(), $this->actorName());
        Response::message($result['message'], 201, $result['data']);
    }

    public function complete(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->complete($this->maintenanceId($request), $request->json(), $this->actorName());
        Response::message($result['message'], 200, $result['data']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function maintenanceId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }

    /**
     * Identitas pelaku diambil dari sesi, bukan payload klien, supaya tidak bisa dipalsukan.
     */
    private function actorName(): string
    {
        $user = Auth::user();

        return (string)($user['name'] ?? 'Sistem');
    }
}
