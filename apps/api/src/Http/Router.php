<?php

declare(strict_types=1);

namespace Inventra\Api\Http;

use Inventra\Core\Exceptions\NotFoundException;

/**
 * Tabel rute deklaratif dengan pencocokan first-match-wins: rute pertama yang
 * path-nya cocok dan method-nya sama yang menang. Parameter rute ditulis {id}
 * dan dikompilasi menjadi grup bernama \d+.
 *
 * Guard global (auth, CSRF) dijalankan sebelum resolusi rute, mengikuti urutan
 * front controller sebelumnya: auth lebih dulu, baru CSRF.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable, guards: list<callable>}> */
    private array $routes = [];

    /** @var list<callable> */
    private array $globalGuards = [];

    /** @var callable|null */
    private $methodNotAllowed = null;

    /** @var callable */
    private $notFound;

    public function __construct()
    {
        $this->notFound = static function (Request $request): void {
            throw new NotFoundException(
                'Endpoint tidak ditemukan: ' . $request->method() . ' ' . $request->path()
            );
        };
    }

    /**
     * @param list<callable> $guards Guard yang hanya berlaku untuk rute ini.
     */
    public function add(string $method, string $path, callable $handler, array $guards = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->compile($path),
            'handler' => $handler,
            'guards' => $guards,
        ];
    }

    /**
     * @param list<callable> $guards
     */
    public function setGlobalGuards(array $guards): void
    {
        $this->globalGuards = $guards;
    }

    public function setNotFound(callable $handler): void
    {
        $this->notFound = $handler;
    }

    /**
     * Bila null (perilaku API), method yang tidak cocok jatuh ke notFound —
     * API tidak pernah mengembalikan 405.
     */
    public function setMethodNotAllowed(?callable $handler): void
    {
        $this->methodNotAllowed = $handler;
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->globalGuards as $guard) {
            $guard($request);
        }

        $path = $request->path();
        $method = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }

            $request->setRouteParams(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

            foreach ($route['guards'] as $guard) {
                $guard($request);
            }

            ($route['handler'])($request);

            return;
        }

        if ($pathMatched && $this->methodNotAllowed !== null) {
            ($this->methodNotAllowed)($request);

            return;
        }

        ($this->notFound)($request);
    }

    private function compile(string $path): string
    {
        $pattern = (string)preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>\d+)', $path);

        return '#^' . $pattern . '$#';
    }
}
