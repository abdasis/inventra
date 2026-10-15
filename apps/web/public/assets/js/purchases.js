/**
 * Inventra - Pengadaan & Penerimaan
 * Daftar pembelian (mode list) dan form tambah/ubah (mode form), dibedakan
 * window.INVENTRA_PURCHASE.mode. Mode detail beserta panel penerimaan ada di
 * purchases-detail.js karena halaman /purchases/{id} memuatnya sendiri.
 * Notifikasi dan dialog memakai helper bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const FLASH_KEY = 'inventra.purchases.flash';
  const SEARCH_DELAY = 250; // Jeda ketikan sebelum permintaan pencarian dikirim.
  const page = window.INVENTRA_PURCHASE || {};
  const ui = window.InventraUI;

  if (!ui || !page.mode) {
    return;
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;

  // Label dan kelas badge disediakan API; peta ini hanya sandaran bila belum ada.
  const STATUS_BADGES = {
    draft: ['Draft', 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    partial: ['Sebagian Diterima', 'bg-warning-subtle text-warning border border-warning-subtle'],
    received: ['Diterima', 'bg-success-subtle text-success border border-success-subtle'],
    cancelled: ['Dibatalkan', 'bg-danger-subtle text-danger border border-danger-subtle']
  };

  const formatRupiah = function (value) {
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
  };

  /** Angka kuantitas dengan pemisah ribuan, tanpa mata uang. */
  const formatNumber = function (value) {
    return Number(value || 0).toLocaleString('id-ID');
  };

  function statusBadge(row) {
    const fallback = STATUS_BADGES[String(row.status || '')] || [];
    const label = String(row.status_label || fallback[0] || 'Tidak dikenal');
    const badgeClass = String(row.status_badge || fallback[1] || 'bg-secondary-subtle text-secondary');

    return '<span class="badge badge-status ' + escapeHtml(badgeClass) + '">' + escapeHtml(label) + '</span>';
  }

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    if (xhr.status === 403) {
      return (res && res.message) || 'Hanya administrator yang dapat mengubah data pembelian.';
    }
    return (res && res.message) || fallback;
  }

  function showBlockError(selector, message, extraClass) {
    $(selector).html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small ' + (extraClass || '') + '" role="alert">' +
      '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  /** Pesan hasil simpan dititipkan lewat sessionStorage sebelum pindah halaman. */
  function consumeFlash() {
    try {
      const message = sessionStorage.getItem(FLASH_KEY);
      if (!message) return;
      sessionStorage.removeItem(FLASH_KEY);
      ui.toast(message);
    } catch (error) {
      // Penyimpanan sesi tidak tersedia.
    }
  }

  /* -------------------------------- Mode daftar -------------------------------- */

  function initList() {
    if (document.getElementById('purchaseTableBody') === null) return;

    let table = null;
    let searchTimer = null;

    /** Satu instance DataTables; muat ulang mengganti baris, bukan tabel baru. */
    function getTable() {
      if (table === null) {
        table = $('#tablePurchase').DataTable({
          searching: false, // Filter server-side sudah ada di atas tabel.
          paging: true,
          pageLength: 10,
          lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
          ordering: false, // Urutan baris mengikuti API.
          info: true,
          order: [],
          language: ui.tableLanguage('pembelian')
        });
      }

      return table;
    }

    function supplierCell(row) {
      const name = String(row.supplier_name || '').trim();
      const count = Number(row.item_count || 0);
      const supplier = name === ''
        ? '<span class="text-muted">Tanpa supplier</span>'
        : '<span class="fw-semibold text-dark">' + escapeHtml(name) + '</span>';

      return supplier +
        '<div class="small text-muted">' + escapeHtml(formatNumber(count)) + ' jenis barang</div>';
    }

    function quantityCell(row) {
      const ordered = Number(row.quantity_ordered || 0);
      const received = Number(row.quantity_received || 0);
      const complete = ordered > 0 && received >= ordered;
      const reference = String(row.reference || '').trim();

      let html = '<div class="small fw-semibold text-dark">' + escapeHtml(formatNumber(ordered)) + ' dipesan</div>' +
        '<div class="small ' + (complete ? 'text-success' : 'text-muted') + '">' +
        escapeHtml(formatNumber(received)) + ' diterima</div>';

      if (reference !== '') {
        html += '<div class="small text-muted">Ref: ' + escapeHtml(reference) + '</div>';
      }

      return html;
    }

    function renderRows(rows) {
      const current = getTable();
      let html = '';

      rows.forEach(function (row) {
        const id = encodeURIComponent(String(row.id));
        const number = String(row.purchase_number || '-');
        const title = escapeHtml('Lihat detail pembelian ' + number);

        html += '<tr data-id="' + escapeHtml(row.id) + '">' +
          '<td><div class="fw-semibold text-dark font-monospace">' + escapeHtml(number) + '</div>' +
          '<div class="small text-muted">' + escapeHtml(row.purchase_date || '-') + '</div></td>' +
          '<td>' + supplierCell(row) + '</td>' +
          '<td>' + quantityCell(row) + '</td>' +
          '<td class="text-end fw-semibold">' +
          escapeHtml(row.total_amount_formatted || formatRupiah(row.total_amount)) + '</td>' +
          '<td>' + statusBadge(row) + '</td>' +
          '<td class="text-center"><div class="table-actions d-inline-flex align-items-center">' +
          '<a href="/purchases/' + id + '" class="btn btn-sm btn-light border px-2" title="' + title + '">' +
          '<i class="bi bi-eye me-1" aria-hidden="true"></i>Detail</a>' +
          '</div></td></tr>';
      });

      // Data kosong ditampilkan DataTables lewat opsi bahasa, bukan baris buatan sendiri.
      current.clear();
      if (html !== '') {
        current.rows.add(ui.rowsFromHtml(html));
      }
      current.draw(false);
    }

    function loadList() {
      const search = String($('#purchaseSearch').val() || '').trim();
      const status = String($('#purchaseStatusFilter').val() || '');
      const params = [];

      if (search !== '') {
        params.push('search=' + encodeURIComponent(search));
      }
      if (status !== '') {
        params.push('status=' + encodeURIComponent(status));
      }

      const url = API_BASE + '/purchases' + (params.length ? '?' + params.join('&') : '');
      const current = getTable();

      $('#purchaseError').empty();
      ui.tableLoading(current, true);

      $.ajax({ url: url, type: 'GET', dataType: 'json' })
        .done(function (res) {
          if (res && res.success && Array.isArray(res.data)) {
            renderRows(res.data);
            return;
          }
          current.clear().draw(false);
          showBlockError('#purchaseError', (res && res.message) || 'Gagal memuat data pembelian.', 'mx-4 mt-3 mb-0');
        })
        .fail(function (xhr) {
          current.clear().draw(false);
          showBlockError('#purchaseError', apiMessage(xhr, 'Gagal memuat data pembelian. Coba lagi ya.'), 'mx-4 mt-3 mb-0');
        })
        .always(function () {
          ui.tableLoading(current, false);
        });
    }

    $('#purchaseSearch').on('input search', function () {
      if (searchTimer !== null) {
        window.clearTimeout(searchTimer);
      }
      searchTimer = window.setTimeout(function () {
        searchTimer = null;
        loadList();
      }, SEARCH_DELAY);
    });

    $('#purchaseStatusFilter').on('change', loadList);

    consumeFlash();
    loadList();
  }

  /* -------------------------------- Mode form -------------------------------- */

  function initForm() {
    const form = document.getElementById('formPurchase');
    // Tanpa form (mis. akun staf) halaman sudah menjelaskan alasannya.
    if (form === null) return;

    const template = document.getElementById('purchaseLineTemplate');
    const $linesBody = $('#purchaseLinesBody');
    const $submit = $('#btnSubmitPurchase');
    const mode = form.dataset.mode === 'edit' ? 'edit' : 'create';
    const id = String(page.id || '');
    const supplierSelect = form.querySelector('select[data-source="suppliers"]');

    let items = [];
    let lineSeq = 0;

    function clearFormErrors() {
      form.querySelectorAll('.is-invalid').forEach(function (field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
      });
      form.querySelectorAll('[id^="error-"]').forEach(function (box) {
        box.textContent = '';
        box.hidden = true;
      });
      $('#purchaseFormError').empty();
    }

    function applyFieldErrors(errors) {
      const invalids = [];

      Object.keys(errors).forEach(function (key) {
        // Error per baris detail (details.<n>.<field>) ditandai pada input barisnya;
        // pesannya sendiri sudah dibawa message dari server.
        const indexed = /^details\.(\d+)\.(.+)$/.exec(key);
        const row = indexed === null ? null : $linesBody.find('tr.purchase-line').get(Number(indexed[1]));
        const lineInput = row ? row.querySelector('[data-line-field="' + indexed[2] + '"]') : null;
        if (lineInput !== null) {
          invalids.push(lineInput);
        }

        const box = document.getElementById('error-' + key);
        if (box) {
          box.textContent = String(errors[key]);
          box.hidden = false;
        }

        const input = form.elements[key];
        if (input) {
          invalids.push(input);
        }
      });

      invalids.forEach(function (input) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
      });

      if (invalids.length > 0) {
        invalids[0].focus();
      }
    }

    /** Penanganan gagal bersama: tandai error per field, tampilkan pesan, buka tombol simpan. */
    function failForm(xhr, fallback) {
      const res = xhr.responseJSON;
      if (xhr.status === 422 && res && res.errors) {
        applyFieldErrors(res.errors);
      }

      showBlockError('#purchaseFormError', apiMessage(xhr, fallback), 'mb-0 d-block');
      $submit.prop('disabled', false);
    }

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

    function itemLabel(item) {
      const code = String(item.code || '').trim();
      const name = String(item.name || '').trim();
      return code === '' ? name : code + ' — ' + name;
    }

    function lineField(row, name) {
      return row.querySelector('[data-line-field="' + name + '"]');
    }

    function lineSubtotal(row) {
      const quantity = Math.max(0, Number(lineField(row, 'quantity_ordered').value || 0));
      const price = Math.max(0, Number(lineField(row, 'unit_price').value || 0));
      return quantity * price;
    }

    /** Subtotal per baris dan total keseluruhan dihitung di klien saat diisi. */
    function updateTotals() {
      let total = 0;

      $linesBody.find('tr.purchase-line').each(function () {
        const subtotal = lineSubtotal(this);
        total += subtotal;
        $(this).find('.purchase-line-subtotal').text(formatRupiah(subtotal));
      });

      $('#purchaseTotal').text(formatRupiah(total));
    }

    function fillLineItems(select, selectedId) {
      let html = '<option value="">Pilih barang</option>';

      items.forEach(function (item) {
        html += '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(itemLabel(item)) + '</option>';
      });

      select.innerHTML = html;
      select.value = selectedId === null || selectedId === undefined || selectedId === '' ? '' : String(selectedId);
    }

    /**
     * Menyalin satu baris dari <template>, memberi id unik pada inputnya lalu
     * menyambungkannya ke label visually-hidden di baris yang sama.
     */
    function addLine(values) {
      if (template === null) {
        return;
      }

      const data = values || {};
      lineSeq += 1;

      const row = template.content.cloneNode(true).querySelector('tr');
      const prefix = 'purchase-line-' + lineSeq + '-';

      row.querySelectorAll('[data-line-field]').forEach(function (input) {
        const field = input.dataset.lineField;
        input.id = prefix + field;

        const label = row.querySelector('[data-line-label="' + field + '"]');
        if (label !== null) {
          label.setAttribute('for', input.id);
        }
      });

      fillLineItems(lineField(row, 'item_id'), data.item_id);
      lineField(row, 'quantity_ordered').value = String(Math.max(1, Number(data.quantity_ordered || 1)));
      lineField(row, 'unit_price').value = String(Math.max(0, Math.round(Number(data.unit_price || 0))));
      lineField(row, 'notes').value =
        data.notes === null || data.notes === undefined ? '' : String(data.notes);

      $linesBody.append(row);
      updateTotals();
    }

    function collectDetails() {
      const details = [];

      $linesBody.find('tr.purchase-line').each(function () {
        const itemId = String(lineField(this, 'item_id').value || '');
        if (itemId === '') {
          return;
        }

        details.push({
          item_id: Number(itemId),
          quantity_ordered: Math.max(1, Number(lineField(this, 'quantity_ordered').value || 1)),
          unit_price: Math.max(0, Math.round(Number(lineField(this, 'unit_price').value || 0))),
          notes: String(lineField(this, 'notes').value || '').trim()
        });
      });

      return details;
    }

    function buildPayload() {
      const supplierId = supplierSelect === null ? '' : String(supplierSelect.value || '');
      const purchaseNumber = String(form.elements['purchase_number'].value || '').trim();
      const reference = String(form.elements['reference'].value || '').trim();
      const notes = String(form.elements['notes'].value || '').trim();

      return {
        // Nomor kosong berarti dibangkitkan server; teks opsional lain dikirim null.
        purchase_number: purchaseNumber === '' ? null : purchaseNumber,
        supplier_id: supplierId === '' ? null : Number(supplierId),
        purchase_date: String(form.elements['purchase_date'].value || ''),
        reference: reference === '' ? null : reference,
        notes: notes === '' ? null : notes,
        details: collectDetails()
      };
    }

    function loadSuppliers() {
      if (supplierSelect === null) {
        return $.Deferred().resolve().promise();
      }

      return $.ajax({ url: API_BASE + '/suppliers', type: 'GET', dataType: 'json' })
        .done(function (res) {
          const list = res && res.success && Array.isArray(res.data) ? res.data : [];
          let html = '<option value="">' +
            escapeHtml(supplierSelect.dataset.emptyLabel || 'Pilih salah satu') + '</option>';

          list.forEach(function (supplier) {
            html += '<option value="' + escapeHtml(supplier.id) + '">' +
              escapeHtml(itemLabel(supplier)) + '</option>';
          });

          supplierSelect.innerHTML = html;
        })
        .fail(function (xhr) {
          failForm(xhr, 'Gagal memuat daftar supplier. Muat ulang halaman ini ya.');
        });
    }

    function loadItems() {
      return $.ajax({ url: API_BASE + '/items', type: 'GET', dataType: 'json' })
        .done(function (res) {
          items = res && res.success && Array.isArray(res.data) ? res.data : [];
        })
        .fail(function (xhr) {
          failForm(xhr, 'Gagal memuat daftar barang. Muat ulang halaman ini ya.');
        });
    }

    function fillHeader(row) {
      ['purchase_number', 'purchase_date', 'reference', 'notes'].forEach(function (name) {
        const field = form.elements[name];
        if (field) {
          field.value = row[name] === null || row[name] === undefined ? '' : String(row[name]);
        }
      });

      if (supplierSelect !== null && row.supplier_id) {
        supplierSelect.value = String(row.supplier_id);
      }
    }

    function loadRecord() {
      $submit.prop('disabled', true);

      $.ajax({ url: API_BASE + '/purchases/' + encodeURIComponent(id), type: 'GET', dataType: 'json' })
        .done(function (res) {
          const row = res && res.success ? (Array.isArray(res.data) ? res.data[0] : res.data) : null;
          if (!row) {
            showBlockError('#purchaseFormError', 'Data pembelian tidak ditemukan. Kembali ke daftar lalu pilih ulang.', 'mb-0 d-block');
            return;
          }

          fillHeader(row);

          $linesBody.empty();
          const details = Array.isArray(row.details) ? row.details : [];
          details.forEach(function (detail) {
            addLine(detail);
          });
          if (details.length === 0) {
            addLine(null);
          }
        })
        .fail(function (xhr) {
          failForm(xhr, 'Gagal memuat data pembelian. Coba lagi ya.');
        })
        .always(function () {
          $submit.prop('disabled', false);
        });
    }

    function submitForm() {
      clearFormErrors();

      if (collectDetails().length === 0) {
        showBlockError('#purchaseFormError', 'Tambahkan minimal satu barang pada detail pembelian.', 'd-block mb-0');
        return;
      }

      $submit.prop('disabled', true);

      const url = mode === 'edit'
        ? API_BASE + '/purchases/' + encodeURIComponent(id)
        : API_BASE + '/purchases';

      $.ajax({
        url: url,
        type: mode === 'edit' ? 'PUT' : 'POST',
        contentType: 'application/json',
        data: JSON.stringify(buildPayload()),
        dataType: 'json'
      })
        .done(function (res) {
          if (res && res.success) {
            // Tombol dibiarkan nonaktif saat berhasil agar tidak terkirim dua kali.
            try {
              sessionStorage.setItem(FLASH_KEY, res.message || 'Pembelian berhasil disimpan.');
            } catch (error) {
              // Penyimpanan sesi tidak tersedia: pesan cukup dilewatkan.
            }
            window.location.href = '/purchases';
            return;
          }

          $submit.prop('disabled', false);
          showBlockError('#purchaseFormError', (res && res.message) || 'Gagal menyimpan pembelian. Coba lagi ya.', 'mb-0 d-block');
        })
        .fail(function (xhr) {
          failForm(xhr, 'Gagal menyimpan pembelian. Coba lagi ya.');
        });
    }

    /** Tanggal hari ini untuk pembelian baru; server tetap memvalidasi. */
    function todayIso() {
      const now = new Date();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      const day = String(now.getDate()).padStart(2, '0');
      return now.getFullYear() + '-' + month + '-' + day;
    }

    if (mode === 'edit') {
      // Barang dan supplier dimuat lebih dulu supaya nilai relasi baris tidak hilang.
      $.when(loadSuppliers(), loadItems()).always(loadRecord);
    } else {
      loadSuppliers();
      loadItems();

      if (form.elements['purchase_date'] && form.elements['purchase_date'].value === '') {
        form.elements['purchase_date'].value = todayIso();
      }
      addLine(null);
    }

    $('#btnAddLine').on('click', function () {
      addLine(null);
      const last = $linesBody.find('tr.purchase-line').last();
      const select = last.find('[data-line-field="item_id"]')[0];
      if (select) {
        select.focus();
      }
    });

    $linesBody.on('click', '.btn-remove-line', function () {
      $(this).closest('tr.purchase-line').remove();
      updateTotals();
    });

    $linesBody.on('input change', 'tr.purchase-line', function () {
      updateTotals();
    });

    $linesBody.on('input change', '.is-invalid', function () {
      $(this).removeClass('is-invalid').removeAttr('aria-invalid');
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      submitForm();
    });

    // Pesan error field dibersihkan begitu pengguna memperbaiki isian.
    form.addEventListener('input', clearFieldState);
    form.addEventListener('change', clearFieldState);
  }

  if (page.mode === 'list') {
    initList();
  } else if (page.mode === 'form') {
    initForm();
  }
});

