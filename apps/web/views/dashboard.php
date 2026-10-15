<?php
// apps/web/views/dashboard.php
?>

<!-- Metrics Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card card-metric p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold">TOTAL ASET KANTOR</span>
        <div class="metric-icon bg-primary-subtle text-primary">
          <i class="bi bi-box-seam"></i>
        </div>
      </div>
      <div class="metric-value text-dark" id="statTotalAssets">-</div>
      <small class="text-muted mt-1 d-block"><span class="text-success fw-semibold"><i class="bi bi-check-circle"></i> Terdata</span> di sistem</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card card-metric p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold">SEDANG DIGUNAKAN</span>
        <div class="metric-icon bg-info-subtle text-info">
          <i class="bi bi-person-workspace"></i>
        </div>
      </div>
      <div class="metric-value text-dark" id="statInUse">-</div>
      <small class="text-muted mt-1 d-block"><span class="text-info fw-semibold" id="statInUsePercent">-</span> dari total barang</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card card-metric p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold">TERSEDIA DI GUDANG</span>
        <div class="metric-icon bg-success-subtle text-success">
          <i class="bi bi-check2-circle"></i>
        </div>
      </div>
      <div class="metric-value text-dark" id="statAvailable">-</div>
      <small class="text-muted mt-1 d-block">Siap didistribusikan</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card card-metric p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold">TOTAL VALUASI ASET</span>
        <div class="metric-icon bg-warning-subtle text-warning">
          <i class="bi bi-cash-stack"></i>
        </div>
      </div>
      <div class="metric-value text-dark fs-4" id="statValuation">-</div>
      <small class="text-muted mt-1 d-block">Total nilai perolehan</small>
    </div>
  </div>
</div>

<!-- Main Inventory Table Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="fw-bold mb-0">Daftar Inventaris & Aset</h5>
      <small class="text-muted">Kelola data seluruh barang dan perangkat kantor secara terpusat</small>
    </div>

    <!-- Filter Tools -->
    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="input-group input-group-sm" style="width: 240px;">
        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" class="form-control bg-light border-start-0" id="filterSearch" placeholder="Cari aset / kode / pemegang...">
      </div>

      <select class="form-select form-select-sm" id="filterCategory" style="width: 170px;">
        <option value="">Semua Kategori</option>
      </select>

      <select class="form-select form-select-sm" id="filterStatus" style="width: 150px;">
        <option value="">Semua Status</option>
        <option value="available">Tersedia</option>
        <option value="in_use">Dipakai</option>
        <option value="maintenance">Perbaikan</option>
        <option value="disposed">Afkir</option>
      </select>

      <button class="btn btn-outline-secondary btn-sm" id="btnResetFilter" title="Reset Filter">
        <i class="bi bi-arrow-counterclockwise"></i>
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tableAssets">
      <thead>
        <tr>
          <th>Kode & Nama Aset</th>
          <th>Kategori</th>
          <th>Lokasi / Ruangan</th>
          <th>Kondisi</th>
          <th>Status & Pemegang</th>
          <th class="text-end">Nilai (Rp)</th>
          <th class="text-center" style="width: 220px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat app.js; keadaan kosong ditangani DataTables. -->
      <tbody id="assetsTableBody"></tbody>
    </table>
  </div>
</div>

<!-- Recent Movements Section -->
<div class="card border-0 shadow-sm rounded-3 mb-4" id="movementsSection">
  <div class="card-header bg-white py-3 px-4 border-bottom">
    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Log Mutasi & Serah Terima Terbaru</h6>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-modern align-middle mb-0">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Aset</th>
            <th>Jenis Aksi</th>
            <th>Dari & Ke Lokasi</th>
            <th>Petugas / Penerima</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody id="movementsTableBody">
          <tr>
            <td colspan="6" class="text-center py-3 text-muted">Memuat log aktivitas...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
