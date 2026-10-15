<?php

declare(strict_types=1);

/**
 * Self-check aturan pengalihan login: sanitasi parameter next (anti open redirect)
 * dan pembentukan URL tujuan dari path request. Tanpa database.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Api\Http\Request;
use Inventra\Web\Support\LoginRedirect;

echo "=== Running Inventra Login Redirect Self-Check ===\n\n";

echo '[TEST 1] sanitizeNext menjaga tujuan internal saja... ';
assert(LoginRedirect::sanitizeNext('/users') === '/users');
assert(LoginRedirect::sanitizeNext('  /users  ') === '/users');
assert(LoginRedirect::sanitizeNext('//jahat.example') === '/');
assert(LoginRedirect::sanitizeNext('https://jahat.example') === '/');
assert(LoginRedirect::sanitizeNext('') === '/');
assert(LoginRedirect::sanitizeNext(null) === '/');
echo "PASSED\n";

echo '[TEST 2] target membentuk /login?next= dari path request... ';
assert(LoginRedirect::target(new Request('GET', '/users')) === '/login?next=%2Fusers');
assert(LoginRedirect::target(new Request('GET', '/users/1/edit')) === '/login?next=%2Fusers%2F1%2Fedit');
echo "PASSED\n";

echo "\n[SUCCESS] Login redirect self-check passed cleanly with 0 failures.\n";
