/**
 * Inventra - Mutasi Aset
 * Memuat daftar mutasi (/api/v1/transfers), menyaringnya, serta mencatat mutasi
 * baru, menyelesaikan, dan membatalkannya. Hanya dimuat pada halaman /transfers.
 * Notifikasi, dialog, dan bahasa DataTables memakai helper bersama dari app.js
 * (window.InventraUI).
 *
 * Helper kecil (escaping, badge, pembersih error per field) sengaja disalin dari
 * assignments.js: tidak ada bundler maupun berkas modul bersama, dan duplikasi
 * sekecil ini lebih murah daripada pola baru.
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.
  const page = window.INVENTRA_TRANSFER || {};
  const ui = window.InventraUI;

  if (!ui || document.getElementById('transferTableBody') === null) {
    return;
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;
  // Mutasi hanya boleh dicatat dan diselesaikan administrator.
  const isAdmin = page.isAdmin === true;

  // Label dan kelas badge disediakan API; peta ini hanya sandaran bila belum ada.
  const STATUS_BADGES = {
    pending: ['Menunggu', 'bg-warning-subtle text-warning border border-warning-subtle'],
    completed: ['Selesai', 'bg-success-subtle text-success border border-success-subtle'],
    cancelled: ['Dibatalkan', 'bg-danger-subtle text-danger border border-danger-subtle']
  };

  let transferTable = null;
  let searchTimer = null;
  let cancelTarget = null; // { id, name, code } baris yang sedang dibatalkan.

  /* --------------------------- Sel bantu tabel --------------------------- */

  function badge(label, className) {
    return '<span class="badge badge-status ' + className + '">' + escapeHtml(label) + '</span>';
  }

  function statusBadge(row) {
    const fallback = STATUS_BADGES[String(row.status || '')] || [];
    const label = String(row.status_label || fallback[0] || 'Tidak dikenal');
    const badgeClass = String(row.status_badge || fallback[1] || 'bg-secondary-subtle text-secondary border border-secondary-subtle');

    return badge(label, badgeClass);
  }

  function assetCell(row) {
    return '<div class="fw-semibold text-dark">' + escapeHtml(row.asset_name || '-') + '</div>' +
      '<div class="small text-muted font-monospace">' + escapeHtml(row.asset_code || '-') + '</div>';
  }

  /** Rute perpindahan: lokasi asal menuju lokasi tujuan. */
  function routeCell(row) {
    const from = String(row.from_location_name || '').trim();
    const to = String(row.to_location_name || '').trim();

    return '<div class="d-flex flex-wrap align-items-center gap-1">' +
      (from === '' ? '<span class="text-muted">-</span>' : escapeHtml(from)) +
      '<i class="bi bi-arrow-right small text-muted" aria-hidden="true"></i>' +
      '<span class="visually-hidden">menuju lokasi tujuan</span>' +
      (to === '' ? '<span class="text-muted">-</span>' : '<span class="fw-semibold text-dark">' + escapeHtml(to) + '</span>') +
      '</div>';
  }

  function holderCell(row) {
    const holder = String(row.to_holder_name || '').trim();

    if (holder === '') {
      return '<span class="text-muted">-</span>';
    }

    const executor = String(row.executed_by_name || '').trim();

    return '<div class="fw-semibold text-dark">' + escapeHtml(holder) + '</div>' +
      (executor !== '' ? '<div class="small text-muted">Dicatat oleh ' + escapeHtml(executor) + '</div>' : '');
  }

  function reasonCell(row) {
    const reason = String(row.reason || '').trim();
    const cancelReason = String(row.cancel_reason || '').trim();
    const notes = String(row.notes || '').trim();

    let html = reason === ''
      ? '<span class="text-muted">-</span>'
      : '<div class="transfer-reason">' + escapeHtml(reason) + '</div>';

    if (String(row.status || '') === 'cancelled' && cancelReason !== '') {
      html += '<div class="small text-danger mt-1">Batal: ' + escapeHtml(cancelReason) + '</div>';
    }

    if (notes !== '') {
      html += '<div class="small text-muted text-wrap-pretty mt-1">' + escapeHtml(notes) + '</div>';
    }

    return html;
  }

  function mutedDate(value) {
    return value ? '<span class="small text-muted">' + escapeHtml(value) + '</span>' : '<span class="text-muted">-</span>';
  }

  /** Aksi per baris hanya untuk administrator dan mutasi yang masih menunggu. */
  function actionsCell(row) {
    if (!isAdmin || String(row.status || '') !== 'pending') {
      return '<span class="text-muted">-</span>';
    }

    const name = escapeHtml(row.asset_name || 'aset ini');

    return '<div class="table-actions d-inline-flex align-items-center flex-wrap justify-content-center gap-1">' +
      '<button type="button" class="btn btn-sm btn-light border px-2 btn-transfer-complete"' +
      ' data-id="' + escapeHtml(row.id) + '" data-name="' + name + '"' +
      ' title="Tandai mutasi ' + name + ' selesai">' +
      '<i class="bi bi-check2-circle" aria-hidden="true"></i> Selesaikan</button>' +
      '<button type="button" class="btn btn-sm btn-light border text-danger px-2 btn-transfer-cancel"' +
      ' data-id="' + escapeHtml(row.id) + '" data-name="' + name +
      '" data-code="' + escapeHtml(row.asset_code || '') + '"' +
      ' title="Batalkan mutasi ' + name + '">' +
      '<i class="bi bi-x-circle" aria-hidden="true"></i> Batalkan</button>' +
      '</div>';
  }

  /* -------------------------------- Daftar -------------------------------- */

  /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
  function getTable() {
    if (transferTable === null) {
      transferTable = $('#tableTransfer').DataTable({
        searching: false, // Pencarian server-side lewat ?search=
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: false, // Urutan baris mengikuti API.
        info: true,
        order: [],
        language: ui.tableLanguage('mutasi aset')
      });
    }

    return transferTable;
  }

  function renderRows(rows) {
    const table = getTable();
    let html = '';

    rows.forEach(function (row) {
      html += '<tr data-id="' + escapeHtml(row.id) + '">' +
        '<td>' + assetCell(row) + '</td>' +
        '<td>' + routeCell(row) + '</td>' +
        '<td>' + holderCell(row) + '</td>' +
        '<td>' + mutedDate(row.transfer_date) + '</td>' +
        '<td>' + reasonCell(row) + '</td>' +
        '<td>' + statusBadge(row) + '</td>' +
        '<td class="text-center">' + actionsCell(row) + '</td>' +
        '</tr>';
    });

    // Data kosong ditampilkan DataTables lewat opsi bahasa, bukan baris buatan sendiri.
    table.clear();
    if (html !== '') {
      table.rows.add(ui.rowsFromHtml(html));
    }
    table.draw(false);
  }

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    if (xhr.status === 403) {
      return (res && res.message) || 'Hanya administrator yang dapat mencatat mutasi aset.';
    }
    return (res && res.message) || fallback;
  }

  function showBlockError(selector, message) {
    $(selector).html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small mx-4 mt-3 mb-0" role="alert">' +
      '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  function loadTransfers() {
    const table = getTable();
    const search = String($('#transferSearch').val() || '').trim();
    const status = String($('#transferStatusFilter').val() || '');
    const params = {};

    if (search !== '') {
      params.search = search;
    }
    if (status !== '') {
      params.status = status;
    }

    $('#transferError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: API_BASE + '/transfers', type: 'GET', data: params, dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          renderRows(res.data);
          return;
        }

        table.clear().draw(false);
        showBlockError('#transferError', (res && res.message) || 'Gagal memuat data mutasi aset.');
      })
      .fail(function (xhr) {
        table.clear().draw(false);
        showBlockError('#transferError', apiMessage(xhr, 'Gagal memuat data mutasi aset. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

  /* -------------------------- Sumber dropdown -------------------------- */

  /** Label aset seragam di seluruh halaman: kode - nama (lokasi). */
  function assetLabel(asset) {
    const code = String(asset.asset_code || '').trim();
    const name = String(asset.name || '').trim();
    const head = code === '' ? name : code + ' - ' + name;
    const location = String(asset.location_name || '').trim();

    return location === '' ? head : head + ' (' + location + ')';
  }

  function loadAssets() {
    return $.ajax({ url: API_BASE + '/assets', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        // Aset yang sudah dihapus tidak lagi dimutasi.
        const assets = res.data.filter(function (asset) {
          return String(asset.status || '') !== 'disposed';
        });

        let html = '<option value="">Pilih aset</option>';
        assets.forEach(function (asset) {
          html += '<option value="' + escapeHtml(asset.id) + '">' + escapeHtml(assetLabel(asset)) + '</option>';
        });

        if (assets.length === 0) {
          html = '<option value="">Belum ada aset yang dapat dimutasi</option>';
        }

        $('#input-asset_id').html(html);
      })
      .fail(function (xhr) {
        showBlockError('#transferError', apiMessage(xhr, 'Gagal memuat daftar aset. Muat ulang halaman ini ya.'));
      });
  }

  function loadLocations() {
    return $.ajax({ url: API_BASE + '/locations', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        let html = '<option value="">Pilih lokasi tujuan</option>';
        res.data.forEach(function (location) {
          html += '<option value="' + escapeHtml(location.id) + '">' +
            escapeHtml(ui.locationLabel(location)) + '</option>';
        });

        $('#input-to_location_id').html(html);
      })
      .fail(function (xhr) {
        showBlockError('#transferError', apiMessage(xhr, 'Gagal memuat daftar lokasi. Muat ulang halaman ini ya.'));
      });
  }

  /* --------------------------- Modal mutasi --------------------------- */

  function clearFormErrors(form, errorBox) {
    form.querySelectorAll('.is-invalid').forEach(function (field) {
      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('[id^="error-"]').forEach(function (box) {
      box.textContent = '';
      box.hidden = true;
    });
    $(errorBox).empty();
  }

  function showFormError(selector, message) {
    $(selector).html(
      '<div class="alert alert-danger d-flex align-items-start gap-2 py-2 small mb-0" role="alert">' +
      '<i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i><span>' + escapeHtml(message) + '</span>' +
      '</div>'
    );
  }

  /**
   * Menandai input dan pesan error per field; field pertama yang salah difokuskan.
   * Aliases memetakan kunci error server ke nama kontrol lokal bila berbeda.
   */
  function applyFieldErrors(errors, inputs, aliases) {
    const map = aliases || {};
    let firstInvalid = null;

    Object.keys(errors).forEach(function (key) {
      const name = map[key] || key;
      const box = document.getElementById('error-' + name);
      if (box) {
        box.textContent = String(errors[key]);
        box.hidden = false;
      }

      const input = inputs[name];
      if (input) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (firstInvalid === null) {
          firstInvalid = input;
        }
      }
    });

    if (firstInvalid !== null) {
      firstInvalid.focus();
    }
  }

  /**
   * Pesan error field dibersihkan begitu pengguna memperbaiki isian. Aliases
   * memetakan nama kontrol ke kotak error lokal bila keduanya berbeda.
   */
  function fieldStateCleaner(aliases) {
    const map = aliases || {};

    return function (event) {
      const field = event.target;
      if (!field || !field.name) {
        return;
      }

      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
      const box = document.getElementById('error-' + (map[field.name] || field.name));
      if (box) {
        box.textContent = '';
        box.hidden = true;
      }
    };
  }

  /** Tanggal hari ini untuk isian awal; server tetap memvalidasi. */
  function todayIso() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return now.getFullYear() + '-' + month + '-' + day;
  }

  function initCreateForm() {
    const modalElement = document.getElementById('transferModal');
    const form = document.getElementById('formTransfer');
    if (modalElement === null || form === null) {
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const submit = document.getElementById('btnSubmitTransfer');
    const fieldInputs = {
      asset_id: form.elements.asset_id,
      to_location_id: form.elements.to_location_id,
      transfer_date: form.elements.transfer_date,
      reason: form.elements.reason,
      notes: form.elements.notes
    };

    function submitForm(payload) {
      // Tombol nonaktif selama permintaan agar tidak terkirim dua kali.
      submit.disabled = true;

      $.ajax({
        url: API_BASE + '/transfers',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            modal.hide();
            ui.toast(res.message || 'Mutasi aset berhasil dicatat.');
            form.reset();
            submit.disabled = false;
            loadTransfers();
            return;
          }

          submit.disabled = false;
          showFormError('#transferFormError', (res && res.message) || 'Gagal mencatat mutasi aset.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors, fieldInputs);
          }
          showFormError('#transferFormError', apiMessage(xhr, 'Gagal mencatat mutasi aset. Coba lagi ya.'));
          submit.disabled = false;
        });
    }

    modalElement.addEventListener('shown.bs.modal', function () {
      form.elements.asset_id.focus();
    });

    form.addEventListener('input', fieldStateCleaner());
    form.addEventListener('change', fieldStateCleaner());

    $('#btnAddTransfer').on('click', function () {
      if (!isAdmin) {
        return;
      }

      clearFormErrors(form, '#transferFormError');
      form.reset();
      form.elements.transfer_date.value = todayIso();
      submit.disabled = false;
      modal.show();
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!isAdmin) {
        return;
      }

      clearFormErrors(form, '#transferFormError');
      const payload = {
        asset_id: Number(form.elements.asset_id.value || 0),
        to_location_id: Number(form.elements.to_location_id.value || 0)
      };
      const date = String(form.elements.transfer_date.value || '');
      const reason = String(form.elements.reason.value || '').trim();
      const notes = String(form.elements.notes.value || '').trim();

      if (date !== '') {
        payload.transfer_date = date;
      }
      if (reason !== '') {
        payload.reason = reason;
      }
      if (notes !== '') {
        payload.notes = notes;
      }

      submitForm(payload);
    });
  }

  /* ------------------------- Modal pembatalan ------------------------- */

  function initCancelForm() {
    const modalElement = document.getElementById('transferCancelModal');
    const form = document.getElementById('formTransferCancel');
    if (modalElement === null || form === null) {
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const submit = document.getElementById('btnSubmitTransferCancel');
    // Alasan pembatalan memakai kotak error terpisah dari modal mutasi.
    const fieldInputs = { cancel_reason: form.elements.reason };

    function submitCancel(payload) {
      submit.disabled = true;

      $.ajax({
        url: API_BASE + '/transfers/' + encodeURIComponent(cancelTarget.id) + '/cancel',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            modal.hide();
            ui.toast(res.message || 'Mutasi aset berhasil dibatalkan.');
            submit.disabled = false;
            loadTransfers();
            return;
          }

          submit.disabled = false;
          showFormError('#transferCancelFormError', (res && res.message) || 'Gagal membatalkan mutasi aset.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors, fieldInputs, { reason: 'cancel_reason' });
          }
          showFormError('#transferCancelFormError', apiMessage(xhr, 'Gagal membatalkan mutasi aset. Coba lagi ya.'));
          submit.disabled = false;
        });
    }

    modalElement.addEventListener('shown.bs.modal', function () {
      form.elements.reason.focus();
    });

    $(document).on('click', '.btn-transfer-cancel', function () {
      const $button = $(this);
      cancelTarget = {
        id: String($button.data('id') || ''),
        name: String($button.data('name') || ''),
        code: String($button.data('code') || '')
      };

      clearFormErrors(form, '#transferCancelFormError');
      form.reset();
      submit.disabled = false;
      $('#transferCancelAsset').text(cancelTarget.name + (cancelTarget.code !== '' ? ' (' + cancelTarget.code + ')' : ''));
      modal.show();
    });

    form.addEventListener('input', fieldStateCleaner({ reason: 'cancel_reason' }));
    form.addEventListener('change', fieldStateCleaner({ reason: 'cancel_reason' }));

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!isAdmin || cancelTarget === null || cancelTarget.id === '') {
        return;
      }

      clearFormErrors(form, '#transferCancelFormError');
      const reason = String(form.elements.reason.value || '').trim();

      if (reason === '') {
        const box = document.getElementById('error-cancel_reason');
        box.textContent = 'Alasan pembatalan wajib diisi.';
        box.hidden = false;
        form.elements.reason.classList.add('is-invalid');
        form.elements.reason.focus();
        return;
      }

      submitCancel({ reason: reason });
    });
  }

  /* -------------------------------- Filter -------------------------------- */

  $('#transferSearch').on('input search', function () {
    if (searchTimer !== null) {
      window.clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(function () {
      searchTimer = null;
      loadTransfers();
    }, SEARCH_DELAY);
  });

  $('#transferStatusFilter').on('change', loadTransfers);

  // Aksi penyelesaian dikonfirmasi lebih dulu karena mengubah status aset.
  $(document).on('click', '.btn-transfer-complete', function () {
    const $button = $(this);
    const id = String($button.data('id') || '');
    const name = String($button.data('name') || 'aset ini');

    if (!isAdmin || id === '') {
      return;
    }

    ui.confirmDialog({
      title: 'Selesaikan mutasi ini?',
      text: 'Aset "' + name + '" akan dipindahkan ke lokasi tujuan dan mutasi ditandai selesai.',
      confirmText: 'Ya, selesaikan'
    }).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      $button.prop('disabled', true);

      $.ajax({
        url: API_BASE + '/transfers/' + encodeURIComponent(id) + '/complete',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({}),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            ui.toast(res.message || 'Mutasi aset berhasil diselesaikan.');
            loadTransfers();
            return;
          }

          $button.prop('disabled', false);
          showBlockError('#transferError', (res && res.message) || 'Gagal menyelesaikan mutasi aset.');
        })
        .fail(function (xhr) {
          $button.prop('disabled', false);
          showBlockError('#transferError', apiMessage(xhr, 'Gagal menyelesaikan mutasi aset. Coba lagi ya.'));
        });
    });
  });

  // Tiap permintaan berdiri sendiri agar satu kegagalan tidak memblokir bagian lain.
  loadLocations();
  loadAssets();
  loadTransfers();
  initCreateForm();
  initCancelForm();
});
