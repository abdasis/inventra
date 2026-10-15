<?php
/**
 * Shell halaman Peminjaman Aset. Halaman ini hanya kerangka: filter, wadah
 * error, tabel kosong, dan dua modal (catat peminjaman dan pengembalian aset).
 * Baris tabel diisi loans.js lewat /api/v1/loans; keadaan kosong ditangani
 * DataTables.
 *
 * Tidak ada data yang dirender di sini: aset, karyawan, dan lokasi diambil
 * loans.js lewat /api/v1/*.
 */

use Inventra\Core\Auth\Auth;

$loadLoanScript = true;
$isAdmin = Auth::isAdmin();

// Label kondisi mengikuti Enum AssetCondition milik API agar pilihan di modal
// sama bunyinya dengan condition_out_label pada tabel.
$conditionOptions = [
    'new' => 'Sangat Baik (Baru)',
    'good' => 'Baik (Normal)',
    'fair' => 'Cukup (Ada Aus)',
    'damaged' => 'Rusak',
];
?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="fw-bold mb-0">Peminjaman Aset</h5>
      <small class="text-muted">Pantau aset yang dipinjam beserta tenggat dan pengembaliannya</small>
    </div>
    <button type="button" class="btn btn-primary btn-sm px-3" id="btnAddLoan">
      <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Catat Peminjaman
    </button>
  </div>

  <div class="px-4 py-3 border-bottom">
    <div class="row g-3 align-items-end">
      <div class="col-12 col-md-5">
        <label for="loanSearch" class="form-label small fw-semibold mb-1">Cari peminjaman</label>
        <input type="search" class="form-control form-control-sm" id="loanSearch" autocomplete="off"
               placeholder="Kode aset, nama aset, atau nama peminjam...">
      </div>

      <div class="col-6 col-md-3">
        <label for="loanStatusFilter" class="form-label small fw-semibold mb-1">Status</label>
        <select class="form-select form-select-sm" id="loanStatusFilter">
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="returned">Dikembalikan</option>
        </select>
      </div>

      <div class="col-6 col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="loanOverdueOnly">
          <label class="form-check-label small fw-semibold" for="loanOverdueOnly">Hanya yang terlambat</label>
        </div>
        <small class="text-muted">Peminjaman aktif yang sudah melewati jatuh tempo.</small>
      </div>
    </div>

    <p class="small text-muted mb-0 mt-2">
      Peminjaman dan pengembalian dapat dicatat seluruh pengguna. Hanya aset berstatus tersedia yang dapat dipinjam.
    </p>
  </div>

  <div id="loanError" role="alert" aria-live="assertive"></div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tableLoan">
      <thead>
        <tr>
          <th>Aset</th>
          <th>Peminjam</th>
          <th style="width: 160px;">Jatuh Tempo</th>
          <th style="width: 140px;">Tanggal Pinjam</th>
          <th style="width: 140px;">Tanggal Kembali</th>
          <th style="width: 200px;">Kondisi (Keluar &rarr; Kembali)</th>
          <th style="width: 140px;">Status</th>
          <th class="text-center" style="width: 210px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat loans.js; keadaan kosong ditangani DataTables. -->
      <tbody id="loanTableBody"></tbody>
    </table>
  </div>
</div>

