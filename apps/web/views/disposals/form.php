<?php
/**
 * Shell form catat dan ubah penghapusan aset. Field memakai fragmen bersama
 * partials/master-field.php supaya label, aria-describedby, dan wadah error per
 * field tidak diduplikasi antar halaman form. Dropdown aset diisi
 * disposal-form.js lewat /api/v1/assets.
 *
 * Penghapusan berfield banyak, jadi memakai halaman penuh, bukan modal.
 *
 * @var bool $isEdit
 * @var int|null $disposalId
 */

use Inventra\Core\Auth\Auth;
use Inventra\Web\Support\View;

$loadDisposalFormScript = true;
$isEdit = (bool)($isEdit ?? false);
$disposalId = $isEdit ? (int)($disposalId ?? 0) : null;
$isAdmin = Auth::isAdmin();

$sections = [
    'Identitas Aset' => [
        ['name' => 'asset_id', 'label' => 'Aset', 'type' => 'select', 'required' => true, 'source' => 'assets', 'emptyLabel' => 'Pilih aset', 'hint' => 'Aset yang dihapus, diambil dari daftar inventaris.'],
        ['name' => 'disposal_date', 'label' => 'Tanggal Penghapusan', 'type' => 'date'],
        // Label opsi sengaja sama persis dengan method_label dari API supaya
        // istilahnya tidak berbeda antara dropdown dan badge di daftar.
        ['name' => 'method', 'label' => 'Metode Penghapusan', 'type' => 'select', 'emptyLabel' => 'Belum ditentukan', 'options' => [
            ['value' => 'sold', 'label' => 'Dijual'],
            ['value' => 'donated', 'label' => 'Disumbangkan'],
            ['value' => 'destroyed', 'label' => 'Dimusnahkan'],
            ['value' => 'written_off', 'label' => 'Dihapus dari Pembukuan'],
        ]],
        ['name' => 'book_value', 'label' => 'Nilai Buku (Rp)', 'type' => 'number', 'min' => '0', 'step' => '1000', 'placeholder' => '0', 'hint' => 'Nilai tercatat aset saat dihapus.'],
    ],
    'Dasar Penghapusan' => [
        ['name' => 'reason', 'label' => 'Alasan Penghapusan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3, 'required' => true, 'placeholder' => 'Kerusakan berat, kehilangan, atau kebijakan penghapusan aset'],
        ['name' => 'document_reference', 'label' => 'Dokumen Pendukung', 'type' => 'text', 'placeholder' => 'Nomor berita acara atau surat keputusan'],
        ['name' => 'approved_by_name', 'label' => 'Disetujui Oleh', 'type' => 'text', 'placeholder' => 'Nama pejabat yang menyetujui'],
        ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2, 'placeholder' => 'Keterangan tambahan (opsional)'],
    ],
];
?>
<?php if (!$isAdmin): ?>
  <div class="row justify-content-center">
    <div class="col-12 col-xl-9">
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
          <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
            <span>Hanya administrator yang dapat mencatat penghapusan aset. Kembali ke daftar untuk melihat data yang tersedia.</span>
          </div>
          <a href="/disposals" class="btn btn-outline-secondary px-4">Kembali ke daftar</a>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row justify-content-center">
    <div class="col-12 col-xl-9">
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom">
          <h5 class="fw-bold mb-0"><?= $isEdit ? 'Ubah Penghapusan' : 'Catat Penghapusan Aset' ?></h5>
          <small class="text-muted">Penghapusan mengeluarkan aset dari daftar inventaris aktif</small>
        </div>

        <div class="card-body p-4">
          <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
            <span>
              <strong>Penghapusan bersifat final.</strong> Aset yang dihapus tidak dapat dipakai pada transaksi
              baru (penempatan, peminjaman, mutasi, maupun pemeliharaan). Pastikan berita acara dan persetujuannya
              sudah lengkap sebelum menyimpan.
            </span>
          </div>

          <div id="disposalFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <form id="formDisposal" data-mode="<?= $isEdit ? 'edit' : 'create' ?>">
            <input type="hidden" name="id" id="disposalId" value="<?= $disposalId !== null ? (int)$disposalId : '' ?>">

            <div class="row g-3">
              <?php foreach ($sections as $sectionTitle => $fields): ?>
                <div class="col-12"><h6 class="fw-bold mb-0 text-uppercase small"><?= View::e((string)$sectionTitle) ?></h6></div>
                <?php foreach ($fields as $field): ?>
                  <?php require __DIR__ . '/../partials/master-field.php'; ?>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>

            <div class="form-actions d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
              <a href="/disposals" class="btn btn-outline-secondary px-4">Batal</a>
              <button type="submit" class="btn btn-danger px-4" id="btnSubmitDisposal">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Penghapusan' ?>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  // Konfigurasi halaman untuk disposal-form.js.
  window.INVENTRA_DISPOSAL_FORM = <?= json_encode(
      [
          'mode' => $isEdit ? 'edit' : 'create',
          'id' => $disposalId,
          'isAdmin' => $isAdmin,
      ],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
