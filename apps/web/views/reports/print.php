<?php
/**
 * Laporan cetak inventaris. Artefak cetak, jadi dirender mandiri tanpa layout
 * dashboard mengikuti pola views/assets/label.php: HTML dan gayanya sendiri,
 * tanpa pustaka tampilan tambahan supaya hasil cetak bersih. Seluruh angka
 * diisi report-print.js dari /api/v1/reports/summary.
 *
 * Halaman mandiri tidak memuat layout/footer, jadi skripnya dimuat langsung di
 * berkas ini.
 */

$scriptVersion = (int) @filemtime(dirname(__DIR__, 2) . '/public/assets/js/report-print.js');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Inventaris Kantor — Inventra</title>
  <style>
    /* Nilai warna dan radius mengikuti token di style.css. */
    :root {
      --report-primary: #0f172a;
      --report-accent: #2563eb;
      --report-muted: #64748b;
      --report-border: #cbd5e1;
      --report-subtle: #f1f5f9;
      --report-ease: cubic-bezier(0.23, 1, 0.32, 1);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 1.5rem 1rem 3rem;
      background-color: var(--report-subtle);
      color: var(--report-primary);
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      font-size: 0.9rem;
      -webkit-font-smoothing: antialiased;
    }

    .report-toolbar {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.5rem;
      margin-bottom: 1.25rem;
    }

    .report-btn {
      border: 1px solid transparent;
      border-radius: 8px;
      padding: 0.55rem 1.1rem;
      font-size: 0.9rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      text-decoration: none;
      transition: transform 160ms var(--report-ease);
    }

    .report-btn-primary {
      background-color: var(--report-accent);
      color: #ffffff;
    }

    .report-btn-ghost {
      background-color: transparent;
      border-color: var(--report-border);
      color: #334155;
    }

    .report-btn:active {
      transform: scale(0.97);
    }

    .report-btn:focus-visible {
      outline: 2px solid var(--report-accent);
      outline-offset: 2px;
    }

    .report-error {
      max-width: 900px;
      margin: 0 auto 1.25rem;
      padding: 0.65rem 0.85rem;
      border: 1px solid #fecaca;
      border-radius: 8px;
      background: #fef2f2;
      color: #b91c1c;
      font-size: 0.85rem;
      text-wrap: pretty;
    }

    /* Lembar laporan: satu kolom terpusat, lebar nyaman dibaca saat dicetak. */
    .report-sheet {
      max-width: 900px;
      margin-inline: auto;
      padding: 2rem;
      background: #ffffff;
      border: 1px solid var(--report-border);
      border-radius: 12px;
    }

    .report-brand {
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--report-muted);
    }

    .report-title {
      margin: 0.35rem 0 0.25rem;
      font-size: 1.4rem;
      font-weight: 700;
      text-wrap: balance;
    }

    .report-subtitle {
      margin: 0;
      color: var(--report-muted);
      text-wrap: pretty;
    }

    .report-date {
      margin: 0.75rem 0 0;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--report-primary);
      font-size: 0.85rem;
      color: #334155;
      font-variant-numeric: tabular-nums;
    }

    .report-metrics {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 0.75rem;
      margin: 1.25rem 0 1.5rem;
    }

    .report-metric {
      padding: 0.6rem 0.75rem;
      border: 1px solid var(--report-border);
      border-radius: 8px;
    }

    .report-metric-label {
      display: block;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: var(--report-muted);
      text-wrap: pretty;
    }

    .report-metric-value {
      display: block;
      margin-top: 0.2rem;
      font-size: 1.1rem;
      font-weight: 700;
      font-variant-numeric: tabular-nums;
    }

    .report-block {
      margin-bottom: 1.5rem;
      break-inside: avoid;
    }

    .report-block h2 {
      margin: 0 0 0.5rem;
      padding-bottom: 0.35rem;
      border-bottom: 1px solid var(--report-border);
      font-size: 0.95rem;
      font-weight: 700;
      text-wrap: balance;
    }

    .report-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
    }

    .report-table th,
    .report-table td {
      padding: 0.45rem 0.6rem;
      border: 1px solid var(--report-border);
      text-align: left;
      vertical-align: top;
      text-wrap: pretty;
    }

    .report-table thead th {
      background-color: var(--report-subtle);
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #475569;
    }

    .report-num,
    .report-table td.report-num {
      text-align: right;
      font-variant-numeric: tabular-nums;
    }

    .report-total td {
      font-weight: 700;
      background-color: #f8fafc;
    }

    .report-signatures {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 2rem;
      margin-top: 2.5rem;
      break-inside: avoid;
    }

    .report-sign-label {
      margin: 0 0 3.5rem;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .report-sign-line {
      border-top: 1px solid var(--report-primary);
      padding-top: 0.35rem;
      font-size: 0.8rem;
      color: var(--report-muted);
      text-align: center;
    }

    @media (prefers-reduced-motion: reduce) {
      .report-btn {
        transition: none;
      }

      .report-btn:active {
        transform: none;
      }
    }

    /* Cetak: hanya lembar laporan yang tersisa, tanpa latar aplikasi. */
    @page {
      margin: 14mm;
    }

    @media print {
      body {
        padding: 0;
        background: #ffffff;
      }

      .report-sheet {
        max-width: none;
        padding: 0;
        border: 0;
        border-radius: 0;
      }

      .report-toolbar,
      .report-error {
        display: none !important;
      }

      .report-block h2 {
        break-after: avoid;
      }

      .report-table thead {
        display: table-header-group;
      }
    }
  </style>
</head>
<body>

  <div class="report-toolbar">
    <button type="button" class="report-btn report-btn-primary" id="btnPrintReport">Cetak</button>
    <a class="report-btn report-btn-ghost" href="/reports">Kembali ke laporan</a>
  </div>

  <p class="report-error" id="reportPrintError" role="alert" hidden></p>

  <main class="report-sheet" id="reportSheet" aria-busy="true">
    <header>
      <div class="report-brand">INVENTRA</div>
      <h1 class="report-title">Laporan Inventaris Kantor</h1>
      <p class="report-subtitle">Rekapitulasi aset, persediaan barang habis pakai, dan aktivitas inventaris</p>
      <p class="report-date">Tanggal cetak: <span id="reportPrintDate">-</span></p>
    </header>

    <section class="report-metrics" aria-label="Ringkasan inventaris">
      <div class="report-metric">
        <span class="report-metric-label">Total Aset</span>
        <span class="report-metric-value" id="metricTotalAssets">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Tersedia</span>
        <span class="report-metric-value" id="metricAvailable">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Digunakan</span>
        <span class="report-metric-value" id="metricInUse">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Dipinjam</span>
        <span class="report-metric-value" id="metricOnLoan">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Perbaikan</span>
        <span class="report-metric-value" id="metricMaintenance">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Dihapus</span>
        <span class="report-metric-value" id="metricDisposed">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Total Valuasi</span>
        <span class="report-metric-value" id="metricValuation">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Stok Rendah</span>
        <span class="report-metric-value" id="metricLowStock">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Peminjaman Aktif</span>
        <span class="report-metric-value" id="metricActiveLoans">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Biaya Pemeliharaan</span>
        <span class="report-metric-value" id="metricMaintenanceCost">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Penghapusan Tahun Ini</span>
        <span class="report-metric-value" id="metricDisposalsYear">-</span>
      </div>
      <div class="report-metric">
        <span class="report-metric-label">Aktivitas 7 Hari</span>
        <span class="report-metric-value" id="metricActivities">-</span>
      </div>
    </section>

    <section class="report-block">
      <h2>Rekapitulasi per Status</h2>
      <table class="report-table">
        <thead>
          <tr>
            <th>Status</th>
            <th class="report-num" style="width: 22%;">Jumlah</th>
            <th class="report-num" style="width: 22%;">Porsi</th>
          </tr>
        </thead>
        <tbody id="reportStatusBody">
          <tr><td colspan="3">Memuat data laporan...</td></tr>
        </tbody>
      </table>
    </section>

    <section class="report-block">
      <h2>Rekapitulasi Kategori Teratas</h2>
      <table class="report-table">
        <thead>
          <tr>
            <th>Kategori</th>
            <th class="report-num" style="width: 22%;">Jumlah Aset</th>
            <th class="report-num" style="width: 22%;">Porsi</th>
          </tr>
        </thead>
        <tbody id="reportCategoryBody">
          <tr><td colspan="3">Memuat data laporan...</td></tr>
        </tbody>
      </table>
    </section>

    <section class="report-block">
      <h2>Barang Habis Pakai Stok Rendah</h2>
      <table class="report-table">
        <thead>
          <tr>
            <th>Barang</th>
            <th class="report-num" style="width: 20%;">Saldo</th>
            <th class="report-num" style="width: 25%;">Stok Minimum</th>
          </tr>
        </thead>
        <tbody id="reportLowStockBody">
          <tr><td colspan="3">Memuat data laporan...</td></tr>
        </tbody>
      </table>
    </section>

    <section class="report-signatures">
      <div>
        <p class="report-sign-label">Disiapkan oleh</p>
        <div class="report-sign-line">Nama dan tanda tangan</div>
      </div>
      <div>
        <p class="report-sign-label">Disetujui oleh</p>
        <div class="report-sign-line">Nama dan tanda tangan</div>
      </div>
    </section>
  </main>

  <script>
    // Penanda versi dari waktu ubah berkas agar peramban tidak memakai salinan lama.
    window.INVENTRA_REPORT_PRINT = { summaryUrl: '/api/v1/reports/summary' };
  </script>
  <script src="/assets/js/report-print.js?v=<?= $scriptVersion ?>"></script>
</body>
</html>
