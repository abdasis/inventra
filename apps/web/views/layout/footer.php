    </div>
  </main>

  <!-- Modal Tambah / Edit Aset -->
  <div class="modal fade" id="assetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form id="formAsset">
          <input type="hidden" name="id" id="assetId">
          <div class="modal-header">
            <h5 class="modal-title fw-bold" id="assetModalTitle">Tambah Aset Baru</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Kode Aset</label>
                <input type="text" class="form-control" name="asset_code" id="inputAssetCode" placeholder="Otomatis jika kosong">
                <small class="text-muted">Contoh: AST-IT-2026-001</small>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Nama Barang / Aset <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" id="inputAssetName" placeholder="Nama lengkap spesifikasi aset" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
                <select class="form-select" name="category_id" id="selectCategory" required></select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Lokasi Penempatan <span class="text-danger">*</span></label>
                <select class="form-select" name="location_id" id="selectLocation" required></select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Nomor Seri (Serial Number)</label>
                <input type="text" class="form-control" name="serial_number" id="inputSerialNumber" placeholder="S/N pabrikan">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Tanggal Pembelian</label>
                <input type="date" class="form-control" name="purchase_date" id="inputPurchaseDate">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Nilai Perolehan / Harga (Rp)</label>
                <input type="number" step="1000" class="form-control" name="purchase_cost" id="inputPurchaseCost" placeholder="0">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Kondisi Fisik</label>
                <select class="form-select" name="condition_level" id="selectCondition">
                  <option value="new">Sangat Baik (Baru)</option>
                  <option value="good" selected>Baik (Normal)</option>
                  <option value="fair">Cukup (Ada Aus)</option>
                  <option value="damaged">Rusak</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Catatan Tambahan / Kelengkapan</label>
                <textarea class="form-control" rows="2" name="notes" id="inputNotes" placeholder="Aksesoris, garansi, kondisi fisik khusus..."></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary px-4" id="btnSaveAsset">Simpan Data</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Serah Terima / Check-Out -->
  <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="formCheckout">
          <input type="hidden" name="asset_id" id="checkoutAssetId">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">Serah Terima / Pinjam Aset</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
              <label class="form-label small fw-semibold">Petugas Penyerah</label>
              <input type="text" class="form-control" name="actor_name" value="Admin GA" required>
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
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
              <label class="form-label small fw-semibold">Petugas Penerima</label>
              <input type="text" class="form-control" name="actor_name" value="Admin GA" required>
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

  <!-- Toast Container -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="liveToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body" id="toastMessage">Operasi berhasil dilakukan.</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>

  <!-- jQuery 3.7.1 (Latest Official) -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>

  <!-- Bootstrap 5.3.8 JS Bundle (Popper included via Context7) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

  <!-- Inventra Frontend Logic -->
  <script src="/assets/js/app.js"></script>
</body>
</html>
