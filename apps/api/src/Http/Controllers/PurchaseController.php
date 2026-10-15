<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\PurchaseDTO;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Repositories\PurchaseRepository;
use Inventra\Core\Services\PurchaseService;

/**
 * Transport endpoint pengadaan: menerjemahkan Request menjadi pemanggilan Service
 * (operasi tulis) atau Repository (baca murni), lalu mengirim respons. Tidak ada
 * aturan bisnis, validasi, maupun narasi log di sini.
 */
final class PurchaseController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola pengadaan.';

    public function __construct(
        private readonly PurchaseService $service,
        private readonly PurchaseRepository $purchases,
    ) {
    }

    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ];

        $purchases = array_map(
            static fn (array $row): array => PurchaseDTO::fromArray($row)->toArray(),
            $this->purchases->all($filters)
        );

        Response::success($purchases, count($purchases));
    }

    public function show(Request $request): void
    {
        $result = $this->service->detail($this->purchaseId($request));
        Response::success($result['data']);
    }

    public function store(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->create($request->json(), $this->actorName());
        Response::message($result['message'], 201, $result['data']);
    }

    public function update(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->update($this->purchaseId($request), $request->json());
        Response::message($result['message'], 200, $result['data']);
    }

    public function receive(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->receive(
            $this->purchaseId($request),
            $request->json(),
            $this->actorName()
        );

        Response::message($result['message'], 200, $result['data']);
    }

    public function cancel(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->cancel($this->purchaseId($request), $request->json());
        Response::message($result['message']);
    }

    public function destroy(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->delete($this->purchaseId($request));
        Response::message($result['message']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function purchaseId(Request $request): int
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
