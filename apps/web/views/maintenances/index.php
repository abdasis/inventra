<?php
/**
 * Shell halaman Pemeliharaan Aset. Halaman ini hanya kerangka: filter, wadah
 * error, tabel kosong, dan modal penyelesaian pemeliharaan. Baris tabel diisi
 * maintenances.js lewat /api/v1/maintenances; keadaan kosong ditangani DataTables.
 *
 * Pencatatan pemeliharaan baru berfield banyak, jadi memakai halaman penuh
 * /maintenances/create, bukan modal.
 */

use Inventra\Core\Auth\Auth;

$loadMaintenanceScript = true;
$isAdmin = Auth::isAdmin();
?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="fw-bold mb-0">Pemeliharaan Aset</h5>
      <small class="text-muted">Catat perawatan aset, biayanya, dan hasil pekerjaannya</small>
    </div>
    <?php if ($isAdmin): ?>
      <a href="/maintenances/create" class="btn btn-primary btn-sm px-3" id="btnAddMaintenance">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Catat Pemeliharaan
      </a>
    <?php endif; ?>
  </div>

  <div class="px-4 py-3 border-bottom">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-7 col-lg-5">
        <label for="maintenanceSearch" class="form-label small fw-semibold mb-1">Cari pemeliharaan</label>
        <input type="search" class="form-control form-control-sm" id="maintenanceSearch" autocomplete="off"
               placeholder="Kode aset, nama aset, atau vendor...">
      </div>

      <div class="col-12 col-md-5 col-lg-3">
        <label for="maintenanceStatusFilter" class="form-label small fw-semibold mb-1">Status</label>
        <select class="form-select form-select-sm" id="maintenanceStatusFilter">
          <option value="">Semua Status</option>
          <option value="ongoing">Berjalan</option>
          <option value="completed">Selesai</option>
        </select>
      </div>
    </div>

    <?php if (!$isAdmin): ?>
      <p class="small text-muted mb-0 mt-2">
        Akun staf hanya dapat melihat data pemeliharaan aset. Pencatatan dan penyelesaian dilakukan administrator.
      </p>
    <?php endif; ?>
  </div>

  <div id="maintenanceError" role="alert" aria-live="assertive"></div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tableMaintenance">
      <thead>
        <tr>
          <th>Aset</th>
          <th style="width: 150px;">Jenis</th>
          <th style="width: 130px;">Tanggal</th>
          <th style="min-width: 160px;">Vendor</th>
          <th class="text-end" style="width: 140px;">Biaya</th>
          <th style="width: 150px;">Jadwal Berikutnya</th>
          <th style="width: 140px;">Status</th>
          <th class="text-center" style="width: 170px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat maintenances.js; keadaan kosong ditangani DataTables. -->
      <tbody id="maintenanceTableBody"></tbody>
    </table>
  </div>
</div>

<!-- Modal penyelesaian pemeliharaan (4 field: hasil pekerjaan, biaya, jadwal berikutnya, catatan). -->
<div class="modal fade" id="maintenanceCompleteModal" tabindex="-1" aria-labelledby="maintenanceCompleteModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formMaintenanceComplete">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="maintenanceCompleteModalTitle">Selesaikan Pemeliharaan</h5>
            <small class="text-muted">Aset kembali berstatus tersedia setelah pemeliharaan selesai</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-4">
          <div id="maintenanceCompleteFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <div class="alert alert-info py-2 small">
            Aset: <strong id="maintenanceCompleteAsset">-</strong>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <label for="input-work_result" class="form-label small fw-semibold">
                Hasil Pekerjaan <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <textarea class="form-control" id="input-work_result" name="work_result" rows="3" required
                        aria-describedby="error-work_result hint-work_result"
                        placeholder="Perbaikan yang dilakukan dan kondisi aset setelah dikerjakan"></textarea>
              <div class="text-danger small mt-1" id="error-work_result" hidden></div>
              <small class="text-muted" id="hint-work_result">Wajib diisi sebagai bukti penyelesaian pekerjaan.</small>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-cost" class="form-label small fw-semibold">Biaya Pemeliharaan (Rp)</label>
              <input type="number" class="form-control" id="input-cost" name="cost" min="0" step="1000"
                     placeholder="0" aria-describedby="error-cost hint-cost">
              <div class="text-danger small mt-1" id="error-cost" hidden></div>
              <small class="text-muted" id="hint-cost">Isi bila biaya berubah dari perkiraan awal.</small>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-next_schedule" class="form-label small fw-semibold">Jadwal Pemeliharaan Berikutnya</label>
              <input type="date" class="form-control" id="input-next_schedule" name="next_schedule"
                     aria-describedby="error-next_schedule hint-next_schedule">
              <div class="text-danger small mt-1" id="error-next_schedule" hidden></div>
              <small class="text-muted" id="hint-next_schedule">Opsional, untuk pemeliharaan berkala.</small>
            </div>

            <div class="col-12">
              <label for="input-notes" class="form-label small fw-semibold">Catatan</label>
              <textarea class="form-control" id="input-notes" name="notes" rows="2"
                        aria-describedby="error-notes hint-notes"
                        placeholder="Suku cadang yang diganti atau keterangan lain (opsional)"></textarea>
              <div class="text-danger small mt-1" id="error-notes" hidden></div>
              <small class="text-muted" id="hint-notes">Opsional.</small>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success px-4" id="btnSubmitMaintenanceComplete">Catat Penyelesaian</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk maintenances.js.
  window.INVENTRA_MAINTENANCE = <?= json_encode(
      ['isAdmin' => $isAdmin],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
