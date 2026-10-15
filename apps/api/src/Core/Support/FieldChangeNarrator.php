<?php

declare(strict_types=1);

namespace Inventra\Core\Support;

/**
 * Menarasikan perubahan beberapa field sekaligus untuk activity log, mis.
 * " — lantai berubah dari Lt 1 menjadi Lt 2". Nilai yang dibandingkan sudah
 * dalam bentuk siap tampil, jadi pemanggil yang memutuskan label status.
 */
final class FieldChangeNarrator
{
    /**
     * @param array<string, mixed> $before Nilai sebelum perubahan.
     * @param array<string, mixed> $after Nilai sesudah perubahan.
     * @param array<string, string> $labels field => label Indonesia.
     * @return string Kosong bila tidak ada field yang berubah.
     */
    public static function describe(array $before, array $after, array $labels): string
    {
        $changes = [];

        foreach ($labels as $field => $label) {
            $old = self::display($before[$field] ?? null);
            $new = self::display($after[$field] ?? null);

            if ($old !== $new) {
                $changes[] = $label . ' berubah dari ' . $old . ' menjadi ' . $new;
            }
        }

        return $changes === [] ? '' : ' — ' . implode('; ', $changes);
    }

    private static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(kosong)';
        }

        return (string)$value;
    }
}
