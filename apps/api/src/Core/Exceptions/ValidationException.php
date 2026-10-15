<?php

declare(strict_types=1);

namespace Inventra\Core\Exceptions;

final class ValidationException extends DomainException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message, 422, $errors);
    }
}
