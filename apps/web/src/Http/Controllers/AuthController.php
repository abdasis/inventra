<?php

declare(strict_types=1);

namespace Inventra\Web\Http\Controllers;

use Inventra\Core\Auth\Auth;
use Inventra\Core\Auth\Csrf;
use Inventra\Web\Support\LoginRedirect;
use Inventra\Web\Support\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            View::redirect('/');
            return;
        }

        $this->renderLogin([
            'email' => '',
            'error' => null,
            'next' => LoginRedirect::sanitizeNext($_GET['next'] ?? null),
        ]);
    }

    public function login(?string $ipAddress = null): void
    {
        $next = LoginRedirect::sanitizeNext($_POST['next'] ?? null);

        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            $this->renderLogin([
                'email' => (string)($_POST['email'] ?? ''),
                'error' => 'Sesi formulir kedaluwarsa, silakan coba lagi.',
                'next' => $next,
            ]);
            return;
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $result = Auth::attempt($email, $password, $ipAddress);

        if ($result['success']) {
            View::redirect($next);
            return;
        }

        $this->renderLogin([
            'email' => $email,
            'error' => $result['message'],
            'next' => $next,
        ]);
    }

    public function logout(?string $ipAddress = null): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            View::flash('danger', 'Sesi formulir kedaluwarsa, silakan coba lagi.');
            View::redirect('/login');
            return;
        }

        Auth::logout($ipAddress);
        View::redirect('/login');
    }

    /**
     * @param array{email: string, error: ?string, next: string} $data
     */
    private function renderLogin(array $data): void
    {
        View::renderStandalone('auth/login', $data);
    }
}
