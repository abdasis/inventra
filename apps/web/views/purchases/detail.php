<?php
/**
 * Shell detail pembelian. Identitas, daftar barang, dan panel penerimaan diisi
 * purchases-detail.js dari API; halaman ini hanya menyediakan kerangka kartu.
 *
 * @var int $purchaseId
 */

use Inventra\Core\Auth\Auth;

$loadPurchaseScript = true;
$loadPurchaseDetailScript = true;
$purchaseId = (int)($purchaseId ?? 0);
$isAdmin = Auth::isAdmin();
?>
<div id="purchaseDetail">
  <div id="purchaseDetailError" role="alert" aria-live="assertive"></div>

  <!-- Kartu identitas pembelian sekaligus tempat aksi dokumen. -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h5 class="fw-bold mb-0" id="purchaseTitle">Memuat pembelian...</h5>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
          <small class="text-muted" id="purchaseSubtitle">Mengambil data dari server</small>
          <span id="purchaseStatus"></span>
        </div>
      </div>

      <div class="detail-actions d-flex flex-wrap align-items-center gap-2">
        <a class="btn btn-light border btn-sm" href="/purchases/<?= $purchaseId ?>/edit" id="btnPurchaseEdit" hidden>
          <i class="bi bi-pencil me-1" aria-hidden="true"></i> Ubah
        </a>
        <button type="button" class="btn btn-light border btn-sm text-danger" id="btnPurchaseCancel" hidden>
          <i class="bi bi-x-circle me-1" aria-hidden="true"></i> Batalkan
        </button>
        <a class="btn btn-outline-secondary btn-sm" href="/purchases">
          <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
        </a>
      </div>
    </div>

    <div class="card-body p-4" id="purchaseIdentity">
      <div class="text-muted small">Memuat data pembelian...</div>
    </div>
  </div>

  <!-- Barang yang dipesan beserta progres penerimaannya. -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom">
      <h6 class="fw-bold mb-0"><i class="bi bi-box-seam me-2 text-primary" aria-hidden="true"></i>Barang yang Dipesan</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-modern align-middle mb-0">
        <thead>
          <tr>
            <th style="min-width: 220px;">Barang</th>
            <th>Jenis</th>
            <th>Satuan</th>
            <th class="text-end">Dipesan</th>
            <th class="text-end">Diterima</th>
            <th class="text-end">Ditolak</th>
            <th class="text-end">Sisa</th>
            <th class="text-end">Harga Satuan</th>
            <th class="text-end">Subtotal</th>
          </tr>
        </thead>
        <tbody id="purchaseDetailBody">
          <tr><td colspan="9" class="text-center py-3 text-muted">Memuat detail barang...</td></tr>
        </tbody>
        <tfoot id="purchaseDetailFoot" hidden></tfoot>
      </table>
    </div>
  </div>

  <!-- Panel penerimaan: hanya terbuka untuk pembelian draft atau sebagian. -->
  <div class="card border-0 shadow-sm rounded-3 mb-4" id="purchaseReceiveCard" hidden>
    <div class="card-header bg-white py-3 px-4 border-bottom">
      <h6 class="fw-bold mb-0"><i class="bi bi-box-arrow-in-down me-2 text-primary" aria-hidden="true"></i>Penerimaan Barang</h6>
      <small class="text-muted">Penerimaan boleh sebagian; sisa barang masih dapat diterima pada penerimaan berikutnya</small>
    </div>

    <div class="table-responsive">
      <table class="table table-modern align-middle mb-0 purchase-receive">
        <thead>
          <tr>
            <th style="min-width: 220px;">Barang</th>
            <th class="text-end">Sisa</th>
            <th class="text-end" style="width: 170px;">Jumlah Diterima</th>
            <th class="text-end" style="width: 170px;">Jumlah Ditolak</th>
          </tr>
        </thead>
        <tbody id="purchaseReceiveBody"></tbody>
      </table>
    </div>

    <div class="card-body border-top">
      <label for="purchaseReceiveNotes" class="form-label small fw-semibold">Catatan Penerimaan</label>
      <textarea class="form-control" id="purchaseReceiveNotes" rows="2"
                placeholder="Kondisi barang, nomor surat jalan, atau keterangan lain"></textarea>

      <div class="text-danger small mt-2" id="error-receive" hidden></div>

      <div class="form-actions d-flex flex-wrap gap-2 justify-content-end mt-3">
        <button type="button" class="btn btn-primary px-4" id="btnSubmitReceive">
          <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Simpan Penerimaan
        </button>
      </div>
    </div>
  </div>

  <?php if (!$isAdmin): ?>
    <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
      <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
      <span>Akun staf hanya dapat melihat detail pembelian. Penerimaan dan pembatalan dilakukan administrator.</span>
    </div>
  <?php endif; ?>
</div>

<script>
  // Konfigurasi halaman untuk purchases.js.
  window.INVENTRA_PURCHASE = <?= json_encode(
      [
          'mode' => 'detail',
          'id' => $purchaseId,
          'isAdmin' => $isAdmin,
      ],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
