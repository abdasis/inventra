<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Services\LocationService;

/**
 * Transport endpoint lokasi: menerjemahkan Request menjadi pemanggilan Service
 * (tulis) atau Repository (baca murni). Tidak ada aturan bisnis di sini.
 */
final class LocationController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola data master.';

    public function __construct(
        private readonly LocationService $service,
        private readonly LocationRepository $locations,
    ) {
    }

    public function index(Request $request): void
    {
        $locations = $this->locations->all(['search' => $request->query('search')]);
        Response::success($locations, count($locations));
    }

    public function show(Request $request): void
    {
        $location = $this->locations->find($this->locationId($request));

        if ($location === null) {
            throw new NotFoundException('Lokasi tidak ditemukan.');
        }

        Response::success($location);
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

        $result = $this->service->update($this->locationId($request), $request->json());
        Response::message($result['message'], 200, $result['data']);
    }

    public function destroy(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->delete($this->locationId($request));
        Response::message($result['message']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function locationId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }
}
