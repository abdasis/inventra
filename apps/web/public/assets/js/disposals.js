/**
 * Inventra - Penghapusan Aset (daftar)
 * Memuat daftar penghapusan (/api/v1/disposals) dan menyaringnya. Pencatatan
 * penghapusan baru berfield banyak, jadi memakai halaman /disposals/create
 * (disposal-form.js). Hanya dimuat pada halaman /disposals.
 *
 * Helper kecil (escaping, badge, wadah error blok) sengaja disalin dari
 * assignments.js: tidak ada bundler maupun berkas modul bersama, dan duplikasi
 * sekecil ini lebih murah daripada pola baru.
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.
  const FLASH_KEY = 'inventra.disposals.flash';
  const ui = window.InventraUI;

  if (!ui || document.getElementById('disposalTableBody') === null) {
    return;
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;

  // Label dan kelas badge disediakan API; peta ini hanya sandaran bila belum ada.
  const METHOD_BADGES = {
    sold: ['Dijual', 'bg-primary-subtle text-primary border border-primary-subtle'],
    donated: ['Disumbangkan', 'bg-info-subtle text-info border border-info-subtle'],
    destroyed: ['Dimusnahkan', 'bg-danger-subtle text-danger border border-danger-subtle'],
    written_off: ['Dihapus dari Pembukuan', 'bg-secondary-subtle text-secondary border border-secondary-subtle']
  };

  let disposalTable = null;
  let searchTimer = null;

  /* --------------------------- Sel bantu tabel --------------------------- */

  function badge(label, className) {
    return '<span class="badge badge-status ' + className + '">' + escapeHtml(label) + '</span>';
  }

  function methodBadge(row) {
    const fallback = METHOD_BADGES[String(row.method || '')] || [];
    const label = String(row.method_label || fallback[0] || '').trim();

    if (label === '') {
      return '<span class="text-muted">-</span>';
    }

    return badge(label, fallback[1] || 'bg-secondary-subtle text-secondary border border-secondary-subtle');
  }

  function assetCell(row) {
    return '<div class="fw-semibold text-dark">' + escapeHtml(row.asset_name || '-') + '</div>' +
      '<div class="small text-muted font-monospace">' + escapeHtml(row.asset_code || '-') + '</div>';
  }

  function formatRupiah(value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
  }

  function mutedDate(value) {
    return value ? '<span class="small text-muted">' + escapeHtml(value) + '</span>' : '<span class="text-muted">-</span>';
  }

  function mutedText(value) {
    const text = String(value === null || value === undefined ? '' : value).trim();

    return text === '' ? '<span class="text-muted">-</span>' : escapeHtml(text);
  }

  /** Alasan penghapusan disertai nomor dokumen pendukung bila ada. */
  function reasonCell(row) {
    const reason = String(row.reason || '').trim();
    const reference = String(row.document_reference || '').trim();
    const notes = String(row.notes || '').trim();

    let html = reason === ''
      ? '<span class="text-muted">-</span>'
      : '<div class="disposal-reason">' + escapeHtml(reason) + '</div>';

    if (reference !== '') {
      html += '<div class="small text-muted mt-1">Dokumen: ' + escapeHtml(reference) + '</div>';
    }

    if (notes !== '') {
      html += '<div class="small text-muted text-wrap-pretty mt-1">' + escapeHtml(notes) + '</div>';
    }

    return html;
  }

  function approvedCell(row) {
    const approved = String(row.approved_by_name || '').trim();
    const executor = String(row.executed_by_name || '').trim();

    if (approved === '' && executor === '') {
      return '<span class="text-muted">-</span>';
    }

    return (approved === '' ? '<span class="text-muted">-</span>' : '<div class="fw-semibold text-dark">' + escapeHtml(approved) + '</div>') +
      (executor !== '' ? '<div class="small text-muted">Dicatat oleh ' + escapeHtml(executor) + '</div>' : '');
  }

  /** Aset yang sudah dihapus tetap dapat dibuka riwayatnya di halaman detail aset. */
  function actionsCell(row) {
    if (!row.asset_id) {
      return '<span class="text-muted">-</span>';
    }

    const name = escapeHtml(row.asset_name || 'aset ini');

    return '<div class="table-actions d-inline-flex align-items-center">' +
      '<a href="/assets/' + escapeHtml(row.asset_id) + '" class="btn btn-sm btn-light border px-2"' +
      ' title="Lihat detail aset ' + name + '" aria-label="Lihat detail aset ' + name + '">' +
      '<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Detail</a>' +
      '</div>';
  }

  /* -------------------------------- Daftar -------------------------------- */

  /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
  function getTable() {
    if (disposalTable === null) {
      disposalTable = $('#tableDisposal').DataTable({
        searching: false, // Pencarian server-side lewat ?search=
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: false, // Urutan baris mengikuti API.
        info: true,
        order: [],
        language: ui.tableLanguage('penghapusan aset')
      });
    }

    return disposalTable;
  }

  function renderRows(rows) {
    const table = getTable();
    let html = '';

    rows.forEach(function (row) {
      html += '<tr data-id="' + escapeHtml(row.id) + '">' +
        '<td>' + assetCell(row) + '</td>' +
        '<td>' + mutedText(row.category_name) + '</td>' +
        '<td>' + mutedDate(row.disposal_date) + '</td>' +
        '<td>' + methodBadge(row) + '</td>' +
        '<td class="text-end fw-semibold text-dark">' +
        escapeHtml(row.book_value_formatted || formatRupiah(row.book_value)) + '</td>' +
        '<td>' + reasonCell(row) + '</td>' +
        '<td>' + approvedCell(row) + '</td>' +
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

  function loadDisposals() {
    const table = getTable();
    const search = String($('#disposalSearch').val() || '').trim();
    const params = {};

    if (search !== '') {
      params.search = search;
    }

    $('#disposalError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: API_BASE + '/disposals', type: 'GET', data: params, dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          renderRows(res.data);
          return;
        }

        table.clear().draw(false);
        showBlockError('#disposalError', (res && res.message) || 'Gagal memuat data penghapusan aset.');
      })
      .fail(function (xhr) {
        table.clear().draw(false);
        showBlockError('#disposalError', apiMessage(xhr, 'Gagal memuat data penghapusan aset. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

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

  /* -------------------------------- Filter -------------------------------- */

  $('#disposalSearch').on('input search', function () {
    if (searchTimer !== null) {
      window.clearTimeout(searchTimer);
    }
    searchTimer = window.setTimeout(function () {
      searchTimer = null;
      loadDisposals();
    }, SEARCH_DELAY);
  });

  consumeFlash();
  loadDisposals();
});
