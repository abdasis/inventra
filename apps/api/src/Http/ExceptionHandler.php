<?php

declare(strict_types=1);

namespace Inventra\Api\Http;

use Inventra\Core\Exceptions\DomainException;
use Throwable;

/**
 * Menerjemahkan exception menjadi respons JSON. Exception non-domain tidak
 * pernah membocorkan pesan aslinya ke klien, hanya dicatat ke error log.
 */
final class ExceptionHandler
{
    public function handle(Throwable $throwable): void
    {
        if ($throwable instanceof DomainException) {
            Response::error($throwable->getMessage(), $throwable->status(), $throwable->errors());

            return;
        }

        error_log(sprintf(
            'Unhandled %s: %s in %s:%d',
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine()
        ));

        Response::error('Terjadi kesalahan pada server. Coba beberapa saat lagi.', 500);
    }
}
