<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Services\EmployeeService;

/**
 * Transport endpoint karyawan: menerjemahkan Request menjadi pemanggilan Service
 * (tulis) atau Repository (baca murni). Tidak ada aturan bisnis di sini.
 */
final class EmployeeController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk mengelola data master.';

    public function __construct(
        private readonly EmployeeService $service,
        private readonly EmployeeRepository $employees,
    ) {
    }

    public function index(Request $request): void
    {
        $employees = $this->employees->all(['search' => $request->query('search')]);
        Response::success($employees, count($employees));
    }

    public function show(Request $request): void
    {
        $employee = $this->employees->find($this->employeeId($request));

        if ($employee === null) {
            throw new NotFoundException('Karyawan tidak ditemukan.');
        }

        Response::success($employee);
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

        $result = $this->service->update($this->employeeId($request), $request->json());
        Response::message($result['message'], 200, $result['data']);
    }

    public function destroy(Request $request): void
    {
        $this->requireAdmin();

        $result = $this->service->delete($this->employeeId($request));
        Response::message($result['message']);
    }

    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }

    private function employeeId(Request $request): int
    {
        return (int)$request->routeParam('id');
    }
}
