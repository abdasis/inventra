<?php
/**
 * Layout header dashboard. Variabel $pageTitle dan $breadcrumbs disediakan oleh Inventra\Web\Support\View.
 *
 * @var string $pageTitle
 * @var array<int, array{label: string, url: ?string}> $breadcrumbs
 */

use Inventra\Core\Auth\Auth;
use Inventra\Core\Auth\Csrf;
use Inventra\Core\Enums\UserRole;
use Inventra\Web\Support\View;

$pageTitle = $pageTitle ?? 'Inventra';
$breadcrumbs = $breadcrumbs ?? [];
$flash = View::consumeFlash();
$authUser = Auth::user();
$authRole = UserRole::tryFrom((string)($authUser['role'] ?? ''));

// Status aktif nav ditentukan dari path yang sedang dibuka, bukan di-hardcode.
$currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$isUsersPage = str_starts_with($currentPath, '/users');

$navLinks = [
    ['label' => 'Dashboard & Inventaris', 'url' => '/', 'icon' => 'bi-grid-1x2', 'active' => $currentPath === '/'],
    ['label' => 'Riwayat Mutasi', 'url' => '/#movementsSection', 'icon' => 'bi-clock-history', 'active' => false],
    ['label' => 'REST API', 'url' => '/api/v1/assets', 'icon' => 'bi-code-slash', 'active' => false, 'external' => true],
];

if (Auth::isAdmin()) {
    $navLinks[] = ['label' => 'Kelola Pengguna', 'url' => '/users', 'icon' => 'bi-people', 'active' => $isUsersPage];
}

$userName = (string)($authUser['name'] ?? 'Pengguna');
$userRoleLabel = $authRole?->label() ?? 'Tidak dikenal';
$userRoleBadge = $authRole?->badgeClass() ?? 'bg-secondary-subtle text-secondary';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= View::e(Csrf::token()) ?>">
  <title><?= View::e($pageTitle) ?></title>

  <!-- Bootstrap 5.3.8 (via Context7 latest) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">

  <!-- Bootstrap Icons 1.11.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Custom Modern Styling -->
  <?php
  /*
   * Penanda versi dari waktu ubah berkas, supaya peramban tidak menyajikan
   * salinan lama setelah CSS diubah.
   */
  $styleVersion = (int) @filemtime(dirname(__DIR__, 2) . '/public/assets/css/style.css');
  ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= $styleVersion ?>">

  <!-- DataTables 3.1.4 - integrasi tema Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.datatables.net/3.1.4/css/dataTables.bootstrap5.css">

  <!-- SweetAlert2 11.26.25 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.css">
</head>
<body>

  <!--
    Header dua baris. Pembungkus terluar .app-header menerima kelas .is-scrolled
    dari app.js saat halaman digulir: baris atas memuat brand dan identitas
    pengguna, baris bawah khusus navigasi.
  -->
  <header class="app-header sticky-top">
    <div class="app-header-top">
      <div class="container-wide">
        <!-- Lockup brand: mark gradient berisi ikon di kiri, wordmark dan label bertumpuk di kanan. -->
        <a class="navbar-brand-modern" href="/">
          <span class="brand-mark" aria-hidden="true"><i class="bi bi-boxes"></i></span>
          <span class="brand-lockup">
            <span class="brand-wordmark">INVENTRA</span>
            <span class="brand-badge d-none d-sm-inline-block">Enterprise</span>
          </span>
        </a>

        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-primary btn-sm px-3" id="btnOpenAddModal">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Aset
          </button>

          <div class="dropdown">
            <button class="user-menu-btn dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu akun <?= View::e($userName) ?>">
              <span class="user-avatar" aria-hidden="true"><?= View::e($userInitial) ?></span>
              <span class="user-meta d-none d-lg-flex">
                <span class="user-name"><?= View::e($userName) ?></span>
                <span class="user-role"><?= View::e($userRoleLabel) ?></span>
              </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-menu">
              <li class="user-menu-identity">
                <span class="user-avatar user-avatar-lg" aria-hidden="true"><?= View::e($userInitial) ?></span>
                <span class="user-menu-text">
                  <span class="user-name"><?= View::e($userName) ?></span>
                  <span class="badge badge-status mt-1 <?= View::e($userRoleBadge) ?>"><?= View::e($userRoleLabel) ?></span>
                </span>
              </li>
              <?php if (Auth::isAdmin()): ?>
                <li><a class="dropdown-item" href="/users"><i class="bi bi-people me-2" aria-hidden="true"></i>Kelola Pengguna</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="/logout">
                  <?= Csrf::field() ?>
                  <button type="submit" class="dropdown-item text-danger">
                    <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Keluar
                  </button>
                </form>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <nav class="navbar navbar-expand-lg app-header-nav navbar-wide">
      <div class="container-wide">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Buka menu navigasi">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <?php foreach ($navLinks as $link): ?>
              <li class="nav-item">
                <a class="nav-link<?= $link['active'] ? ' active' : '' ?>"
                   href="<?= View::e((string)$link['url']) ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?><?= (!empty($link['external'])) ? ' target="_blank" rel="noopener"' : '' ?>>
                  <i class="bi <?= View::e((string)$link['icon']) ?>" aria-hidden="true"></i><?= View::e((string)$link['label']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <main class="py-4">
    <div class="container-wide">

      <?php if (!empty($breadcrumbs)): ?>
        <nav aria-label="Breadcrumb" class="mb-3">
          <ol class="breadcrumb breadcrumb-modern mb-0 small">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
              <?php $isCurrent = $index === array_key_last($breadcrumbs); ?>
              <li class="breadcrumb-item<?= $isCurrent ? ' active' : '' ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                <?php if ($isCurrent || empty($crumb['url'])): ?>
                  <?= View::e((string)($crumb['label'] ?? '')) ?>
                <?php else: ?>
                  <a href="<?= View::e((string)$crumb['url']) ?>"><?= View::e((string)($crumb['label'] ?? '')) ?></a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </nav>
      <?php endif; ?>

      <?php if ($flash !== null): ?>
        <div class="alert alert-<?= View::e($flash['type']) ?> alert-dismissible fade show py-2 small" role="alert">
          <?= View::e($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
      <?php endif; ?>
