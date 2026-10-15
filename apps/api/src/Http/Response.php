<?php

declare(strict_types=1);

namespace Inventra\Api\Http;

/**
 * Satu-satunya tempat lapisan transport API menulis status, header, dan body.
 * Selalu exit setelah mengirim supaya tidak ada kode lanjutan yang ikut jalan.
 */
final class Response
{
    public static function success(mixed $data = null, ?int $count = null, int $status = 200): void
    {
        $payload = ['success' => true, 'data' => $data];
        if ($count !== null) {
            $payload['count'] = $count;
        }

        self::send($status, $payload);
    }

    /**
     * Respons sukses bernarasi. Urutan key (success, message, data) bagian dari
     * kontrak respons, dan "data" hanya ikut bila memang ada isinya.
     */
    public static function message(string $message, int $status = 200, mixed $data = null): void
    {
        $payload = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        self::send($status, $payload);
    }

    /**
     * @param array<string, string> $errors
     */
    public static function error(string $message, int $status, array $errors = []): void
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        self::send($status, $payload);
    }

    /**
     * Unduhan berkas mentah. Ekspor CSV tidak bisa memakai envelope JSON, jadi
     * ini satu-satunya jalur transport di luar JSON yang dilayani modul laporan.
     */
    public static function download(
        string $filename,
        string $content,
        string $contentType = 'text/csv; charset=utf-8'
    ): void {
        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
        exit;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function send(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
