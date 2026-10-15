<?php
/**
 * Halaman login mandiri, sengaja tidak memakai layout dashboard.
 *
 * @var string $email Email yang terakhir diketik
 * @var string|null $error Pesan kesalahan bila login gagal
 * @var string $next Tujuan setelah login berhasil
 */

use Inventra\Core\Auth\Csrf;
use Inventra\Web\Support\View;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk — Inventra</title>

  <!-- Bootstrap 5.3.8 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">

  <!-- Bootstrap Icons 1.11.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-shell">

  <main class="login-card card">
    <div class="card-body p-4 p-sm-5">
      <div class="text-center mb-4">
        <div class="login-brand">
          <i class="bi bi-boxes text-primary"></i>
          <span>INVENTRA</span>
        </div>
        <small class="text-muted d-block mt-2">Masuk untuk mengelola inventaris kantor</small>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small d-flex align-items-start gap-2" role="alert">
          <i class="bi bi-exclamation-triangle mt-1"></i>
          <span><?= View::e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" action="/login">
        <?= Csrf::field() ?>
        <input type="hidden" name="next" value="<?= View::e($next) ?>">

        <div class="mb-3">
          <label for="loginEmail" class="form-label small fw-semibold">Email</label>
          <input type="email" class="form-control" id="loginEmail" name="email"
                 value="<?= View::e($email) ?>" autocomplete="username" required autofocus>
        </div>

        <div class="mb-4">
          <label for="loginPassword" class="form-label small fw-semibold">Kata Sandi</label>
          <input type="password" class="form-control" id="loginPassword" name="password"
                 autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
        </button>
      </form>

      <p class="text-center small text-muted mt-4 mb-0">
        Hubungi administrator bila Anda belum memiliki akun.
      </p>
    </div>
  </main>

</body>
</html>
