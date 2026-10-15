/**
 * Inventra - Form Aset (tambah dan ubah)
 * Mengisi dropdown dari API, mengisi kategori dan nama aset otomatis saat jenis
 * barang dipilih, mengirim POST/PUT JSON, lalu menampilkan error validasi per
 * field. Hanya dimuat pada halaman /assets/create dan /assets/{id}/edit.
 * Notifikasi dan dialog memakai helper bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const FLASH_KEY = 'inventra.assets.flash';
  const page = window.INVENTRA_ASSET || {};
  const ui = window.InventraUI;
  const form = document.getElementById('formAsset');

  if (form === null || !page.mode) {
    return;
  }

  const escapeHtml = ui.escapeHtml;
  const $submit = $('#btnSubmitAsset');
  const mode = form.dataset.mode === 'edit' ? 'edit' : 'create';
  const id = String(page.id || '');

  // Relasi dikirim sebagai angka atau null; kolom tanggal tidak menerima string
  // kosong, jadi nilai kosong pada daftar ini dikirim sebagai null.
  const RELATION_FIELDS = ['item_id', 'category_id', 'location_id'];
  const NULL_WHEN_EMPTY = ['serial_number', 'purchase_date', 'purchase_reference', 'warranty_expiry', 'photo_path', 'document_path', 'notes'];

  const itemSelect = form.elements['item_id'];
  const categorySelect = form.elements['category_id'];
  const nameInput = form.elements['name'];
  const quantityInput = form.elements['quantity'];

  let itemsCache = [];

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    return (res && res.message) || fallback;
  }

  function showFormError(message) {
    $('#assetFormError').html(
      '<div class="alert alert-danger d-flex align-items-start gap-2 py-2 small mb-0">' +
      '<i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i><span>' + escapeHtml(message) + '</span>' +
      '</div>'
    );
  }

  function clearFormErrors() {
    form.querySelectorAll('.is-invalid').forEach(function (field) {
      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('[id^="error-"]').forEach(function (box) {
      box.textContent = '';
      box.hidden = true;
    });
    $('#assetFormError').empty();
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

  /** Label lokasi disamakan dengan format daftar dashboard. */
  function locationLabel(row) {
    const parts = [row.building, row.floor].filter(function (part) {
      return part !== null && part !== undefined && String(part).trim() !== '';
    });

    return parts.length ? parts.join(' - ') + ' (' + row.name + ')' : String(row.name || '');
  }

  function loadSelect(select, format) {
    return $.ajax({ url: API_BASE + '/' + select.dataset.source, type: 'GET', dataType: 'json' })
      .done(function (res) {
        const list = res && res.success && Array.isArray(res.data) ? res.data : [];
        let html = '<option value="">' + escapeHtml(select.dataset.emptyLabel || 'Pilih salah satu') + '</option>';

        list.forEach(function (row) {
          html += '<option value="' + escapeHtml(row.id) + '">' + escapeHtml(format(row)) + '</option>';
        });

        select.innerHTML = html;

        if (select === itemSelect) {
          itemsCache = list;
        }
      });
  }

  /** Dropdown yang opsinya dari API diisi lebih dulu agar nilai relasi tidak hilang. */
  function fillDynamicSelects() {
    const requests = [];

    form.querySelectorAll('select[data-source]').forEach(function (select) {
      const format = select.dataset.source === 'locations'
        ? locationLabel
        : function (row) { return row.name; };

      requests.push(loadSelect(select, format));
    });

    return $.when.apply($, requests);
  }

  /** Menyalin nilai baris ke field form; hanya field yang namanya cocok. */
  function fillForm(row) {
    Object.keys(row).forEach(function (key) {
      const field = form.elements[key];
      if (!field) {
        return;
      }

      field.value = row[key] === null || row[key] === undefined ? '' : String(row[key]);
    });
  }

  function loadRecord() {
    $submit.prop('disabled', true);

    $.ajax({ url: API_BASE + '/assets/' + encodeURIComponent(id), type: 'GET', dataType: 'json' })
      .done(function (res) {
        const row = res && res.success ? (Array.isArray(res.data) ? res.data[0] : res.data) : null;
        if (!row) {
          showFormError('Data aset tidak ditemukan. Kembali ke daftar lalu pilih ulang.');
          return;
        }

        fillForm(row);
        $('#assetCodeHint').text('(' + String(row.asset_code || '') + ')');
      })
      .fail(function (xhr) {
        showFormError(apiMessage(xhr, 'Gagal memuat data aset. Coba lagi ya.'));
      })
      .always(function () {
        $submit.prop('disabled', false);
      });
  }

  /** Jenis barang mengisi kategori dan menyarankan nama aset bila masih kosong. */
  function applyItemSuggestion() {
    const selected = itemsCache.find(function (item) {
      return String(item.id) === String(itemSelect.value);
    });

    if (!selected) {
      return;
    }

    if (categorySelect && selected.category_id) {
      categorySelect.value = String(selected.category_id);
    }

    if (nameInput && nameInput.value.trim() === '') {
      nameInput.value = String(selected.name || '');
    }
  }

  function buildPayload() {
    const payload = {};

    Array.prototype.forEach.call(form.elements, function (field) {
      const name = field.name;
      if (!name || name === 'id' || field.type === 'submit' || field.type === 'button') {
        return;
      }

      const value = String(field.value).trim();

      if (name === 'quantity') {
        // Jumlah unit hanya berlaku saat registrasi; ubah data mengurus satu aset.
        if (mode === 'create') {
          payload.quantity = value === '' ? 1 : Number(value);
        }
        return;
      }

      if (name === 'status') {
        if (mode === 'edit') {
          payload.status = value;
        }
        return;
      }

      if (RELATION_FIELDS.indexOf(name) !== -1) {
        payload[name] = value === '' ? null : Number(value);
        return;
      }

      if (name === 'purchase_cost') {
        payload[name] = value === '' ? 0 : Math.round(Number(value));
        return;
      }

      if (NULL_WHEN_EMPTY.indexOf(name) !== -1) {
        payload[name] = value === '' ? null : value;
        return;
      }

      payload[name] = value;
    });

    return payload;
  }

  function submitForm() {
    clearFormErrors();
    $submit.prop('disabled', true);

    $.ajax({
      url: mode === 'edit' ? API_BASE + '/assets/' + encodeURIComponent(id) : API_BASE + '/assets',
      type: mode === 'edit' ? 'PUT' : 'POST',
      contentType: 'application/json',
      data: JSON.stringify(buildPayload()),
      dataType: 'json'
    })
      .done(function (res) {
        if (res && res.success) {
          // Tombol dibiarkan nonaktif saat berhasil agar tidak terkirim dua kali.
          try {
            sessionStorage.setItem(FLASH_KEY, res.message || 'Aset berhasil disimpan.');
          } catch (error) {
            // Penyimpanan sesi tidak tersedia: pesan cukup dilewatkan.
          }
          window.location.href = '/';
          return;
        }

        $submit.prop('disabled', false);
        showFormError((res && res.message) || 'Gagal menyimpan aset. Coba lagi ya.');
      })
      .fail(function (xhr) {
        const res = xhr.responseJSON;
        if (xhr.status === 422 && res && res.errors) {
          applyFieldErrors(res.errors);
          showFormError(res.message || 'Periksa kembali isian formulir.');
        } else {
          showFormError(apiMessage(xhr, 'Gagal menyimpan aset. Coba lagi ya.'));
        }
        $submit.prop('disabled', false);
      });
  }

  if (mode === 'edit') {
    fillDynamicSelects().always(loadRecord);
  } else {
    fillDynamicSelects();
    if (quantityInput) {
      quantityInput.value = '1';
    }
  }

  if (itemSelect) {
    itemSelect.addEventListener('change', applyItemSuggestion);
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    submitForm();
  });

  // Pesan error field dibersihkan begitu pengguna memperbaiki isian.
  form.addEventListener('input', clearFieldState);
  form.addEventListener('change', clearFieldState);
});
