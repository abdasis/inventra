<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\StockBalanceDTO;
use Inventra\Core\DTO\StockMovementDTO;
use Inventra\Core\Repositories\StockRepository;
use Inventra\Core\Services\StockService;

/**
 * Transport endpoint stok barang habis pakai. Baca saldo dan riwayat pergerakan
 * langsung dari Repository; pencatatan pergerakan didelegasikan ke Service.
 * Pencatatan stok adalah aksi operasional, jadi boleh dilakukan staf.
 */
final class StockController
{
    public function __construct(
        private readonly StockService $service,
        private readonly StockRepository $stocks,
    ) {
    }

    public function index(Request $request): void
    {
        $balances = array_map(
            static fn (array $row): array => StockBalanceDTO::fromArray($row)->toArray(),
            $this->stocks->balances([
                'search' => $request->query('search'),
                'low_only' => $request->query('low_only'),
            ])
        );

        Response::success($balances, count($balances));
    }

    public function movements(Request $request): void
    {
        $movements = array_map(
            static fn (array $row): array => StockMovementDTO::fromArray($row)->toArray(),
            $this->stocks->movements([
                'item_id' => $request->query('item_id'),
                'movement_type' => $request->query('movement_type'),
            ])
        );

        Response::success($movements, count($movements));
    }

    public function store(Request $request): void
    {
        $result = $this->service->recordMovement($request->json(), $this->actorName());
        Response::message($result['message'], 201, $result['data']);
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
