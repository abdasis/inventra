<?php

declare(strict_types=1);

namespace Inventra\Api\Http;

/**
 * Nilai request HTTP. Query dan body dipisah tegas: filter daftar dibaca dari
 * query, sedangkan mutasi dibaca dari body.
 */
final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $decodedBody = null;

    private ?string $path = null;

    /** @var array<string, string> */
    private array $routeParams = [];

    public function __construct(
        private readonly string $method,
        private readonly string $uri,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string)($_SERVER['REQUEST_URI'] ?? '/'),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * Path mentah dari URI, tanpa pemangkasan prefiks maupun garis miring ujung.
     * Dipakai saat path asli perlu diteruskan apa adanya, mis. sebagai parameter
     * next pada pengalihan ke halaman login.
     */
    public function rawPath(): string
    {
        return (string)parse_url($this->uri, PHP_URL_PATH);
    }

    /**
     * Path tanpa prefiks /api atau /api/v1, tanpa garis miring di ujung.
     */
    public function path(): string
    {
        if ($this->path !== null) {
            return $this->path;
        }

        $path = (string)preg_replace('#^/api(/v1)?#', '', $this->rawPath());

        return $this->path = (rtrim($path, '/') ?: '/');
    }

    public function routeParam(string $name): ?string
    {
        return $this->routeParams[$name] ?? null;
    }

    /**
     * @param array<string, string> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Body JSON, dengan fallback ke $_POST bila body mentah kosong atau tidak valid.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->decodedBody !== null) {
            return $this->decodedBody;
        }

        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->decodedBody = $decoded;
            }
        }

        return $this->decodedBody = $_POST;
    }

    public function query(?string $key = null): mixed
    {
        return $key === null ? $_GET : ($_GET[$key] ?? null);
    }

    /**
     * Field body request. Dibaca lewat json() supaya body JSON dari klien ikut terbaca,
     * bukan hanya form POST biasa.
     */
    public function body(?string $key = null): mixed
    {
        $body = $this->json();

        return $key === null ? $body : ($body[$key] ?? null);
    }

    public function header(string $name): ?string
    {
        $value = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? null;

        return is_string($value) ? $value : null;
    }

    public function ip(): ?string
    {
        $value = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Request datang lewat HTTPS. Dipakai front controller untuk menentukan
     * flag secure pada cookie sesi, karena Core tidak boleh membaca $_SERVER.
     */
    public function isSecure(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}
