/**
 * Inventra - Halaman Stok Barang Habis Pakai
 * Memuat saldo stok (/api/v1/stock) dan riwayat pergerakan
 * (/api/v1/stock/movements), menyaring keduanya, serta mencatat pergerakan baru
 * lewat satu modal yang melayani masuk, keluar, dan koreksi saldo. Hanya dimuat
 * pada halaman /stock. Notifikasi, dialog, dan bahasa DataTables memakai helper
 * bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.

  if (document.getElementById('stockTableBody') === null) {
    return;
  }

  const ui = window.InventraUI;
  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;

  const LOW_BADGE = 'bg-danger-subtle text-danger border border-danger-subtle';
  const TYPE_BADGES = {
    in: 'bg-success-subtle text-success border border-success-subtle',
    out: 'bg-primary-subtle text-primary border border-primary-subtle',
    adjustment: 'bg-warning-subtle text-warning border border-warning-subtle'
  };
  const TYPE_LABELS = { in: 'Masuk', out: 'Keluar', adjustment: 'Koreksi' };

  let stockTable = null;
  let movementTable = null;
  let stockRows = []; // Saldo terakhir dari API.
  let movementRows = []; // Riwayat terakhir dari API.
  let consumableItems = []; // Barang habis pakai, sumber dropdown modal.
  let movementItem = null; // { id, name } bila riwayat disaring per barang.
  let searchTimer = null;
  const categoryByItemId = {}; // item_id -> nama kategori, untuk menyaring riwayat.

  /* --------------------------- Sel bantu tabel --------------------------- */

  function badge(label, className) {
    return '<span class="badge badge-status ' + className + '">' + escapeHtml(label) + '</span>';
  }

  function mutedCell(value) {
    return value ? escapeHtml(value) : '<span class="text-muted">-</span>';
  }

  function smallMutedCell(value) {
    return '<span class="small text-muted">' + escapeHtml(value || '-') + '</span>';
  }

  function numberText(value) {
    return escapeHtml(Number(value || 0).toLocaleString('id-ID'));
  }

  function itemCell(row) {
    return '<div class="fw-semibold text-dark">' + escapeHtml(row.item_name || '-') + '</div>' +
      '<div class="small text-muted font-monospace">' + escapeHtml(row.item_code || '-') + '</div>';
  }

  /** Penerima bisa berupa karyawan (beserta departemennya) atau departemen saja. */
  function recipientCell(row) {
    const employee = String(row.employee_name || '');
    const department = String(row.department || '');

    if (employee === '' && department === '') {
      return '<span class="text-muted">-</span>';
    }

    if (employee === '') {
      return escapeHtml(department);
    }

    return '<div class="fw-semibold text-dark">' + escapeHtml(employee) + '</div>' +
      (department !== '' ? '<div class="small text-muted">' + escapeHtml(department) + '</div>' : '');
  }

  function noteCell(row) {
    const reason = String(row.reason || '');
    const notes = String(row.notes || '');

    if (reason === '' && notes === '') {
      return '<span class="text-muted">-</span>';
    }

    return (reason !== '' ? '<div class="stock-reason">' + escapeHtml(reason) + '</div>' : '') +
      (notes !== '' ? '<div class="small text-muted stock-note">' + escapeHtml(notes) + '</div>' : '');
  }

  function typeBadge(row) {
    const type = String(row.movement_type || '');
    return badge(row.movement_type_label || TYPE_LABELS[type] || type || '-', TYPE_BADGES[type] || 'bg-light text-secondary border');
  }

  /* ------------------------------ Daftar saldo ------------------------------ */

  /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
  function getStockTable() {
    if (stockTable === null) {
      stockTable = $('#tableStock').DataTable({
        searching: false, // Pencarian server-side lewat ?search=
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: false, // Urutan baris mengikuti API.
        info: true,
        order: [],
        language: ui.tableLanguage('barang')
      });
    }

    return stockTable;
  }

  function stockActions(row) {
    const id = escapeHtml(row.item_id);
    const name = escapeHtml(row.item_name || 'barang ini');

    const masuk = '<button type="button" class="btn btn-sm btn-light border px-2 btn-stock-movement"' +
      ' data-id="' + id + '" data-type="in" data-name="' + name + '" title="Catat stok masuk untuk ' + name + '">' +
      '<i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> Masuk</button>';

    const keluar = '<button type="button" class="btn btn-sm btn-light border px-2 btn-stock-movement"' +
      ' data-id="' + id + '" data-type="out" data-name="' + name + '" title="Catat stok keluar untuk ' + name + '">' +
      '<i class="bi bi-box-arrow-up" aria-hidden="true"></i> Keluar</button>';

    const riwayat = '<button type="button" class="btn btn-sm btn-light border px-2 btn-stock-history"' +
      ' data-id="' + id + '" data-name="' + name + '" title="Saring riwayat pergerakan ' + name + '"' +
      ' aria-label="Saring riwayat pergerakan ' + name + '">' +
      '<i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat</button>';

    return '<div class="table-actions d-inline-flex align-items-center">' + masuk + keluar + riwayat + '</div>';
  }

  function renderStock() {
    const table = getStockTable();
    const category = String($('#stockCategory').val() || '');
    // Filter kategori diterapkan di klien karena /api/v1/stock hanya menerima
    // parameter search dan low_only pada kontraknya.
    const rows = stockRows.filter(function (row) {
      return category === '' || String(row.category_name || '') === category;
    });

    let html = '';

    rows.forEach(function (row) {
      const isLow = Number(row.is_low) === 1 || row.is_low === true;
      const balance = '<span class="fw-semibold' + (isLow ? ' text-danger' : '') + '">' + numberText(row.quantity) + '</span>' +
        (isLow ? ' ' + badge('Stok rendah', LOW_BADGE) : '');
      const minimum = '<span class="text-muted">' + numberText(row.minimum_stock) + '</span>';

      html += '<tr data-id="' + escapeHtml(row.item_id) + '">' +
        '<td>' + itemCell(row) + '</td>' +
        '<td>' + mutedCell(row.category_name) + '</td>' +
        '<td>' + mutedCell(row.unit_name) + '</td>' +
        '<td class="text-end">' + balance + '</td>' +
        '<td class="text-end">' + minimum + '</td>' +
        '<td>' + smallMutedCell(row.updated_at) + '</td>' +
        '<td class="text-center">' + stockActions(row) + '</td>' +
        '</tr>';
    });

    // Data kosong ditampilkan DataTables lewat opsi bahasa, bukan baris buatan sendiri.
    table.clear();
    if (html !== '') {
      table.rows.add(ui.rowsFromHtml(html));
    }
    table.draw(false);
  }

  /* -------------------------------- Riwayat -------------------------------- */

  function getMovementTable() {
    if (movementTable === null) {
      movementTable = $('#tableStockMovement').DataTable({
        searching: false, // Filter sudah tersedia di header kartu riwayat.
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: false, // Kronologis mengikuti API.
        info: true,
        order: [],
        language: ui.tableLanguage('pergerakan stok')
      });
    }

    return movementTable;
  }

  function renderMovements() {
    const table = getMovementTable();
    const category = String($('#stockCategory').val() || '');
    // Peta kategori berasal dari daftar barang; bila peta belum tersedia, filter
    // kategori dilewati agar tabel tidak kosong tanpa alasan yang jelas.
    const hasCategoryMap = Object.keys(categoryByItemId).length > 0;
    const rows = movementRows.filter(function (row) {
      if (category === '' || !hasCategoryMap) {
        return true;
      }

      return String(categoryByItemId[row.item_id] || '') === category;
    });

    let html = '';

    rows.forEach(function (row) {
      html += '<tr data-id="' + escapeHtml(row.id) + '">' +
        '<td>' + smallMutedCell(row.created_at) + '</td>' +
        '<td>' + itemCell(row) + '</td>' +
        '<td>' + typeBadge(row) + '</td>' +
        '<td class="text-end">' + numberText(row.quantity) + '</td>' +
        '<td class="text-end">' + numberText(row.balance_after) + '</td>' +
        '<td>' + recipientCell(row) + '</td>' +
        '<td>' + noteCell(row) + '</td>' +
        '<td>' + mutedCell(row.actor_name) + '</td>' +
        '</tr>';
    });

    table.clear();
    if (html !== '') {
      table.rows.add(ui.rowsFromHtml(html));
    }
    table.draw(false);
  }

  function syncMovementHint() {
    const label = document.getElementById('stockMovementHint');
    const reset = document.getElementById('btnResetMovementFilter');

    if (movementItem === null) {
      label.textContent = 'Seluruh barang habis pakai';
      reset.hidden = true;
      return;
    }

    label.textContent = 'Riwayat barang: ' + movementItem.name;
    reset.hidden = false;
  }

  /* ------------------------------- Pemuatan ------------------------------- */

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    return (res && res.message) || fallback;
  }

  function showError(selector, message) {
    $(selector).html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small mx-4 mt-3 mb-0">' +
      '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  function loadStock() {
    const table = getStockTable();
    const search = String($('#stockSearch').val() || '').trim();
    const params = { search: search };

    if (document.getElementById('stockLowOnly').checked) {
      params.low_only = 1;
    }

    $('#stockError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: API_BASE + '/stock', type: 'GET', data: params, dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          stockRows = res.data;
          renderStock();
          return;
        }

        stockRows = [];
        renderStock();
        showError('#stockError', (res && res.message) || 'Gagal memuat saldo stok.');
      })
      .fail(function (xhr) {
        stockRows = [];
        renderStock();
        showError('#stockError', apiMessage(xhr, 'Gagal memuat saldo stok. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

  function loadMovements() {
    const table = getMovementTable();
    const type = String($('#stockMovementType').val() || '');
    const params = {};

    if (type !== '') {
      params.movement_type = type;
    }
    if (movementItem !== null) {
      params.item_id = movementItem.id;
    }

    $('#stockMovementError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: API_BASE + '/stock/movements', type: 'GET', data: params, dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          movementRows = res.data;
          renderMovements();
          return;
        }

        movementRows = [];
        renderMovements();
        showError('#stockMovementError', (res && res.message) || 'Gagal memuat riwayat pergerakan.');
      })
      .fail(function (xhr) {
        movementRows = [];
        renderMovements();
        showError('#stockMovementError', apiMessage(xhr, 'Gagal memuat riwayat pergerakan. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

  function loadCategories() {
    $.ajax({ url: API_BASE + '/categories', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        const select = document.getElementById('stockCategory');
        const current = String(select.value || '');
        let html = '<option value="">Semua kategori</option>';

        // Nilai opsi memakai nama kategori karena baris saldo hanya memuat
        // category_name, bukan category_id.
        res.data.forEach(function (category) {
          html += '<option value="' + escapeHtml(category.name) + '">' + escapeHtml(category.name) + '</option>';
        });

        select.innerHTML = html;
        select.value = current;
      });
  }

  function loadItems() {
    $.ajax({ url: API_BASE + '/items', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        // Peta kategori dibangun dari seluruh barang, sedangkan dropdown modal
        // hanya memuat barang habis pakai yang boleh dicatat pergerakannya.
        res.data.forEach(function (item) {
          categoryByItemId[item.id] = item.category_name;
        });

        consumableItems = res.data.filter(function (item) {
          return String(item.item_type || '') === 'consumable';
        });

        fillItemOptions();
      });
  }

  function fillItemOptions() {
    const select = document.getElementById('input-item_id');
    const current = String(select.value || '');
    let html = '<option value="">Pilih barang</option>';

    consumableItems.forEach(function (item) {
      html += '<option value="' + escapeHtml(item.id) + '">' +
        escapeHtml(item.code) + ' - ' + escapeHtml(item.name) + '</option>';
    });

    select.innerHTML = html;
    if (current !== '') {
      select.value = current;
    }
  }

  function loadRecipients() {
    $.ajax({ url: API_BASE + '/employees', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        const employees = res.data.filter(function (employee) {
          return employee.is_active === undefined || Number(employee.is_active) === 1;
        });

        // Departemen diambil dari daftar karyawan; tidak ada endpoint departemen.
        const departments = [];
        employees.forEach(function (employee) {
          const department = String(employee.department || '');
          if (department !== '' && departments.indexOf(department) === -1) {
            departments.push(department);
          }
        });

        let html = '<option value="">Tanpa penerima tertentu</option>';

        if (departments.length > 0) {
          html += '<optgroup label="Departemen">';
          departments.forEach(function (department) {
            html += '<option value="dept:' + escapeHtml(department) + '">' + escapeHtml(department) + '</option>';
          });
          html += '</optgroup>';
        }

        html += '<optgroup label="Karyawan">';
        employees.forEach(function (employee) {
          html += '<option value="emp:' + escapeHtml(employee.id) + '">' + escapeHtml(employee.name) +
            (employee.department ? ' - ' + escapeHtml(employee.department) : '') + '</option>';
        });
        html += '</optgroup>';

        document.getElementById('input-recipient').innerHTML = html;
      });
  }

  /* ------------------------- Modal pencatatan stok ------------------------- */

  function findStockRow(itemId) {
    let found = null;

    stockRows.forEach(function (row) {
      if (String(row.item_id) === String(itemId)) {
        found = row;
      }
    });

    return found;
  }

  function findItem(itemId) {
    let found = null;

    consumableItems.forEach(function (item) {
      if (String(item.id) === String(itemId)) {
        found = item;
      }
    });

    return found;
  }

  function clearFormErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(function (field) {
      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('[id^="error-"]').forEach(function (box) {
      box.textContent = '';
      box.hidden = true;
    });
    $('#stockMovementFormError').empty();
  }

  function showFormError(message) {
    $('#stockMovementFormError').html(
      '<div class="alert alert-danger d-flex align-items-start gap-2 py-2 small mb-0">' +
      '<i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i><span>' + escapeHtml(message) + '</span>' +
      '</div>'
    );
  }

  function initMovementForm() {
    const modalElement = document.getElementById('stockMovementModal');
    const form = document.getElementById('formStockMovement');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const typeSelect = form.elements.movement_type;
    const notesField = form.elements.notes;
    const recipientField = form.elements.recipient;

    /** Jenis pergerakan menentukan label, wajib-tidaknya alasan, dan field penerima. */
    function syncForm() {
      const isAdjustment = String(typeSelect.value) === 'adjustment';

      notesField.required = isAdjustment;
      notesField.placeholder = isAdjustment ? 'Contoh: koreksi hasil stok opname' : 'Catatan pergerakan (opsional)';
      document.getElementById('label-notes').textContent = isAdjustment ? 'Alasan Koreksi' : 'Alasan / Catatan';
      document.getElementById('hint-notes').textContent = isAdjustment
        ? 'Wajib diisi: jelaskan alasan penyesuaian saldo.'
        : 'Opsional, isi bila ada keterangan penting.';

      // Koreksi saldo tidak ditujukan ke penerima mana pun.
      recipientField.disabled = isAdjustment;
      document.getElementById('hint-recipient').textContent = isAdjustment
        ? 'Tidak dipakai untuk koreksi saldo.'
        : 'Opsional, untuk pengeluaran per departemen atau ke karyawan tertentu.';
    }

    function openModal(itemId, type, itemName) {
      clearFormErrors(form);
      form.reset();
      $('#btnSubmitMovement').prop('disabled', false);

      if (itemId !== undefined && itemId !== null && itemId !== '') {
        form.elements.item_id.value = String(itemId);
      }
      typeSelect.value = type || 'in';
      syncForm();

      // Nama barang dari tombol aksi dipakai bila daftar barang belum termuat.
      form.dataset.itemName = itemName || '';
      modal.show();
    }

    modalElement.addEventListener('shown.bs.modal', function () {
      const first = modalElement.querySelector('select, input, textarea');
      if (first !== null) {
        first.focus();
      }
    });

    typeSelect.addEventListener('change', syncForm);
    syncForm();

    $('#btnAddMovement').on('click', function () {
      openModal(null, 'in', '');
    });

    $(document).on('click', '.btn-stock-movement', function () {
      const $button = $(this);
      openModal($button.data('id'), String($button.data('type')), String($button.data('name')));
    });

    function buildPayload() {
      const detail = String(notesField.value || '').trim();
      const type = String(typeSelect.value || '');
      const recipient = recipientField.disabled ? '' : String(recipientField.value || '');
      const payload = {
        item_id: Number(form.elements.item_id.value || 0),
        movement_type: type,
        quantity: Number(form.elements.quantity.value || 0),
        notes: detail
      };

      // Satu field penerima melayani dua muatan: nilai berawalan emp:/dept:
      // menentukan apakah dikirim sebagai employee_id atau department.
      if (recipient.indexOf('emp:') === 0) {
        payload.employee_id = Number(recipient.slice(4));
      } else if (recipient.indexOf('dept:') === 0) {
        payload.department = recipient.slice(5);
      }

      if (type === 'adjustment') {
        payload.reason = detail;
      }

      return payload;
    }

    function applyFieldErrors(errors) {
      let firstInvalid = null;
      // Field penerima dikirim sebagai employee_id atau department; keduanya
      // diarahkan kembali ke satu kontrol penerima.
      const aliases = { employee_id: 'recipient', department: 'recipient' };

      Object.keys(errors).forEach(function (key) {
        const name = aliases[key] || key;
        const box = document.getElementById('error-' + name);
        if (box) {
          box.textContent = String(errors[key]);
          box.hidden = false;
        }

        const input = form.elements[name];
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

    function submitMovement(payload) {
      $('#btnSubmitMovement').prop('disabled', true);

      $.ajax({
        url: API_BASE + '/stock/movements',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            modal.hide();
            ui.toast(res.message || 'Pergerakan stok berhasil dicatat.');
            $('#btnSubmitMovement').prop('disabled', false);
            loadStock();
            loadMovements();
            return;
          }

          $('#btnSubmitMovement').prop('disabled', false);
          showFormError((res && res.message) || 'Gagal mencatat pergerakan stok.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors);
          }
          showFormError((res && res.message) || apiMessage(xhr, 'Gagal mencatat pergerakan stok. Coba lagi ya.'));
          $('#btnSubmitMovement').prop('disabled', false);
        });
    }

    /** Ringkasan tindakan sebelum dikirim; saldo akhir diketahui dari daftar saldo. */
    function confirmationText(payload, itemName) {
      const quantity = Number(payload.quantity || 0).toLocaleString('id-ID');

      if (payload.movement_type === 'adjustment') {
        return 'Saldo ' + itemName + ' akan disesuaikan sebanyak ' + quantity +
          ' unit. Pastikan angka sudah sesuai hasil pemeriksaan fisik.';
      }

      const row = findStockRow(payload.item_id);
      if (row === null) {
        return 'Stok ' + itemName + ' akan berkurang ' + quantity + ' unit.';
      }

      const remaining = Number(row.quantity || 0) - Number(payload.quantity || 0);
      const unit = String(row.unit_name || 'unit');

      return 'Stok ' + itemName + ' akan berkurang ' + quantity + ' ' + unit +
        '. Sisa saldo menjadi ' + Number(remaining).toLocaleString('id-ID') + ' ' + unit + '.';
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      clearFormErrors(form);

      const payload = buildPayload();
      const item = findItem(payload.item_id);
      const itemName = item !== null ? item.name : (String(form.dataset.itemName || '') || 'barang ini');

      // Stok masuk langsung dicatat; pengurangan saldo dikonfirmasi lebih dulu.
      if (payload.movement_type === 'in') {
        submitMovement(payload);
        return;
      }

      // Tombol dinonaktifkan selama dialog terbuka agar tidak terkirim dua kali.
      $('#btnSubmitMovement').prop('disabled', true);

      ui.confirmDialog({
        title: payload.movement_type === 'out' ? 'Catat stok keluar?' : 'Catat koreksi saldo?',
        text: confirmationText(payload, itemName),
        confirmText: 'Ya, catat',
        destructive: payload.movement_type === 'adjustment'
      }).then(function (confirmed) {
        if (confirmed) {
          submitMovement(payload);
          return;
        }

        $('#btnSubmitMovement').prop('disabled', false);
      });
    });

    // Pesan error field dibersihkan begitu pengguna memperbaiki isian.
    function clearFieldState(event) {
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
    }

    form.addEventListener('input', clearFieldState);
    form.addEventListener('change', clearFieldState);
  }

  /* -------------------------------- Filter -------------------------------- */

  $('#stockSearch').on('input search', function () {
    if (searchTimer !== null) {
      window.clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(function () {
      searchTimer = null;
      loadStock();
    }, SEARCH_DELAY);
  });

  $('#stockLowOnly').on('change', function () {
    loadStock();
  });

  // Kategori disaring di klien, jadi cukup merender ulang tanpa permintaan baru.
  $('#stockCategory').on('change', function () {
    renderStock();
    renderMovements();
  });

  $('#stockMovementType').on('change', function () {
    loadMovements();
  });

  $(document).on('click', '.btn-stock-history', function () {
    const $button = $(this);
    movementItem = { id: $button.data('id'), name: String($button.data('name') || '') };
    syncMovementHint();
    loadMovements();

    // Sorot kartu riwayat tanpa memaksa gerak halus bagi yang menolaknya.
    const section = document.getElementById('stockMovementSection');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    section.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  });

  $('#btnResetMovementFilter').on('click', function () {
    movementItem = null;
    syncMovementHint();
    loadMovements();
  });

  // Tiap permintaan berdiri sendiri agar satu kegagalan tidak memblokir bagian lain.
  loadCategories();
  loadItems();
  loadRecipients();
  loadStock();
  loadMovements();
  initMovementForm();
});
