<?php
/**
 * Shell halaman Mutasi Aset. Halaman ini hanya kerangka: filter, wadah error,
 * tabel kosong, dan dua modal (catat mutasi dan pembatalannya). Baris tabel
 * diisi transfers.js lewat /api/v1/transfers; keadaan kosong ditangani DataTables.
 *
 * Tidak ada data yang dirender di sini: aset dan lokasi diambil transfers.js
 * lewat /api/v1/*.
 */

use Inventra\Core\Auth\Auth;

$loadTransferScript = true;
$isAdmin = Auth::isAdmin();
?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="fw-bold mb-0">Mutasi Aset</h5>
      <small class="text-muted">Catat perpindahan aset antar lokasi beserta penanggung jawab tujuan barunya</small>
    </div>
    <?php if ($isAdmin): ?>
      <button type="button" class="btn btn-primary btn-sm px-3" id="btnAddTransfer">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Catat Mutasi
      </button>
    <?php endif; ?>
  </div>

  <div class="px-4 py-3 border-bottom">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-7 col-lg-5">
        <label for="transferSearch" class="form-label small fw-semibold mb-1">Cari mutasi</label>
        <input type="search" class="form-control form-control-sm" id="transferSearch" autocomplete="off"
               placeholder="Kode aset, nama aset, atau lokasi tujuan...">
      </div>

      <div class="col-12 col-md-5 col-lg-3">
        <label for="transferStatusFilter" class="form-label small fw-semibold mb-1">Status</label>
        <select class="form-select form-select-sm" id="transferStatusFilter">
          <option value="">Semua Status</option>
          <option value="pending">Menunggu</option>
          <option value="completed">Selesai</option>
          <option value="cancelled">Dibatalkan</option>
        </select>
      </div>
    </div>

    <?php if (!$isAdmin): ?>
      <p class="small text-muted mb-0 mt-2">
        Akun staf hanya dapat melihat data mutasi aset. Pencatatan, penyelesaian, dan pembatalan dilakukan administrator.
      </p>
    <?php endif; ?>
  </div>

  <div id="transferError" role="alert" aria-live="assertive"></div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tableTransfer">
      <thead>
        <tr>
          <th>Aset</th>
          <th style="min-width: 220px;">Dari &rarr; Ke Lokasi</th>
          <th>Penanggung Jawab Tujuan</th>
          <th style="width: 130px;">Tanggal</th>
          <th style="min-width: 200px;">Alasan</th>
          <th style="width: 140px;">Status</th>
          <th class="text-center" style="width: 220px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat transfers.js; keadaan kosong ditangani DataTables. -->
      <tbody id="transferTableBody"></tbody>
    </table>
  </div>
</div>

<!-- Modal catat mutasi (5 field: aset, lokasi tujuan, tanggal, alasan, catatan). -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formTransfer">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="transferModalTitle">Catat Mutasi Aset</h5>
            <small class="text-muted">Mutasi berstatus menunggu sampai dicatat penyelesaiannya</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-4">
          <div id="transferFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <div class="row g-3">
            <div class="col-12">
              <label for="input-asset_id" class="form-label small fw-semibold">
                Aset <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-asset_id" name="asset_id" required
                      aria-describedby="error-asset_id hint-asset_id"></select>
              <div class="text-danger small mt-1" id="error-asset_id" hidden></div>
              <small class="text-muted" id="hint-asset_id">Aset yang dipindahkan beserta lokasi asalnya.</small>
            </div>

            <div class="col-12">
              <label for="input-to_location_id" class="form-label small fw-semibold">
                Lokasi Tujuan <span class="text-danger" aria-hidden="true">*</span>
              </label>
              <select class="form-select" id="input-to_location_id" name="to_location_id" required
                      aria-describedby="error-to_location_id hint-to_location_id"></select>
              <div class="text-danger small mt-1" id="error-to_location_id" hidden></div>
              <small class="text-muted" id="hint-to_location_id">Lokasi baru tempat aset akan digunakan.</small>
            </div>

            <div class="col-12 col-md-6">
              <label for="input-transfer_date" class="form-label small fw-semibold">Tanggal Mutasi</label>
              <input type="date" class="form-control" id="input-transfer_date" name="transfer_date"
                     aria-describedby="error-transfer_date hint-transfer_date">
              <div class="text-danger small mt-1" id="error-transfer_date" hidden></div>
              <small class="text-muted" id="hint-transfer_date">Kosongkan untuk memakai tanggal hari ini.</small>
            </div>

            <div class="col-12">
              <label for="input-reason" class="form-label small fw-semibold">Alasan Mutasi</label>
              <textarea class="form-control" id="input-reason" name="reason" rows="2"
                        aria-describedby="error-reason hint-reason"
                        placeholder="Keperluan pemindahan, mis. penataan ruang kerja"></textarea>
              <div class="text-danger small mt-1" id="error-reason" hidden></div>
              <small class="text-muted" id="hint-reason">Opsional, memudahkan penelusuran riwayat perpindahan.</small>
            </div>

            <div class="col-12">
              <label for="input-notes" class="form-label small fw-semibold">Catatan</label>
              <textarea class="form-control" id="input-notes" name="notes" rows="2"
                        aria-describedby="error-notes hint-notes"
                        placeholder="Keterangan tambahan (opsional)"></textarea>
              <div class="text-danger small mt-1" id="error-notes" hidden></div>
              <small class="text-muted" id="hint-notes">Opsional, isi bila ada kelengkapan atau kebutuhan khusus.</small>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary px-4" id="btnSubmitTransfer">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal pembatalan mutasi (1 field: alasan pembatalan). -->
<div class="modal fade" id="transferCancelModal" tabindex="-1" aria-labelledby="transferCancelModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formTransferCancel">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="transferCancelModalTitle">Batalkan Mutasi</h5>
            <small class="text-muted">Mutasi dibatalkan dan aset tetap berada di lokasi asalnya</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body p-4">
          <div id="transferCancelFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <div class="alert alert-warning py-2 small">
            Aset: <strong id="transferCancelAsset">-</strong>
          </div>

          <label for="input-cancel_reason" class="form-label small fw-semibold">
            Alasan Pembatalan <span class="text-danger" aria-hidden="true">*</span>
          </label>
          <textarea class="form-control" id="input-cancel_reason" name="reason" rows="3" required
                    aria-describedby="error-cancel_reason"
                    placeholder="Sebab mutasi dibatalkan"></textarea>
          <div class="text-danger small mt-1" id="error-cancel_reason" hidden></div>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-danger px-4" id="btnSubmitTransferCancel">Batalkan Mutasi</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk transfers.js.
  window.INVENTRA_TRANSFER = <?= json_encode(
      ['isAdmin' => $isAdmin],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
