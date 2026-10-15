<?php
/**
 * Fragmen halaman daftar master data: kartu ber-header, kotak pencarian
 * server-side, wadah error, tabel kosong untuk DataTables, dan modal form bila
 * entitas memakainya. Isi tabel dirender master-data.js lewat AJAX.
 *
 * @var string $entity
 * @var string $label
 * @var string $subtitle
 * @var array<int, array{label: string, width?: string}> $columns
 * @var bool|null $hasModal
 * @var array<int, array<string, mixed>> $modalFields
 * @var string|null $createUrl
 */

use Inventra\Web\Support\View;

$loadMasterScript = true;
$hasModal = !empty($hasModal);
$modalFields = $modalFields ?? [];
?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="fw-bold mb-0">Master <?= View::e($label) ?></h5>
      <small class="text-muted"><?= View::e($subtitle) ?></small>
    </div>
    <?php if ($hasModal): ?>
      <button type="button" class="btn btn-primary btn-sm px-3" id="btnAddMaster">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah <?= View::e($label) ?>
      </button>
    <?php else: ?>
      <a href="<?= View::e((string)$createUrl) ?>" class="btn btn-primary btn-sm px-3">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah <?= View::e($label) ?>
      </a>
    <?php endif; ?>
  </div>

  <div class="px-4 py-3 border-bottom">
    <label for="masterSearch" class="form-label small fw-semibold mb-1">Cari <?= View::e($label) ?></label>
    <input type="search" class="form-control form-control-sm" id="masterSearch" autocomplete="off"
           style="max-width: 22rem;"
           placeholder="Kode atau nama <?= View::e(mb_strtolower($label)) ?>...">
  </div>

  <div id="masterError" role="alert" aria-live="assertive"></div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tableMaster">
      <thead>
        <tr>
          <?php foreach ($columns as $column): ?>
            <th<?= isset($column['width']) ? ' style="width: ' . View::e((string)$column['width']) . ';"' : '' ?>><?= View::e($column['label']) ?></th>
          <?php endforeach; ?>
          <th class="text-center" style="width: 240px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat master-data.js; keadaan kosong ditangani DataTables. -->
      <tbody id="masterTableBody"></tbody>
    </table>
  </div>
</div>

<?php if ($hasModal): ?>
  <div class="modal fade" id="masterModal" tabindex="-1" aria-labelledby="masterModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="formMaster" data-mode="create">
          <input type="hidden" name="id" id="masterId">
          <div class="modal-header">
            <h5 class="modal-title fw-bold" id="masterModalTitle">Tambah <?= View::e($label) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body p-4">
            <div id="masterModalError" class="mb-3" role="alert" aria-live="assertive"></div>
            <div class="row g-3">
              <?php foreach ($modalFields as $field): ?>
                <?php require __DIR__ . '/master-field.php'; ?>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary px-4" id="btnSubmitMaster">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  // Konfigurasi halaman untuk master-data.js.
  window.INVENTRA_MASTER = <?= json_encode(
      ['entity' => $entity, 'label' => $label],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
