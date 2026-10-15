<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\AssetLoanDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Services\LoanService;

/**
 * Transport endpoint peminjaman aset: menerjemahkan Request menjadi pemanggilan
 * Service (operasi tulis) atau Repository (baca murni), lalu mengirim respons.
 * Peminjaman adalah aksi operasional, jadi staf boleh mencatatnya; identitas
 * pelaku tetap diambil dari sesi.
 */
final class LoanController
{
    public function __construct(
        private readonly LoanService $service,
        private readonly AssetLoanRepository $loans,
    ) {
    }

    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'overdue' => $request->query('overdue'),
        ];

        $rows = array_map(
            static fn (array $row): array => AssetLoanDTO::fromArray($row)->toArray(),
            $this->loans->all($filters)
        );

        Response::success($rows, count($rows));
    }

    public function show(Request $request): void
    {
        $row = $this->loans->find($this->loanId($request))
            ?? throw new NotFoundException('Peminjaman tidak ditemukan.');

        Response::success(AssetLoanDTO::fromArray($row)->toArray());
    }

    public function store(Request $request): void
    {
        $result = $this->service->create($request->json(), $this->actorName());
        Response::message($result['message'], 201, $result['data']);
    }

    public function return(Request $request): void
    {
        $result = $this->service->return($this->loanId($request), $request->json(), $this->actorName());
        Response::message($result['message'], 200, $result['data']);
    }

    private function loanId(Request $request): int
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