<!-- Modal catat peminjaman (5 field: aset, peminjam, jatuh tempo, kondisi keluar, tujuan). -->
<div class="modal fade" id="loanModal" tabindex="-1" aria-labelledby="loanModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formLoan">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="loanModalTitle">Catat Peminjaman Aset</h5>
            <small class="text-muted">Aset akan berstatus dipinjam sampai dicatat pengembaliannya</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-4">
          <div id="loanFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <div class="row g-3">
            <div class="col-12">
              <label for="input-asset_id" class="form-label small fw-semibold">
                Aset <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-asset_id" name="asset_id" required
                      aria-describedby="error-asset_id hint-asset_id"></select>
              <div class="text-danger small mt-1" id="error-asset_id" hidden></div>
              <small class="text-muted" id="hint-asset_id">Hanya aset berstatus tersedia yang dapat dipinjam.</small>
            </div>

            <div class="col-12">
              <label for="input-holder" class="form-label small fw-semibold">
                Peminjam <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-holder" name="holder" required
                      aria-describedby="error-employee_id error-borrower_name hint-holder"></select>

              <div class="mt-2" id="holderNameWrapper" hidden>
                <label for="input-borrower_name" class="form-label small fw-semibold">Nama Peminjam</label>
                <input type="text" class="form-control" id="input-borrower_name" name="borrower_name"
                       autocomplete="off" placeholder="Nama lengkap peminjam" aria-describedby="error-borrower_name">
              </div>

              <div class="text-danger small mt-1" id="error-employee_id" hidden></div>
              <div class="text-danger small mt-1" id="error-borrower_name" hidden></div>
              <small class="text-muted" id="hint-holder">
                Pilih karyawan, atau pilih "Peminjam lain" lalu isi namanya sendiri.
              </small>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-due_at" class="form-label small fw-semibold">Jatuh Tempo</label>
              <input type="date" class="form-control" id="input-due_at" name="due_at"
                     aria-describedby="error-due_at hint-due_at">
              <div class="text-danger small mt-1" id="error-due_at" hidden></div>
              <small class="text-muted" id="hint-due_at">Opsional, kosongkan bila tanpa tenggat.</small>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-condition_out" class="form-label small fw-semibold">Kondisi Saat Dipinjam</label>
              <select class="form-select" id="input-condition_out" name="condition_out"
                      aria-describedby="error-condition_out hint-condition_out">
                <option value="">Tidak dicatat</option>
                <?php foreach ($conditionOptions as $value => $label): ?>
                  <option value="<?= $value ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
              <div class="text-danger small mt-1" id="error-condition_out" hidden></div>
              <small class="text-muted" id="hint-condition_out">Opsional.</small>
            </div>

            <div class="col-12">
              <label for="input-purpose" class="form-label small fw-semibold">Tujuan Peminjaman</label>
              <textarea class="form-control" id="input-purpose" name="purpose" rows="2"
                        aria-describedby="error-purpose hint-purpose"
                        placeholder="Keperluan peminjaman, mis. rapat luar kantor"></textarea>
              <div class="text-danger small mt-1" id="error-purpose" hidden></div>
              <small class="text-muted" id="hint-purpose">Opsional, memudahkan penelusuran pemakaian aset.</small>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary px-4" id="btnSubmitLoan">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal pengembalian (3 field: lokasi kembali, kondisi masuk, catatan). -->
<div class="modal fade" id="loanReturnModal" tabindex="-1" aria-labelledby="loanReturnModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formLoanReturn">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="loanReturnModalTitle">Kembalikan Aset</h5>
            <small class="text-muted">Aset kembali berstatus tersedia setelah pengembalian dicatat</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-4">
          <div id="loanReturnFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <div class="alert alert-info py-2 small">
            Aset: <strong id="loanReturnAsset">-</strong>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <label for="input-return_location_id" class="form-label small fw-semibold">
                Kembalikan ke Lokasi <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-return_location_id" name="return_location_id" required
                      aria-describedby="error-return_location_id"></select>
              <div class="text-danger small mt-1" id="error-return_location_id" hidden></div>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-condition_in" class="form-label small fw-semibold">Kondisi Saat Dikembalikan</label>
              <select class="form-select" id="input-condition_in" name="condition_in"
                      aria-describedby="error-condition_in hint-condition_in">
                <option value="">Tidak dicatat</option>
                <?php foreach ($conditionOptions as $value => $label): ?>
                  <option value="<?= $value ?>"><?= $label ?></option>
                <?php endforeach; ?>
              </select>
              <div class="text-danger small mt-1" id="error-condition_in" hidden></div>
              <small class="text-muted" id="hint-condition_in">Opsional, catat bila kondisinya berubah.</small>
            </div>

            <div class="col-12">
              <label for="input-return_notes" class="form-label small fw-semibold">Catatan Pengembalian</label>
              <textarea class="form-control" id="input-return_notes" name="notes" rows="2"
                        aria-describedby="error-return_notes"
                        placeholder="Kelengkapan atau kerusakan saat dikembalikan (opsional)"></textarea>
              <div class="text-danger small mt-1" id="error-return_notes" hidden></div>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success px-4" id="btnSubmitLoanReturn">Catat Pengembalian</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk loans.js. isAdmin disuntik untuk keseragaman
  // konfigurasi halaman; pencatatan peminjaman terbuka bagi seluruh pengguna.
  window.INVENTRA_LOAN = <?= json_encode(
      ['isAdmin' => $isAdmin],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
