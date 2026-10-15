/**
 * Inventra - Penempatan Aset
 * Memuat daftar penempatan (/api/v1/assignments), menyaringnya, serta mencatat
 * penempatan baru dan pengembaliannya lewat dua modal. Hanya dimuat pada halaman
 * /assignments. Notifikasi, dialog, dan bahasa DataTables memakai helper bersama
 * dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.
  const page = window.INVENTRA_ASSIGNMENT || {};
  const ui = window.InventraUI;

  if (!ui || document.getElementById('assignmentTableBody') === null) {
    return;
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;
  // Penempatan hanya boleh dicatat administrator; staf cukup melihat daftarnya.
  const isAdmin = page.isAdmin === true;

  // Label dan kelas badge disediakan API; peta ini hanya sandaran bila belum ada.
  const STATUS_BADGES = {
    active: ['Aktif', 'bg-primary-subtle text-primary border border-primary-subtle'],
    returned: ['Dikembalikan', 'bg-success-subtle text-success border border-success-subtle']
  };

  // Label mengikuti Enum AssetCondition milik API.
  const CONDITIONS = {
    new: ['Sangat Baik (Baru)', 'bg-info-subtle text-info border border-info-subtle'],
    good: ['Baik (Normal)', 'bg-success-subtle text-success border border-success-subtle'],
    fair: ['Cukup (Ada Aus)', 'bg-warning-subtle text-warning border border-warning-subtle'],
    damaged: ['Rusak', 'bg-danger-subtle text-danger border border-danger-subtle']
  };

  let assignmentTable = null;
  let searchTimer = null;
  let availableAssets = []; // Aset berstatus tersedia, sumber dropdown modal.
  let returnTarget = null; // { id, name, code } baris yang sedang dikembalikan.

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

  function conditionBadge(value, label) {
    const key = String(value === null || value === undefined ? '' : value);
    if (key === '') {
      return '<span class="text-muted">-</span>';
    }

    const fallback = CONDITIONS[key] || [];
    return badge(
      String(label || fallback[0] || key),
      fallback[1] || 'bg-secondary-subtle text-secondary border border-secondary-subtle'
    );
  }

  function assetCell(row) {
    return '<div class="fw-semibold text-dark">' + escapeHtml(row.asset_name || '-') + '</div>' +
      '<div class="small text-muted font-monospace">' + escapeHtml(row.asset_code || '-') + '</div>';
  }

  function holderCell(row) {
    const employee = String(row.employee_name || '').trim();
    const receiver = String(row.received_by_name || '').trim();
    const holder = employee !== '' ? employee : receiver;

    if (holder === '') {
      return '<span class="text-muted">-</span>';
    }

    const handedBy = String(row.handed_by_name || '').trim();

    return '<div class="fw-semibold text-dark">' + escapeHtml(holder) + '</div>' +
      (handedBy !== '' ? '<div class="small text-muted">Diserahkan oleh ' + escapeHtml(handedBy) + '</div>' : '');
  }

  function locationCell(row) {
    if (!row.location_name) {
      return '<span class="text-muted">-</span>';
    }

    return '<i class="bi bi-geo-alt text-muted me-1" aria-hidden="true"></i>' + escapeHtml(row.location_name);
  }

  function conditionCell(row) {
    return '<div class="d-flex flex-wrap align-items-center gap-1">' +
      conditionBadge(row.condition_out, row.condition_out_label) +
      '<i class="bi bi-arrow-right small text-muted" aria-hidden="true"></i>' +
      '<span class="visually-hidden">berubah menjadi</span>' +
      conditionBadge(row.condition_in, row.condition_in_label) +
      '</div>';
  }

  /** Aksi tulis hanya administrator; tautan riwayat selalu tersedia. */
  function actionsCell(row) {
    const name = escapeHtml(row.asset_name || 'aset ini');
    let html = '';

    if (isAdmin && String(row.status || '') === 'active') {
      html += '<button type="button" class="btn btn-sm btn-light border px-2 btn-assignment-return"' +
        ' data-id="' + escapeHtml(row.id) + '" data-name="' + name +
        '" data-code="' + escapeHtml(row.asset_code || '') + '"' +
        ' title="Catat pengembalian ' + name + '">' +
        '<i class="bi bi-box-arrow-in-left" aria-hidden="true"></i> Kembalikan</button>';
    }

    html += '<a href="/assets/' + escapeHtml(row.asset_id) + '" class="btn btn-sm btn-light border px-2"' +
      ' title="Lihat riwayat aset ' + name + '" aria-label="Lihat riwayat aset ' + name + '">' +
      '<i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat</a>';

    return '<div class="table-actions d-inline-flex align-items-center">' + html + '</div>';
  }

  /* -------------------------------- Daftar -------------------------------- */

  /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
  function getTable() {
    if (assignmentTable === null) {
      assignmentTable = $('#tableAssignment').DataTable({
        searching: false, // Pencarian server-side lewat ?search=
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: false, // Urutan baris mengikuti API.
        info: true,
        order: [],
        language: ui.tableLanguage('penempatan aset')
      });
    }

    return assignmentTable;
  }

  function renderRows(rows) {
    const table = getTable();
    let html = '';

    rows.forEach(function (row) {
      html += '<tr data-id="' + escapeHtml(row.id) + '">' +
        '<td>' + assetCell(row) + '</td>' +
        '<td>' + holderCell(row) + '</td>' +
        '<td>' + locationCell(row) + '</td>' +
        '<td><span class="small text-muted">' + escapeHtml(row.assigned_at || '-') + '</span></td>' +
        '<td>' + conditionCell(row) + '</td>' +
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
      return (res && res.message) || 'Hanya administrator yang dapat mencatat penempatan aset.';
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

  function loadAssignments() {
    const table = getTable();
    const search = String($('#assignmentSearch').val() || '').trim();
    const status = String($('#assignmentStatusFilter').val() || '');
    const params = {};

    if (search !== '') {
      params.search = search;
    }
    if (status !== '') {
      params.status = status;
    }

    $('#assignmentError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: API_BASE + '/assignments', type: 'GET', data: params, dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          renderRows(res.data);
          return;
        }

        table.clear().draw(false);
        showBlockError('#assignmentError', (res && res.message) || 'Gagal memuat data penempatan.');
      })
      .fail(function (xhr) {
        table.clear().draw(false);
        showBlockError('#assignmentError', apiMessage(xhr, 'Gagal memuat data penempatan. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

  /* -------------------------- Sumber dropdown -------------------------- */

  function locationOptions() {
    return $.ajax({ url: API_BASE + '/locations', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        let html = '<option value="">Pilih lokasi</option>';
        res.data.forEach(function (location) {
          html += '<option value="' + escapeHtml(location.id) + '">' +
            escapeHtml(ui.locationLabel(location)) + '</option>';
        });

        $('#input-location_id').html(html);
        $('#input-return_location_id').html(html);
      });
  }

  function loadAssets() {
    return $.ajax({ url: API_BASE + '/assets', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        // Aset yang sedang dipakai atau dipinjam ditolak server; dropdown hanya
        // menawarkan yang berstatus tersedia agar tidak menabrak penolakan itu.
        availableAssets = res.data.filter(function (asset) {
          return String(asset.status || '') === 'available';
        });

        let html = '<option value="">Pilih aset</option>';
        availableAssets.forEach(function (asset) {
          html += '<option value="' + escapeHtml(asset.id) + '">' +
            escapeHtml(asset.asset_code || '') + ' - ' + escapeHtml(asset.name || '') + '</option>';
        });

        if (availableAssets.length === 0) {
          html = '<option value="">Belum ada aset berstatus tersedia</option>';
        }

        $('#input-asset_id').html(html);
      })
      .fail(function (xhr) {
        showBlockError('#assignmentError', apiMessage(xhr, 'Gagal memuat daftar aset. Muat ulang halaman ini ya.'));
      });
  }

  function loadEmployees() {
    return $.ajax({ url: API_BASE + '/employees', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        const employees = res.data.filter(function (employee) {
          return employee.is_active === undefined || Number(employee.is_active) === 1;
        });

        let html = '<option value="">Pilih karyawan</option>';

        if (employees.length > 0) {
          html += '<optgroup label="Karyawan">';
          employees.forEach(function (employee) {
            html += '<option value="emp:' + escapeHtml(employee.id) + '">' + escapeHtml(employee.name) +
              (employee.department ? ' - ' + escapeHtml(employee.department) : '') + '</option>';
          });
          html += '</optgroup>';
        }

        html += '<option value="free">Penerima lain (isi nama sendiri)</option>';

        $('#input-holder').html(html);
      })
      .fail(function (xhr) {
        showBlockError('#assignmentError', apiMessage(xhr, 'Gagal memuat daftar karyawan. Muat ulang halaman ini ya.'));
      });
  }

  /* --------------------------- Modal penempatan --------------------------- */

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

  function initCreateForm() {
    const modalElement = document.getElementById('assignmentModal');
    const form = document.getElementById('formAssignment');
    if (modalElement === null || form === null) {
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const submit = document.getElementById('btnSubmitAssignment');
    const holderSelect = form.elements.holder;
    const nameInput = form.elements.received_by_name;
    const nameWrapper = document.getElementById('holderNameWrapper');
    // Error employee_id dan received_by_name diarahkan ke kontrol pemegangnya.
    const fieldInputs = {
      asset_id: form.elements.asset_id,
      employee_id: holderSelect,
      received_by_name: nameInput,
      location_id: form.elements.location_id,
      condition_out: form.elements.condition_out,
      notes: form.elements.notes
    };

    /** Satu kontrol pemegang melayani dua muatan: karyawan atau nama bebas. */
    function syncHolder() {
      const free = String(holderSelect.value || '') === 'free';
      nameWrapper.hidden = !free;
      nameInput.required = free;

      if (!free) {
        nameInput.value = '';
      }
    }

    function buildPayload() {
      const holder = String(holderSelect.value || '');
      const payload = {
        asset_id: Number(form.elements.asset_id.value || 0),
        location_id: Number(form.elements.location_id.value || 0),
        notes: String(form.elements.notes.value || '').trim()
      };
      const condition = String(form.elements.condition_out.value || '');

      if (condition !== '') {
        payload.condition_out = condition;
      }

      if (holder.indexOf('emp:') === 0) {
        payload.employee_id = Number(holder.slice(4));
      }

      if (holder === 'free') {
        payload.received_by_name = String(nameInput.value || '').trim();
      }

      return payload;
    }

    function submitForm(payload) {
      // Tombol nonaktif selama permintaan agar tidak terkirim dua kali.
      submit.disabled = true;

      $.ajax({
        url: API_BASE + '/assignments',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            modal.hide();
            ui.toast(res.message || 'Penempatan aset berhasil dicatat.');
            form.reset();
            syncHolder();
            submit.disabled = false;
            loadAssignments();
            // Aset yang baru ditempatkan tidak lagi berstatus tersedia.
            loadAssets();
            return;
          }

          submit.disabled = false;
          showFormError('#assignmentFormError', (res && res.message) || 'Gagal mencatat penempatan aset.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors, fieldInputs);
          }
          showFormError('#assignmentFormError', apiMessage(xhr, 'Gagal mencatat penempatan aset. Coba lagi ya.'));
          submit.disabled = false;
        });
    }

    modalElement.addEventListener('shown.bs.modal', function () {
      form.elements.asset_id.focus();
    });

    holderSelect.addEventListener('change', syncHolder);
    form.addEventListener('input', fieldStateCleaner());
    form.addEventListener('change', fieldStateCleaner());

    $('#btnAddAssignment').on('click', function () {
      if (!isAdmin) {
        return;
      }

      clearFormErrors(form, '#assignmentFormError');
      form.reset();
      syncHolder();
      submit.disabled = false;
      modal.show();
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!isAdmin) {
        return;
      }

      clearFormErrors(form, '#assignmentFormError');
      const payload = buildPayload();

      // Nama penerima bebas tidak dijamin server lewat field employee_id.
      if (payload.received_by_name !== undefined && payload.received_by_name === '') {
        const box = document.getElementById('error-received_by_name');
        box.textContent = 'Nama penerima wajib diisi bila bukan karyawan terdaftar.';
        box.hidden = false;
        nameInput.classList.add('is-invalid');
        nameInput.focus();
        return;
      }

      submitForm(payload);
    });
  }

  /* ------------------------- Modal pengembalian ------------------------- */

  function initReturnForm() {
    const modalElement = document.getElementById('assignmentReturnModal');
    const form = document.getElementById('formAssignmentReturn');
    if (modalElement === null || form === null) {
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const submit = document.getElementById('btnSubmitAssignmentReturn');
    // Catatan pada modal ini memakai kotak error terpisah dari modal penempatan.
    const fieldInputs = {
      return_location_id: form.elements.return_location_id,
      condition_in: form.elements.condition_in,
      return_notes: form.elements.notes
    };

    function submitReturn(payload) {
      submit.disabled = true;

      $.ajax({
        url: API_BASE + '/assignments/' + encodeURIComponent(returnTarget.id) + '/return',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            modal.hide();
            ui.toast(res.message || 'Pengembalian aset berhasil dicatat.');
            submit.disabled = false;
            loadAssignments();
            loadAssets();
            return;
          }

          submit.disabled = false;
          showFormError('#assignmentReturnFormError', (res && res.message) || 'Gagal mencatat pengembalian aset.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors, fieldInputs, { notes: 'return_notes' });
          }
          showFormError('#assignmentReturnFormError', apiMessage(xhr, 'Gagal mencatat pengembalian aset. Coba lagi ya.'));
          submit.disabled = false;
        });
    }

    modalElement.addEventListener('shown.bs.modal', function () {
      form.elements.return_location_id.focus();
    });

    $(document).on('click', '.btn-assignment-return', function () {
      const $button = $(this);
      returnTarget = {
        id: String($button.data('id') || ''),
        name: String($button.data('name') || ''),
        code: String($button.data('code') || '')
      };

      clearFormErrors(form, '#assignmentReturnFormError');
      form.reset();
      submit.disabled = false;
      $('#assignmentReturnAsset').text(returnTarget.name + (returnTarget.code !== '' ? ' (' + returnTarget.code + ')' : ''));
      modal.show();
    });

    form.addEventListener('input', fieldStateCleaner({ notes: 'return_notes' }));
    form.addEventListener('change', fieldStateCleaner({ notes: 'return_notes' }));

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!isAdmin || returnTarget === null || returnTarget.id === '') {
        return;
      }

      clearFormErrors(form, '#assignmentReturnFormError');
      const payload = {
        return_location_id: Number(form.elements.return_location_id.value || 0),
        notes: String(form.elements.notes.value || '').trim()
      };
      const condition = String(form.elements.condition_in.value || '');

      if (condition !== '') {
        payload.condition_in = condition;
      }

      submitReturn(payload);
    });
  }

  /* -------------------------------- Filter -------------------------------- */

  $('#assignmentSearch').on('input search', function () {
    if (searchTimer !== null) {
      window.clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(function () {
      searchTimer = null;
      loadAssignments();
    }, SEARCH_DELAY);
  });

  $('#assignmentStatusFilter').on('change', loadAssignments);

  // Tiap permintaan berdiri sendiri agar satu kegagalan tidak memblokir bagian lain.
  loadEmployees();
  locationOptions();
  loadAssets();
  loadAssignments();
  initCreateForm();
  initReturnForm();
});
