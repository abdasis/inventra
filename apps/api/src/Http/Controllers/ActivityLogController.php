<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Auth\Auth;
use Inventra\Core\DTO\ActivityLogDTO;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Repositories\ReportRepository;

/**
 * Transport endpoint jejak audit. Operasi baca murni, jadi Controller langsung
 * membaca Repository tanpa Service; tidak ada unit kerja yang perlu dibungkus
 * maupun ditransaksikan. Jejak audit berisi siapa melakukan apa, jadi hanya
 * administrator yang boleh membacanya.
 */
final class ActivityLogController
{
    private const FORBIDDEN_MESSAGE = 'Anda tidak memiliki izin untuk melihat audit trail.';

    public function __construct(
        private readonly ReportRepository $reports,
    ) {
    }

    public function index(Request $request): void
    {
        $this->requireAdmin();

        $rows = $this->reports->activityRows([
            'search' => $request->query('search'),
            'action' => $request->query('action'),
            'subject_type' => $request->query('subject_type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'limit' => $request->query('limit'),
        ]);

        $logs = array_map(
            static fn (array $row): array => ActivityLogDTO::fromArray($row)->toArray(),
            $rows
        );

        Response::success($logs, count($logs));
    }

    /**
     * Daftar nilai action yang pernah tercatat, untuk mengisi dropdown filter.
     */
    public function actions(): void
    {
        $this->requireAdmin();

        Response::success($this->reports->activityActions());
    }

    /**
     * Penjaga peran di backend; menyembunyikan menu di frontend saja tidak cukup.
     */
    private function requireAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new ForbiddenException(self::FORBIDDEN_MESSAGE);
        }
    }
}
