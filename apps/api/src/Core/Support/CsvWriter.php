<?php

declare(strict_types=1);

namespace Inventra\Core\Support;

/**
 * Menyusun teks CSV dari baris data dan daftar kolom (label => key). Pemisah
 * koma, nilai dikutip ganda bila mengandung koma/kutip/baris baru (kutip di
 * dalam nilai digandakan), baris diakhiri CRLF, dan berkas diawali BOM UTF-8
 * supaya Excel membaca aksen dengan benar.
 *
 * Pengkodean ditulis sendiri, bukan fputcsv(), karena fputcsv juga mengutip
 * nilai yang sekadar mengandung spasi sehingga judul kolom ikut ter kutip.
 */
final class CsvWriter
{
    private const BOM = "\xEF\xBB\xBF";
    private const EOL = "\r\n";
    private const MUST_QUOTE = ",\"\r\n";

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $columns label kolom => key baris
     */
    public static function toString(array $rows, array $columns): string
    {
        $keys = array_values($columns);
        $lines = [self::encode(array_keys($columns))];

        foreach ($rows as $row) {
            $cells = [];
            foreach ($keys as $key) {
                $value = $row[$key] ?? null;
                $cells[] = $value === null ? '' : (string)$value;
            }

            $lines[] = self::encode($cells);
        }

        return self::BOM . implode('', $lines);
    }

    /**
     * @param array<int, mixed> $cells
     */
    private static function encode(array $cells): string
    {
        $encoded = [];
        foreach ($cells as $cell) {
            $value = (string)$cell;
            if (strpbrk($value, self::MUST_QUOTE) !== false) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }

            $encoded[] = $value;
        }

        return implode(',', $encoded) . self::EOL;
    }
}
