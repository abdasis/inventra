<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\ItemDTO;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Services\ItemService;

/**
 * Transport endpoint barang: menerjemahkan Request menjadi pemanggilan Service
 * (operasi tulis) atau Repository (baca murni), lalu mengirim respons. Tidak ada
 * aturan bisnis, validasi, maupun narasi log di sini.
 */
final class ItemController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola data master.';

    public function __construct(
        private readonly ItemService $service,
        private readonly ItemRepository $items,
    ) {
    }

    public function index(Request $request): void
    {
        $items = $this->items->all(['search' => $request->query('search')]);
        $items = array_map(static fn (array $row): array => ItemDTO::fromArray($row)->toArray(), $items);

        Response::success($items, count($items));
    }

    public function show(Request $request): void
    {
        $item = $this->items->find($this->itemId($request));

        if ($item === null) {
            throw new NotFoundException('Barang tidak ditemukan.');
        }

        // Daftar dan detail memakai DTO yang sama supaya key presentasi
        // (item_type_label, item_type_badge) tidak perlu disusun ulang di frontend.
        Response::success(ItemDTO::fromArray($item)->toArray());
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

        $result = $this->service->update($this->itemId($request), $request->json());
        Response::message($result['message'], 200, $result['data']);
    }

    public function destroy(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->delete($this->itemId($request));
        Response::message($result['message']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function itemId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }
}
