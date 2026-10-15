/**
 * Inventra - Form Master Data (barang, karyawan, supplier)
 * Memuat baris saat mode ubah, mengisi dropdown yang opsinya dari API
 * (kategori dan satuan barang), mengirim POST/PUT JSON, lalu menampilkan error
 * validasi per field. Hanya dimuat pada halaman /master/{entitas}/*.
 * Notifikasi memakai helper bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const FLASH_KEY = 'inventra.master.flash';
  const page = window.INVENTRA_MASTER || {};
  const ui = window.InventraUI;
  const form = document.getElementById('formMaster');

  if (form === null || !page.entity) {
    return;
  }

  const escapeHtml = ui.escapeHtml;
  const subject = String(page.label || '').toLowerCase();

  // Field yang perlu dikirim sebagai angka, bukan teks; boolean sudah ditangani
  // dari tipe input checkbox.
  const NUMBER_FIELDS = { items: ['minimum_stock'] };

  const $submit = $('#btnSubmitMaster');
  const mode = form.dataset.mode === 'edit' ? 'edit' : 'create';
  const id = String($('#masterId').val() || '');
  const entityUrl = API_BASE + '/' + page.entity;

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    if (xhr.status === 403) {
      return (res && res.message) || 'Hanya administrator yang dapat mengelola data master.';
    }
    return (res && res.message) || fallback;
  }

  function showFormError(message) {
    $('#masterFormError').html(
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
    $('#masterFormError').empty();
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

  /** Menyalin nilai baris ke field form; hanya field yang namanya cocok. */
  function fillForm(row) {
    Object.keys(row).forEach(function (key) {
      const field = form.elements[key];
      if (!field) {
        return;
      }

      // is_active dari API bertipe number 1/0, sama seperti pada users.js.
      if (field.type === 'checkbox') {
        field.checked = Number(row[key]) === 1;
        return;
      }

      field.value = row[key] === null || row[key] === undefined ? '' : String(row[key]);
    });
  }

  /** Dropdown yang opsinya berasal dari API diisi sebelum data baris dipasang. */
  function fillDynamicSelects() {
    const requests = [];

    form.querySelectorAll('select[data-source]').forEach(function (select) {
      requests.push(
        $.ajax({ url: API_BASE + '/' + select.dataset.source, type: 'GET', dataType: 'json' })
          .done(function (res) {
            const list = res && res.success && Array.isArray(res.data) ? res.data : [];
            let html = '<option value="">' + escapeHtml(select.dataset.emptyLabel || 'Pilih salah satu') + '</option>';

            list.forEach(function (item) {
              html += '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.name) + '</option>';
            });

            select.innerHTML = html;
          })
      );
    });

    return $.when.apply($, requests);
  }

  function loadRecord() {
    $submit.prop('disabled', true);

    $.ajax({ url: entityUrl + '/' + encodeURIComponent(id), type: 'GET', dataType: 'json' })
      .done(function (res) {
        const row = res && res.success ? (Array.isArray(res.data) ? res.data[0] : res.data) : null;
        if (!row) {
          showFormError('Data ' + subject + ' tidak ditemukan. Kembali ke daftar lalu pilih ulang.');
          return;
        }
        fillForm(row);
      })
      .fail(function (xhr) {
        showFormError(apiMessage(xhr, 'Gagal memuat data. Coba lagi ya.'));
      })
      .always(function () {
        $submit.prop('disabled', false);
      });
  }

  function buildPayload() {
    const numbers = NUMBER_FIELDS[page.entity] || [];
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

      if (numbers.indexOf(name) !== -1) {
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
      url: mode === 'edit' ? entityUrl + '/' + encodeURIComponent(id) : entityUrl,
      type: mode === 'edit' ? 'PUT' : 'POST',
      contentType: 'application/json',
      data: JSON.stringify(buildPayload()),
      dataType: 'json'
    })
      .done(function (res) {
        if (res && res.success) {
          // Tombol dibiarkan nonaktif saat berhasil agar tidak terkirim dua kali.
          try {
            sessionStorage.setItem(FLASH_KEY, res.message || 'Data berhasil disimpan.');
          } catch (error) {
            // Penyimpanan sesi tidak tersedia: pesan cukup dilewatkan.
          }
          window.location.href = '/master/' + encodeURIComponent(String(page.entity));
          return;
        }

        $submit.prop('disabled', false);
        showFormError((res && res.message) || 'Gagal menyimpan data. Coba lagi ya.');
      })
      .fail(function (xhr) {
        const res = xhr.responseJSON;
        if (xhr.status === 422 && res && res.errors) {
          applyFieldErrors(res.errors);
          showFormError(res.message || 'Periksa kembali isian formulir.');
        } else {
          showFormError(apiMessage(xhr, 'Gagal menyimpan data. Coba lagi ya.'));
        }
        $submit.prop('disabled', false);
      });
  }

  if (mode === 'edit') {
    // Dropdown diisi lebih dulu supaya nilai relasi baris tidak hilang.
    fillDynamicSelects().always(loadRecord);
  } else {
    fillDynamicSelects();
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    submitForm();
  });

  // Pesan error field dibersihkan begitu pengguna memperbaiki isian.
  form.addEventListener('input', clearFieldState);
  form.addEventListener('change', clearFieldState);
});
