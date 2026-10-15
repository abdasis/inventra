<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\SupplierRepository;
use Inventra\Core\Services\SupplierService;

/**
 * Transport endpoint supplier: menerjemahkan Request menjadi pemanggilan Service
 * (tulis) atau Repository (baca murni). Tidak ada aturan bisnis di sini.
 */
final class SupplierController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola data master.';

    public function __construct(
        private readonly SupplierService $service,
        private readonly SupplierRepository $suppliers,
    ) {
    }

    public function index(Request $request): void
    {
        $suppliers = $this->suppliers->all(['search' => $request->query('search')]);
        Response::success($suppliers, count($suppliers));
    }

    public function show(Request $request): void
    {
        $supplier = $this->suppliers->find($this->supplierId($request));

        if ($supplier === null) {
            throw new NotFoundException('Supplier tidak ditemukan.');
        }

        Response::success($supplier);
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

        $result = $this->service->update($this->supplierId($request), $request->json());
        Response::message($result['message'], 200, $result['data']);
    }

    public function destroy(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->delete($this->supplierId($request));
        Response::message($result['message']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function supplierId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }
}
