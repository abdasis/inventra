<?php
/**
 * Shell halaman detail aset. Identitas, perolehan, dan riwayat mutasi diisi
 * assets-detail.js dari API; halaman ini hanya menyediakan kerangka kartu.
 *
 * @var int $assetId
 */

use Inventra\Core\Auth\Auth;
use Inventra\Web\Support\View;

$loadAssetDetailScript = true;
$assetId = (int)($assetId ?? 0);
$isAdmin = Auth::isAdmin();
?>
<div id="assetDetail">
  <div id="assetDetailError" role="alert" aria-live="assertive"></div>

  <!-- Kartu identitas sekaligus tempat aksi aset. -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h5 class="fw-bold mb-0" id="assetTitle">Memuat aset...</h5>
        <small class="text-muted" id="assetSubtitle">Mengambil data dari server</small>
      </div>

      <div class="detail-actions d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAssetCheckout" hidden>
          <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Serah Terima
        </button>
        <button type="button" class="btn btn-outline-success btn-sm" id="btnAssetCheckin" hidden>
          <i class="bi bi-box-arrow-in-left me-1" aria-hidden="true"></i> Kembalikan
        </button>
        <a class="btn btn-light border btn-sm" href="/assets/<?= $assetId ?>/edit">
          <i class="bi bi-pencil me-1" aria-hidden="true"></i> Ubah
        </a>
        <a class="btn btn-light border btn-sm" href="/assets/<?= $assetId ?>/label">
          <i class="bi bi-qr-code me-1" aria-hidden="true"></i> Cetak Label
        </a>
        <?php if ($isAdmin): ?>
          <button type="button" class="btn btn-light border btn-sm text-danger" id="btnAssetDelete">
            <i class="bi bi-trash me-1" aria-hidden="true"></i> Hapus
          </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="card-body p-4" id="assetIdentity">
      <div class="text-muted small">Memuat identitas aset...</div>
    </div>
  </div>

  <!-- Kartu perolehan dan dokumen. -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom">
      <h6 class="fw-bold mb-0"><i class="bi bi-cash-stack me-2 text-primary" aria-hidden="true"></i>Perolehan &amp; Dokumen</h6>
    </div>
    <div class="card-body p-4" id="assetAcquisition">
      <div class="text-muted small">Memuat data perolehan...</div>
    </div>
  </div>

  <!-- Riwayat mutasi aset. -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom">
      <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary" aria-hidden="true"></i>Riwayat Mutasi Aset</h6>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-modern align-middle mb-0">
          <thead>
            <tr>
              <th>Waktu</th>
              <th>Jenis Aksi</th>
              <th>Dari &amp; Ke Lokasi</th>
              <th>Petugas / Penerima</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody id="assetMovementsBody">
            <tr><td colspan="5" class="text-center py-3 text-muted">Memuat riwayat mutasi...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk assets-detail.js.
  window.INVENTRA_ASSET = <?= json_encode(
      ['id' => $assetId, 'isAdmin' => $isAdmin],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
