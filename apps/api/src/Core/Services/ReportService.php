<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\DTO\ActivityLogDTO;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Repositories\ReportRepository;
use Inventra\Core\Support\ActivityLogger;
use Inventra\Core\Support\CsvWriter;

/**
 * Orkestrasi modul laporan: menyusun angka mentah dari ReportRepository menjadi
 * bentuk siap tampil, dan merangkai baris ekspor menjadi teks CSV. Modul ini
 * hanya membaca, jadi tidak ada transaksi; satu-satunya penulisan adalah jejak
 * audit ekspor, karena mengunduh data adalah tindakan yang perlu tercatat.
 */
final class ReportService
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $raw = $this->reports->summary();
        $total = array_sum($raw['assets_by_status']);
        $valuation = (float)$raw['total_valuation'];
        $maintenanceCost = (float)$raw['maintenance_cost_this_year'];

        $byStatus = [];
        foreach (AssetStatus::cases() as $status) {
            $count = (int)($raw['assets_by_status'][$status->value] ?? 0);
            $byStatus[] = [
                'status' => $status->value,
                'status_label' => $status->label(),
                'count' => $count,
                'percentage' => self::percentage($count, $total),
            ];
        }

        $categories = [];
        foreach ($raw['top_categories'] as $row) {
            $count = (int)$row['count'];
            $categories[] = [
                'category_name' => (string)$row['name'],
                'count' => $count,
                'percentage' => self::percentage($count, $total),
            ];
        }

        return [
            'assets_total' => $total,
            'assets_by_status' => $byStatus,
            'total_valuation' => $valuation,
            'total_valuation_formatted' => self::rupiah($valuation),
            'top_categories' => $categories,
            'low_stock_items' => (int)$raw['low_stock_items'],
            'active_loans' => (int)$raw['active_loans'],
            'overdue_loans' => (int)$raw['overdue_loans'],
            'maintenance_cost_this_year' => $maintenanceCost,
            'maintenance_cost_this_year_formatted' => self::rupiah($maintenanceCost),
            'disposals_this_year' => (int)$raw['disposals_this_year'],
            'active_users' => (int)$raw['active_users'],
            'activity_last_7_days' => (int)$raw['activity_last_7_days'],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{filename: string, content: string}
     */
    public function assetsCsv(array $filters): array
    {
        $rows = $this->reports->assetRows($filters);

        // Kolom CSV memuat label yang terbaca manusia, bukan nilai enum mentah.
        foreach ($rows as $index => $row) {
            $status = (string)$row['status'];
            $condition = (string)$row['condition_level'];
            $rows[$index]['status'] = AssetStatus::tryFrom($status)?->label() ?? $status;
            $rows[$index]['condition_level'] = AssetCondition::tryFrom($condition)?->label() ?? $condition;
        }

        $content = CsvWriter::toString($rows, [
            'Kode Aset' => 'asset_code',
            'Nama' => 'name',
            'Kategori' => 'category_name',
            'Lokasi' => 'location_name',
            'Jenis Barang' => 'item_name',
            'Nomor Seri' => 'serial_number',
            'Tanggal Perolehan' => 'purchase_date',
            'Harga Perolehan' => 'purchase_cost',
            'Status' => 'status',
            'Kondisi' => 'condition_level',
            'Pemegang' => 'assigned_to',
        ]);

        $this->logExport('daftar aset', $filters, ['status', 'category_id', 'location_id']);

        return ['filename' => self::filename('aset'), 'content' => $content];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{filename: string, content: string}
     */
    public function stockCsv(array $filters): array
    {
        $rows = $this->reports->stockRows($filters);

        foreach ($rows as $index => $row) {
            $rows[$index]['is_low'] = $row['is_low'] ? 'Ya' : 'Tidak';
        }

        $content = CsvWriter::toString($rows, [
            'Kode Barang' => 'item_code',
            'Nama' => 'item_name',
            'Kategori' => 'category_name',
            'Satuan' => 'unit_name',
            'Saldo' => 'quantity',
            'Stok Minimum' => 'minimum_stock',
            'Stok Menipis' => 'is_low',
        ]);

        $this->logExport('saldo stok barang habis pakai', $filters, ['low_only']);

        return ['filename' => self::filename('stok'), 'content' => $content];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{filename: string, content: string}
     */
    public function activityCsv(array $filters): array
    {
        $rows = array_map(
            static fn (array $row): array => ActivityLogDTO::fromArray($row)->toArray(),
            $this->reports->activityRows($filters)
        );

        $content = CsvWriter::toString($rows, [
            'Waktu' => 'created_at_formatted',
            'Pengguna' => 'user_name',
            'Aksi' => 'action_label',
            'Objek' => 'subject_type',
            'ID Objek' => 'subject_id',
            'Keterangan' => 'description',
            'Alamat IP' => 'ip_address',
        ]);

        $this->logExport('jejak audit', $filters, ['search', 'action', 'subject_type', 'date_from', 'date_to']);

        return ['filename' => self::filename('aktivitas'), 'content' => $content];
    }

    /**
     * Jejak audit ekspor. Aksi ini tidak mengubah data, tetapi mengunduh data
     * tetap perlu tercatat siapa dan dengan filter apa.
     *
     * @param array<string, mixed> $filters
     * @param array<int, string> $keys
     */
    private function logExport(string $subject, array $filters, array $keys): void
    {
        $applied = [];
        foreach ($keys as $key) {
            if (!empty($filters[$key])) {
                $applied[] = $key . '=' . $filters[$key];
            }
        }

        $filterText = $applied === [] ? '' : ' (filter: ' . implode(', ', $applied) . ')';

        ActivityLogger::log(
            'export',
            'Mengekspor ' . $subject . ' ke berkas CSV' . $filterText,
            'report',
            null,
            $this->ipAddress
        );
    }

    private static function filename(string $slug): string
    {
        return 'inventra-' . $slug . '-' . date('Y-m-d') . '.csv';
    }

    private static function rupiah(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    private static function percentage(int $count, int $total): float
    {
        return $total === 0 ? 0.0 : round($count / $total * 100, 1);
    }
}
