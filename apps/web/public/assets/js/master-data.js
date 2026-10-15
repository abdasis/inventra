/**
 * Inventra - Halaman Master Data
 * Daftar master (kategori, satuan, barang, lokasi, karyawan, supplier) dengan
 * pencarian server-side, modal CRUD untuk kategori/satuan/lokasi, dan navigasi
 * ke halaman form untuk barang/karyawan/supplier. Bentuk kolom tiap entitas
 * ditentukan ENTITY_CONFIG supaya tidak ada satu skrip per entitas.
 * Hanya dimuat pada halaman /master/*. Notifikasi dan dialog memakai helper
 * bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const FLASH_KEY = 'inventra.master.flash';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.
  const page = window.INVENTRA_MASTER || {};
  const ui = window.InventraUI;

  if (document.getElementById('masterTableBody') === null) {
    return;
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;

  const ACTIVE_BADGE = 'bg-success-subtle text-success border border-success-subtle';
  const INACTIVE_BADGE = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
  const NEUTRAL_BADGE = 'bg-light text-secondary border';

  function badge(label, className) {
    return '<span class="badge badge-status ' + className + '">' + escapeHtml(label) + '</span>';
  }

  function codeCell(value) {
    return '<span class="badge bg-light text-secondary border">' + escapeHtml(value) + '</span>';
  }

  function nameCell(value) {
    return '<span class="fw-semibold text-dark">' + escapeHtml(value) + '</span>';
  }

  function mutedCell(value) {
    return value ? escapeHtml(value) : '<span class="text-muted">-</span>';
  }

  function stackedCell(primary, secondary) {
    let html = '<div class="fw-semibold text-dark">' + escapeHtml(primary || '-') + '</div>';
    if (secondary) {
      html += '<div class="small text-muted">' + escapeHtml(secondary) + '</div>';
    }
    return html;
  }

  function numberCell(value) {
    return '<span class="fw-semibold">' + escapeHtml(Number(value || 0).toLocaleString('id-ID')) + '</span>';
  }

  // is_active dari API bertipe number 1/0, sama seperti pada users.js.
  function statusCell(row) {
    const isActive = Number(row.is_active) === 1;
    return badge(isActive ? 'Aktif' : 'Nonaktif', isActive ? ACTIVE_BADGE : INACTIVE_BADGE);
  }

  function typeCell(row) {
    return badge(row.item_type_label || row.item_type || '-', row.item_type_badge || NEUTRAL_BADGE);
  }

  /**
   * Kolom per entitas. Setiap kolom hanya perlu tahu cara merender satu baris;
   * urutannya harus sama dengan <thead> pada view halaman.
   */
  const ENTITY_CONFIG = {
    categories: {
      // Daftar hanya memuat parent_id; hanya endpoint detail yang menambah
      // parent_name. Nama induk karena itu diselesaikan dari baris daftar sendiri.
      prepare: function (rows) {
        const names = {};
        rows.forEach(function (row) {
          names[row.id] = row.name;
        });
        rows.forEach(function (row) {
          row.parent_name = row.parent_id && names[row.parent_id] ? names[row.parent_id] : null;
        });
      },
      columns: [
        { render: function (row) { return codeCell(row.code); } },
        { render: function (row) { return nameCell(row.name); } },
        { render: function (row) { return mutedCell(row.parent_name); } },
        { render: function (row) { return mutedCell(row.description); } }
      ]
    },
    units: {
      columns: [
        { render: function (row) { return codeCell(row.code); } },
        { render: function (row) { return nameCell(row.name); } },
        { render: function (row) { return mutedCell(row.description); } }
      ]
    },
    locations: {
      columns: [
        { render: function (row) { return codeCell(row.code); } },
        { render: function (row) { return nameCell(row.name); } },
        { render: function (row) { return mutedCell(row.building); } },
        { render: function (row) { return mutedCell(row.floor); } }
      ]
    },
    items: {
      columns: [
        { render: function (row) { return codeCell(row.code); } },
        { render: function (row) { return nameCell(row.name); } },
        { render: function (row) { return mutedCell(row.category_name); } },
        { render: function (row) { return mutedCell(row.unit_name); } },
        { render: function (row) { return typeCell(row); } },
        { render: function (row) { return numberCell(row.minimum_stock); } }
      ]
    },
    employees: {
      columns: [
        { render: function (row) { return escapeHtml(row.employee_number); } },
        { render: function (row) { return stackedCell(row.name, row.position); } },
        { render: function (row) { return mutedCell(row.department); } },
        { render: function (row) { return stackedCell(row.phone, row.email); } },
        { render: function (row) { return statusCell(row); } }
      ]
    },
    suppliers: {
      columns: [
        { render: function (row) { return codeCell(row.code); } },
        { render: function (row) { return nameCell(row.name); } },
        { render: function (row) { return stackedCell(row.contact_person, row.email); } },
        { render: function (row) { return mutedCell(row.phone); } },
        { render: function (row) { return statusCell(row); } }
      ]
    }
  };

  const config = ENTITY_CONFIG[page.entity] || null;
  if (config === null) {
    return;
  }

  const hasModal = document.getElementById('masterModal') !== null;
  const subject = String(page.label || '').toLowerCase();

  let table = null;
  let searchTimer = null;

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    if (xhr.status === 403) {
      return (res && res.message) || 'Hanya administrator yang dapat mengelola data master.';
    }
    return (res && res.message) || fallback;
  }

  function showListError(message) {
    $('#masterError').html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small mx-4 mt-3 mb-0">' +
      '<i class="bi bi-exclamation-triangle me-1"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  function consumeFlash() {
    try {
      const message = sessionStorage.getItem(FLASH_KEY);
      if (message) {
        sessionStorage.removeItem(FLASH_KEY);
        ui.toast(message);
      }
    } catch (error) {
      // Penyimpanan sesi tidak tersedia.
    }
  }

  /* -------------------------------- Daftar data -------------------------------- */

  /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
  function getTable() {
    if (table === null) {
      table = $('#tableMaster').DataTable({
        searching: false, // Filter server-side sudah ada di kotak pencarian di atas tabel.
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: false, // Urutan baris mengikuti API.
        info: true,
        order: [],
        language: ui.tableLanguage(subject)
      });
    }

    return table;
  }

  function rowActions(row) {
    const id = escapeHtml(row.id);
    const name = escapeHtml(row.name || row.code || '');
    const target = '/master/' + encodeURIComponent(String(page.entity)) + '/' + encodeURIComponent(row.id) + '/edit';

    const edit = hasModal
      ? '<button type="button" class="btn btn-sm btn-light border px-2 btn-edit-master"' +
        ' data-id="' + id + '" data-name="' + name + '" title="Ubah data ' + name + '">' +
        '<i class="bi bi-pencil" aria-hidden="true"></i> Ubah</button>'
      : '<a href="' + target + '" class="btn btn-sm btn-light border px-2" title="Ubah data ' + name + '">' +
        '<i class="bi bi-pencil" aria-hidden="true"></i> Ubah</a>';

    const remove = '<button type="button" class="btn btn-sm btn-light border px-2 text-danger btn-delete-master"' +
      ' data-id="' + id + '" data-name="' + name + '" title="Hapus data ' + name + '">' +
      '<i class="bi bi-trash" aria-hidden="true"></i> Hapus</button>';

    return '<div class="table-actions d-inline-flex align-items-center">' + edit + remove + '</div>';
  }

  function renderTable(rows) {
    const current = getTable();
    let html = '';

    if (typeof config.prepare === 'function') {
      config.prepare(rows);
    }

    rows.forEach(function (row) {
      html += '<tr data-id="' + escapeHtml(row.id) + '">';
      config.columns.forEach(function (column) {
        html += '<td>' + column.render(row) + '</td>';
      });
      html += '<td class="text-center">' + rowActions(row) + '</td></tr>';
    });

    // Data kosong ditampilkan DataTables lewat opsi bahasa, bukan baris buatan sendiri.
    current.clear();
    if (html !== '') {
      current.rows.add(ui.rowsFromHtml(html));
    }
    current.draw(false);
  }

  function loadList() {
    const search = String($('#masterSearch').val() || '').trim();
    const url = API_BASE + '/' + page.entity + (search === '' ? '' : '?search=' + encodeURIComponent(search));
    const current = getTable();

    $('#masterError').empty();
    ui.tableLoading(current, true);

    $.ajax({ url: url, type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          renderTable(res.data);
          return;
        }
        current.clear().draw(false);
        showListError((res && res.message) || 'Gagal memuat data master.');
      })
      .fail(function (xhr) {
        current.clear().draw(false);
        showListError(apiMessage(xhr, 'Gagal memuat data master. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(current, false);
      });
  }

  /* ----------------------------- Modal form CRUD ----------------------------- */

  /** Menyalin nilai baris ke field form; hanya field yang namanya cocok. */
  function fillForm(form, row) {
    Object.keys(row).forEach(function (key) {
      const field = form.elements[key];
      if (!field) {
        return;
      }

      if (field.type === 'checkbox') {
        field.checked = Number(row[key]) === 1;
        return;
      }

      field.value = row[key] === null || row[key] === undefined ? '' : String(row[key]);
    });
  }

  function clearFields(form) {
    Array.prototype.forEach.call(form.elements, function (field) {
      if (field.type === 'submit' || field.type === 'button') {
        return;
      }
      if (field.tagName === 'SELECT') {
        field.selectedIndex = 0;
        return;
      }
      if (field.type === 'checkbox') {
        field.checked = false;
        return;
      }
      field.value = '';
    });
  }

  /**
   * Dropdown yang opsinya berasal dari API (mis. kategori induk) diisi ulang
   * setiap modal dibuka, tanpa menyertakan baris yang sedang diubah.
   */
  function fillDynamicSelects(form, excludeId) {
    form.querySelectorAll('select[data-source]').forEach(function (select) {
      $.ajax({ url: API_BASE + '/' + select.dataset.source, type: 'GET', dataType: 'json' })
        .done(function (res) {
          const list = res && res.success && Array.isArray(res.data) ? res.data : [];
          const selected = String(select.value || '');
          let html = '<option value="">' + escapeHtml(select.dataset.emptyLabel || 'Pilih salah satu') + '</option>';

          list.forEach(function (item) {
            if (excludeId !== null && String(item.id) === String(excludeId)) {
              return;
            }
            html += '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.name) + '</option>';
          });

          select.innerHTML = html;
          select.value = selected;
        });
    });
  }

  function buildPayload(form) {
    const payload = {};

    Array.prototype.forEach.call(form.elements, function (field) {
      const name = field.name;
      if (!name || name === 'id' || field.type === 'submit' || field.type === 'button') {
        return;
      }

      if (field.type === 'checkbox') {
        payload[name] = field.checked;
        return;
      }

      const value = String(field.value).trim();
      if (field.tagName === 'SELECT' && field.hasAttribute('data-source')) {
        // Id relasi dikirim sebagai angka; pilihan kosong berarti tanpa relasi.
        payload[name] = value === '' ? null : Number(value);
        return;
      }

      payload[name] = value;
    });

    return payload;
  }

  function initModal() {
    const modalElement = document.getElementById('masterModal');
    if (modalElement === null) {
      return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const form = document.getElementById('formMaster');
    const $submit = $('#btnSubmitMaster');
    const title = document.getElementById('masterModalTitle');

    // Fokus dipindahkan ke field pertama tiap kali modal terbuka.
    modalElement.addEventListener('shown.bs.modal', function () {
      const first = modalElement.querySelector('input:not([type="hidden"]), select, textarea');
      if (first !== null) {
        first.focus();
      }
    });

    function clearFormErrors() {
      form.querySelectorAll('.is-invalid').forEach(function (field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
      });
      form.querySelectorAll('[id^="error-"]').forEach(function (box) {
        box.textContent = '';
        box.hidden = true;
      });
      $('#masterModalError').empty();
    }

    function openModal(row) {
      clearFormErrors();
      $submit.prop('disabled', false);
      form.dataset.mode = row === null ? 'create' : 'edit';
      clearFields(form);
      form.elements.id.value = row === null ? '' : String(row.id);
      title.textContent = (row === null ? 'Tambah ' : 'Ubah ') + page.label;

      if (row !== null) {
        fillForm(form, row);
      }
      fillDynamicSelects(form, row === null ? null : row.id);

      modal.show();
    }

    $('#btnAddMaster').on('click', function () {
      openModal(null);
    });

    $(document).on('click', '.btn-edit-master', function () {
      const id = $(this).data('id');

      $.ajax({ url: API_BASE + '/' + page.entity + '/' + encodeURIComponent(id), type: 'GET', dataType: 'json' })
        .done(function (res) {
          const row = res && res.success ? (Array.isArray(res.data) ? res.data[0] : res.data) : null;
          if (!row) {
            ui.toast('Data tidak ditemukan. Muat ulang halaman ini ya.', true);
            return;
          }
          openModal(row);
        })
        .fail(function (xhr) {
          ui.toast(apiMessage(xhr, 'Gagal memuat data. Coba lagi ya.'), true);
        });
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      clearFormErrors();

      const editing = form.dataset.mode === 'edit';
      const id = String(form.elements.id.value || '');
      $submit.prop('disabled', true);

      $.ajax({
        url: editing ? API_BASE + '/' + page.entity + '/' + encodeURIComponent(id) : API_BASE + '/' + page.entity,
        type: editing ? 'PUT' : 'POST',
        contentType: 'application/json',
        data: JSON.stringify(buildPayload(form)),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            // Tombol dibiarkan nonaktif saat berhasil; modal ditutup dan daftar dimuat ulang.
            modal.hide();
            showToast(res.message || 'Data berhasil disimpan.');
            loadList();
            return;
          }
          $submit.prop('disabled', false);
          showModalError((res && res.message) || 'Gagal menyimpan data.');
        })
        .fail(function (xhr) {
          const res = xhr.responseJSON;
          if (xhr.status === 422 && res && res.errors) {
            applyFieldErrors(res.errors);
          }
          showModalError((res && res.message) || apiMessage(xhr, 'Gagal menyimpan data. Coba lagi ya.'));
          $submit.prop('disabled', false);
        });
    });

    // Pesan error field dibersihkan begitu pengguna memperbaiki isian.
    form.addEventListener('input', clearFieldState);
    form.addEventListener('change', clearFieldState);

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

    function applyFieldErrors(errors) {
      let firstInvalid = null;

      Object.keys(errors).forEach(function (field) {
        const box = document.getElementById('error-' + field);
        if (box) {
          box.textContent = String(errors[field]);
          box.hidden = false;
        }

        const input = form.elements[field];
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

    function showModalError(message) {
      $('#masterModalError').html(
        '<div class="alert alert-danger d-flex align-items-start gap-2 py-2 small mb-0">' +
        '<i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i><span>' + escapeHtml(message) + '</span>' +
        '</div>'
      );
    }
  }

  function showToast(message, isError) {
    ui.toast(message, isError === true);
  }

  /* ------------------------------- Hapus data ------------------------------- */

  $(document).on('click', '.btn-delete-master', function () {
    const $button = $(this);
    const id = $button.data('id');
    const name = String($button.data('name'));

    ui.confirmDialog({
      title: 'Hapus ' + subject + ' ini?',
      text: 'Data ' + name + ' akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.',
      confirmText: 'Ya, hapus',
      destructive: true
    }).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      $button.prop('disabled', true);

      $.ajax({ url: API_BASE + '/' + page.entity + '/' + encodeURIComponent(id), type: 'DELETE', dataType: 'json' })
        .done(function (res) {
          showToast((res && res.message) || 'Data berhasil dihapus.');
          loadList();
        })
        .fail(function (xhr) {
          $button.prop('disabled', false);
          showToast(apiMessage(xhr, 'Gagal menghapus data. Coba lagi ya.'), true);
        });
    });
  });

  /* -------------------------------- Pencarian -------------------------------- */

  $('#masterSearch').on('input search', function () {
    if (searchTimer !== null) {
      window.clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(function () {
      searchTimer = null;
      loadList();
    }, SEARCH_DELAY);
  });

  if (hasModal) {
    initModal();
  }

  consumeFlash();
  loadList();
});
