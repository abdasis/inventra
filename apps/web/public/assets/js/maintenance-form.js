/**
 * Inventra - Form Pemeliharaan Aset
 * Mengisi dropdown aset dari /api/v1/assets, mengirim pemeliharaan baru lewat
 * POST /api/v1/maintenances, lalu menampilkan error validasi per field. Mode ubah
 * disiapkan bila kelak rutenya ditambahkan integrator.
 * Hanya dimuat pada halaman /maintenances/create. Notifikasi memakai helper
 * bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const FLASH_KEY = 'inventra.maintenances.flash';
  const page = window.INVENTRA_MAINTENANCE_FORM || {};
  const ui = window.InventraUI;
  const form = document.getElementById('formMaintenance');

  if (form === null || !page.mode) {
    return;
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;

  // Field yang dikirim sebagai angka; sisanya teks atau id relasi.
  const NUMBER_FIELDS = ['cost'];

  const $submit = $('#btnSubmitMaintenance');
  const mode = form.dataset.mode === 'edit' ? 'edit' : 'create';
  const id = String(page.id || '');

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    if (xhr.status === 403) {
      return (res && res.message) || 'Hanya administrator yang dapat mencatat pemeliharaan aset.';
    }
    return (res && res.message) || fallback;
  }

  function showFormError(message) {
    $('#maintenanceFormError').html(
      '<div class="alert alert-danger d-flex align-items-start gap-2 py-2 small mb-0" role="alert">' +
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
    $('#maintenanceFormError').empty();
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

  /** Label aset seragam di seluruh halaman: kode - nama (lokasi). */
  function assetLabel(asset) {
    const code = String(asset.asset_code || '').trim();
    const name = String(asset.name || '').trim();
    const head = code === '' ? name : code + ' - ' + name;
    const location = String(asset.location_name || '').trim();

    return location === '' ? head : head + ' (' + location + ')';
  }

  /** Dropdown yang opsinya berasal dari API diisi sebelum data baris dipasang. */
  function fillAssetSelect() {
    const select = form.querySelector('select[data-source="assets"]');
    if (select === null) {
      return $.Deferred().resolve().promise();
    }

    return $.ajax({ url: API_BASE + '/assets', type: 'GET', dataType: 'json' })
      .done(function (res) {
        const list = res && res.success && Array.isArray(res.data) ? res.data : [];
        let html = '<option value="">' + escapeHtml(select.dataset.emptyLabel || 'Pilih aset') + '</option>';

        list.forEach(function (asset) {
          html += '<option value="' + escapeHtml(asset.id) + '">' + escapeHtml(assetLabel(asset)) + '</option>';
        });

        select.innerHTML = html;
      })
      .fail(function (xhr) {
        showFormError(apiMessage(xhr, 'Gagal memuat daftar aset. Muat ulang halaman ini ya.'));
      });
  }

  /** Menyalin nilai baris ke field form; hanya field yang namanya cocok. */
  function fillForm(row) {
    Object.keys(row).forEach(function (key) {
      const field = form.elements[key];
      if (!field || key === 'id') {
        return;
      }

      field.value = row[key] === null || row[key] === undefined ? '' : String(row[key]);
    });
  }

  function loadRecord() {
    $submit.prop('disabled', true);

    $.ajax({ url: API_BASE + '/maintenances/' + encodeURIComponent(id), type: 'GET', dataType: 'json' })
      .done(function (res) {
        const row = res && res.success ? (Array.isArray(res.data) ? res.data[0] : res.data) : null;
        if (!row) {
          showFormError('Data pemeliharaan tidak ditemukan. Kembali ke daftar lalu pilih ulang.');
          return;
        }
        fillForm(row);
      })
      .fail(function (xhr) {
        showFormError(apiMessage(xhr, 'Gagal memuat data pemeliharaan. Coba lagi ya.'));
      })
      .always(function () {
        $submit.prop('disabled', false);
      });
  }

  function buildPayload() {
    const payload = {};

    Array.prototype.forEach.call(form.elements, function (field) {
      const name = field.name;
      if (!name || name === 'id' || field.type === 'submit' || field.type === 'button') {
        return;
      }

      const value = String(field.value).trim();

      if (field.tagName === 'SELECT' && field.hasAttribute('data-source')) {
        // Id relasi dikirim sebagai angka; pilihan kosong divalidasi server.
        payload[name] = value === '' ? null : Number(value);
        return;
      }

      if (NUMBER_FIELDS.indexOf(name) !== -1) {
        payload[name] = value === '' ? 0 : Number(value);
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
      url: mode === 'edit' ? API_BASE + '/maintenances/' + encodeURIComponent(id) : API_BASE + '/maintenances',
      type: mode === 'edit' ? 'PUT' : 'POST',
      contentType: 'application/json',
      data: JSON.stringify(buildPayload()),
      dataType: 'json'
    })
      .done(function (res) {
        if (res && res.success) {
          // Tombol dibiarkan nonaktif saat berhasil agar tidak terkirim dua kali.
          try {
            sessionStorage.setItem(FLASH_KEY, res.message || 'Pemeliharaan aset berhasil dicatat.');
          } catch (error) {
            // Penyimpanan sesi tidak tersedia: pesan cukup dilewatkan.
          }
          window.location.href = '/maintenances';
          return;
        }

        $submit.prop('disabled', false);
        showFormError((res && res.message) || 'Gagal menyimpan pemeliharaan aset. Coba lagi ya.');
      })
      .fail(function (xhr) {
        const res = xhr.responseJSON;
        if (xhr.status === 422 && res && res.errors) {
          applyFieldErrors(res.errors);
          showFormError(res.message || 'Periksa kembali isian formulir.');
        } else {
          showFormError(apiMessage(xhr, 'Gagal menyimpan pemeliharaan aset. Coba lagi ya.'));
        }
        $submit.prop('disabled', false);
      });
  }

  /** Tanggal hari ini untuk pemeliharaan baru; server tetap memvalidasi. */
  function todayIso() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return now.getFullYear() + '-' + month + '-' + day;
  }

  if (mode === 'edit') {
    // Dropdown diisi lebih dulu supaya nilai relasi baris tidak hilang.
    fillAssetSelect().always(loadRecord);
  } else {
    fillAssetSelect();

    if (form.elements.maintenance_date && form.elements.maintenance_date.value === '') {
      form.elements.maintenance_date.value = todayIso();
    }
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    submitForm();
  });

  // Pesan error field dibersihkan begitu pengguna memperbaiki isian.
  form.addEventListener('input', clearFieldState);
  form.addEventListener('change', clearFieldState);
});
