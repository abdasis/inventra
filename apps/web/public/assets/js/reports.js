/**
 * Inventra - Halaman Laporan Inventaris
 * Memuat ringkasan (/api/v1/reports/summary) untuk mengisi kartu angka dan
 * rekapitulasi per kategori, serta merangkai tautan ekspor aset dari filter
 * yang dipilih. Unduhan CSV tidak lewat AJAX: tautan biasa supaya peramban
 * yang menangani Content-Disposition dari server. Hanya dimuat pada /reports.
 * Notifikasi dan escaping memakai helper bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';

  if (document.getElementById('reportMetrics') === null) {
    return;
  }

  const ui = window.InventraUI;
  const escapeHtml = ui.escapeHtml;

  // Filter yang berlaku untuk ekspor aset: id kontrol -> nama parameter query.
  const EXPORT_FILTERS = [
    { id: 'exportStatus', param: 'status' },
    { id: 'exportCategory', param: 'category_id' },
    { id: 'exportLocation', param: 'location_id' }
  ];

  function number(value) {
    return Number(value || 0).toLocaleString('id-ID');
  }

  /** assets_by_status dari API berbentuk daftar; ambil jumlah pada satu kunci status. */
  function statusCount(list, key) {
    let count = 0;

    (Array.isArray(list) ? list : []).forEach(function (entry) {
      if (String(entry.status || '') === key) {
        count = Number(entry.count || 0);
      }
    });

    return count;
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element !== null) {
      element.textContent = value;
    }
  }

  function showError(message) {
    $('#reportError').html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small mb-4">' +
      '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  /* ------------------------------ Kartu angka ------------------------------ */

  function renderSummary(data) {
    const byStatus = data.assets_by_status;

    setText('statTotalAssets', number(data.assets_total));
    setText('statAvailable', number(statusCount(byStatus, 'available')));
    setText('statInUse', number(statusCount(byStatus, 'in_use')));
    setText('statOnLoan', number(statusCount(byStatus, 'on_loan')));
    setText('statMaintenance', number(statusCount(byStatus, 'maintenance')));
    setText('statDisposed', number(statusCount(byStatus, 'disposed')));

    // Nilai uang memakai format dari server bila tersedia.
    setText('statTotalValuation', data.total_valuation_formatted || 'Rp ' + number(data.total_valuation));
    setText('statMaintenanceCost', data.maintenance_cost_this_year_formatted || 'Rp ' + number(data.maintenance_cost_this_year));

    setText('statLowStock', number(data.low_stock_items));
    setText('statActiveLoans', number(data.active_loans));
    setText('statDisposalsYear', number(data.disposals_this_year));
    setText('statActivities7Days', number(data.activity_last_7_days));

    // Angka terlambat ditonjolkan sebagai penanda, bukan kartu tersendiri.
    const overdue = Number(data.overdue_loans || 0);
    const hint = document.getElementById('statOverdueHint');

    if (hint !== null) {
      if (overdue > 0) {
        hint.innerHTML = '<span class="text-danger fw-semibold">' +
          '<i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>' +
          number(overdue) + ' peminjaman terlambat</span>';
      } else {
        hint.textContent = 'Tidak ada peminjaman terlambat';
      }
    }
  }

  /* ---------------------------- Per kategori ---------------------------- */

  function renderCategories(rows) {
    let html = '';

    rows.forEach(function (row) {
      html += '<tr>' +
        '<td>' + escapeHtml(row.category_name || 'Tanpa kategori') + '</td>' +
        '<td class="text-end fw-semibold">' + number(row.count) + '</td>' +
        '<td class="text-end text-muted">' + number(row.percentage) + '%</td>' +
        '</tr>';
    });

    if (html === '') {
      html = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada data kategori.</td></tr>';
    }

    document.getElementById('reportCategoryBody').innerHTML = html;
  }

  /* ------------------------------- Pemuatan ------------------------------- */

  function loadSummary() {
    const page = document.getElementById('reportPage');
    page.setAttribute('aria-busy', 'true');
    $('#reportError').empty();

    $.ajax({ url: API_BASE + '/reports/summary', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || res.success !== true || !res.data) {
          renderCategories([]);
          showError((res && res.message) || 'Gagal memuat ringkasan laporan.');
          return;
        }

        renderSummary(res.data);
        renderCategories(Array.isArray(res.data.top_categories) ? res.data.top_categories : []);
      })
      .fail(function (xhr) {
        const res = xhr.responseJSON;
        renderCategories([]);
        showError((res && res.message) || 'Gagal memuat ringkasan laporan. Coba lagi ya.');
      })
      .always(function () {
        page.setAttribute('aria-busy', 'false');
      });
  }

  /** Tautan ekspor aset selalu mengikuti filter yang sedang dipilih. */
  function syncExportLink() {
    const params = new URLSearchParams();

    EXPORT_FILTERS.forEach(function (filter) {
      const value = String($('#' + filter.id).val() || '');
      if (value !== '') {
        params.set(filter.param, value);
      }
    });

    const query = params.toString();
    document.getElementById('exportAssetLink').href = API_BASE + '/reports/assets.csv' +
      (query === '' ? '' : '?' + query);
  }

  function loadCategories() {
    $.ajax({ url: API_BASE + '/categories', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        let html = '<option value="">Semua kategori</option>';
        res.data.forEach(function (category) {
          html += '<option value="' + escapeHtml(category.id) + '">' + escapeHtml(category.name) + '</option>';
        });

        document.getElementById('exportCategory').innerHTML = html;
        syncExportLink();
      });
  }

  function loadLocations() {
    $.ajax({ url: API_BASE + '/locations', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        let html = '<option value="">Semua lokasi</option>';
        res.data.forEach(function (location) {
          html += '<option value="' + escapeHtml(location.id) + '">' +
            escapeHtml(ui.locationLabel(location)) + '</option>';
        });

        document.getElementById('exportLocation').innerHTML = html;
        syncExportLink();
      });
  }

  EXPORT_FILTERS.forEach(function (filter) {
    $('#' + filter.id).on('change', syncExportLink);
  });

  syncExportLink();
  loadSummary();
  loadCategories();
  loadLocations();
});
