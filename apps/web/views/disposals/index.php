<?php
/**
 * Shell halaman Penghapusan Aset. Halaman ini hanya kerangka: pencarian, wadah
 * error, dan tabel kosong. Baris tabel diisi disposals.js lewat
 * /api/v1/disposals; keadaan kosong ditangani DataTables.
 *
 * Pencatatan penghapusan berfield banyak, jadi memakai halaman penuh
 * /disposals/create, bukan modal.
 */

use Inventra\Core\Auth\Auth;

$loadDisposalScript = true;
$isAdmin = Auth::isAdmin();
?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="fw-bold mb-0">Penghapusan Aset</h5>
      <small class="text-muted">Riwayat aset yang dikeluarkan dari daftar inventaris beserta dasar penghapusannya</small>
    </div>
    <?php if ($isAdmin): ?>
      <a href="/disposals/create" class="btn btn-primary btn-sm px-3" id="btnAddDisposal">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Catat Penghapusan
      </a>
    <?php endif; ?>
  </div>

  <div class="px-4 py-3 border-bottom">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-7 col-lg-5">
        <label for="disposalSearch" class="form-label small fw-semibold mb-1">Cari penghapusan</label>
        <input type="search" class="form-control form-control-sm" id="disposalSearch" autocomplete="off"
               placeholder="Kode aset, nama aset, atau nomor dokumen...">
      </div>
    </div>

    <?php if (!$isAdmin): ?>
      <p class="small text-muted mb-0 mt-2">
        Akun staf hanya dapat melihat data penghapusan aset. Pencatatan dilakukan administrator.
      </p>
    <?php endif; ?>
  </div>

  <div id="disposalError" role="alert" aria-live="assertive"></div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tableDisposal">
      <thead>
        <tr>
          <th>Aset</th>
          <th>Kategori</th>
          <th style="width: 130px;">Tanggal</th>
          <th style="width: 150px;">Metode</th>
          <th class="text-end" style="width: 150px;">Nilai Buku</th>
          <th style="min-width: 200px;">Alasan</th>
          <th style="min-width: 160px;">Disetujui Oleh</th>
          <th class="text-center" style="width: 130px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat disposals.js; keadaan kosong ditangani DataTables. -->
      <tbody id="disposalTableBody"></tbody>
    </table>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk disposals.js.
  window.INVENTRA_DISPOSAL = <?= json_encode(
      ['isAdmin' => $isAdmin],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
