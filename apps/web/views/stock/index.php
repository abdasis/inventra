<?php
/**
 * Shell halaman Stok Barang Habis Pakai. Dua tabel (saldo stok dan riwayat
 * pergerakan) diisi stock.js dari API; halaman ini hanya menyediakan kerangka
 * kartu, filter, dan satu modal pencatatan pergerakan.
 *
 * Tidak ada data yang dirender di sini: kategori, barang, dan karyawan diambil
 * stock.js lewat /api/v1/*.
 */

$loadStockScript = true;
?>
<div id="stockPage">
  <!-- Kartu saldo stok barang habis pakai -->
  <div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h5 class="fw-bold mb-0">Saldo Stok Barang Habis Pakai</h5>
        <small class="text-muted">Pantau persediaan barang habis pakai beserta pergerakan masuk, keluar, dan koreksinya</small>
      </div>
      <button type="button" class="btn btn-primary btn-sm px-3" id="btnAddMovement">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Catat Pergerakan Stok
      </button>
    </div>

    <div class="px-4 py-3 border-bottom">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-5">
          <label for="stockSearch" class="form-label small fw-semibold mb-1">Cari barang</label>
          <input type="search" class="form-control form-control-sm" id="stockSearch" autocomplete="off"
                 placeholder="Kode atau nama barang...">
        </div>

        <div class="col-6 col-md-3">
          <label for="stockCategory" class="form-label small fw-semibold mb-1">Kategori</label>
          <select class="form-select form-select-sm" id="stockCategory">
            <option value="">Semua kategori</option>
          </select>
        </div>

        <div class="col-6 col-md-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="stockLowOnly">
            <label class="form-check-label small fw-semibold" for="stockLowOnly">Hanya stok rendah</label>
          </div>
          <small class="text-muted">Barang yang saldonya menyentuh atau di bawah stok minimum.</small>
        </div>
      </div>
    </div>

    <div id="stockError" role="alert" aria-live="assertive"></div>

    <div class="table-responsive">
      <table class="table table-modern table-hover align-middle mb-0" id="tableStock">
        <thead>
          <tr>
            <th>Kode &amp; Nama Barang</th>
            <th>Kategori</th>
            <th>Satuan</th>
            <th class="text-end" style="width: 170px;">Saldo</th>
            <th class="text-end" style="width: 140px;">Stok Minimum</th>
            <th style="width: 160px;">Diperbarui</th>
            <th class="text-center" style="width: 260px;">Aksi</th>
          </tr>
        </thead>
        <!-- Baris diisi DataTables lewat stock.js; keadaan kosong ditangani DataTables. -->
        <tbody id="stockTableBody"></tbody>
      </table>
    </div>
  </div>

  <!-- Kartu riwayat pergerakan stok -->
  <div class="card border-0 shadow-sm rounded-3 mb-4" id="stockMovementSection">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h6 class="fw-bold mb-0">
          <i class="bi bi-clock-history me-2 text-primary" aria-hidden="true"></i>Riwayat Pergerakan Stok
        </h6>
        <small class="text-muted" id="stockMovementHint">Seluruh barang habis pakai</small>
      </div>

      <div class="d-flex flex-wrap align-items-center gap-2">
        <label for="stockMovementType" class="visually-hidden">Saring jenis pergerakan</label>
        <select class="form-select form-select-sm" id="stockMovementType" style="width: 11rem;">
          <option value="">Semua jenis</option>
          <option value="in">Masuk</option>
          <option value="out">Keluar</option>
          <option value="adjustment">Koreksi</option>
        </select>
        <button type="button" class="btn btn-light border btn-sm" id="btnResetMovementFilter" hidden>
          <i class="bi bi-x-lg me-1" aria-hidden="true"></i> Semua barang
        </button>
      </div>
    </div>

    <div id="stockMovementError" role="alert" aria-live="assertive"></div>

    <div class="table-responsive">
      <table class="table table-sm table-modern align-middle mb-0" id="tableStockMovement">
        <thead>
          <tr>
            <th style="width: 160px;">Waktu</th>
            <th>Barang</th>
            <th style="width: 130px;">Jenis</th>
            <th class="text-end" style="width: 90px;">Jumlah</th>
            <th class="text-end" style="width: 120px;">Saldo Setelah</th>
            <th>Departemen / Penerima</th>
            <th>Alasan / Catatan</th>
            <th style="width: 140px;">Petugas</th>
          </tr>
        </thead>
        <tbody id="stockMovementTableBody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal pencatatan pergerakan: satu modal untuk masuk, keluar, dan koreksi. -->
<div class="modal fade" id="stockMovementModal" tabindex="-1" aria-labelledby="stockMovementModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formStockMovement">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="stockMovementModalTitle">Catat Pergerakan Stok</h5>
            <small class="text-muted">Pilih jenis pergerakan; koreksi saldo wajib disertai alasan</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-4">
          <div id="stockMovementFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <div class="row g-3">
            <div class="col-12">
              <label for="input-item_id" class="form-label small fw-semibold">
                Barang <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-item_id" name="item_id" required aria-describedby="error-item_id"></select>
              <div class="text-danger small mt-1" id="error-item_id" hidden></div>
              <small class="text-muted">Hanya barang berjenis barang habis pakai yang dapat dicatat.</small>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-movement_type" class="form-label small fw-semibold">
                Jenis Pergerakan <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-movement_type" name="movement_type" required aria-describedby="error-movement_type">
                <option value="in">Masuk</option>
                <option value="out">Keluar</option>
                <option value="adjustment">Koreksi Saldo</option>
              </select>
              <div class="text-danger small mt-1" id="error-movement_type" hidden></div>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-quantity" class="form-label small fw-semibold">
                Jumlah <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <input type="number" class="form-control" id="input-quantity" name="quantity" min="1" step="1"
                     inputmode="numeric" required aria-describedby="error-quantity">
              <div class="text-danger small mt-1" id="error-quantity" hidden></div>
              <small class="text-muted">Jumlah unit, bilangan bulat lebih dari nol.</small>
            </div>

            <div class="col-12">
              <label for="input-recipient" class="form-label small fw-semibold">Departemen / Penerima</label>
              <select class="form-select" id="input-recipient" name="recipient" aria-describedby="error-recipient"></select>
              <div class="text-danger small mt-1" id="error-recipient" hidden></div>
              <small class="text-muted" id="hint-recipient">Opsional, untuk pengeluaran per departemen atau ke karyawan tertentu.</small>
            </div>

            <div class="col-12">
              <label for="input-notes" class="form-label small fw-semibold" id="label-notes">Alasan / Catatan</label>
              <textarea class="form-control" id="input-notes" name="notes" rows="2"
                        aria-describedby="error-notes" placeholder="Catatan pergerakan (opsional)"></textarea>
              <div class="text-danger small mt-1" id="error-notes" hidden></div>
              <small class="text-muted" id="hint-notes">Opsional, isi bila ada keterangan penting.</small>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary px-4" id="btnSubmitMovement">Catat</button>
        </div>
      </form>
    </div>
  </div>
</div>
