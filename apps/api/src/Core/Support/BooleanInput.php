<?php

declare(strict_types=1);

namespace Inventra\Core\Support;

/**
 * Konversi boolean dari payload yang bentuknya bisa string multipart ("0",
 * "false", "off") maupun boolean asli. Semantiknya sengaja sama dengan
 * UserInput::boolValue() supaya field is_active seragam antar modul.
 */
final class BooleanInput
{
    public static function value(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['', '0', 'false', 'off', 'no'], true);
        }

        return (bool)$value;
    }
}
