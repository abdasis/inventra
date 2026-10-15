/**
 * Inventra - Cetak Laporan Inventaris
 * Mengambil ringkasan (/api/v1/reports/summary) lalu mengisi lembar cetak.
 * Halaman cetak berdiri sendiri tanpa app.js dan jQuery, jadi berkas ini
 * memakai fetch bawaan peramban dan escaping lokal. Hanya dimuat pada
 * /reports/print.
 */

(function () {
  const sheet = document.getElementById('reportSheet');

  if (sheet === null) {
    return;
  }

  const page = window.INVENTRA_REPORT_PRINT || {};
  const summaryUrl = String(page.summaryUrl || '/api/v1/reports/summary');
  const printButton = document.getElementById('btnPrintReport');

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function number(value) {
    return Number(value || 0).toLocaleString('id-ID');
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element !== null) {
      element.textContent = value;
    }
  }

  function setRows(id, html) {
    document.getElementById(id).innerHTML = html;
  }

  function messageRow(columns, message) {
    return '<tr><td colspan="' + columns + '">' + escapeHtml(message) + '</td></tr>';
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

  /* ------------------------------ Pengisian ------------------------------ */

  function renderMetrics(data) {
    const byStatus = data.assets_by_status;

    setText('metricTotalAssets', number(data.assets_total));
    setText('metricAvailable', number(statusCount(byStatus, 'available')));
    setText('metricInUse', number(statusCount(byStatus, 'in_use')));
    setText('metricOnLoan', number(statusCount(byStatus, 'on_loan')));
    setText('metricMaintenance', number(statusCount(byStatus, 'maintenance')));
    setText('metricDisposed', number(statusCount(byStatus, 'disposed')));

    // Nilai uang memakai format dari server bila tersedia.
    setText('metricValuation', data.total_valuation_formatted || 'Rp ' + number(data.total_valuation));
    setText('metricMaintenanceCost', data.maintenance_cost_this_year_formatted || 'Rp ' + number(data.maintenance_cost_this_year));

    setText('metricLowStock', number(data.low_stock_items));
    setText('metricActiveLoans', number(data.active_loans));
    setText('metricDisposalsYear', number(data.disposals_this_year));
    setText('metricActivities', number(data.activity_last_7_days));
  }

  function porsiCell(percentage) {
    return '<td class="report-num">' + number(percentage) + '%</td>';
  }

  function renderStatus(assetsByStatus, total) {
    const list = Array.isArray(assetsByStatus) ? assetsByStatus : [];

    if (list.length === 0) {
      setRows('reportStatusBody', messageRow(3, 'Belum ada data status aset.'));
      return;
    }

    let html = '';
    list.forEach(function (entry) {
      html += '<tr><td>' + escapeHtml(entry.status_label || entry.status || '-') + '</td>' +
        '<td class="report-num">' + number(entry.count) + '</td>' +
        porsiCell(entry.percentage) + '</tr>';
    });

    html += '<tr class="report-total"><td>Total</td>' +
      '<td class="report-num">' + number(total) + '</td>' +
      '<td class="report-num">100%</td></tr>';

    setRows('reportStatusBody', html);
  }

  function renderCategories(rows) {
    if (rows.length === 0) {
      setRows('reportCategoryBody', messageRow(3, 'Belum ada data kategori.'));
      return;
    }

    let html = '';
    rows.forEach(function (row) {
      html += '<tr><td>' + escapeHtml(row.category_name || 'Tanpa kategori') + '</td>' +
        '<td class="report-num">' + number(row.count) + '</td>' +
        porsiCell(row.percentage) + '</tr>';
    });

    setRows('reportCategoryBody', html);
  }

  /**
   * Ringkasan hanya mengirim jumlah barang berstok rendah, jadi blok ini
   * menampilkan kalimat rekap. Bila kelak server mengirim daftar barangnya,
   * baris per barang langsung dirender.
   */
  function renderLowStock(lowStock) {
    if (Array.isArray(lowStock)) {
      if (lowStock.length === 0) {
        setRows('reportLowStockBody', messageRow(3, 'Tidak ada barang habis pakai yang stoknya rendah.'));
        return;
      }

      let html = '';
      lowStock.forEach(function (row) {
        html += '<tr><td>' + escapeHtml(row.item_name || row.name || '-') + '</td>' +
          '<td class="report-num">' + number(row.quantity) + '</td>' +
          '<td class="report-num">' + number(row.minimum_stock) + '</td></tr>';
      });
      setRows('reportLowStockBody', html);
      return;
    }

    const count = Number(lowStock || 0);
    const summary = count === 0
      ? 'Tidak ada barang habis pakai yang stoknya menyentuh batas minimum.'
      : 'Terdapat ' + number(count) + ' barang habis pakai yang stoknya menyentuh atau di bawah batas minimum.';

    setRows('reportLowStockBody', messageRow(3, summary));
  }

  function fillSheet(data) {
    renderMetrics(data);
    renderStatus(data.assets_by_status, data.assets_total);
    renderCategories(Array.isArray(data.top_categories) ? data.top_categories : []);
    renderLowStock(data.low_stock_items);
    sheet.setAttribute('aria-busy', 'false');
  }

  /** Gagal muat harus terbaca jelas, bukan lembar cetak berisi tanda hubung. */
  function failSheet(message) {
    const box = document.getElementById('reportPrintError');
    box.textContent = message;
    box.hidden = false;

    setRows('reportStatusBody', messageRow(3, 'Data status aset tidak dapat dimuat.'));
    setRows('reportCategoryBody', messageRow(3, 'Data kategori tidak dapat dimuat.'));
    setRows('reportLowStockBody', messageRow(3, 'Data stok rendah tidak dapat dimuat.'));

    // Tombol cetak disembunyikan supaya laporan kosong tidak ikut tercetak.
    if (printButton !== null) {
      printButton.hidden = true;
    }

    sheet.setAttribute('aria-busy', 'false');
  }

  function loadSummary() {
    fetch(summaryUrl, { headers: { Accept: 'application/json' } })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.json();
      })
      .then(function (payload) {
        const data = payload && payload.success === true ? payload.data : null;

        if (!data) {
          failSheet('Ringkasan laporan tidak dapat dibaca dari server. Muat ulang halaman ini untuk mencoba lagi.');
          return;
        }

        fillSheet(data);
      })
      .catch(function () {
        failSheet('Gagal memuat data laporan dari server. Pastikan sesi masih aktif, lalu muat ulang halaman ini.');
      });
  }

  // Tanggal cetak dipasang lebih dulu agar tidak kosong bila permintaan gagal.
  setText('reportPrintDate', new Date().toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }));

  if (printButton !== null) {
    printButton.addEventListener('click', function () {
      window.print();
    });
  }

  loadSummary();
})();
