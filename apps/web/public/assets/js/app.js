/**
 * Inventra - Modern Office Inventory System
 * jQuery 3.7.1 Frontend Application Script
 *
 * Dimuat di seluruh halaman. Pengaturan AJAX, notifikasi, dan dialog konfirmasi
 * bersifat global (dipakai juga oleh users.js); logika inventaris hanya berjalan
 * bila DOM dashboard tersedia.
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';

  // Sertakan token CSRF pada seluruh permintaan AJAX, termasuk halaman pengguna.
  $.ajaxSetup({
    headers: { 'X-CSRF-Token': csrfToken }
  });

  // Bila sesi berakhir di tengah pemakaian, arahkan ke halaman login.
  $(document).ajaxError(function (event, xhr) {
    if (xhr.status === 401) {
      window.location.href = '/login';
    }
  });

  // SweetAlert2 dipakai sebagai satu-satunya sistem notifikasi dan konfirmasi.
  const swalToast = Swal.mixin({
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 3200,
    timerProgressBar: true,
    didOpen: function (element) {
      element.addEventListener('mouseenter', Swal.stopTimer);
      element.addEventListener('mouseleave', Swal.resumeTimer);
    }
  });

  /**
   * Helper UI bersama antar skrip halaman.
   */
  window.InventraUI = {
    /**
     * Escaping untuk teks maupun nilai atribut HTML. Setiap nilai yang berasal
     * dari API wajib lewat sini sebelum disisipkan ke string HTML.
     */
    escapeHtml: function (value) {
      return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },

    /**
     * Notifikasi ringkas. Nama dan tanda tangan dipertahankan dari showToast() lama
     * agar seluruh pemanggil tidak perlu diubah.
     */
    toast: function (message, isError = false) {
      swalToast.fire({
        icon: isError ? 'error' : 'success',
        title: String(message === null || message === undefined ? '' : message)
      });
    },

    /**
     * Dialog konfirmasi berbasis promise; mengembalikan true bila dikonfirmasi.
     * Tindakan destruktif: fokus awal di tombol batal dan tombol konfirmasi berwarna bahaya.
     */
    confirmDialog: function (options) {
      const settings = options || {};
      const destructive = settings.destructive === true;

      return Swal.fire({
        title: settings.title || 'Lanjutkan tindakan ini?',
        text: settings.text || '',
        icon: settings.icon || 'warning',
        showCancelButton: true,
        confirmButtonText: settings.confirmText || 'Ya, lanjutkan',
        cancelButtonText: settings.cancelText || 'Batal',
        confirmButtonColor: destructive ? '#dc3545' : '#2563eb',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        focusCancel: destructive,
        buttonsStyling: true
      }).then(function (result) {
        return result.isConfirmed === true;
      });
    },

    /**
     * Bahasa antarmuka DataTables (Indonesia) untuk kedua tabel.
     * `entries` adalah nama opsi pada DataTables 2/3, `lengthMenu` disertakan
     * sebagai cadangan untuk versi yang masih memakai nama lama.
     */
    tableLanguage: function (subject) {
      const menu = 'Tampilkan _MENU_ ' + subject;

      return {
        emptyTable: 'Belum ada data ' + subject + '.',
        zeroRecords: 'Tidak ada data ' + subject + ' yang cocok dengan kriteria yang dipilih.',
        info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ ' + subject,
        infoEmpty: 'Menampilkan 0 ' + subject,
        infoFiltered: '(disaring dari _MAX_ total ' + subject + ')',
        entries: { _: menu, 1: 'Tampilkan 1 ' + subject },
        lengthMenu: menu,
        loadingRecords: 'Memuat data ' + subject + '...',
        processing: '<span class="spinner-border spinner-border-sm text-primary me-2" aria-hidden="true"></span> Memuat data...',
        search: 'Cari:',
        searchPlaceholder: 'Cari ' + subject + '...',
        paginate: {
          first: 'Awal',
          last: 'Akhir',
          next: 'Berikutnya',
          previous: 'Sebelumnya'
        },
        aria: {
          sortAscending: ': urutkan menaik',
          sortDescending: ': urutkan menurun'
        }
      };
    },

    /** Indikator proses bawaan DataTables; dijaga agar tetap aman lintas versi. */
    tableLoading: function (table, isLoading) {
      if (table && typeof table.processing === 'function') {
        table.processing(isLoading);
      }
    },

    /** Ubah rangkaian markup <tr> menjadi baris siap ditambahkan ke DataTables. */
    rowsFromHtml: function (html) {
      return $($.parseHTML('<table><tbody>' + html + '</tbody></table>')).find('tr');
    }
  };

  // Header: bayangan elevasi baru muncul setelah halaman digulir. Berjalan di
  // semua halaman (termasuk /users), jadi dipasang sebelum guard dashboard.
  const appHeader = document.querySelector('.app-header');
  if (appHeader !== null) {
    const syncHeaderShadow = function () {
      appHeader.classList.toggle('is-scrolled', window.scrollY > 4);
    };

    syncHeaderShadow();
    window.addEventListener('scroll', syncHeaderShadow, { passive: true });
  }

  // Halaman non-dashboard (mis. /users) memakai skripnya sendiri.
  if (document.getElementById('tableAssets') === null) {
    return;
  }

  const isAdmin = window.INVENTRA_IS_ADMIN === true;
  const escapeHtml = window.InventraUI.escapeHtml;

  let categoriesCache = [];
  let locationsCache = [];
  let searchTimer = null;
  let assetsTable = null;

  // Bootstrap Modals
  const assetModal = new bootstrap.Modal($('#assetModal')[0]);
  const checkoutModal = new bootstrap.Modal($('#checkoutModal')[0]);
  const checkinModal = new bootstrap.Modal($('#checkinModal')[0]);

  function showToast(message, isError = false) {
    window.InventraUI.toast(message, isError);
  }

  function formatRupiah(number) {
    return 'Rp ' + Number(number || 0).toLocaleString('id-ID');
  }

  /**
   * Tabel aset dikelola satu instance DataTables. Pemuatan ulang data dilakukan
   * lewat API lalu barisnya diganti, bukan dengan menginisialisasi tabel baru.
   */
  function getAssetsTable() {
    if (assetsTable === null) {
      assetsTable = $('#tableAssets').DataTable({
        searching: false, // Filter sudah tersedia di atas tabel.
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: true,
        info: true,
        order: [], // Ikuti urutan dari API sampai pengguna mengurutkan sendiri.
        columnDefs: [{ targets: 6, orderable: false }],
        language: window.InventraUI.tableLanguage('aset')
      });
    }

    return assetsTable;
  }

  // 1. Load Categories
  function loadCategories() {
    return $.getJSON(`${API_BASE}/categories`, function (res) {
      if (res.success && Array.isArray(res.data)) {
        categoriesCache = res.data;
        let filterOpts = '<option value="">Semua Kategori</option>';
        let formOpts = '<option value="">-- Pilih Kategori --</option>';

        res.data.forEach(function (cat) {
          filterOpts += `<option value="${escapeHtml(cat.id)}">${escapeHtml(cat.name)}</option>`;
          formOpts += `<option value="${escapeHtml(cat.id)}">${escapeHtml(cat.name)}</option>`;
        });

        $('#filterCategory').html(filterOpts);
        $('#selectCategory').html(formOpts);
      }
    });
  }

  // 2. Load Locations
  function loadLocations() {
    return $.getJSON(`${API_BASE}/locations`, function (res) {
      if (res.success && Array.isArray(res.data)) {
        locationsCache = res.data;
        let formOpts = '<option value="">-- Pilih Lokasi Penempatan --</option>';

        res.data.forEach(function (loc) {
          formOpts += `<option value="${escapeHtml(loc.id)}">${escapeHtml(loc.building)} - ${escapeHtml(loc.floor)} (${escapeHtml(loc.name)})</option>`;
        });

        $('#selectLocation').html(formOpts);
        $('#checkoutLocation').html(formOpts);
        $('#checkinLocation').html(formOpts);
      }
    });
  }

  // 3. Load Stats & Activity Logs
  function loadStats() {
    $.getJSON(`${API_BASE}/stats`, function (res) {
      if (res.success && res.data) {
        const stats = res.data.stats;
        $('#statTotalAssets').text(stats.total_assets);
        $('#statInUse').text(stats.count_in_use);
        $('#statAvailable').text(stats.count_available);
        $('#statValuation').text(stats.total_valuation_formatted);

        const inUsePct = stats.total_assets > 0 ? Math.round((stats.count_in_use / stats.total_assets) * 100) : 0;
        $('#statInUsePercent').text(inUsePct + '%');

        // Render Recent Movements
        renderMovements(res.data.recent_movements || []);
      }
    });
  }

  function renderMovements(items) {
    const $tbody = $('#movementsTableBody');
    if (!items.length) {
      $tbody.html('<tr><td colspan="6" class="text-center py-3 text-muted">Belum ada aktivitas mutasi.</td></tr>');
      return;
    }

    let rows = '';
    items.forEach(function (m) {
      let typeBadge = '<span class="badge bg-secondary">Mutasi</span>';
      if (m.movement_type === 'check_out') {
        typeBadge = '<span class="badge bg-primary">Serah Terima / Pakai</span>';
      } else if (m.movement_type === 'check_in') {
        typeBadge = '<span class="badge bg-success">Pengembalian</span>';
      } else if (m.movement_type === 'repair') {
        typeBadge = '<span class="badge bg-warning text-dark">Perbaikan</span>';
      }

      // Markup ikon dan <strong> di dalam route/parties sengaja dipertahankan; hanya nilai dari API yang di-escape.
      const route = `${escapeHtml(m.from_location_name) || 'Gudang'} <i class="bi bi-arrow-right text-muted mx-1"></i> ${escapeHtml(m.to_location_name) || 'Gudang'}`;
      const parties = m.recipient_name ? `Penerima: <strong>${escapeHtml(m.recipient_name)}</strong> (Oleh: ${escapeHtml(m.actor_name)})` : `Oleh: ${escapeHtml(m.actor_name)}`;

      rows += `
        <tr>
          <td class="small text-muted">${escapeHtml(m.created_at)}</td>
          <td><strong>${escapeHtml(m.asset_name)}</strong><br><small class="text-muted font-monospace">${escapeHtml(m.asset_code)}</small></td>
          <td>${typeBadge}</td>
          <td class="small">${route}</td>
          <td class="small">${parties}</td>
          <td class="small text-muted">${escapeHtml(m.notes) || '-'}</td>
        </tr>
      `;
    });
    $tbody.html(rows);
  }

  // 4. Load & Render Assets
  function loadAssets() {
    const table = getAssetsTable();
    const params = {
      search: $('#filterSearch').val().trim(),
      category_id: $('#filterCategory').val(),
      status: $('#filterStatus').val()
    };

    window.InventraUI.tableLoading(table, true);

    $.getJSON(`${API_BASE}/assets`, params, function (res) {
      if (res.success) {
        renderAssetsTable(res.data || []);
      }
    }).fail(function () {
      table.clear().draw(false);
      showToast('Gagal memuat data aset dari server.', true);
    }).always(function () {
      window.InventraUI.tableLoading(table, false);
    });
  }

  function renderAssetsTable(assets) {
    const table = getAssetsTable();
    let rows = '';

    assets.forEach(function (a) {
      let statusBadge = '';
      if (a.status === 'available') {
        statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle badge-status">Tersedia</span>';
      } else if (a.status === 'in_use') {
        statusBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle badge-status">Dipakai</span>';
      } else if (a.status === 'maintenance') {
        statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle badge-status">Perbaikan</span>';
      } else {
        statusBadge = '<span class="badge bg-secondary-subtle text-secondary badge-status">Afkir</span>';
      }

      let condBadge = '';
      if (a.condition_level === 'new') {
        condBadge = '<span class="badge bg-info-subtle text-info badge-status">Baru</span>';
      } else if (a.condition_level === 'good') {
        condBadge = '<span class="badge bg-success-subtle text-success badge-status">Baik</span>';
      } else if (a.condition_level === 'fair') {
        condBadge = '<span class="badge bg-warning-subtle text-warning badge-status">Cukup</span>';
      } else {
        condBadge = '<span class="badge bg-danger-subtle text-danger badge-status">Rusak</span>';
      }

      const assignedDisplay = a.assigned_to
        ? `<div class="small fw-semibold text-dark"><i class="bi bi-person me-1 text-muted"></i>${escapeHtml(a.assigned_to)}</div>`
        : '<span class="text-muted small">-</span>';

      const checkoutBtn = a.status === 'available'
        ? `<button class="btn btn-sm btn-outline-primary py-0 px-2 btn-checkout" data-id="${escapeHtml(a.id)}" data-name="${escapeHtml(a.name)}" data-code="${escapeHtml(a.asset_code)}" title="Serah Terima / Pakai"><i class="bi bi-box-arrow-right"></i> Pinjam</button>`
        : (a.status === 'in_use'
          ? `<button class="btn btn-sm btn-outline-success py-0 px-2 btn-checkin" data-id="${escapeHtml(a.id)}" data-name="${escapeHtml(a.name)}" data-assigned="${escapeHtml(a.assigned_to)}" title="Kembalikan ke Gudang"><i class="bi bi-box-arrow-in-left"></i> Kembali</button>`
          : '');

      // Aksi hapus khusus administrator, sembunyikan agar staf tidak menabrak 403.
      const deleteBtn = isAdmin
        ? `<button class="btn btn-sm btn-light border py-0 px-2 text-danger btn-delete" data-id="${escapeHtml(a.id)}" data-name="${escapeHtml(a.name)}" title="Hapus"><i class="bi bi-trash"></i></button>`
        : '';

      rows += `
        <tr data-id="${escapeHtml(a.id)}">
          <td>
            <div class="fw-bold text-dark">${escapeHtml(a.name)}</div>
            <div class="small text-muted font-monospace"><i class="bi bi-upc-scan me-1"></i>${escapeHtml(a.asset_code)}</div>
            ${a.serial_number ? `<small class="text-muted d-block">S/N: ${escapeHtml(a.serial_number)}</small>` : ''}
          </td>
          <td><span class="badge bg-light text-dark border">${escapeHtml(a.category_name)}</span></td>
          <td class="small">
            <i class="bi bi-geo-alt text-muted me-1"></i>${escapeHtml(a.location_name)}
          </td>
          <td>${condBadge}</td>
          <td>
            <div class="mb-1">${statusBadge}</div>
            ${assignedDisplay}
          </td>
          <td class="text-end fw-semibold text-dark">
            ${formatRupiah(a.purchase_cost)}
          </td>
          <td class="text-center">
            <div class="d-inline-flex gap-1 align-items-center">
              ${checkoutBtn}
              <button class="btn btn-sm btn-light border py-0 px-2 btn-edit" data-id="${escapeHtml(a.id)}" title="Edit"><i class="bi bi-pencil"></i></button>
              ${deleteBtn}
            </div>
          </td>
        </tr>
      `;
    });

    // Data kosong ditampilkan DataTables lewat opsi bahasa, bukan baris buatan sendiri.
    table.clear();
    if (rows !== '') {
      table.rows.add(window.InventraUI.rowsFromHtml(rows));
    }
    table.draw(false);
  }

  // 5. Filter Listeners
  $('#filterSearch').on('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadAssets, 250);
  });

  $('#filterCategory, #filterStatus').on('change', function () {
    loadAssets();
  });

  $('#btnResetFilter').on('click', function () {
    $('#filterSearch').val('');
    $('#filterCategory').val('');
    $('#filterStatus').val('');
    loadAssets();
  });

  // 6. Open Modal Tambah Aset
  $('#btnOpenAddModal').on('click', function () {
    $('#formAsset')[0].reset();
    $('#assetId').val('');
    $('#assetModalTitle').text('Tambah Aset Baru');
    assetModal.show();
  });

  // 7. Submit Form Aset (Create / Update)
  $('#formAsset').on('submit', function (e) {
    e.preventDefault();
    const id = $('#assetId').val();
    const isEdit = !!id;

    const payload = {
      asset_code: $('#inputAssetCode').val().trim(),
      name: $('#inputAssetName').val().trim(),
      category_id: parseInt($('#selectCategory').val(), 10),
      location_id: parseInt($('#selectLocation').val(), 10),
      serial_number: $('#inputSerialNumber').val().trim(),
      purchase_date: $('#inputPurchaseDate').val() || null,
      purchase_cost: parseFloat($('#inputPurchaseCost').val()) || 0,
      condition_level: $('#selectCondition').val(),
      notes: $('#inputNotes').val().trim()
    };

    const url = isEdit ? `${API_BASE}/assets/${id}` : `${API_BASE}/assets`;
    const method = isEdit ? 'PUT' : 'POST';

    $.ajax({
      url: url,
      type: method,
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: function (res) {
        if (res.success) {
          assetModal.hide();
          showToast(res.message);
          loadAssets();
          loadStats();
        } else {
          showToast(res.message || 'Gagal menyimpan aset', true);
        }
      },
      error: function (xhr) {
        const res = xhr.responseJSON;
        showToast(res && res.message ? res.message : 'Terjadi kesalahan sistem', true);
      }
    });
  });

  // 8. Edit Asset
  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');
    $.getJSON(`${API_BASE}/assets/${id}`, function (res) {
      if (res.success && res.data) {
        const a = res.data;
        $('#assetId').val(a.id);
        $('#inputAssetCode').val(a.asset_code);
        $('#inputAssetName').val(a.name);
        $('#selectCategory').val(a.category_id);
        $('#selectLocation').val(a.location_id);
        $('#inputSerialNumber').val(a.serial_number || '');
        $('#inputPurchaseDate').val(a.purchase_date || '');
        $('#inputPurchaseCost').val(a.purchase_cost);
        $('#selectCondition').val(a.condition_level);
        $('#inputNotes').val(a.notes || '');

        $('#assetModalTitle').text('Edit Aset: ' + a.name);
        assetModal.show();
      }
    });
  });

  // 9. Delete Asset
  $(document).on('click', '.btn-delete', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');

    window.InventraUI.confirmDialog({
      title: 'Hapus aset ini?',
      text: 'Aset "' + name + '" akan dihapus permanen dan tidak dapat dikembalikan.',
      confirmText: 'Ya, hapus',
      destructive: true
    }).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      $.ajax({
        url: `${API_BASE}/assets/${id}`,
        type: 'DELETE',
        success: function (res) {
          if (res.success) {
            showToast(res.message);
            loadAssets();
            loadStats();
          } else {
            showToast(res.message || 'Gagal menghapus aset', true);
          }
        },
        error: function () {
          showToast('Gagal menghubungi server', true);
        }
      });
    });
  });

  // 10. Check-out Asset Modal
  $(document).on('click', '.btn-checkout', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');
    const code = $(this).data('code');

    $('#checkoutAssetId').val(id);
    $('#checkoutAssetName').text(name);
    $('#checkoutAssetCode').text(code);
    $('#checkoutRecipient').val('');
    checkoutModal.show();
  });

  $('#formCheckout').on('submit', function (e) {
    e.preventDefault();
    const id = $('#checkoutAssetId').val();
    const payload = {
      recipient_name: $('#checkoutRecipient').val().trim(),
      target_location_id: parseInt($('#checkoutLocation').val(), 10),
      notes: $('#formCheckout textarea[name="notes"]').val().trim()
    };

    $.ajax({
      url: `${API_BASE}/assets/${id}/checkout`,
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: function (res) {
        if (res.success) {
          checkoutModal.hide();
          showToast(res.message);
          loadAssets();
          loadStats();
        } else {
          showToast(res.message || 'Gagal serah terima', true);
        }
      },
      error: function (xhr) {
        const res = xhr.responseJSON;
        showToast(res && res.message ? res.message : 'Kesalahan sistem', true);
      }
    });
  });

  // 11. Check-in Asset Modal
  $(document).on('click', '.btn-checkin', function () {
    const id = $(this).data('id');
    const name = $(this).data('name');
    const assigned = $(this).data('assigned');

    $('#checkinAssetId').val(id);
    $('#checkinAssetName').text(name);
    $('#checkinAssignedTo').text(assigned || '-');
    checkinModal.show();
  });

  $('#formCheckin').on('submit', function (e) {
    e.preventDefault();
    const id = $('#checkinAssetId').val();
    const payload = {
      return_location_id: parseInt($('#checkinLocation').val(), 10),
      notes: $('#formCheckin textarea[name="notes"]').val().trim()
    };

    $.ajax({
      url: `${API_BASE}/assets/${id}/checkin`,
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      success: function (res) {
        if (res.success) {
          checkinModal.hide();
          showToast(res.message);
          loadAssets();
          loadStats();
        } else {
          showToast(res.message || 'Gagal pengembalian', true);
        }
      },
      error: function (xhr) {
        const res = xhr.responseJSON;
        showToast(res && res.message ? res.message : 'Kesalahan sistem', true);
      }
    });
  });

  // Init sequence: tiap permintaan berdiri sendiri agar satu kegagalan
  // tidak memblokir pemuatan bagian dashboard lainnya.
  getAssetsTable();
  loadCategories();
  loadLocations();
  loadStats();
  loadAssets();
});
