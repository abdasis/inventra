<?php
/**
 * Shell form tambah dan ubah pembelian. Field header memakai fragmen bersama
 * partials/master-field.php supaya label, aria-describedby, dan wadah error per
 * field tidak diduplikasi antar halaman form. Baris barang dinamis disalin dari
 * <template> oleh purchases.js.
 *
 * @var bool $isEdit
 * @var int|null $purchaseId
 */

use Inventra\Core\Auth\Auth;

$loadPurchaseScript = true;
$isEdit = (bool)($isEdit ?? false);
$purchaseId = $isEdit ? (int)($purchaseId ?? 0) : null;
$isAdmin = Auth::isAdmin();

$headerFields = [
    ['name' => 'purchase_number', 'label' => 'Nomor Pembelian', 'type' => 'text', 'placeholder' => 'Contoh: PO-2026-0001', 'hint' => 'Kosongkan bila ingin nomor dibangkitkan otomatis oleh sistem.'],
    ['name' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'source' => 'suppliers', 'emptyLabel' => 'Pilih supplier (opsional)', 'hint' => 'Diambil dari Master Data > Supplier.'],
    ['name' => 'purchase_date', 'label' => 'Tanggal Pembelian', 'type' => 'date', 'required' => true],
    ['name' => 'reference', 'label' => 'Referensi Dokumen', 'type' => 'text', 'placeholder' => 'Nomor faktur, SPK, atau berita acara'],
    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2, 'placeholder' => 'Keperluan pembelian, termin pembayaran, atau keterangan lain'],
];
?>

<?php if (!$isAdmin): ?>
  <div class="row justify-content-center">
    <div class="col-12 col-xl-9">
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
          <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
            <span>Hanya administrator yang dapat menambah atau mengubah pembelian. Kembali ke daftar untuk melihat data yang tersedia.</span>
          </div>
          <a href="/purchases" class="btn btn-outline-secondary px-4">Kembali ke daftar</a>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="row justify-content-center">
    <div class="col-12 col-xl-11">
      <form id="formPurchase" data-mode="<?= $isEdit ? 'edit' : 'create' ?>">
        <input type="hidden" name="id" id="purchaseId" value="<?= $purchaseId !== null ? (int)$purchaseId : '' ?>">

        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-white py-3 px-4 border-bottom">
            <h5 class="fw-bold mb-0"><?= $isEdit ? 'Ubah Pembelian' : 'Tambah Pembelian' ?></h5>
            <small class="text-muted">Isi identitas pembelian, lalu tambahkan barang yang dipesan pada bagian detail</small>
          </div>

          <div class="card-body p-4">
            <div id="purchaseFormError" class="mb-3" role="alert" aria-live="assertive"></div>

            <div class="row g-3">
              <?php foreach ($headerFields as $field): ?>
                <?php require __DIR__ . '/../partials/master-field.php'; ?>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 mb-4">
          <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
              <h6 class="fw-bold mb-0">Detail Barang</h6>
              <small class="text-muted">Tambahkan minimal satu barang beserta jumlah dipesan dan harga satuannya</small>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm px-3" id="btnAddLine">
              <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Baris
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-modern align-middle mb-0 purchase-lines">
              <thead>
                <tr>
                  <th style="min-width: 240px;">Barang</th>
                  <th class="text-end" style="width: 150px;">Jumlah Dipesan</th>
                  <th class="text-end" style="width: 180px;">Harga Satuan (Rp)</th>
                  <th style="min-width: 180px;">Catatan</th>
                  <th class="text-end" style="width: 150px;">Subtotal</th>
                  <th class="text-center" style="width: 70px;">Hapus</th>
                </tr>
              </thead>
              <!-- Baris disalin purchases.js dari template di bawah tabel. -->
              <tbody id="purchaseLinesBody"></tbody>
              <tfoot>
                <tr>
                  <th colspan="4" class="text-end">Total</th>
                  <th class="text-end" id="purchaseTotal">Rp 0</th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="px-4 py-3 border-top">
            <div class="text-danger small" id="error-details" hidden></div>
          </div>
        </div>

        <div class="form-actions d-flex flex-wrap gap-2 justify-content-end mb-4">
          <a href="/purchases" class="btn btn-outline-secondary px-4">Batal</a>
          <button type="submit" class="btn btn-primary px-4" id="btnSubmitPurchase">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Pembelian' ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!--
    Satu baris detail barang. purchases.js menyalin isinya, menyambungkan id
    input dengan label visually-hidden-nya, lalu mengisi pilihan barangnya.
  -->
  <template id="purchaseLineTemplate">
    <tr class="purchase-line">
      <td>
        <label class="visually-hidden" data-line-label="item_id">Barang</label>
        <select class="form-select form-select-sm" data-line-field="item_id"></select>
      </td>
      <td>
        <label class="visually-hidden" data-line-label="quantity_ordered">Jumlah dipesan</label>
        <input type="number" class="form-control form-control-sm text-end purchase-qty-input ms-auto"
               data-line-field="quantity_ordered" min="1" step="1" value="1">
      </td>
      <td>
        <label class="visually-hidden" data-line-label="unit_price">Harga satuan</label>
        <input type="number" class="form-control form-control-sm text-end purchase-price-input ms-auto"
               data-line-field="unit_price" min="0" step="100" value="0">
      </td>
      <td>
        <label class="visually-hidden" data-line-label="notes">Catatan baris</label>
        <input type="text" class="form-control form-control-sm" data-line-field="notes" placeholder="Opsional">
      </td>
      <td class="text-end fw-semibold purchase-line-subtotal">Rp 0</td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-light border text-danger btn-remove-line" aria-label="Hapus baris ini">
          <i class="bi bi-trash" aria-hidden="true"></i>
        </button>
      </td>
    </tr>
  </template>
<?php endif; ?>

<script>
  // Konfigurasi halaman untuk purchases.js.
  window.INVENTRA_PURCHASE = <?= json_encode(
      [
          'mode' => 'form',
          'id' => $purchaseId,
          'isAdmin' => $isAdmin,
      ],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
