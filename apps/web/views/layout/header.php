<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inventra — Sistem Inventaris Kantor Modern</title>
  
  <!-- Bootstrap 5.3.8 (via Context7 latest) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  
  <!-- Bootstrap Icons 1.11.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom Modern Styling -->
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

  <!-- Top Navigation Bar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark py-2 px-3 sticky-top">
    <div class="container-fluid">
      <a class="navbar-brand navbar-brand-modern text-white" href="/">
        <i class="bi bi-boxes text-primary"></i>
        <span>INVENTRA</span>
        <span class="brand-badge ms-1">Enterprise</span>
      </a>
      
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="/"><i class="bi bi-grid-1x2 me-1"></i> Dashboard & Inventaris</a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white-50" href="#movementsSection"><i class="bi bi-clock-history me-1"></i> Riwayat Mutasi</a>
          </li>
          <li class="nav-item">
            <a class="nav-link text-white-50" href="/api/v1/assets" target="_blank"><i class="bi bi-code-slash me-1"></i> REST API</a>
          </li>
        </ul>

        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-secondary-subtle text-light border border-secondary px-3 py-2">
            <i class="bi bi-building me-1"></i> Kantor Pusat
          </span>
          <button class="btn btn-primary btn-sm px-3" id="btnOpenAddModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Aset
          </button>
        </div>
      </div>
    </div>
  </nav>

  <main class="py-4">
    <div class="container-fluid px-lg-4">
