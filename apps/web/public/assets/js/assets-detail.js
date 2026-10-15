/**
 * Inventra - Detail Aset
 * Memuat satu aset beserta riwayat mutasinya, mengisi kartu identitas dan
 * perolehan, lalu menyambungkan aksi serah terima, pengembalian, dan hapus ke
 * modal yang sudah ada di layout. Hanya dimuat pada halaman /assets/{id}.
 * Notifikasi dan dialog memakai helper bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const FLASH_KEY = 'inventra.assets.flash';
  const page = window.INVENTRA_ASSET || {};
  const ui = window.InventraUI;
  const root = document.getElementById('assetDetail');
  const id = String(page.id || '');

  if (root === null || id === '') {
    return;
  }

  const escapeHtml = ui.escapeHtml;
  const isAdmin = page.isAdmin === true;
  const checkoutModal = new bootstrap.Modal($('#checkoutModal')[0]);
  const checkinModal = new bootstrap.Modal($('#checkinModal')[0]);

  const MOVEMENT_LABELS = {
    check_out: ['Serah Terima / Pakai', 'bg-primary'],
    check_in: ['Pengembalian', 'bg-success'],
    repair: ['Perbaikan', 'bg-warning text-dark']
  };

  // Label dan kelas badge disediakan API (status_label/status_badge dan
  // condition_label/condition_badge). Peta berikut hanya sandaran bila respons
  // belum memuatnya, dengan nilai yang sama seperti daftar dashboard.
  const CONDITION_BADGES = {
    new: ['Baru', 'bg-info-subtle text-info'],
    good: ['Baik', 'bg-success-subtle text-success'],
    fair: ['Cukup', 'bg-warning-subtle text-warning'],
    damaged: ['Rusak', 'bg-danger-subtle text-danger']
  };

  const STATUS_BADGES = {
    available: ['Tersedia', 'bg-success-subtle text-success border border-success-subtle'],
    in_use: ['Dipakai', 'bg-primary-subtle text-primary border border-primary-subtle'],
    maintenance: ['Perbaikan', 'bg-warning-subtle text-warning border border-warning-subtle'],
    disposed: ['Afkir', 'bg-secondary-subtle text-secondary']
  };

  const SOURCE_LABELS = {
    purchase: 'Pembelian',
    grant: 'Hibah',
    transfer: 'Transfer',
    other: 'Lainnya'
  };

  let assetRow = null;
  let itemsById = {};

  /** Nilai peta cadangan; undefined bila kuncinya tidak dikenal. */
  function mappedValue(map, key) {
    const value = String(key === null || key === undefined ? '' : key);
    return Object.prototype.hasOwnProperty.call(map, value) ? map[value] : undefined;
  }

  /** Jenis mutasi yang tidak dikenal tetap tampil dengan label dari API. */
  function movementBadge(movement) {
    const type = String(movement.movement_type || '');

    if (Object.prototype.hasOwnProperty.call(MOVEMENT_LABELS, type)) {
      return MOVEMENT_LABELS[type];
    }

    return [String(movement.movement_label || 'Mutasi'), 'bg-secondary'];
  }

  /** Nilai rupiah cadangan bila API belum mengirim bentuk terformatnya. */
  function formatCost(value) {
    const amount = Number(value || 0);
    return amount > 0 ? 'Rp ' + amount.toLocaleString('id-ID') : '';
  }

  function showError(message) {
    $('#assetDetailError').html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">' +
      '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    if (xhr.status === 403) {
      return (res && res.message) || 'Hanya administrator yang dapat menghapus aset.';
    }
    return (res && res.message) || fallback;
  }

  /* ------------------------------ Kerangka kartu ------------------------------ */

  function emptyValue() {
    return '<span class="text-muted">-</span>';
  }

  function textOrEmpty(value) {
    const text = String(value === null || value === undefined ? '' : value).trim();
    return text === '' ? emptyValue() : escapeHtml(text);
  }

  function fieldCol(label, value) {
    return '<div class="col-6 col-md-4">' +
      '<div class="text-muted small fw-semibold">' + escapeHtml(label) + '</div>' +
      '<div>' + textOrEmpty(value) + '</div>' +
      '</div>';
  }

  function fieldColHtml(label, html) {
    return '<div class="col-6 col-md-4">' +
      '<div class="text-muted small fw-semibold">' + escapeHtml(label) + '</div>' +
      '<div>' + html + '</div>' +
      '</div>';
  }

  function fieldColLink(label, value) {
    const url = String(value === null || value === undefined ? '' : value).trim();
    // Hanya alamat http(s) atau path internal yang boleh menjadi tautan.
    const safe = /^(https?:\/\/|\/)/i.test(url) ? url : '';

    return '<div class="col-6 col-md-4">' +
      '<div class="text-muted small fw-semibold">' + escapeHtml(label) + '</div>' +
      '<div>' + (safe === ''
        ? textOrEmpty(url)
        : '<a href="' + escapeHtml(safe) + '" target="_blank" rel="noopener">Buka</a>') +
      '</div>' +
      '</div>';
  }

  function badge(label, badgeClass) {
    const text = String(label === null || label === undefined ? '' : label).trim();
    if (text === '') {
      return emptyValue();
    }

    const klass = String(badgeClass === null || badgeClass === undefined ? '' : badgeClass).trim();
    return '<span class="badge badge-status ' + escapeHtml(klass) + '">' + escapeHtml(text) + '</span>';
  }

  /** Sel badge: label dari API dipakai lebih dulu, peta cadangan menjadi sandaran. */
  function badgeCell(asset, valueKey, labelKey, badgeKey, fallbackMap) {
    const fallback = mappedValue(fallbackMap, asset[valueKey]) || [];
    const label = String(asset[labelKey] || fallback[0] || '');
    const badgeClass = String(asset[badgeKey] || fallback[1] || '');

    return badge(label, badgeClass);
  }

  function renderIdentity(a) {
    const item = itemsById[String(a.item_id)] || null;
    const brandModel = item
      ? [item.brand, item.model].filter(function (part) { return !!part; }).join(' ')
      : '';

    let itemCell = textOrEmpty(a.item_name);
    if (brandModel !== '') {
      itemCell += ' <span class="text-muted">— ' + escapeHtml(brandModel) + '</span>';
    }

    const identity = [
      fieldCol('Kode Aset', a.asset_code),
      fieldColHtml('Jenis Barang', itemCell),
      fieldCol('Kategori', a.category_name),
      fieldCol('Lokasi Penempatan', a.location_name),
      fieldCol('Nomor Seri', a.serial_number),
      fieldCol('Pemegang', a.assigned_to),
      fieldColHtml('Kondisi Fisik', badgeCell(a, 'condition_level', 'condition_label', 'condition_badge', CONDITION_BADGES)),
      fieldColHtml('Status', badgeCell(a, 'status', 'status_label', 'status_badge', STATUS_BADGES))
    ];

    if (item && item.specification) {
      identity.push('<div class="col-12">' +
        '<div class="text-muted small fw-semibold">Spesifikasi</div>' +
        '<div class="detail-note">' + escapeHtml(item.specification) + '</div>' +
        '</div>');
    }

    $('#assetIdentity').html('<div class="row g-3">' + identity.join('') + '</div>');

    const acquisition = [
      fieldCol('Tanggal Perolehan', a.purchase_date),
      fieldCol('Nilai Perolehan', a.purchase_cost_formatted || formatCost(a.purchase_cost)),
      fieldCol('Sumber Perolehan', a.acquisition_source_label || mappedValue(SOURCE_LABELS, a.acquisition_source)),
      fieldCol('Referensi Pembelian', a.purchase_reference),
      fieldCol('Masa Garansi Berakhir', a.warranty_expiry),
      fieldColLink('Foto Aset', a.photo_path),
      fieldColLink('Dokumen Pendukung', a.document_path)
    ];

    if (a.notes) {
      acquisition.push('<div class="col-12">' +
        '<div class="text-muted small fw-semibold">Catatan</div>' +
        '<div class="detail-note">' + escapeHtml(a.notes) + '</div>' +
        '</div>');
    }

    $('#assetAcquisition').html('<div class="row g-3">' + acquisition.join('') + '</div>');

    $('#assetTitle').text(String(a.name || 'Aset'));
    $('#assetSubtitle').text(
      [a.asset_code, a.item_name].filter(function (part) { return !!part; }).join(' · ')
    );

    // Item terakhir breadcrumb diisi kode aset begitu datanya tersedia.
    const crumb = document.querySelector('nav[aria-label="Breadcrumb"] .breadcrumb-item[aria-current="page"]');
    if (crumb !== null && a.asset_code) {
      crumb.textContent = String(a.asset_code);
    }

    $('#btnAssetCheckout').prop('hidden', a.status !== 'available');
    $('#btnAssetCheckin').prop('hidden', a.status !== 'in_use');
  }

  function renderMovements(list) {
    const items = Array.isArray(list) ? list : [];
    const $body = $('#assetMovementsBody');

    if (items.length === 0) {
      $body.html('<tr><td colspan="5" class="text-center py-3 text-muted">Belum ada riwayat mutasi untuk aset ini.</td></tr>');
      return;
    }

    let rows = '';
    items.forEach(function (m) {
      const preset = movementBadge(m);
      const label = preset[0];
      const badgeClass = preset[1];
      const route = escapeHtml(m.from_location_name || 'Gudang') +
        ' <i class="bi bi-arrow-right text-muted mx-1" aria-hidden="true"></i> ' +
        escapeHtml(m.to_location_name || 'Gudang');
      const parties = m.recipient_name
        ? 'Penerima: <strong>' + escapeHtml(m.recipient_name) + '</strong> (Oleh: ' + escapeHtml(m.actor_name) + ')'
        : 'Oleh: ' + escapeHtml(m.actor_name);

      rows += '<tr>' +
        '<td class="small text-muted">' + textOrEmpty(m.created_at) + '</td>' +
        '<td><span class="badge badge-status ' + escapeHtml(badgeClass) + '">' + escapeHtml(label) + '</span></td>' +
        '<td class="small">' + route + '</td>' +
        '<td class="small">' + parties + '</td>' +
        '<td class="small text-muted">' + textOrEmpty(m.notes) + '</td>' +
        '</tr>';
    });

    $body.html(rows);
  }

  /* --------------------------------- Pemuatan --------------------------------- */

  function loadItems() {
    return $.ajax({ url: API_BASE + '/items', type: 'GET', dataType: 'json' })
      .done(function (res) {
        const list = res && res.success && Array.isArray(res.data) ? res.data : [];
        list.forEach(function (item) {
          itemsById[String(item.id)] = item;
        });
      })
      .fail(function () {
        // Merek, model, dan spesifikasi hanya pelengkap; identitas tetap tampil.
      });
  }

  function loadDetail() {
    $.ajax({ url: API_BASE + '/assets/' + encodeURIComponent(id), type: 'GET', dataType: 'json' })
      .done(function (res) {
        const row = res && res.success ? (Array.isArray(res.data) ? res.data[0] : res.data) : null;
        if (!row) {
          showError('Data aset tidak ditemukan. Kembali ke daftar aset untuk memilih ulang.');
          $('#assetIdentity').html('<div class="text-muted small">Data aset tidak ditemukan.</div>');
          $('#assetAcquisition').empty();
          renderMovements([]);
          return;
        }

        assetRow = row;
        renderIdentity(row);
        renderMovements(row.movements);
      })
      .fail(function (xhr) {
        showError(apiMessage(xhr, 'Gagal memuat data aset. Coba lagi ya.'));
        $('#assetIdentity').html('<div class="text-muted small">Data aset tidak dapat dimuat.</div>');
        $('#assetAcquisition').empty();
        renderMovements([]);
      });
  }

  function loadLocations() {
    return $.ajax({ url: API_BASE + '/locations', type: 'GET', dataType: 'json' })
      .done(function (res) {
        const list = res && res.success && Array.isArray(res.data) ? res.data : [];
        let html = '<option value="">Pilih lokasi</option>';

        list.forEach(function (loc) {
          html += '<option value="' + escapeHtml(loc.id) + '">' + escapeHtml(ui.locationLabel(loc)) + '</option>';
        });

        $('#checkoutLocation').html(html);
        $('#checkinLocation').html(html);
      });
  }

  /* ----------------------------------- Aksi ----------------------------------- */

  $('#btnAssetCheckout').on('click', function () {
    if (assetRow === null) {
      return;
    }

    $('#checkoutAssetId').val(assetRow.id);
    $('#checkoutAssetName').text(String(assetRow.name || ''));
    $('#checkoutAssetCode').text(String(assetRow.asset_code || ''));
    $('#checkoutRecipient').val('');
    $('#formCheckout textarea[name="notes"]').val('');
    checkoutModal.show();
  });

  $('#btnAssetCheckin').on('click', function () {
    if (assetRow === null) {
      return;
    }

    $('#checkinAssetId').val(assetRow.id);
    $('#checkinAssetName').text(String(assetRow.name || ''));
    $('#checkinAssignedTo').text(assetRow.assigned_to ? String(assetRow.assigned_to) : '-');
    $('#formCheckin textarea[name="notes"]').val('');
    checkinModal.show();
  });

  $('#formCheckout').on('submit', function (event) {
    event.preventDefault();
    const $button = $(this).find('button[type="submit"]').prop('disabled', true);
    const payload = {
      recipient_name: $('#checkoutRecipient').val().trim(),
      target_location_id: Number($('#checkoutLocation').val()),
      notes: $('#formCheckout textarea[name="notes"]').val().trim()
    };

    $.ajax({
      url: API_BASE + '/assets/' + encodeURIComponent(id) + '/checkout',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      dataType: 'json'
    })
      .done(function (res) {
        checkoutModal.hide();
        ui.toast((res && res.message) || 'Aset diserahkan.');
        loadDetail();
      })
      .fail(function (xhr) {
        showError(apiMessage(xhr, 'Gagal memproses serah terima. Coba lagi ya.'));
      })
      .always(function () {
        $button.prop('disabled', false);
      });
  });

  $('#formCheckin').on('submit', function (event) {
    event.preventDefault();
    const $button = $(this).find('button[type="submit"]').prop('disabled', true);
    const payload = {
      return_location_id: Number($('#checkinLocation').val()),
      notes: $('#formCheckin textarea[name="notes"]').val().trim()
    };

    $.ajax({
      url: API_BASE + '/assets/' + encodeURIComponent(id) + '/checkin',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      dataType: 'json'
    })
      .done(function (res) {
        checkinModal.hide();
        ui.toast((res && res.message) || 'Aset dikembalikan.');
        loadDetail();
      })
      .fail(function (xhr) {
        showError(apiMessage(xhr, 'Gagal memproses pengembalian. Coba lagi ya.'));
      })
      .always(function () {
        $button.prop('disabled', false);
      });
  });

  if (isAdmin) {
    $('#btnAssetDelete').on('click', function () {
      const name = assetRow !== null ? String(assetRow.name || '') : '';

      ui.confirmDialog({
        title: 'Hapus aset ini?',
        text: 'Aset "' + name + '" akan dihapus permanen dan tidak dapat dikembalikan.',
        confirmText: 'Ya, hapus',
        destructive: true
      }).then(function (confirmed) {
        if (!confirmed) {
          return;
        }

        const $button = $('#btnAssetDelete').prop('disabled', true);
        $.ajax({ url: API_BASE + '/assets/' + encodeURIComponent(id), type: 'DELETE', dataType: 'json' })
          .done(function (res) {
            try {
              sessionStorage.setItem(FLASH_KEY, (res && res.message) || 'Aset berhasil dihapus.');
            } catch (error) {
              // Penyimpanan sesi tidak tersedia: pesan cukup dilewatkan.
            }
            window.location.href = '/';
          })
          .fail(function (xhr) {
            $button.prop('disabled', false);
            ui.toast(apiMessage(xhr, 'Gagal menghapus aset. Coba lagi ya.'), true);
          });
      });
    });
  }

  loadLocations();
  // Merek, model, dan spesifikasi jenis barang diambil lebih dulu agar identitas
  // dapat dirender sekali jalan.
  loadItems().always(loadDetail);
});
