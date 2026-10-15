<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Controllers;

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Response;
use Inventra\Core\Services\ReportService;

/**
 * Transport endpoint laporan. Ringkasan dikirim sebagai JSON biasa, sedangkan
 * ketiga ekspor dikirim sebagai unduhan berkas karena isinya CSV, bukan
 * envelope JSON. Tidak ada aturan bisnis di sini: penyusunan angka dan teks
 * berada di ReportService.
 */
final class ReportController
{
    public function __construct(
        private readonly ReportService $service,
    ) {
    }

    public function summary(Request $request): void
    {
        Response::success($this->service->summary());
    }

    public function assetsCsv(Request $request): void
    {
        $this->download($this->service->assetsCsv([
            'status' => $request->query('status'),
            'category_id' => $request->query('category_id'),
            'location_id' => $request->query('location_id'),
        ]));
    }

    public function stockCsv(Request $request): void
    {
        $this->download($this->service->stockCsv([
            'low_only' => $request->query('low_only'),
        ]));
    }

    public function activityCsv(Request $request): void
    {
        $this->download($this->service->activityCsv([
            'search' => $request->query('search'),
            'action' => $request->query('action'),
            'subject_type' => $request->query('subject_type'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'limit' => $request->query('limit'),
        ]));
    }

    /**
     * @param array{filename: string, content: string} $result
     */
    private function download(array $result): void
    {
        Response::download($result['filename'], $result['content']);
    }
}
