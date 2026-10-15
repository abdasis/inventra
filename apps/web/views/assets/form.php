<?php
/**
 * Shell form tambah dan ubah aset. Data, dropdown, dan pengiriman ditangani
 * assets-form.js. Grid dan fragmen field memakai pola form master data supaya
 * markup input, pesan error per field, dan atribut aksesibilitasnya sama.
 *
 * @var bool $isEdit
 * @var int|null $assetId
 */

use Inventra\Web\Support\View;

$loadAssetFormScript = true;
$isEdit = (bool)($isEdit ?? false);
$assetId = $isEdit ? (int)($assetId ?? 0) : null;

$sections = [
    'Identitas Aset' => [
        ['name' => 'item_id', 'label' => 'Jenis Barang', 'type' => 'select', 'source' => 'items', 'emptyLabel' => 'Pilih jenis barang', 'hint' => 'Diambil dari Master Data > Barang. Kategori dan nama aset terisi otomatis bila masih kosong.'],
        ['name' => 'name', 'label' => 'Nama Aset', 'type' => 'text', 'required' => true, 'placeholder' => 'Contoh: Laptop Lenovo ThinkPad T14', 'hint' => 'Nama yang tampil pada daftar inventaris.'],
        ['name' => 'category_id', 'label' => 'Kategori', 'type' => 'select', 'required' => true, 'source' => 'categories', 'emptyLabel' => 'Pilih kategori'],
        ['name' => 'location_id', 'label' => 'Lokasi Penempatan', 'type' => 'select', 'required' => true, 'source' => 'locations', 'emptyLabel' => 'Pilih lokasi penempatan'],
        ['name' => 'serial_number', 'label' => 'Nomor Seri', 'type' => 'text', 'placeholder' => 'Nomor seri pabrikan'],
    ],
    'Perolehan' => [
        ['name' => 'purchase_date', 'label' => 'Tanggal Perolehan', 'type' => 'date'],
        ['name' => 'purchase_cost', 'label' => 'Nilai Perolehan (Rp)', 'type' => 'number', 'min' => '0', 'step' => '1000', 'placeholder' => '0'],
        ['name' => 'acquisition_source', 'label' => 'Sumber Perolehan', 'type' => 'select', 'default' => 'purchase', 'options' => [
            ['value' => 'purchase', 'label' => 'Pembelian'],
            ['value' => 'grant', 'label' => 'Hibah'],
            ['value' => 'transfer', 'label' => 'Transfer'],
            ['value' => 'other', 'label' => 'Lainnya'],
        ]],
        ['name' => 'purchase_reference', 'label' => 'Referensi Pembelian', 'type' => 'text', 'hint' => 'Nomor faktur, nota, atau berita acara.'],
        ['name' => 'warranty_expiry', 'label' => 'Masa Garansi Berakhir', 'type' => 'date'],
    ],
    'Kondisi & Dokumen' => [
        ['name' => 'condition_level', 'label' => 'Kondisi Fisik', 'type' => 'select', 'default' => 'good', 'options' => [
            ['value' => 'new', 'label' => 'Sangat Baik (Baru)'],
            ['value' => 'good', 'label' => 'Baik (Normal)'],
            ['value' => 'fair', 'label' => 'Cukup (Ada Aus)'],
            ['value' => 'damaged', 'label' => 'Rusak'],
        ]],
        ['name' => 'photo_path', 'label' => 'Tautan Foto', 'type' => 'text', 'hint' => 'Alamat atau lokasi berkas foto aset.'],
        ['name' => 'document_path', 'label' => 'Tautan Dokumen', 'type' => 'text', 'hint' => 'Alamat atau lokasi berkas dokumen pendukung.'],
        ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3, 'placeholder' => 'Kelengkapan, aksesori, atau keterangan khusus'],
    ],
];

if ($isEdit) {
    // Status hanya diubah saat ubah data; aset baru selalu lahir berstatus tersedia.
    $sections['Kondisi & Dokumen'][] = ['name' => 'status', 'label' => 'Status Aset', 'type' => 'select', 'default' => 'available', 'options' => [
        ['value' => 'available', 'label' => 'Tersedia'],
        ['value' => 'in_use', 'label' => 'Dipakai'],
        ['value' => 'maintenance', 'label' => 'Perbaikan'],
        ['value' => 'disposed', 'label' => 'Afkir'],
    ], 'hint' => 'Status biasanya berubah otomatis lewat serah terima atau pengembalian aset.'];
} else {
    $sections['Jumlah Unit'] = [
        ['name' => 'quantity', 'label' => 'Jumlah Unit', 'type' => 'number', 'min' => '1', 'step' => '1', 'hint' => 'Isi lebih dari 1 untuk membuat beberapa aset sekaligus dari data yang sama (maksimal 50).'],
    ];
}
?>
<div class="row justify-content-center">
  <div class="col-12 col-xl-10">
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-header bg-white py-3 px-4 border-bottom">
        <h5 class="fw-bold mb-0"><?= $isEdit ? 'Ubah Aset' : 'Tambah Aset Baru' ?></h5>
        <small class="text-muted">
          <?php if ($isEdit): ?>
            Perbarui identitas, perolehan, atau kondisi aset <span class="font-monospace" id="assetCodeHint"></span>
          <?php else: ?>
            Lengkapi identitas aset agar mudah ditelusuri, dihitung, dan dicetak labelnya
          <?php endif; ?>
        </small>
      </div>

      <div class="card-body p-4">
        <div id="assetFormError" class="mb-3" role="alert" aria-live="assertive"></div>

        <form id="formAsset" data-mode="<?= $isEdit ? 'edit' : 'create' ?>">
          <input type="hidden" name="id" id="assetId" value="<?= $assetId !== null ? (int)$assetId : '' ?>">

          <div class="row g-3">
            <?php foreach ($sections as $sectionTitle => $fields): ?>
              <div class="col-12"><h6 class="fw-bold mb-0 text-uppercase small"><?= View::e((string)$sectionTitle) ?></h6></div>
              <?php foreach ($fields as $field): ?>
                <?php require __DIR__ . '/../partials/master-field.php'; ?>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>

          <div class="form-actions d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
            <a href="/" class="btn btn-outline-secondary px-4">Batal</a>
            <button type="submit" class="btn btn-primary px-4" id="btnSubmitAsset">
              <i class="bi bi-check-lg me-1" aria-hidden="true"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Aset' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  // Konfigurasi halaman untuk assets-form.js.
  window.INVENTRA_ASSET = <?= json_encode(
      ['id' => $assetId, 'mode' => $isEdit ? 'edit' : 'create'],
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
  ) ?>;
</script>
