<?php

declare(strict_types=1);

namespace Inventra\Core\Exceptions;

final class UnauthenticatedException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 401);
    }
}
