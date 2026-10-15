<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Concerns;

use DateTimeImmutable;
use Inventra\Core\Enums\AssetCondition;

/**
 * Normalisasi dan validasi bentuk payload yang dipakai bersama Action penempatan
 * dan peminjaman: teks, bilangan, tanggal, dan enum kondisi. Aturan domain yang
 * berbeda antar use case tetap tinggal di Action masing-masing; trait ini hanya
 * mencegah pemeriksaan bentuk input ditulis empat kali.
 */
trait AssetCustodyInput
{
    private function custodyText(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function custodyOptionalText(mixed $value): ?string
    {
        $text = $this->custodyText($value);

        return $text === '' ? null : $text;
    }

    private function custodyInt(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    private function custodyOptionalInt(mixed $value): ?int
    {
        $int = $this->custodyInt($value);

        return $int > 0 ? $int : null;
    }

    /**
     * Nilai uang: kosong berarti nol, null hanya bila isinya bukan angka. Skalanya
     * dibulatkan dua desimal supaya cocok dengan kolom DECIMAL(15,2).
     */
    private function custodyDecimal(mixed $value): ?float
    {
        if ($value === null || (is_scalar($value) && trim((string)$value) === '')) {
            return 0.0;
        }

        return is_numeric($value) ? round((float)$value, 2) : null;
    }

    /**
     * Format Y-m-d H:i:s yang tanggalnya benar-benar ada; null bila kosong atau
     * tidak sesuai. Pemanggil membedakan keduanya lewat custodyText().
     */
    private function custodyDateTime(mixed $value): ?string
    {
        $text = $this->custodyText($value);
        if ($text === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $text);

        return $date !== false && $date->format('Y-m-d H:i:s') === $text ? $text : null;
    }

    /**
     * Format Y-m-d yang tanggalnya benar-benar ada; null bila kosong atau tidak
     * sesuai, supaya "2026-02-31" tidak lolos.
     */
    private function custodyDate(mixed $value): ?string
    {
        $text = $this->custodyText($value);
        if ($text === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

        return $date !== false && $date->format('Y-m-d') === $text ? $text : null;
    }

    /**
     * Kondisi saat keluar. Kosong berarti default (baik); nilai kedua false bila
     * isinya ada tetapi bukan enum yang dikenal.
     *
     * @return array{0: AssetCondition, 1: bool}
     */
    private function custodyCondition(mixed $value): array
    {
        $text = $this->custodyText($value);
        if ($text === '') {
            return [AssetCondition::Good, true];
        }

        $condition = AssetCondition::tryFrom($text);

        return [$condition ?? AssetCondition::Good, $condition !== null];
    }
}
