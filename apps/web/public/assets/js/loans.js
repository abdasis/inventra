/**
 * Inventra - Peminjaman Aset
 * Memuat daftar peminjaman (/api/v1/loans), menyaringnya (termasuk penanda
 * terlambat), serta mencatat peminjaman baru dan pengembaliannya lewat dua
 * modal. Hanya dimuat pada halaman /loans. Notifikasi, dialog, dan bahasa
 * DataTables memakai helper bersama dari app.js (window.InventraUI).
 *
 * Helper kecil (escaping, badge, pembersih error per field) sengaja disalin dari
 * assignments.js: tidak ada bundler maupun berkas modul bersama, dan duplikasi
 * sekecil ini lebih murah daripada pola baru.
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.
  const ui = window.InventraUI;

  if (!ui || document.getElementById('loanTableBody') === null) {
    return;
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;

  // Label dan kelas badge disediakan API; peta ini hanya sandaran bila belum ada.
  const STATUS_BADGES = {
    active: ['Dipinjam', 'bg-primary-subtle text-primary border border-primary-subtle'],
    returned: ['Dikembalikan', 'bg-success-subtle text-success border border-success-subtle']
  };

  // Label mengikuti Enum AssetCondition milik API.
  const CONDITIONS = {
    new: ['Sangat Baik (Baru)', 'bg-info-subtle text-info border border-info-subtle'],
    good: ['Baik (Normal)', 'bg-success-subtle text-success border border-success-subtle'],
    fair: ['Cukup (Ada Aus)', 'bg-warning-subtle text-warning border border-warning-subtle'],
    damaged: ['Rusak', 'bg-danger-subtle text-danger border border-danger-subtle']
  };

  const OVERDUE_BADGE = 'bg-danger-subtle text-danger border border-danger-subtle';

  let loanTable = null;
  let searchTimer = null;
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

  function borrowerCell(row) {
    const employee = String(row.employee_name || '').trim();
    const borrower = String(row.borrower_name || '').trim();
    const name = employee !== '' ? employee : borrower;

    if (name === '') {
      return '<span class="text-muted">-</span>';
    }

    const handedBy = String(row.handed_by_name || '').trim();

    return '<div class="fw-semibold text-dark">' + escapeHtml(name) + '</div>' +
      (handedBy !== '' ? '<div class="small text-muted">Diserahkan oleh ' + escapeHtml(handedBy) + '</div>' : '');
  }

  /** Jatuh tempo disertai penanda tegas bila peminjaman sudah terlambat. */
  function dueCell(row) {
    if (!row.due_at) {
      return '<span class="text-muted">Tanpa tenggat</span>';
    }

    const overdue = Number(row.is_overdue) === 1 || row.is_overdue === true;

    return '<div class="small' + (overdue ? ' fw-semibold text-danger' : ' text-muted') + '">' +
      escapeHtml(row.due_at) + '</div>' +
      (overdue ? '<div class="mt-1">' + badge('Terlambat', OVERDUE_BADGE) + '</div>' : '');
  }

  function conditionCell(row) {
    return '<div class="d-flex flex-wrap align-items-center gap-1">' +
      conditionBadge(row.condition_out, row.condition_out_label) +
      '<i class="bi bi-arrow-right small text-muted" aria-hidden="true"></i>' +
      '<span class="visually-hidden">berubah menjadi</span>' +
      conditionBadge(row.condition_in, row.condition_in_label) +
      '</div>';
  }

  function mutedDate(value) {
    return value ? '<span class="small text-muted">' + escapeHtml(value) + '</span>' : '<span class="text-muted">-</span>';
  }

  function actionsCell(row) {
    const name = escapeHtml(row.asset_name || 'aset ini');
    let html = '';

    if (String(row.status || '') === 'active') {
      html += '<button type="button" class="btn btn-sm btn-light border px-2 btn-loan-return"' +
        ' data-id="' + escapeHtml(row.id) + '" data-name="' + name +
        '" data-code="' + escapeHtml(row.asset_code || '') + '"' +
        ' title="Catat pengembalian ' + name + '">' +
        '<i class="bi bi-box-arrow-in-left" aria-hidden="true"></i> Kembalikan</button>';
    }

    html += '<a href="/assets/' + escapeHtml(row.asset_id) + '" class="btn btn-sm btn-light border px-2"' +
      ' title="Lihat detail aset ' + name + '" aria-label="Lihat detail aset ' + name + '">' +
      '<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Detail</a>';

    return '<div class="table-actions d-inline-flex align-items-center">' + html + '</div>';
  }

  /* -------------------------------- Daftar -------------------------------- */

  /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
  function getTable() {
    if (loanTable === null) {
      loanTable = $('#tableLoan').DataTable({
        searching: false, // Pencarian server-side lewat ?search=
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: false, // Urutan baris mengikuti API.
        info: true,
        order: [],
        language: ui.tableLanguage('peminjaman aset')
      });
    }

    return loanTable;
  }

  function renderRows(rows) {
    const table = getTable();
    let html = '';

    rows.forEach(function (row) {
      html += '<tr data-id="' + escapeHtml(row.id) + '">' +
        '<td>' + assetCell(row) + '</td>' +
        '<td>' + borrowerCell(row) + '</td>' +
        '<td>' + dueCell(row) + '</td>' +
        '<td>' + mutedDate(row.loaned_at) + '</td>' +
        '<td>' + mutedDate(row.returned_at) + '</td>' +
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

  function loadLoans() {
    const table = getTable();
    const search = String($('#loanSearch').val() || '').trim();
    const status = String($('#loanStatusFilter').val() || '');
    const params = {};

    if (search !== '') {
      params.search = search;
    }
    if (status !== '') {
      params.status = status;
    }
    if (document.getElementById('loanOverdueOnly').checked) {
      params.overdue = 1;
    }

    $('#loanError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: API_BASE + '/loans', type: 'GET', data: params, dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          renderRows(res.data);
          return;
        }

        table.clear().draw(false);
        showBlockError('#loanError', (res && res.message) || 'Gagal memuat data peminjaman.');
      })
      .fail(function (xhr) {
        table.clear().draw(false);
        showBlockError('#loanError', apiMessage(xhr, 'Gagal memuat data peminjaman. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

  /* -------------------------- Sumber dropdown -------------------------- */

  function loadLocations() {
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

        $('#input-return_location_id').html(html);
      });
  }

  function loadAssets() {
    return $.ajax({ url: API_BASE + '/assets', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        // Aset yang sedang dipinjam atau ditempatkan ditolak server dengan pesan
        // jelas; dropdown hanya menawarkan yang tersedia agar tidak menabraknya.
        const available = res.data.filter(function (asset) {
          return String(asset.status || '') === 'available';
        });

        let html = '<option value="">Pilih aset</option>';
        available.forEach(function (asset) {
          html += '<option value="' + escapeHtml(asset.id) + '">' +
            escapeHtml(asset.asset_code || '') + ' - ' + escapeHtml(asset.name || '') + '</option>';
        });

        if (available.length === 0) {
          html = '<option value="">Belum ada aset berstatus tersedia</option>';
        }

        $('#input-asset_id').html(html);
      })
      .fail(function (xhr) {
        showBlockError('#loanError', apiMessage(xhr, 'Gagal memuat daftar aset. Muat ulang halaman ini ya.'));
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

        html += '<option value="free">Peminjam lain (isi nama sendiri)</option>';

        $('#input-holder').html(html);
      })
      .fail(function (xhr) {
        showBlockError('#loanError', apiMessage(xhr, 'Gagal memuat daftar karyawan. Muat ulang halaman ini ya.'));
      });
  }

  /* --------------------------- Modal peminjaman --------------------------- */

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
    const modalElement = document.getElementById('loanModal');
    const form = document.getElementById('formLoan');
    if (modalElement === null || form === null) {
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const submit = document.getElementById('btnSubmitLoan');
    const holderSelect = form.elements.holder;
    const nameInput = form.elements.borrower_name;
    const nameWrapper = document.getElementById('holderNameWrapper');
    // Error employee_id dan borrower_name diarahkan ke kontrol peminjamnya.
    const fieldInputs = {
      asset_id: form.elements.asset_id,
      employee_id: holderSelect,
      borrower_name: nameInput,
      due_at: form.elements.due_at,
      condition_out: form.elements.condition_out,
      purpose: form.elements.purpose
    };

    /** Satu kontrol peminjam melayani dua muatan: karyawan atau nama bebas. */
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
        purpose: String(form.elements.purpose.value || '').trim()
      };
      const dueAt = String(form.elements.due_at.value || '');
      const condition = String(form.elements.condition_out.value || '');

      if (dueAt !== '') {
        payload.due_at = dueAt;
      }
      if (condition !== '') {
        payload.condition_out = condition;
      }

      if (holder.indexOf('emp:') === 0) {
        payload.employee_id = Number(holder.slice(4));
      }

      if (holder === 'free') {
        payload.borrower_name = String(nameInput.value || '').trim();
      }

      return payload;
    }

    function submitForm(payload) {
      // Tombol nonaktif selama permintaan agar tidak terkirim dua kali.
      submit.disabled = true;

      $.ajax({
        url: API_BASE + '/loans',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            modal.hide();
            ui.toast(res.message || 'Peminjaman aset berhasil dicatat.');
            form.reset();
            syncHolder();
            submit.disabled = false;
            loadLoans();
            // Aset yang baru dipinjam tidak lagi berstatus tersedia.
            loadAssets();
            return;
          }

          submit.disabled = false;
          showFormError('#loanFormError', (res && res.message) || 'Gagal mencatat peminjaman aset.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors, fieldInputs);
          }
          // Penolakan server (mis. aset sedang dipinjam) ditampilkan apa adanya.
          showFormError('#loanFormError', apiMessage(xhr, 'Gagal mencatat peminjaman aset. Coba lagi ya.'));
          submit.disabled = false;
        });
    }

    modalElement.addEventListener('shown.bs.modal', function () {
      form.elements.asset_id.focus();
    });

    holderSelect.addEventListener('change', syncHolder);
    form.addEventListener('input', fieldStateCleaner());
    form.addEventListener('change', fieldStateCleaner());

    $('#btnAddLoan').on('click', function () {
      clearFormErrors(form, '#loanFormError');
      form.reset();
      syncHolder();
      submit.disabled = false;
      modal.show();
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      clearFormErrors(form, '#loanFormError');
      const payload = buildPayload();

      // Nama peminjam bebas tidak dijamin server lewat field employee_id.
      if (payload.borrower_name !== undefined && payload.borrower_name === '') {
        const box = document.getElementById('error-borrower_name');
        box.textContent = 'Nama peminjam wajib diisi bila bukan karyawan terdaftar.';
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
    const modalElement = document.getElementById('loanReturnModal');
    const form = document.getElementById('formLoanReturn');
    if (modalElement === null || form === null) {
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const submit = document.getElementById('btnSubmitLoanReturn');
    // Catatan pada modal ini memakai kotak error terpisah dari modal peminjaman.
    const fieldInputs = {
      return_location_id: form.elements.return_location_id,
      condition_in: form.elements.condition_in,
      return_notes: form.elements.notes
    };

    function submitReturn(payload) {
      submit.disabled = true;

      $.ajax({
        url: API_BASE + '/loans/' + encodeURIComponent(returnTarget.id) + '/return',
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
            loadLoans();
            loadAssets();
            return;
          }

          submit.disabled = false;
          showFormError('#loanReturnFormError', (res && res.message) || 'Gagal mencatat pengembalian aset.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors, fieldInputs, { notes: 'return_notes' });
          }
          showFormError('#loanReturnFormError', apiMessage(xhr, 'Gagal mencatat pengembalian aset. Coba lagi ya.'));
          submit.disabled = false;
        });
    }

    modalElement.addEventListener('shown.bs.modal', function () {
      form.elements.return_location_id.focus();
    });

    $(document).on('click', '.btn-loan-return', function () {
      const $button = $(this);
      returnTarget = {
        id: String($button.data('id') || ''),
        name: String($button.data('name') || ''),
        code: String($button.data('code') || '')
      };

      clearFormErrors(form, '#loanReturnFormError');
      form.reset();
      submit.disabled = false;
      $('#loanReturnAsset').text(returnTarget.name + (returnTarget.code !== '' ? ' (' + returnTarget.code + ')' : ''));
      modal.show();
    });

    form.addEventListener('input', fieldStateCleaner({ notes: 'return_notes' }));
    form.addEventListener('change', fieldStateCleaner({ notes: 'return_notes' }));

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (returnTarget === null || returnTarget.id === '') {
        return;
      }

      clearFormErrors(form, '#loanReturnFormError');
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

  $('#loanSearch').on('input search', function () {
    if (searchTimer !== null) {
      window.clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(function () {
      searchTimer = null;
      loadLoans();
    }, SEARCH_DELAY);
  });

  $('#loanStatusFilter').on('change', loadLoans);

  $('#loanOverdueOnly').on('change', loadLoans);

  // Tiap permintaan berdiri sendiri agar satu kegagalan tidak memblokir bagian lain.
  loadEmployees();
  loadLocations();
  loadAssets();
  loadLoans();
  initCreateForm();
  initReturnForm();
});
