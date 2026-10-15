<?php
/**
 * Shell halaman Audit Trail (khusus administrator). Halaman ini hanya kerangka:
 * pencarian, filter, wadah error, dan tabel kosong. Baris aktivitas diisi
 * activity-logs.js lewat /api/v1/activity-logs; keadaan kosong ditangani
 * DataTables.
 */

$loadActivityLogScript = true;
?>
<div id="activityLogPage">
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h5 class="fw-bold mb-0">Audit Trail</h5>
        <small class="text-muted">Jejak seluruh perubahan data inventaris beserta pelaku dan waktunya</small>
      </div>
      <button type="button" class="btn btn-light border btn-sm px-3" id="btnResetLogFilter">
        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> Reset Filter
      </button>
    </div>

    <div class="px-4 py-3 border-bottom">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-4">
          <label for="logSearch" class="form-label small fw-semibold mb-1">Cari aktivitas</label>
          <input type="search" class="form-control form-control-sm" id="logSearch" autocomplete="off"
                 placeholder="Deskripsi, pelaku, atau nama subjek...">
        </div>

        <div class="col-6 col-lg-3">
          <label for="logActionFilter" class="form-label small fw-semibold mb-1">Jenis aksi</label>
          <select class="form-select form-select-sm" id="logActionFilter">
            <option value="">Semua jenis aksi</option>
          </select>
        </div>

        <div class="col-6 col-lg-2">
          <label for="logDateFrom" class="form-label small fw-semibold mb-1">Dari tanggal</label>
          <input type="date" class="form-control form-control-sm" id="logDateFrom">
        </div>

        <div class="col-6 col-lg-3">
          <label for="logDateTo" class="form-label small fw-semibold mb-1">Sampai tanggal</label>
          <input type="date" class="form-control form-control-sm" id="logDateTo">
        </div>
      </div>

      <p class="small text-muted mb-0 mt-3">
        Hanya aktivitas terbaru yang dimuat sekaligus. Persempit rentang tanggal bila catatan yang dicari belum tampak.
      </p>
    </div>

    <div id="logError" role="alert" aria-live="assertive"></div>

    <div class="table-responsive">
      <table class="table table-modern table-hover align-middle mb-0" id="tableLog">
        <thead>
          <tr>
            <th style="width: 180px;">Waktu</th>
            <th style="min-width: 150px;">Pelaku</th>
            <th style="width: 150px;">Aksi</th>
            <th style="width: 170px;">Subjek</th>
            <th style="min-width: 260px;">Deskripsi</th>
            <th style="width: 130px;">IP</th>
          </tr>
        </thead>
        <!-- Baris diisi DataTables lewat activity-logs.js; keadaan kosong ditangani DataTables. -->
        <tbody id="logTableBody"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk activity-logs.js: banyaknya catatan yang dimuat sekaligus.
  window.INVENTRA_ACTIVITY_LOG = { limit: 200 };
</script>
