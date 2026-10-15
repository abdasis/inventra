<?php

declare(strict_types=1);

namespace Inventra\Web\Support;

use RuntimeException;

/**
 * Helper render view dan utilitas HTTP untuk halaman web.
 * View berupa file PHP polos di apps/web/views, tanpa templating engine.
 */
final class View
{
    private const VIEW_PATH = __DIR__ . '/../../views';

    /**
     * Render view lengkap dengan layout dashboard.
     *
     * @param array<string, mixed> $data Data yang diekstrak menjadi variabel view
     * @param array<int, array{label: string, url: ?string}> $breadcrumbs Item terakhir = halaman aktif
     */
    public static function render(
        string $view,
        array $data = [],
        array $breadcrumbs = [],
        string $pageTitle = 'Inventra'
    ): void {
        $viewFile = self::resolve($view);

        extract($data, EXTR_SKIP);

        require self::VIEW_PATH . '/layout/header.php';
        require $viewFile;
        require self::VIEW_PATH . '/layout/footer.php';
    }

    /**
     * Render view tanpa layout, untuk halaman mandiri seperti login.
     *
     * @param array<string, mixed> $data Data yang diekstrak menjadi variabel view
     */
    public static function renderStandalone(string $view, array $data = []): void
    {
        $viewFile = self::resolve($view);

        extract($data, EXTR_SKIP);

        require $viewFile;
    }

    public static function redirect(string $to): void
    {
        header('Location: ' . $to);
        exit;
    }

    public static function methodNotAllowed(): void
    {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Metode HTTP tidak diizinkan untuk alamat ini.';
        exit;
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    public static function consumeFlash(): ?array
    {
        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return [
            'type' => (string)($flash['type'] ?? 'info'),
            'message' => (string)($flash['message'] ?? ''),
        ];
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private static function resolve(string $view): string
    {
        $viewFile = self::VIEW_PATH . '/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('View tidak ditemukan: ' . $view);
        }

        return $viewFile;
    }
}
