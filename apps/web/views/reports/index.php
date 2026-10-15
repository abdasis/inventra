<?php
/**
 * Shell halaman Laporan Inventaris. Angka ringkasan dan rekapitulasi kategori
 * diisi reports.js dari /api/v1/reports/summary; halaman ini hanya menyediakan
 * kerangka kartu, tabel kosong, dan tautan unduhan.
 *
 * Unduhan CSV sengaja berupa tautan biasa, bukan AJAX: server mengirim berkas
 * dengan Content-Disposition: attachment, sehingga peramban yang menanganinya.
 */

$loadReportScript = true;
?>
<div id="reportPage">
  <!-- Kartu ringkasan inventaris -->
  <div class="row g-3 mb-4" id="reportMetrics">
    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">TOTAL ASET</span>
          <div class="metric-icon bg-primary-subtle text-primary">
            <i class="bi bi-box-seam" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statTotalAssets">-</div>
        <small class="text-muted mt-1 d-block">Seluruh aset yang terdata</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">TERSEDIA</span>
          <div class="metric-icon bg-success-subtle text-success">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statAvailable">-</div>
        <small class="text-muted mt-1 d-block">Siap dipakai atau didistribusikan</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">DIGUNAKAN</span>
          <div class="metric-icon bg-info-subtle text-info">
            <i class="bi bi-person-workspace" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statInUse">-</div>
        <small class="text-muted mt-1 d-block">Sedang dipegang unit kerja</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">DIPINJAM</span>
          <div class="metric-icon bg-primary-subtle text-primary">
            <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statOnLoan">-</div>
        <small class="text-muted mt-1 d-block">Keluar lewat peminjaman</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">PERBAIKAN</span>
          <div class="metric-icon bg-warning-subtle text-warning">
            <i class="bi bi-tools" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statMaintenance">-</div>
        <small class="text-muted mt-1 d-block">Sedang dalam pemeliharaan</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">DIHAPUS</span>
          <div class="metric-icon bg-secondary-subtle text-secondary">
            <i class="bi bi-archive" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statDisposed">-</div>
        <small class="text-muted mt-1 d-block">Sudah dikeluarkan dari inventaris</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">TOTAL VALUASI</span>
          <div class="metric-icon bg-success-subtle text-success">
            <i class="bi bi-cash-stack" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark fs-4" id="statTotalValuation">-</div>
        <small class="text-muted mt-1 d-block">Total nilai perolehan aset aktif</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">STOK RENDAH</span>
          <div class="metric-icon bg-danger-subtle text-danger">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statLowStock">-</div>
        <small class="text-muted mt-1 d-block">Barang habis pakai menyentuh stok minimum</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">PEMINJAMAN AKTIF</span>
          <div class="metric-icon bg-info-subtle text-info">
            <i class="bi bi-journal-arrow-up" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statActiveLoans">-</div>
        <small class="text-muted mt-1 d-block" id="statOverdueHint">Belum ada data keterlambatan</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">BIAYA PEMELIHARAAN</span>
          <div class="metric-icon bg-warning-subtle text-warning">
            <i class="bi bi-receipt" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark fs-4" id="statMaintenanceCost">-</div>
        <small class="text-muted mt-1 d-block">Realisasi biaya tahun ini</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">PENGHAPUSAN</span>
          <div class="metric-icon bg-secondary-subtle text-secondary">
            <i class="bi bi-trash3" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statDisposalsYear">-</div>
        <small class="text-muted mt-1 d-block">Penghapusan tahun ini</small>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-metric p-3 h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-semibold">AKTIVITAS 7 HARI</span>
          <div class="metric-icon bg-primary-subtle text-primary">
            <i class="bi bi-activity" aria-hidden="true"></i>
          </div>
        </div>
        <div class="metric-value text-dark" id="statActivities7Days">-</div>
        <small class="text-muted mt-1 d-block">Perubahan data tercatat sepekan terakhir</small>
      </div>
    </div>
  </div>

  <!-- Wadah error ringkasan: satu pesan untuk seluruh angka di halaman ini. -->
  <div id="reportError" role="alert" aria-live="assertive"></div>

  <div class="row g-3">
    <!-- Rekapitulasi per kategori -->
    <div class="col-12 col-xl-7">
      <div class="card border-0 shadow-sm rounded-3 h-100">
        <div class="card-header bg-white py-3 px-4 border-bottom">
          <h6 class="fw-bold mb-0">
            <i class="bi bi-diagram-3 me-2 text-primary" aria-hidden="true"></i>Ringkasan per Kategori
          </h6>
          <small class="text-muted">Kategori dengan jumlah aset terbanyak beserta porsinya</small>
        </div>

        <div class="table-responsive">
          <table class="table table-modern align-middle mb-0" id="tableReportCategory">
            <thead>
              <tr>
                <th>Kategori</th>
                <th class="text-end" style="width: 140px;">Jumlah Aset</th>
                <th class="text-end" style="width: 100px;">Porsi</th>
              </tr>
            </thead>
            <!-- Baris diisi DataTables lewat reports.js; keadaan kosong ditangani DataTables. -->
            <tbody id="reportCategoryBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Ekspor dan cetak -->
    <div class="col-12 col-xl-5">
      <div class="card border-0 shadow-sm rounded-3 h-100">
        <div class="card-header bg-white py-3 px-4 border-bottom">
          <h6 class="fw-bold mb-0">
            <i class="bi bi-download me-2 text-primary" aria-hidden="true"></i>Ekspor &amp; Cetak
          </h6>
          <small class="text-muted">Unduh data dalam format CSV atau cetak laporan ringkas untuk arsip</small>
        </div>

        <div class="card-body p-4">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-sm-4">
              <label for="exportStatus" class="form-label small fw-semibold mb-1">Status aset</label>
              <select class="form-select form-select-sm" id="exportStatus">
                <option value="">Semua status</option>
                <option value="available">Tersedia</option>
                <option value="in_use">Dipakai</option>
                <option value="on_loan">Dipinjam</option>
                <option value="maintenance">Perbaikan</option>
                <option value="disposed">Afkir</option>
              </select>
            </div>

            <div class="col-12 col-sm-4">
              <label for="exportCategory" class="form-label small fw-semibold mb-1">Kategori</label>
              <select class="form-select form-select-sm" id="exportCategory">
                <option value="">Semua kategori</option>
              </select>
            </div>

            <div class="col-12 col-sm-4">
              <label for="exportLocation" class="form-label small fw-semibold mb-1">Lokasi</label>
              <select class="form-select form-select-sm" id="exportLocation">
                <option value="">Semua lokasi</option>
              </select>
            </div>
          </div>

          <p class="small text-muted mt-3 mb-3 text-wrap-pretty">
            Filter di atas hanya berlaku untuk ekspor aset. Berkas CSV langsung diunduh peramban.
          </p>

          <div class="d-flex flex-wrap gap-2 report-actions">
            <a class="btn btn-primary btn-sm px-3" id="exportAssetLink" href="/api/v1/reports/assets.csv">
              <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i> Ekspor Aset (CSV)
            </a>
            <a class="btn btn-light border btn-sm px-3" id="exportStockLink" href="/api/v1/reports/stock.csv">
              <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i> Ekspor Stok (CSV)
            </a>
            <a class="btn btn-light border btn-sm px-3" id="exportActivityLink" href="/api/v1/reports/activity.csv">
              <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i> Ekspor Audit Trail (CSV)
            </a>
            <a class="btn btn-light border btn-sm px-3" href="/reports/print">
              <i class="bi bi-printer me-1" aria-hidden="true"></i> Cetak Laporan
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk reports.js; endpoint laporan tidak butuh parameter awal.
  window.INVENTRA_REPORT = {};
</script>
