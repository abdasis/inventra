<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\UserDTO;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Repositories\UserRepository;
use Inventra\Core\Services\UserService;

/**
 * Transport endpoint kelola pengguna: memeriksa izin, menerjemahkan Request
 * menjadi pemanggilan Service (tulis) atau Repository (baca murni), lalu
 * mengirim respons. Tidak ada aturan bisnis, validasi, maupun narasi log di sini.
 */
final class UserController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola pengguna.';

    public function __construct(
        private readonly UserService $service,
        private readonly UserRepository $users,
    ) {
    }

    public function index(Request $request): void
    {
        $this->requireAdmin();

        $users = array_map(UserDTO::fromRow(...), $this->users->all());
        Response::success($users, count($users));
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

        $result = $this->service->update($this->userId($request), $request->json(), $this->actorId());
        Response::message($result['message'], 200, $result['data']);
    }

    public function destroy(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->delete($this->userId($request), $this->actorId());
        Response::message($result['message']);
    }

    public function toggleActive(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->toggleActive($this->userId($request), $this->actorId());
        Response::message($result['message'], 200, $result['data']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    /**
     * Identitas pelaku diambil dari sesi, bukan payload klien, supaya tidak bisa dipalsukan.
     */
    private function actorId(): int
    {
        return (int)(Auth::id() ?? 0);
    }

    private function userId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }
}
