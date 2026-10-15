<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\AssetAssignmentDTO;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Services\AssignmentService;

/**
 * Transport endpoint penempatan aset: menerjemahkan Request menjadi pemanggilan
 * Service (operasi tulis) atau Repository (baca murni), lalu mengirim respons.
 * Tidak ada aturan bisnis, validasi, maupun narasi log di sini.
 */
final class AssignmentController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola penempatan.';

    public function __construct(
        private readonly AssignmentService $service,
        private readonly AssetAssignmentRepository $assignments,
    ) {
    }

    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'employee_id' => $request->query('employee_id'),
        ];

        $rows = array_map(
            static fn (array $row): array => AssetAssignmentDTO::fromArray($row)->toArray(),
            $this->assignments->all($filters)
        );

        Response::success($rows, count($rows));
    }

    /**
     * Detail satu penempatan sekaligus seluruh riwayat penempatan asetnya,
     * sehingga histori pemegang bisa dibaca tanpa request tambahan.
     */
    public function show(Request $request): void
    {
        $row = $this->assignments->find($this->assignmentId($request))
            ?? throw new NotFoundException('Penempatan tidak ditemukan.');

        $data = AssetAssignmentDTO::fromArray($row)->toArray();
        $data['history'] = array_map(
            static fn (array $history): array => AssetAssignmentDTO::fromArray($history)->toArray(),
            $this->assignments->historyForAsset((int)$row['asset_id'])
        );

        Response::success($data);
    }

    public function store(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->create($request->json(), $this->actorName());
        Response::message($result['message'], 201, $result['data']);
    }

    public function return(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->return($this->assignmentId($request), $request->json(), $this->actorName());
        Response::message($result['message'], 200, $result['data']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function assignmentId(Request $request): int
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
