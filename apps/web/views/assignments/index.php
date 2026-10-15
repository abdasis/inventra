<?php
/**
 * Shell halaman Penempatan Aset. Halaman ini hanya kerangka: filter, wadah
 * error, tabel kosong, dan dua modal (catat penempatan dan pengembalian aset).
 * Baris tabel diisi assignments.js lewat /api/v1/assignments; keadaan kosong
 * ditangani DataTables.
 *
 * Tidak ada data yang dirender di sini: aset, karyawan, dan lokasi diambil
 * assignments.js lewat /api/v1/*.
 */

use Inventra\Core\Auth\Auth;

$loadAssignmentScript = true;
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
      <h5 class="fw-bold mb-0">Penempatan Aset</h5>
      <small class="text-muted">Catat aset yang diserahkan ke karyawan atau lokasi tertentu beserta pengembaliannya</small>
    </div>
    <?php if ($isAdmin): ?>
      <button type="button" class="btn btn-primary btn-sm px-3" id="btnAddAssignment">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Catat Penempatan
      </button>
    <?php endif; ?>
  </div>

  <div class="px-4 py-3 border-bottom">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-7 col-lg-5">
        <label for="assignmentSearch" class="form-label small fw-semibold mb-1">Cari penempatan</label>
        <input type="search" class="form-control form-control-sm" id="assignmentSearch" autocomplete="off"
               placeholder="Kode aset, nama aset, atau nama pemegang...">
      </div>

      <div class="col-12 col-md-5 col-lg-3">
        <label for="assignmentStatusFilter" class="form-label small fw-semibold mb-1">Status</label>
        <select class="form-select form-select-sm" id="assignmentStatusFilter">
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="returned">Dikembalikan</option>
        </select>
      </div>
    </div>

    <?php if (!$isAdmin): ?>
      <p class="small text-muted mb-0 mt-2">
        Akun staf hanya dapat melihat data penempatan aset. Pencatatan dan pengembalian dilakukan administrator.
      </p>
    <?php endif; ?>
  </div>

  <div id="assignmentError" role="alert" aria-live="assertive"></div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tableAssignment">
      <thead>
        <tr>
          <th>Aset</th>
          <th>Pemegang</th>
          <th>Lokasi</th>
          <th style="width: 150px;">Serah Terima</th>
          <th style="width: 200px;">Kondisi (Keluar &rarr; Kembali)</th>
          <th style="width: 140px;">Status</th>
          <th class="text-center" style="width: 210px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat assignments.js; keadaan kosong ditangani DataTables. -->
      <tbody id="assignmentTableBody"></tbody>
    </table>
  </div>
</div>

<!-- Modal catat penempatan (5 field: aset, pemegang, lokasi, kondisi keluar, catatan). -->
<div class="modal fade" id="assignmentModal" tabindex="-1" aria-labelledby="assignmentModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formAssignment">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="assignmentModalTitle">Catat Penempatan Aset</h5>
            <small class="text-muted">Aset akan berstatus dipakai sampai dicatat pengembaliannya</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-4">
          <div id="assignmentFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <div class="row g-3">
            <div class="col-12">
              <label for="input-asset_id" class="form-label small fw-semibold">
                Aset <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-asset_id" name="asset_id" required
                      aria-describedby="error-asset_id hint-asset_id"></select>
              <div class="text-danger small mt-1" id="error-asset_id" hidden></div>
              <small class="text-muted" id="hint-asset_id">Hanya aset berstatus tersedia yang dapat ditempatkan.</small>
            </div>

            <div class="col-12">
              <label for="input-holder" class="form-label small fw-semibold">
                Pemegang <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-holder" name="holder" required
                      aria-describedby="error-employee_id error-received_by_name hint-holder"></select>

              <div class="mt-2" id="holderNameWrapper" hidden>
                <label for="input-received_by_name" class="form-label small fw-semibold">Nama Penerima</label>
                <input type="text" class="form-control" id="input-received_by_name" name="received_by_name"
                       autocomplete="off" placeholder="Nama lengkap penerima" aria-describedby="error-received_by_name">
              </div>

              <div class="text-danger small mt-1" id="error-employee_id" hidden></div>
              <div class="text-danger small mt-1" id="error-received_by_name" hidden></div>
              <small class="text-muted" id="hint-holder">
                Pilih karyawan, atau pilih "Penerima lain" lalu isi namanya sendiri.
              </small>
            </div>

            <div class="col-12">
              <label for="input-location_id" class="form-label small fw-semibold">
                Lokasi Penempatan <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-location_id" name="location_id" required
                      aria-describedby="error-location_id hint-location_id"></select>
              <div class="text-danger small mt-1" id="error-location_id" hidden></div>
              <small class="text-muted" id="hint-location_id">Lokasi tempat aset akan digunakan.</small>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-condition_out" class="form-label small fw-semibold">Kondisi Saat Diserahkan</label>
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
              <label for="input-notes" class="form-label small fw-semibold">Catatan</label>
              <textarea class="form-control" id="input-notes" name="notes" rows="2"
                        aria-describedby="error-notes hint-notes"
                        placeholder="Keterangan penempatan (opsional)"></textarea>
              <div class="text-danger small mt-1" id="error-notes" hidden></div>
              <small class="text-muted" id="hint-notes">Opsional, isi bila ada kelengkapan atau kebutuhan khusus.</small>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary px-4" id="btnSubmitAssignment">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal pengembalian (3 field: lokasi kembali, kondisi masuk, catatan). -->
<div class="modal fade" id="assignmentReturnModal" tabindex="-1" aria-labelledby="assignmentReturnModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formAssignmentReturn">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="assignmentReturnModalTitle">Kembalikan Aset</h5>
            <small class="text-muted">Aset kembali berstatus tersedia setelah pengembalian dicatat</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-4">
          <div id="assignmentReturnFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <div class="alert alert-info py-2 small">
            Aset: <strong id="assignmentReturnAsset">-</strong>
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
          <button type="submit" class="btn btn-success px-4" id="btnSubmitAssignmentReturn">Catat Pengembalian</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk assignments.js.
  window.INVENTRA_ASSIGNMENT = <?= json_encode(
      ['isAdmin' => $isAdmin],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
