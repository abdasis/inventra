/**
 * Inventra - Pemeliharaan Aset (daftar)
 * Memuat daftar pemeliharaan (/api/v1/maintenances), menyaringnya, dan mencatat
 * penyelesaian pemeliharaan lewat modal. Pencatatan pemeliharaan baru berfield
 * banyak, jadi memakai halaman /maintenances/create (maintenance-form.js).
 * Hanya dimuat pada halaman /maintenances.
 *
 * Helper kecil (escaping, badge, pembersih error per field) sengaja disalin dari
 * assignments.js: tidak ada bundler maupun berkas modul bersama, dan duplikasi
 * sekecil ini lebih murah daripada pola baru.
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.
  const FLASH_KEY = 'inventra.maintenances.flash';
  const page = window.INVENTRA_MAINTENANCE || {};
  const ui = window.InventraUI;

  if (!ui || document.getElementById('maintenanceTableBody') === null) {
    return;
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;
  // Penyelesaian pemeliharaan hanya boleh dicatat administrator.
  const isAdmin = page.isAdmin === true;

  // Label dan kelas badge disediakan API; peta ini hanya sandaran bila belum ada.
  const STATUS_BADGES = {
    ongoing: ['Berjalan', 'bg-warning-subtle text-warning border border-warning-subtle'],
    completed: ['Selesai', 'bg-success-subtle text-success border border-success-subtle'],
    cancelled: ['Dibatalkan', 'bg-danger-subtle text-danger border border-danger-subtle']
  };

  // Kelas badge per jenis perawatan; labelnya tetap dari API.
  const TYPE_BADGES = {
    preventive: 'bg-info-subtle text-info border border-info-subtle',
    corrective: 'bg-warning-subtle text-warning border border-warning-subtle',
    inspection: 'bg-secondary-subtle text-secondary border border-secondary-subtle'
  };

  let maintenanceTable = null;
  let searchTimer = null;
  let completeTarget = null; // { id, name, code } baris yang sedang diselesaikan.

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

  function typeBadge(row) {
    const key = String(row.maintenance_type || '');
    const label = String(row.maintenance_type_label || '').trim();

    if (label === '') {
      return '<span class="text-muted">-</span>';
    }

    return badge(label, TYPE_BADGES[key] || 'bg-secondary-subtle text-secondary border border-secondary-subtle');
  }

  function assetCell(row) {
    return '<div class="fw-semibold text-dark">' + escapeHtml(row.asset_name || '-') + '</div>' +
      '<div class="small text-muted font-monospace">' + escapeHtml(row.asset_code || '-') + '</div>' +
      (String(row.damage_description || '').trim() !== ''
        ? '<div class="small text-muted text-wrap-pretty">' + escapeHtml(row.damage_description) + '</div>'
        : '');
  }

  function formatRupiah(value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
  }

  function mutedDate(value) {
    return value ? '<span class="small text-muted">' + escapeHtml(value) + '</span>' : '<span class="text-muted">-</span>';
  }

  function vendorCell(row) {
    const vendor = String(row.vendor || '').trim();
    const performedBy = String(row.performed_by_name || '').trim();

    if (vendor === '') {
      return performedBy === ''
        ? '<span class="text-muted">-</span>'
        : '<div class="small text-muted">Dikerjakan ' + escapeHtml(performedBy) + '</div>';
    }

    return '<div class="fw-semibold text-dark">' + escapeHtml(vendor) + '</div>' +
      (performedBy !== '' ? '<div class="small text-muted">Dicatat oleh ' + escapeHtml(performedBy) + '</div>' : '');
  }

  /** Hasil pekerjaan menyertai badge status bila sudah terisi. */
  function resultNote(row) {
    const result = String(row.work_result || '').trim();

    return result === ''
      ? ''
      : '<div class="small text-muted text-wrap-pretty mt-1">' + escapeHtml(result) + '</div>';
  }

  /** Aksi per baris hanya untuk administrator dan pemeliharaan yang berjalan. */
  function actionsCell(row) {
    if (!isAdmin || String(row.status || '') !== 'ongoing') {
      return '<span class="text-muted">-</span>';
    }

    const name = escapeHtml(row.asset_name || 'aset ini');

    return '<div class="table-actions d-inline-flex align-items-center">' +
      '<button type="button" class="btn btn-sm btn-light border px-2 btn-maintenance-complete"' +
      ' data-id="' + escapeHtml(row.id) + '" data-name="' + name +
      '" data-code="' + escapeHtml(row.asset_code || '') + '"' +
      ' data-cost="' + escapeHtml(row.cost === null || row.cost === undefined ? '' : row.cost) + '"' +
      ' data-next="' + escapeHtml(row.next_schedule || '') + '"' +
      ' title="Catat penyelesaian pemeliharaan ' + name + '">' +
      '<i class="bi bi-check2-circle" aria-hidden="true"></i> Selesaikan</button>' +
      '</div>';
  }

  /* -------------------------------- Daftar -------------------------------- */

  /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
  function getTable() {
    if (maintenanceTable === null) {
      maintenanceTable = $('#tableMaintenance').DataTable({
        searching: false, // Pencarian server-side lewat ?search=
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: false, // Urutan baris mengikuti API.
        info: true,
        order: [],
        language: ui.tableLanguage('pemeliharaan aset')
      });
    }

    return maintenanceTable;
  }

  function renderRows(rows) {
    const table = getTable();
    let html = '';

    rows.forEach(function (row) {
      html += '<tr data-id="' + escapeHtml(row.id) + '">' +
        '<td>' + assetCell(row) + '</td>' +
        '<td>' + typeBadge(row) + '</td>' +
        '<td>' + mutedDate(row.maintenance_date) + '</td>' +
        '<td>' + vendorCell(row) + '</td>' +
        '<td class="text-end fw-semibold text-dark">' +
        escapeHtml(row.cost_formatted || formatRupiah(row.cost)) + '</td>' +
        '<td>' + mutedDate(row.next_schedule) + '</td>' +
        '<td>' + statusBadge(row) + resultNote(row) + '</td>' +
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
      return (res && res.message) || 'Hanya administrator yang dapat mencatat pemeliharaan aset.';
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

  function loadMaintenances() {
    const table = getTable();
    const search = String($('#maintenanceSearch').val() || '').trim();
    const status = String($('#maintenanceStatusFilter').val() || '');
    const params = {};

    if (search !== '') {
      params.search = search;
    }
    if (status !== '') {
      params.status = status;
    }

    $('#maintenanceError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: API_BASE + '/maintenances', type: 'GET', data: params, dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          renderRows(res.data);
          return;
        }

        table.clear().draw(false);
        showBlockError('#maintenanceError', (res && res.message) || 'Gagal memuat data pemeliharaan aset.');
      })
      .fail(function (xhr) {
        table.clear().draw(false);
        showBlockError('#maintenanceError', apiMessage(xhr, 'Gagal memuat data pemeliharaan aset. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

  /* --------------------------- Modal penyelesaian --------------------------- */

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

  /** Menandai input dan pesan error per field; field pertama yang salah difokuskan. */
  function applyFieldErrors(errors, inputs) {
    let firstInvalid = null;

    Object.keys(errors).forEach(function (key) {
      const box = document.getElementById('error-' + key);
      if (box) {
        box.textContent = String(errors[key]);
        box.hidden = false;
      }

      const input = inputs[key];
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

  function fieldStateCleaner() {
    return function (event) {
      const field = event.target;
      if (!field || !field.name) {
        return;
      }

      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
      const box = document.getElementById('error-' + field.name);
      if (box) {
        box.textContent = '';
        box.hidden = true;
      }
    };
  }

  function initCompleteForm() {
    const modalElement = document.getElementById('maintenanceCompleteModal');
    const form = document.getElementById('formMaintenanceComplete');
    if (modalElement === null || form === null) {
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const submit = document.getElementById('btnSubmitMaintenanceComplete');
    const fieldInputs = {
      work_result: form.elements.work_result,
      cost: form.elements.cost,
      next_schedule: form.elements.next_schedule,
      notes: form.elements.notes
    };

    function submitForm(payload) {
      // Tombol nonaktif selama permintaan agar tidak terkirim dua kali.
      submit.disabled = true;

      $.ajax({
        url: API_BASE + '/maintenances/' + encodeURIComponent(completeTarget.id) + '/complete',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            modal.hide();
            ui.toast(res.message || 'Penyelesaian pemeliharaan berhasil dicatat.');
            submit.disabled = false;
            loadMaintenances();
            return;
          }

          submit.disabled = false;
          showFormError('#maintenanceCompleteFormError', (res && res.message) || 'Gagal mencatat penyelesaian pemeliharaan.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors, fieldInputs);
          }
          showFormError('#maintenanceCompleteFormError', apiMessage(xhr, 'Gagal mencatat penyelesaian pemeliharaan. Coba lagi ya.'));
          submit.disabled = false;
        });
    }

    modalElement.addEventListener('shown.bs.modal', function () {
      form.elements.work_result.focus();
    });

    $(document).on('click', '.btn-maintenance-complete', function () {
      const $button = $(this);
      completeTarget = {
        id: String($button.data('id') || ''),
        name: String($button.data('name') || ''),
        code: String($button.data('code') || '')
      };

      clearFormErrors(form, '#maintenanceCompleteFormError');
      form.reset();
      submit.disabled = false;
      // Perkiraan biaya dan jadwal dari baris dibawa sebagai isian awal.
      form.elements.cost.value = String($button.data('cost') || '');
      form.elements.next_schedule.value = String($button.data('next') || '');
      $('#maintenanceCompleteAsset').text(
        completeTarget.name + (completeTarget.code !== '' ? ' (' + completeTarget.code + ')' : '')
      );
      modal.show();
    });

    form.addEventListener('input', fieldStateCleaner());
    form.addEventListener('change', fieldStateCleaner());

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!isAdmin || completeTarget === null || completeTarget.id === '') {
        return;
      }

      clearFormErrors(form, '#maintenanceCompleteFormError');
      const result = String(form.elements.work_result.value || '').trim();

      if (result === '') {
        const box = document.getElementById('error-work_result');
        box.textContent = 'Hasil pekerjaan wajib diisi.';
        box.hidden = false;
        form.elements.work_result.classList.add('is-invalid');
        form.elements.work_result.focus();
        return;
      }

      const cost = String(form.elements.cost.value || '').trim();
      const nextSchedule = String(form.elements.next_schedule.value || '');
      const notes = String(form.elements.notes.value || '').trim();
      const payload = { work_result: result };

      if (cost !== '') {
        payload.cost = Number(cost);
      }
      if (nextSchedule !== '') {
        payload.next_schedule = nextSchedule;
      }
      if (notes !== '') {
        payload.notes = notes;
      }

      submitForm(payload);
    });
  }

  /* -------------------------------- Filter -------------------------------- */

  /** Pesan hasil simpan dari halaman form dititipkan lewat sessionStorage. */
  function consumeFlash() {
    try {
      const message = sessionStorage.getItem(FLASH_KEY);
      if (!message) {
        return;
      }
      sessionStorage.removeItem(FLASH_KEY);
      ui.toast(message);
    } catch (error) {
      // Penyimpanan sesi tidak tersedia.
    }
  }

  $('#maintenanceSearch').on('input search', function () {
    if (searchTimer !== null) {
      window.clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(function () {
      searchTimer = null;
      loadMaintenances();
    }, SEARCH_DELAY);
  });

  $('#maintenanceStatusFilter').on('change', loadMaintenances);

  consumeFlash();
  loadMaintenances();
  initCompleteForm();
});
