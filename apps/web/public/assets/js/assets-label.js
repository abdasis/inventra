/**
 * Inventra - Label QR Aset
 * Mengambil satu aset lalu mengisi label cetak dan membuat QR code dari kode
 * aset. Halaman label berdiri sendiri tanpa app.js, jadi berkas ini memakai
 * fetch bawaan peramban dan tidak bergantung pada helper mana pun.
 * Hanya dimuat pada halaman /assets/{id}/label.
 */

(function () {
  const API_BASE = '/api/v1';
  const page = window.INVENTRA_ASSET || {};
  const id = String(page.id || '');
  const printButton = document.getElementById('btnPrintLabel');

  function showError(message) {
    const box = document.getElementById('labelError');
    if (box === null) {
      return;
    }

    box.textContent = message;
    box.hidden = false;
  }

  function fillLabel(asset) {
    document.getElementById('labelCode').textContent = String(asset.asset_code || '-');
    document.getElementById('labelName').textContent = String(asset.name || '');

    const meta = [asset.category_name, asset.location_name].filter(function (part) {
      return part !== null && part !== undefined && String(part).trim() !== '';
    });

    document.getElementById('labelMeta').textContent = meta.join(' · ');
  }

  function drawQr(text) {
    // QR dibuat dari kode aset supaya pemindai langsung mengenali barangnya.
    new QRCode(document.getElementById('labelQr'), {
      text: text,
      width: 132,
      height: 132,
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  if (printButton !== null) {
    printButton.addEventListener('click', function () {
      window.print();
    });
  }

  if (id === '') {
    showError('Kode aset tidak dikenali. Buka halaman ini dari detail aset.');
    return;
  }

  if (typeof QRCode === 'undefined') {
    showError('Pustaka pembuat QR gagal dimuat. Periksa koneksi lalu muat ulang halaman ini.');
    return;
  }

  fetch(API_BASE + '/assets/' + encodeURIComponent(id), {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin'
  })
    .then(function (response) {
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }
      return response.json();
    })
    .then(function (payload) {
      const asset = payload && payload.success
        ? (Array.isArray(payload.data) ? payload.data[0] : payload.data)
        : null;

      if (!asset) {
        showError('Data aset tidak ditemukan. Kembali ke daftar aset untuk memilih ulang.');
        return;
      }

      if (!asset.asset_code) {
        showError('Aset ini belum memiliki kode aset, jadi label QR belum bisa dibuat.');
        return;
      }

      fillLabel(asset);

      try {
        drawQr(String(asset.asset_code));
      } catch (error) {
        showError('QR code gagal dibuat. Muat ulang halaman ini.');
      }
    })
    .catch(function () {
      showError('Gagal memuat data aset. Periksa koneksi lalu muat ulang halaman ini.');
    });
})();
