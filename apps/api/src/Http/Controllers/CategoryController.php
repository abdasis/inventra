<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\CategoryRepository;
use Inventra\Core\Services\CategoryService;

/**
 * Transport endpoint kategori: menerjemahkan Request menjadi pemanggilan Service
 * (tulis) atau Repository (baca murni). Tidak ada aturan bisnis di sini.
 */
final class CategoryController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola data master.';

    public function __construct(
        private readonly CategoryService $service,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function index(Request $request): void
    {
        $categories = $this->categories->all(['search' => $request->query('search')]);
        Response::success($categories, count($categories));
    }

    public function show(Request $request): void
    {
        $category = $this->categories->find($this->categoryId($request));

        if ($category === null) {
            throw new NotFoundException('Kategori tidak ditemukan.');
        }

        Response::success($category);
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

        $result = $this->service->update($this->categoryId($request), $request->json());
        Response::message($result['message'], 200, $result['data']);
    }

    public function destroy(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->delete($this->categoryId($request));
        Response::message($result['message']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function categoryId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }
}
