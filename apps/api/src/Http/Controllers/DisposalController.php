<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\AssetDisposalDTO;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Repositories\AssetDisposalRepository;
use Inventra\Core\Services\DisposalService;

/**
 * Transport endpoint penghapusan aset: menerjemahkan Request menjadi pemanggilan
 * Service (operasi tulis) atau Repository (baca murni), lalu mengirim respons.
 * Tidak ada aturan bisnis, validasi, maupun narasi log di sini.
 */
final class DisposalController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola penghapusan aset.';

    public function __construct(
        private readonly DisposalService $service,
        private readonly AssetDisposalRepository $disposals,
    ) {
    }

    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->query('search'),
            'method' => $request->query('method'),
            'asset_id' => $request->query('asset_id'),
        ];

        $rows = array_map(
            static fn (array $row): array => AssetDisposalDTO::fromArray($row)->toArray(),
            $this->disposals->all($filters)
        );

        Response::success($rows, count($rows));
    }

    public function show(Request $request): void
    {
        $result = $this->service->detail($this->disposalId($request));
        Response::success($result['data']);
    }

    public function store(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->create($request->json(), $this->actorName());
        Response::message($result['message'], 201, $result['data']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function disposalId(Request $request): int
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
