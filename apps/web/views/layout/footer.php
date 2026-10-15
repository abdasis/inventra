<?php
/**
 * Layout footer dashboard. Menutup container dan memuat skrip frontend.
 *
 * @var bool|null $loadUsersScript Diisi oleh view halaman pengguna agar users.js dimuat kondisional
 * @var bool|null $loadMasterScript Diisi oleh view halaman master agar master-data.js dimuat kondisional
 * @var bool|null $loadMasterFormScript Diisi oleh view form master agar master-form.js dimuat kondisional
 * @var bool|null $loadAssetFormScript Diisi oleh view form aset agar assets-form.js dimuat kondisional
 * @var bool|null $loadAssetDetailScript Diisi oleh view detail aset agar assets-detail.js dimuat kondisional
 * @var bool|null $loadPurchaseScript Diisi oleh view pengadaan agar purchases.js dimuat kondisional
 * @var bool|null $loadPurchaseDetailScript Diisi oleh view detail pembelian agar purchases-detail.js dimuat
 * @var bool|null $loadStockScript Diisi oleh view stok agar stock.js dimuat kondisional
 * @var bool|null $loadAssignmentScript Diisi oleh view penempatan agar assignments.js dimuat
 * @var bool|null $loadLoanScript Diisi oleh view peminjaman agar loans.js dimuat
 * @var bool|null $loadTransferScript Diisi oleh view mutasi agar transfers.js dimuat
 * @var bool|null $loadMaintenanceScript Diisi oleh view daftar pemeliharaan
 * @var bool|null $loadMaintenanceFormScript Diisi oleh view form pemeliharaan
 * @var bool|null $loadDisposalScript Diisi oleh view daftar penghapusan
 * @var bool|null $loadDisposalFormScript Diisi oleh view form penghapusan
 * @var bool|null $loadReportScript Diisi oleh view laporan agar reports.js dimuat
 * @var bool|null $loadActivityLogScript Diisi oleh view audit trail
 */

use Inventra\Core\Auth\Auth;
use Inventra\Web\Support\View;

