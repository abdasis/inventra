<?php
/**
 * Shell form catat dan ubah pemeliharaan. Field memakai fragmen bersama
 * partials/master-field.php supaya label, aria-describedby, dan wadah error per
 * field tidak diduplikasi antar halaman form. Dropdown aset diisi
 * maintenance-form.js lewat /api/v1/assets.
 *
 * Pemeliharaan berfield banyak, jadi memakai halaman penuh, bukan modal.
 *
 * @var bool $isEdit
 * @var int|null $maintenanceId
 */

use Inventra\Core\Auth\Auth;
use Inventra\Web\Support\View;

$loadMaintenanceFormScript = true;
$isEdit = (bool)($isEdit ?? false);
$maintenanceId = $isEdit ? (int)($maintenanceId ?? 0) : null;
$isAdmin = Auth::isAdmin();

$sections = [
    'Identitas Pemeliharaan' => [
        ['name' => 'asset_id', 'label' => 'Aset', 'type' => 'select', 'required' => true, 'source' => 'assets', 'emptyLabel' => 'Pilih aset', 'hint' => 'Aset yang dirawat, diambil dari daftar inventaris.'],
        // Label opsi sengaja sama persis dengan maintenance_type_label dari API
        // supaya istilahnya tidak berbeda antara dropdown dan badge di daftar.
        ['name' => 'maintenance_type', 'label' => 'Jenis Perawatan', 'type' => 'select', 'default' => 'preventive', 'options' => [
            ['value' => 'preventive', 'label' => 'Preventif'],
            ['value' => 'corrective', 'label' => 'Korektif'],
            ['value' => 'inspection', 'label' => 'Inspeksi'],
        ], 'hint' => 'Preventif untuk perawatan berkala, korektif untuk perbaikan kerusakan.'],
        ['name' => 'maintenance_date', 'label' => 'Tanggal Pemeliharaan', 'type' => 'date', 'required' => true],
        ['name' => 'vendor', 'label' => 'Vendor / Teknisi', 'type' => 'text', 'placeholder' => 'Nama bengkel, vendor, atau teknisi internal'],
    ],
    'Detail Pekerjaan' => [
        ['name' => 'cost', 'label' => 'Biaya Pemeliharaan (Rp)', 'type' => 'number', 'min' => '0', 'step' => '1000', 'placeholder' => '0', 'hint' => 'Perkiraan atau biaya yang sudah dikeluarkan.'],
        ['name' => 'damage_description', 'label' => 'Deskripsi Kerusakan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3, 'placeholder' => 'Gejala kerusakan atau pekerjaan yang akan dilakukan'],
        ['name' => 'next_schedule', 'label' => 'Jadwal Berikutnya', 'type' => 'date', 'hint' => 'Opsional, untuk pemeliharaan berkala.'],
        ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2, 'placeholder' => 'Suku cadang, nomor surat tugas, atau keterangan lain'],
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
            <span>Hanya administrator yang dapat mencatat pemeliharaan aset. Kembali ke daftar untuk melihat data yang tersedia.</span>
          </div>
          <a href="/maintenances" class="btn btn-outline-secondary px-4">Kembali ke daftar</a>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row justify-content-center">
    <div class="col-12 col-xl-9">
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom">
          <h5 class="fw-bold mb-0"><?= $isEdit ? 'Ubah Pemeliharaan' : 'Catat Pemeliharaan' ?></h5>
          <small class="text-muted">Isi identitas pekerjaan, lalu lengkapi detailnya; aset berstatus perbaikan sampai pemeliharaan selesai</small>
        </div>

        <div class="card-body p-4">
          <div id="maintenanceFormError" class="mb-3" role="alert" aria-live="assertive"></div>

          <form id="formMaintenance" data-mode="<?= $isEdit ? 'edit' : 'create' ?>">
            <input type="hidden" name="id" id="maintenanceId" value="<?= $maintenanceId !== null ? (int)$maintenanceId : '' ?>">

            <div class="row g-3">
              <?php foreach ($sections as $sectionTitle => $fields): ?>
                <div class="col-12"><h6 class="fw-bold mb-0 text-uppercase small"><?= View::e((string)$sectionTitle) ?></h6></div>
                <?php foreach ($fields as $field): ?>
                  <?php require __DIR__ . '/../partials/master-field.php'; ?>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>

            <div class="form-actions d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
              <a href="/maintenances" class="btn btn-outline-secondary px-4">Batal</a>
              <button type="submit" class="btn btn-primary px-4" id="btnSubmitMaintenance">
                <i class="bi bi-check-lg me-1" aria-hidden="true"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Pemeliharaan' ?>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  // Konfigurasi halaman untuk maintenance-form.js.
  window.INVENTRA_MAINTENANCE_FORM = <?= json_encode(
      [
          'mode' => $isEdit ? 'edit' : 'create',
          'id' => $maintenanceId,
          'isAdmin' => $isAdmin,
      ],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
