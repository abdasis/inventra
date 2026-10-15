<?php

declare(strict_types=1);

namespace Inventra\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception domain yang membawa status HTTP dan opsional daftar error per field.
 * Turunannya menetapkan status lewat konstruktor, sehingga handler transport
 * tidak perlu tahu jenis exception satu per satu.
 */
class DomainException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        string $message,
        private readonly int $status = 400,
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