$authUser = Auth::user();
$actorName = (string)($authUser['name'] ?? 'Pengguna');
?>
    </div>
  </main>

  <!-- Modal Serah Terima / Check-Out -->
  <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="formCheckout">
          <input type="hidden" name="asset_id" id="checkoutAssetId">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">Serah Terima / Pinjam Aset</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body p-4">
            <div class="alert alert-info py-2 small mb-3">
              <strong id="checkoutAssetName">-</strong> (<span id="checkoutAssetCode">-</span>)
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Nama Pegawai / Penerima <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="recipient_name" id="checkoutRecipient" placeholder="Nama lengkap pegawai" required>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Lokasi Penempatan Baru <span class="text-danger">*</span></label>
              <select class="form-select" name="target_location_id" id="checkoutLocation" required></select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold" for="checkoutActor">Petugas Penyerah</label>
              <input type="text" class="form-control" id="checkoutActor" value="<?= View::e($actorName) ?>" disabled>
              <small class="text-muted">Pelaku tercatat otomatis dari akun yang sedang login.</small>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Catatan Serah Terima</label>
              <textarea class="form-control" rows="2" name="notes" placeholder="Tujuan peminjaman / kebutuhan kerja"></textarea>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary px-4">Konfirmasi Checkout</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Pengembalian / Check-In -->
  <div class="modal fade" id="checkinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="formCheckin">
          <input type="hidden" name="asset_id" id="checkinAssetId">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">Pengembalian Aset ke Gudang</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body p-4">
            <div class="alert alert-warning py-2 small mb-3">
              Aset: <strong id="checkinAssetName">-</strong> saat ini dipegang oleh <strong id="checkinAssignedTo">-</strong>.
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Kembalikan ke Lokasi <span class="text-danger">*</span></label>
              <select class="form-select" name="return_location_id" id="checkinLocation" required></select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold" for="checkinActor">Petugas Penerima</label>
              <input type="text" class="form-control" id="checkinActor" value="<?= View::e($actorName) ?>" disabled>
              <small class="text-muted">Pelaku tercatat otomatis dari akun yang sedang login.</small>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Catatan Pengembalian</label>
              <textarea class="form-control" rows="2" name="notes" placeholder="Kondisi kelengkapan saat dikembalikan..."></textarea>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success px-4">Konfirmasi Checkin</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- jQuery 3.7.1 (Latest Official) -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>

  <!-- Bootstrap 5.3.8 JS Bundle (Popper included via Context7) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

  <!-- DataTables 3.1.4: core harus dimuat sebelum integrasi Bootstrap 5 -->
  <script src="https://cdn.datatables.net/3.1.4/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/3.1.4/js/dataTables.bootstrap5.js"></script>

  <!-- SweetAlert2 11.26.25: notifikasi dan dialog konfirmasi -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.js"></script>

  <script>
    // Flag peran untuk app.js: staf tidak perlu menabrak aksi khusus administrator.
    window.INVENTRA_IS_ADMIN = <?= Auth::isAdmin() ? 'true' : 'false' ?>;
    // Identitas pengguna aktif untuk users.js.
    window.INVENTRA_USER = <?= json_encode(
        [
            'id' => (int)Auth::id(),
            'name' => (string)($authUser['name'] ?? ''),
            'isAdmin' => Auth::isAdmin(),
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
  </script>

  <!-- Inventra Frontend Logic -->
  <?php
  // Penanda versi dari waktu ubah berkas agar peramban tidak memakai salinan lama.
  $publicDir = dirname(__DIR__, 2) . '/public';
  ?>
  <script src="/assets/js/app.js?v=<?= (int) @filemtime($publicDir . '/assets/js/app.js') ?>"></script>

  <?php if (!empty($loadUsersScript)): ?>
    <!-- Logika halaman kelola pengguna, hanya dimuat pada halaman user. -->
    <script src="/assets/js/users.js?v=<?= (int) @filemtime($publicDir . '/assets/js/users.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadMasterScript)): ?>
    <!-- Logika daftar master data, hanya dimuat pada halaman /master. -->
    <script src="/assets/js/master-data.js?v=<?= (int) @filemtime($publicDir . '/assets/js/master-data.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadMasterFormScript)): ?>
    <!-- Logika form master data, hanya dimuat pada halaman form /master. -->
    <script src="/assets/js/master-form.js?v=<?= (int) @filemtime($publicDir . '/assets/js/master-form.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadAssetFormScript)): ?>
    <!-- Logika form aset, hanya dimuat pada halaman /assets/create dan /assets/{id}/edit. -->
    <script src="/assets/js/assets-form.js?v=<?= (int) @filemtime($publicDir . '/assets/js/assets-form.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadAssetDetailScript)): ?>
    <!-- Logika detail aset, hanya dimuat pada halaman /assets/{id}. -->
    <script src="/assets/js/assets-detail.js?v=<?= (int) @filemtime($publicDir . '/assets/js/assets-detail.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadPurchaseScript)): ?>
    <!-- Logika pengadaan (daftar, form, detail + penerimaan), hanya pada halaman /purchases. -->
    <script src="/assets/js/purchases.js?v=<?= (int) @filemtime($publicDir . '/assets/js/purchases.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadPurchaseDetailScript)): ?>
    <!-- Logika detail pembelian dipisah agar tiap berkas tetap di bawah batas ukuran. -->
    <script src="/assets/js/purchases-detail.js?v=<?= (int) @filemtime($publicDir . '/assets/js/purchases-detail.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadStockScript)): ?>
    <!-- Logika stok barang habis pakai, hanya dimuat pada halaman /stock. -->
    <script src="/assets/js/stock.js?v=<?= (int) @filemtime($publicDir . '/assets/js/stock.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadAssignmentScript)): ?>
    <!-- Logika penempatan aset, hanya dimuat pada halaman /assignments. -->
    <script src="/assets/js/assignments.js?v=<?= (int) @filemtime($publicDir . '/assets/js/assignments.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadLoanScript)): ?>
    <!-- Logika peminjaman aset, hanya dimuat pada halaman /loans. -->
    <script src="/assets/js/loans.js?v=<?= (int) @filemtime($publicDir . '/assets/js/loans.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadTransferScript)): ?>
    <!-- Logika mutasi aset, hanya dimuat pada halaman /transfers. -->
    <script src="/assets/js/transfers.js?v=<?= (int) @filemtime($publicDir . '/assets/js/transfers.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadMaintenanceScript)): ?>
    <!-- Logika daftar pemeliharaan, hanya dimuat pada halaman /maintenances. -->
    <script src="/assets/js/maintenances.js?v=<?= (int) @filemtime($publicDir . '/assets/js/maintenances.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadMaintenanceFormScript)): ?>
    <!-- Logika form pemeliharaan, hanya dimuat pada halaman /maintenances/create. -->
    <script src="/assets/js/maintenance-form.js?v=<?= (int) @filemtime($publicDir . '/assets/js/maintenance-form.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadDisposalScript)): ?>
    <!-- Logika daftar penghapusan, hanya dimuat pada halaman /disposals. -->
    <script src="/assets/js/disposals.js?v=<?= (int) @filemtime($publicDir . '/assets/js/disposals.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadDisposalFormScript)): ?>
    <!-- Logika form penghapusan, hanya dimuat pada halaman /disposals/create. -->
    <script src="/assets/js/disposal-form.js?v=<?= (int) @filemtime($publicDir . '/assets/js/disposal-form.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadReportScript)): ?>
    <!-- Logika halaman laporan, hanya dimuat pada halaman /reports. -->
    <script src="/assets/js/reports.js?v=<?= (int) @filemtime($publicDir . '/assets/js/reports.js') ?>"></script>
  <?php endif; ?>

  <?php if (!empty($loadActivityLogScript)): ?>
    <!-- Logika audit trail, hanya dimuat pada halaman /activity-logs. -->
    <script src="/assets/js/activity-logs.js?v=<?= (int) @filemtime($publicDir . '/assets/js/activity-logs.js') ?>"></script>
  <?php endif; ?>
</body>
</html>
