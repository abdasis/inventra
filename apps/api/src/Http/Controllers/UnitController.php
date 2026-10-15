<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\UnitRepository;
use Inventra\Core\Services\UnitService;

/**
 * Transport endpoint satuan: menerjemahkan Request menjadi pemanggilan Service
 * (operasi tulis) atau Repository (baca murni), lalu mengirim respons. Tidak ada
 * aturan bisnis, validasi, maupun narasi log di sini.
 */
final class UnitController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola data master.';

    public function __construct(
        private readonly UnitService $service,
        private readonly UnitRepository $units,
    ) {
    }

    public function index(Request $request): void
    {
        $units = $this->units->all(['search' => $request->query('search')]);
        Response::success($units, count($units));
    }

    public function show(Request $request): void
    {
        $unit = $this->units->find($this->unitId($request));

        if ($unit === null) {
            throw new NotFoundException('Satuan tidak ditemukan.');
        }

        Response::success($unit);
    }

    public function store(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->create($request->json());
        Response::message($result['message'], 201, $result['data']);
    }

    public function update(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->update($this->unitId($request), $request->json());
        Response::message($result['message'], 200, $result['data']);
    }

    public function destroy(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->delete($this->unitId($request));
        Response::message($result['message']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function unitId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }
}
