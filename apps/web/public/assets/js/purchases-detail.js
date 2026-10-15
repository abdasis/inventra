/**
 * Inventra - Detail Pembelian
 * Memuat satu pembelian beserta barangnya, menyediakan form penerimaan per baris
 * dan pembatalan yang wajib disertai alasan. Hanya dimuat pada halaman
 * /purchases/{id} lewat flag $loadPurchaseDetailScript.
 *
 * Helper kecil (escaping, format angka, badge status, pesan galat) sengaja
 * disalin dari purchases.js: tidak ada bundler maupun berkas modul bersama, dan
 * duplikasi sekecil ini lebih murah daripada pola baru. Notifikasi dan dialog
 * memakai helper bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const page = window.INVENTRA_PURCHASE || {};
  const ui = window.InventraUI;

  if (!ui || page.mode !== 'detail' || document.getElementById('purchaseDetail') === null) {
    return;
  }

  const escapeHtml = ui.escapeHtml;
  // Aksi tulis pengadaan hanya untuk administrator; staf hanya melihat.
  const isAdmin = page.isAdmin === true;
  const id = String(page.id || '');

  if (id === '') return;

  // Label dan kelas badge disediakan API; peta ini hanya sandaran bila belum ada.
  const STATUS_BADGES = {
    draft: ['Draft', 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    partial: ['Sebagian Diterima', 'bg-warning-subtle text-warning border border-warning-subtle'],
    received: ['Diterima', 'bg-success-subtle text-success border border-success-subtle'],
    cancelled: ['Dibatalkan', 'bg-danger-subtle text-danger border border-danger-subtle']
  };

  const ITEM_TYPES = { asset: 'Aset', consumable: 'Barang Habis Pakai' };

  const formatRupiah = function (value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
  };

  const formatNumber = function (value) {
    return Number(value || 0).toLocaleString('id-ID');
  };

  function statusBadge(row) {
    const fallback = STATUS_BADGES[String(row.status || '')] || [];
    const label = String(row.status_label || fallback[0] || 'Tidak dikenal');
    const badgeClass = String(row.status_badge || fallback[1] || 'bg-secondary-subtle text-secondary');

    return '<span class="badge badge-status ' + escapeHtml(badgeClass) + '">' + escapeHtml(label) + '</span>';
  }

  function itemTypeLabel(value) {
    const type = String(value === null || value === undefined ? '' : value);
    return Object.prototype.hasOwnProperty.call(ITEM_TYPES, type) ? ITEM_TYPES[type] : (type || '-');
  }

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    if (xhr.status === 403) {
      return (res && res.message) || 'Hanya administrator yang dapat mengubah data pembelian.';
    }
    return (res && res.message) || fallback;
  }

  function showError(message) {
    $('#purchaseDetailError').html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small mb-3" role="alert">' +
      '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  let purchase = null;
  let receiving = false;

  /** Nilai kosong tampil sebagai tanda hubung; nilai ber-rawHtml sudah di-escape pemanggilnya. */
  function textOrEmpty(value) {
    const text = String(value === null || value === undefined ? '' : value).trim();
    return text === '' ? '<span class="text-muted">-</span>' : escapeHtml(text);
  }

  function fieldCol(label, value, rawHtml) {
    return '<div class="col-6 col-md-4">' +
      '<div class="text-muted small fw-semibold">' + escapeHtml(label) + '</div>' +
      '<div>' + (rawHtml === true ? value : textOrEmpty(value)) + '</div>' +
      '</div>';
  }

  function renderIdentity(row) {
    const number = String(row.purchase_number || 'Pembelian');
    const quantity = formatNumber(row.quantity_ordered) + ' dipesan, ' +
      formatNumber(row.quantity_received) + ' diterima';

    $('#purchaseTitle').text(number);
    $('#purchaseSubtitle').text(
      [row.purchase_date, row.supplier_name].filter(Boolean).join(' · ') || 'Tanpa supplier'
    );
    $('#purchaseStatus').html(statusBadge(row));

    // Item terakhir breadcrumb diisi nomor pembelian begitu datanya tersedia.
    const crumb = document.querySelector('nav[aria-label="Breadcrumb"] .breadcrumb-item[aria-current="page"]');
    if (crumb !== null) {
      crumb.textContent = number;
    }

    const fields = [
      fieldCol('Tanggal Pembelian', row.purchase_date),
      fieldCol('Supplier', row.supplier_name),
      fieldCol('Status', statusBadge(row), true),
      fieldCol('Total', row.total_amount_formatted || formatRupiah(row.total_amount)),
      fieldCol('Referensi Dokumen', row.reference),
      fieldCol('Dibuat Oleh', row.created_by_name),
      fieldCol('Jumlah Barang', quantity),
      fieldCol('Terakhir Diperbarui', row.created_at)
    ];

    if (row.notes) {
      fields.push('<div class="col-12">' +
        '<div class="text-muted small fw-semibold">Catatan</div>' +
        '<div class="detail-note">' + escapeHtml(row.notes) + '</div>' +
        '</div>');
    }

    $('#purchaseIdentity').html('<div class="row g-3">' + fields.join('') + '</div>');
  }

  function renderDetails(list) {
    const details = Array.isArray(list) ? list : [];
    const $body = $('#purchaseDetailBody');
    const $foot = $('#purchaseDetailFoot');

    if (details.length === 0) {
      $body.html('<tr><td colspan="9" class="text-center py-3 text-muted">Belum ada barang pada pembelian ini.</td></tr>');
      $foot.attr('hidden', true).empty();
      return;
    }

    let rows = '';
    let total = 0;

    details.forEach(function (detail) {
      total += Number(detail.subtotal || 0);
      const rejected = Number(detail.quantity_rejected || 0);

      rows += '<tr data-detail-id="' + escapeHtml(detail.id) + '">' +
        '<td><div class="fw-semibold text-dark">' + textOrEmpty(detail.item_name) + '</div>' +
        '<div class="small text-muted font-monospace">' + escapeHtml(detail.item_code || '') + '</div></td>' +
        '<td class="small text-muted">' + escapeHtml(itemTypeLabel(detail.item_type)) + '</td>' +
        '<td class="small text-muted">' + textOrEmpty(detail.unit_name) + '</td>' +
        '<td class="text-end">' + escapeHtml(formatNumber(detail.quantity_ordered)) + '</td>' +
        '<td class="text-end text-success fw-semibold">' + escapeHtml(formatNumber(detail.quantity_received)) + '</td>' +
        '<td class="text-end ' + (rejected > 0 ? 'text-danger fw-semibold' : 'text-muted') + '">' +
        escapeHtml(formatNumber(detail.quantity_rejected)) + '</td>' +
        '<td class="text-end fw-semibold">' + escapeHtml(formatNumber(detail.quantity_outstanding)) + '</td>' +
        '<td class="text-end">' + escapeHtml(detail.unit_price_formatted || formatRupiah(detail.unit_price)) + '</td>' +
        '<td class="text-end fw-semibold">' +
        escapeHtml(detail.subtotal_formatted || formatRupiah(detail.subtotal)) + '</td>' +
        '</tr>';
    });

    $body.html(rows);

    const totalText = purchase !== null && purchase.total_amount_formatted
      ? purchase.total_amount_formatted
      : formatRupiah(total);

    $foot.removeAttr('hidden').html(
      '<tr><th colspan="8" class="text-end">Total</th><th class="text-end">' + escapeHtml(totalText) + '</th></tr>'
    );
  }

  /** Satu input penerimaan dengan label tak terlihat yang tersambung lewat for. */
  function receiveInput(inputId, label, value, field) {
    return '<label class="visually-hidden" for="' + inputId + '">' + label + '</label>' +
      '<input type="number" class="form-control form-control-sm text-end purchase-qty-input ms-auto"' +
      ' id="' + inputId + '" min="0" step="1" value="' + value + '" data-receive-field="' + field + '">';
  }

  /** Form penerimaan hanya memuat baris yang sisanya masih ada. */
  function renderReceive(list) {
    const details = Array.isArray(list) ? list : [];
    const outstanding = details.filter(function (detail) {
      return Number(detail.quantity_outstanding) > 0;
    });
    const $body = $('#purchaseReceiveBody');

    if (outstanding.length === 0) {
      $body.html('<tr><td colspan="4" class="text-center py-3 text-muted">Semua barang sudah diterima penuh.</td></tr>');
      $('#btnSubmitReceive').prop('disabled', true);
      return;
    }

    let rows = '';

    outstanding.forEach(function (detail) {
      const name = escapeHtml(String(detail.item_name || ''));
      const outstandingValue = String(Math.max(0, Number(detail.quantity_outstanding || 0)));

      rows += '<tr data-detail-id="' + escapeHtml(detail.id) + '" data-outstanding="' + escapeHtml(outstandingValue) + '">' +
        '<td><div class="fw-semibold text-dark">' + textOrEmpty(detail.item_name) + '</div>' +
        '<div class="small text-muted font-monospace">' + escapeHtml(detail.item_code || '') + '</div></td>' +
        '<td class="text-end">' + escapeHtml(formatNumber(detail.quantity_outstanding)) + '</td>' +
        '<td>' + receiveInput('receive-quantity-' + escapeHtml(detail.id), 'Jumlah diterima ' + name, escapeHtml(outstandingValue), 'quantity_received') + '</td>' +
        '<td>' + receiveInput('receive-rejected-' + escapeHtml(detail.id), 'Jumlah ditolak ' + name, '0', 'quantity_rejected') + '</td>' +
        '</tr>';
    });

    $body.html(rows);
    $('#btnSubmitReceive').prop('disabled', false);
  }

  /** Tombol tulis mengikuti status dokumen dan peran pengguna. */
  function syncActions(row) {
    const status = String(row.status || '');
    const receivable = status === 'draft' || status === 'partial';

    $('#purchaseReceiveCard').prop('hidden', !(isAdmin && receivable));
    $('#btnPurchaseEdit').prop('hidden', !(isAdmin && status === 'draft'));
    $('#btnPurchaseCancel').prop('hidden', !(isAdmin && status === 'draft'));
  }

  function loadDetail() {
    $.ajax({ url: API_BASE + '/purchases/' + encodeURIComponent(id), type: 'GET', dataType: 'json' })
      .done(function (res) {
        const row = res && res.success ? (Array.isArray(res.data) ? res.data[0] : res.data) : null;
        if (!row) {
          showError('Data pembelian tidak ditemukan. Kembali ke daftar untuk memilih ulang.');
          $('#purchaseIdentity').html('<div class="text-muted small">Data pembelian tidak ditemukan.</div>');
          renderDetails([]);
          $('#purchaseReceiveCard').prop('hidden', true);
          return;
        }

        purchase = row;
        renderIdentity(row);
        renderDetails(row.details);
        renderReceive(row.details);
        syncActions(row);
      })
      .fail(function (xhr) {
        showError(apiMessage(xhr, 'Gagal memuat data pembelian. Coba lagi ya.'));
        $('#purchaseIdentity').html('<div class="text-muted small">Data pembelian tidak dapat dimuat.</div>');
        renderDetails([]);
        $('#purchaseReceiveCard').prop('hidden', true);
      });
  }

  function lineValue(row, field) {
    return row.querySelector('[data-receive-field="' + field + '"]');
  }

  function submitReceive() {
    if (receiving || purchase === null) {
      return;
    }

    const details = [];
    let invalid = false;
    let hasChange = false;

    $('#purchaseReceiveBody').find('tr[data-detail-id]').each(function () {
      const row = this;
      const outstanding = Number(row.dataset.outstanding || 0);
      const received = Math.max(0, Number(lineValue(row, 'quantity_received') || 0));
      const rejected = Math.max(0, Number(lineValue(row, 'quantity_rejected') || 0));

      if (received + rejected > outstanding) {
        // Rambu di klien; server tetap sumber kebenaran.
        invalid = true;
        $(lineValue(row, 'quantity_received')).addClass('is-invalid');
        $(lineValue(row, 'quantity_rejected')).addClass('is-invalid');
      }

      if (received > 0 || rejected > 0) {
        hasChange = true;
      }

      details.push({
        id: Number(row.dataset.detailId),
        quantity_received: received,
        quantity_rejected: rejected
      });
    });

    if (invalid) {
      $('#error-receive').text('Jumlah diterima dan ditolak tidak boleh melebihi sisa barang.').prop('hidden', false);
      return;
    }

    $('#error-receive').prop('hidden', true).text('');

    if (!hasChange) {
      $('#error-receive').text('Isi jumlah diterima atau ditolak pada minimal satu baris.').prop('hidden', false);
      return;
    }

    receiving = true;
    const $button = $('#btnSubmitReceive').prop('disabled', true);
    const notes = String($('#purchaseReceiveNotes').val() || '').trim();

    $.ajax({
      url: API_BASE + '/purchases/' + encodeURIComponent(id) + '/receive',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ details: details, notes: notes === '' ? null : notes }),
      dataType: 'json'
    })
      .done(function (res) {
        ui.toast((res && res.message) || 'Penerimaan berhasil disimpan.');
        $('#purchaseReceiveNotes').val('');
        loadDetail();
      })
      .fail(function (xhr) {
        showError(apiMessage(xhr, 'Gagal menyimpan penerimaan. Coba lagi ya.'));
      })
      .always(function () {
        receiving = false;
        $button.prop('disabled', false);
      });
  }

  /** Pembatalan wajib disertai alasan, jadi dialognya memakai input SweetAlert2. */
  function cancelPurchase() {
    if (purchase === null) {
      return;
    }

    Swal.fire({
      title: 'Batalkan pembelian ini?',
      text: 'Pembelian ' + String(purchase.purchase_number || '') + ' akan dibatalkan dan tidak dapat dilanjutkan kembali.',
      input: 'textarea',
      inputLabel: 'Alasan pembatalan',
      inputPlaceholder: 'Contoh: supplier tidak dapat memenuhi pesanan',
      inputAttributes: { 'aria-label': 'Alasan pembatalan' },
      showCancelButton: true,
      confirmButtonText: 'Ya, batalkan',
      cancelButtonText: 'Tidak jadi',
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#64748b',
      reverseButtons: true,
      focusCancel: true,
      inputValidator: function (value) {
        return String(value || '').trim() === '' ? 'Alasan pembatalan wajib diisi.' : undefined;
      }
    }).then(function (result) {
      if (result.isConfirmed !== true) {
        return;
      }

      const $button = $('#btnPurchaseCancel').prop('disabled', true);

      $.ajax({
        url: API_BASE + '/purchases/' + encodeURIComponent(id) + '/cancel',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ reason: String(result.value || '').trim() }),
        dataType: 'json'
      })
        .done(function (res) {
          ui.toast((res && res.message) || 'Pembelian dibatalkan.');
          loadDetail();
        })
        .fail(function (xhr) {
          showError(apiMessage(xhr, 'Gagal membatalkan pembelian. Coba lagi ya.'));
        })
        .always(function () {
          $button.prop('disabled', false);
        });
    });
  }

  $('#btnPurchaseCancel').on('click', cancelPurchase);
  $('#btnSubmitReceive').on('click', submitReceive);

  // Rambu baris penerimaan dibersihkan begitu pengguna memperbaiki isian.
  $('#purchaseReceiveBody').on('input', '[data-receive-field]', function () {
    $(this).removeClass('is-invalid').removeAttr('aria-invalid');
    $('#error-receive').prop('hidden', true);
  });

  loadDetail();
});
