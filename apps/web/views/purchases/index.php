<?php
/**
 * Shell daftar pembelian (Pengadaan & Penerimaan). Halaman ini hanya kerangka:
 * filter, wadah error, dan tabel kosong. Baris diisi purchases.js lewat AJAX,
 * keadaan kosong ditangani DataTables.
 *
 * @var bool $isAdmin
 */

use Inventra\Core\Auth\Auth;

$loadPurchaseScript = true;
$isAdmin = Auth::isAdmin();
?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="fw-bold mb-0">Pengadaan &amp; Penerimaan</h5>
      <small class="text-muted">Catat pembelian barang dan penerimaan bertahap dari supplier</small>
    </div>
    <?php if ($isAdmin): ?>
      <a href="/purchases/create" class="btn btn-primary btn-sm px-3">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Pembelian
      </a>
    <?php endif; ?>
  </div>

  <div class="px-4 py-3 border-bottom">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-7 col-lg-5">
        <label for="purchaseSearch" class="form-label small fw-semibold mb-1">Cari pembelian</label>
        <input type="search" class="form-control form-control-sm" id="purchaseSearch" autocomplete="off"
               placeholder="Nomor pembelian, supplier, atau referensi...">
      </div>

      <div class="col-12 col-md-5 col-lg-3">
        <label for="purchaseStatusFilter" class="form-label small fw-semibold mb-1">Status</label>
        <select class="form-select form-select-sm" id="purchaseStatusFilter">
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="partial">Sebagian Diterima</option>
          <option value="received">Diterima</option>
          <option value="cancelled">Dibatalkan</option>
        </select>
      </div>
    </div>

    <?php if (!$isAdmin): ?>
      <p class="small text-muted mb-0 mt-2">
        Akun staf hanya dapat melihat data pembelian. Penambahan, perubahan, dan penerimaan dilakukan administrator.
      </p>
    <?php endif; ?>
  </div>

  <div id="purchaseError" role="alert" aria-live="assertive"></div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tablePurchase">
      <thead>
        <tr>
          <th>Nomor &amp; Tanggal</th>
          <th>Supplier</th>
          <th>Jumlah Barang</th>
          <th class="text-end">Total</th>
          <th>Status</th>
          <th class="text-center" style="width: 140px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat purchases.js; keadaan kosong ditangani DataTables. -->
      <tbody id="purchaseTableBody"></tbody>
    </table>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk purchases.js.
  window.INVENTRA_PURCHASE = <?= json_encode(
      ['mode' => 'list', 'isAdmin' => $isAdmin],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
