/**
 * Inventra - Halaman Audit Trail
 * Memuat catatan aktivitas (/api/v1/activity-logs) beserta daftar jenis aksi
 * (/api/v1/activity-logs/actions), lalu menyaringnya lewat pencarian, jenis
 * aksi, dan rentang tanggal. Hanya dimuat pada /activity-logs. Notifikasi,
 * dialog, dan bahasa DataTables memakai helper bersama dari app.js
 * (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.
  const ACTION_BADGE = 'bg-primary-subtle text-primary border border-primary-subtle';

  if (document.getElementById('logTableBody') === null) {
    return;
  }

  const ui = window.InventraUI;
  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;
  const page = window.INVENTRA_ACTIVITY_LOG || {};
  const LIMIT = Number(page.limit || 200); // Banyaknya catatan yang dimuat sekaligus.

  let logTable = null;
  let searchTimer = null;

  /* ------------------------------ Sel bantu ------------------------------ */

  /** Waktu memakai waktu mentah pada data-order agar pengurutan tetap kronologis. */
  function timeCell(row) {
    const raw = String(row.created_at || '');
    const shown = String(row.created_at_formatted || raw || '-');
    return '<td data-order="' + escapeHtml(raw) + '">' +
      '<span class="small text-muted">' + escapeHtml(shown) + '</span></td>';
  }

  function actorCell(row) {
    const name = String(row.user_name || '').trim();
    if (name === '') {
      return '<td><span class="small text-muted">Sistem</span></td>';
    }

    return '<td><span class="fw-semibold text-dark">' + escapeHtml(name) + '</span></td>';
  }

  function actionCell(row) {
    const label = String(row.action_label || row.action || '').trim();
    if (label === '') {
      return '<td><span class="text-muted">-</span></td>';
    }

    return '<td><span class="badge badge-status ' + ACTION_BADGE + '">' + escapeHtml(label) + '</span></td>';
  }

  function subjectCell(row) {
    const type = String(row.subject_type || '').trim();
    if (type === '') {
      return '<td><span class="text-muted">-</span></td>';
    }

    const id = row.subject_id === null || row.subject_id === undefined || row.subject_id === ''
      ? ''
      : '<span class="small text-muted font-monospace ms-1">#' + escapeHtml(row.subject_id) + '</span>';

    return '<td><span class="badge bg-light text-dark border">' + escapeHtml(type) + '</span>' + id + '</td>';
  }

  function descriptionCell(row) {
    const description = String(row.description || '').trim();
    if (description === '') {
      return '<td><span class="text-muted">-</span></td>';
    }

    return '<td class="text-wrap-pretty">' + escapeHtml(description) + '</td>';
  }

  function ipCell(row) {
    const ip = String(row.ip_address || '').trim();
    if (ip === '') {
      return '<td><span class="text-muted">-</span></td>';
    }

    return '<td><span class="small text-muted font-monospace">' + escapeHtml(ip) + '</span></td>';
  }

  /* -------------------------------- Tabel -------------------------------- */

  /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
  function getLogTable() {
    if (logTable === null) {
      logTable = $('#tableLog').DataTable({
        searching: false, // Pencarian server-side lewat ?search=
        paging: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'Semua']],
        ordering: true,
        info: true,
        order: [], // Ikuti urutan dari API sampai pengguna mengurutkan sendiri.
        language: ui.tableLanguage('aktivitas')
      });
    }

    return logTable;
  }

  function renderLogs(rows) {
    const table = getLogTable();
    let html = '';

    rows.forEach(function (row) {
      html += '<tr data-id="' + escapeHtml(row.id) + '">' +
        timeCell(row) + actorCell(row) + actionCell(row) + subjectCell(row) +
        descriptionCell(row) + ipCell(row) +
        '</tr>';
    });

    // Data kosong ditampilkan DataTables lewat opsi bahasa, bukan baris buatan sendiri.
    table.clear();
    if (html !== '') {
      table.rows.add(ui.rowsFromHtml(html));
    }
    table.draw(false);
  }

  /* ------------------------------- Pemuatan ------------------------------- */

  function showError(message) {
    $('#logError').html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small mx-4 mt-3 mb-0">' +
      '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  function filters() {
    const params = {
      search: String($('#logSearch').val() || '').trim(),
      action: String($('#logActionFilter').val() || ''),
      date_from: String($('#logDateFrom').val() || ''),
      date_to: String($('#logDateTo').val() || '')
    };

    params.limit = LIMIT;

    // Rentang terbalik ditukar supaya pengguna tidak menerima hasil kosong tanpa sebab.
    // Format YYYY-MM-DD bisa dibandingkan langsung sebagai teks.
    if (params.date_from !== '' && params.date_to !== '' && params.date_from > params.date_to) {
      const earlier = params.date_from;
      params.date_from = params.date_to;
      params.date_to = earlier;
      $('#logDateFrom').val(params.date_from);
      $('#logDateTo').val(params.date_to);
    }

    return params;
  }

  function loadLogs() {
    const table = getLogTable();
    $('#logError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: API_BASE + '/activity-logs', type: 'GET', data: filters(), dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          renderLogs(res.data);
          return;
        }

        renderLogs([]);
        showError((res && res.message) || 'Gagal memuat audit trail.');
      })
      .fail(function (xhr) {
        const res = xhr.responseJSON;
        const fallback = xhr.status === 403
          ? 'Audit trail hanya dapat diakses administrator.'
          : 'Gagal memuat audit trail. Coba lagi ya.';

        renderLogs([]);
        showError((res && res.message) || fallback);
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

  function loadActions() {
    $.ajax({ url: API_BASE + '/activity-logs/actions', type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (!res || !res.success || !Array.isArray(res.data)) {
          return;
        }

        let html = '<option value="">Semua jenis aksi</option>';

        res.data.forEach(function (entry) {
          // Server boleh mengirim daftar string maupun pasangan value/label.
          const value = typeof entry === 'string' ? entry : String(entry.value === undefined ? '' : entry.value);
          const label = typeof entry === 'string' ? entry : String(entry.label === undefined ? value : entry.label);

          if (value === '') {
            return;
          }

          html += '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
        });

        document.getElementById('logActionFilter').innerHTML = html;
      });
  }

  /* -------------------------------- Filter -------------------------------- */

  $('#logSearch').on('input search', function () {
    if (searchTimer !== null) {
      window.clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(function () {
      searchTimer = null;
      loadLogs();
    }, SEARCH_DELAY);
  });

  $('#logActionFilter, #logDateFrom, #logDateTo').on('change', function () {
    loadLogs();
  });

  $('#btnResetLogFilter').on('click', function () {
    $('#logSearch').val('');
    $('#logActionFilter').val('');
    $('#logDateFrom').val('');
    $('#logDateTo').val('');
    loadLogs();
  });

  // Tiap permintaan berdiri sendiri agar satu kegagalan tidak memblokir bagian lain.
  getLogTable();
  loadActions();
  loadLogs();
});
