<?php
/**
 * Fragmen halaman form master data (barang, karyawan, supplier): kartu, grid
 * field dari master-field.php, tombol simpan/batal, dan konfigurasi halaman
 * untuk master-form.js. Data dan pengiriman ditangani skrip tersebut.
 *
 * @var string $entity
 * @var string $label
 * @var string $subtitle
 * @var array<int, array<string, mixed>> $fields
 * @var bool|null $isEdit
 * @var int|null $entityId
 */

use Inventra\Web\Support\View;

$loadMasterFormScript = true;
$isEdit = (bool)($isEdit ?? false);
$entityId = $isEdit ? (int)($entityId ?? 0) : null;
?>
<div class="row justify-content-center">
  <div class="col-12 col-xl-9">
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-header bg-white py-3 px-4 border-bottom">
        <h5 class="fw-bold mb-0"><?= $isEdit ? 'Ubah ' : 'Tambah ' ?><?= View::e($label) ?></h5>
        <small class="text-muted"><?= View::e($subtitle) ?></small>
      </div>

      <div class="card-body p-4">
        <div id="masterFormError" class="mb-3" role="alert" aria-live="assertive"></div>

        <form id="formMaster" data-mode="<?= $isEdit ? 'edit' : 'create' ?>">
          <input type="hidden" name="id" id="masterId" value="<?= $entityId !== null ? (int)$entityId : '' ?>">

          <div class="row g-3">
            <?php foreach ($fields as $field): ?>
              <?php require __DIR__ . '/master-field.php'; ?>
            <?php endforeach; ?>
          </div>

          <div class="form-actions d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
            <a href="/master/<?= View::e($entity) ?>" class="btn btn-outline-secondary px-4">Batal</a>
            <button type="submit" class="btn btn-primary px-4" id="btnSubmitMaster">
              <i class="bi bi-check-lg me-1" aria-hidden="true"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan ' . View::e($label) ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk master-form.js.
  window.INVENTRA_MASTER = <?= json_encode(
      [
          'entity' => $entity,
          'label' => $label,
          'mode' => $isEdit ? 'edit' : 'create',
          'id' => $entityId,
      ],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
