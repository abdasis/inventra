/**
 * Inventra - Halaman Kelola Pengguna
 * Daftar dan form pengguna dikelola lewat AJAX ke API. Hanya dimuat pada halaman /users.
 * Notifikasi dan dialog konfirmasi memakai helper bersama dari app.js (window.InventraUI).
 */

$(document).ready(function () {
  const API_BASE = '/api/v1';
  const USERS_URL = `${API_BASE}/users`;
  const FLASH_KEY = 'inventra.users.flash';
  const currentUser = window.INVENTRA_USER || { id: 0, isAdmin: false };
  const ui = window.InventraUI;

  let usersTable = null;

  function showToast(message, isError = false) {
    ui.toast(message, isError);
  }

  function confirmAction(options) {
    return ui.confirmDialog(options);
  }

  // Escaping bersama dari app.js, dipakai untuk teks maupun nilai atribut HTML.
  const escapeHtml = ui.escapeHtml;

  function apiMessage(xhr, fallback) {
    const res = xhr.responseJSON;
    if (xhr.status === 403) {
      return (res && res.message) || 'Hanya administrator yang dapat mengelola pengguna.';
    }
    return (res && res.message) || fallback;
  }

  function showListError(message) {
    $('#usersError').html(
      '<div class="alert alert-danger alert-dismissible fade show py-2 small mx-4 mt-3 mb-0">' +
      '<i class="bi bi-exclamation-triangle me-1"></i>' + escapeHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>' +
      '</div>'
    );
  }

  function showFormError(message) {
    $('#formUserError').html(
      '<div class="alert alert-danger d-flex align-items-start gap-2 py-2 small mb-0">' +
      '<i class="bi bi-exclamation-triangle mt-1"></i><span>' + escapeHtml(message) + '</span>' +
      '</div>'
    );
  }

  function setFlash(message) {
    try {
      sessionStorage.setItem(FLASH_KEY, message);
    } catch (error) {
      // Penyimpanan sesi tidak tersedia: pesan cukup dilewatkan.
    }
  }

  function consumeFlash() {
    try {
      const message = sessionStorage.getItem(FLASH_KEY);
      if (message) {
        sessionStorage.removeItem(FLASH_KEY);
        showToast(message);
      }
    } catch (error) {
      // Penyimpanan sesi tidak tersedia.
    }
  }

  /* ------------------------------- Daftar user ------------------------------- */

  /**
   * Tabel pengguna dikelola satu instance DataTables; muat ulang data dilakukan
   * lewat API lalu barisnya diganti, bukan dengan menginisialisasi tabel baru.
   */
  function getUsersTable() {
    if (usersTable === null) {
      usersTable = $('#tableUsers').DataTable({
        searching: true, // Halaman ini tidak punya filter kustom.
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        ordering: true,
        info: true,
        order: [], // Ikuti urutan dari API sampai pengguna mengurutkan sendiri.
        columnDefs: [{ targets: 4, orderable: false }],
        language: ui.tableLanguage('pengguna')
      });
    }

    return usersTable;
  }

  function renderUsersTable(users) {
    const table = getUsersTable();
    const statusBadge = {
      active: 'bg-success-subtle text-success border border-success-subtle',
      inactive: 'bg-secondary-subtle text-secondary border border-secondary-subtle'
    };

    let rows = '';
    users.forEach(function (user) {
      const isActive = user.is_active === true || user.is_active === 1;
      const isSelf = Number(user.id) === Number(currentUser.id);
      const selfBadge = isSelf ? '<span class="badge bg-light text-secondary border ms-1">Anda</span>' : '';
      const disabled = isSelf ? ' disabled' : '';
      const lastLogin = user.last_login_formatted ? escapeHtml(user.last_login_formatted) : '-';

      rows +=
        '<tr data-id="' + escapeHtml(user.id) + '">' +
          '<td>' +
            '<div class="fw-bold text-dark">' + escapeHtml(user.name) + selfBadge + '</div>' +
            '<div class="small text-muted">' + escapeHtml(user.email) + '</div>' +
          '</td>' +
          '<td><span class="badge badge-status ' + escapeHtml(user.role_badge_class) + '">' +
            escapeHtml(user.role_label) + '</span></td>' +
          '<td><span class="badge badge-status ' +
            (isActive ? statusBadge.active : statusBadge.inactive) + '">' +
            escapeHtml(user.status_label) + '</span></td>' +
          '<td class="small text-muted">' + lastLogin + '</td>' +
          '<td class="text-center">' +
            '<div class="table-actions d-inline-flex align-items-center">' +
              '<a href="/users/' + encodeURIComponent(user.id) + '/edit" class="btn btn-sm btn-light border px-2"' +
                ' title="Ubah data ' + escapeHtml(user.name) + '">' +
                '<i class="bi bi-pencil"></i> Ubah</a>' +
              '<button type="button" class="btn btn-sm btn-light border px-2 btn-toggle-user"' +
                ' data-id="' + escapeHtml(user.id) + '" data-name="' + escapeHtml(user.name) + '"' +
                ' data-active="' + (isActive ? '1' : '0') + '"' + disabled +
                ' title="' + (isSelf ? 'Tidak dapat mengubah status akun sendiri' : 'Ubah status aktif pengguna') + '">' +
                '<i class="bi ' + (isActive ? 'bi-pause-circle' : 'bi-play-circle') + '"></i> ' +
                (isActive ? 'Nonaktifkan' : 'Aktifkan') + '</button>' +
              '<button type="button" class="btn btn-sm btn-light border px-2 text-danger btn-delete-user"' +
                ' data-id="' + escapeHtml(user.id) + '" data-name="' + escapeHtml(user.name) + '"' + disabled +
                ' title="' + (isSelf ? 'Tidak dapat menghapus akun sendiri' : 'Hapus pengguna') + '">' +
                '<i class="bi bi-trash"></i> Hapus</button>' +
            '</div>' +
          '</td>' +
        '</tr>';
    });

    // Data kosong ditampilkan DataTables lewat opsi bahasa, bukan baris buatan sendiri.
    table.clear();
    if (rows !== '') {
      table.rows.add(ui.rowsFromHtml(rows));
    }
    table.draw(false);
  }

  function loadUsers() {
    const table = getUsersTable();
    $('#usersError').empty();
    ui.tableLoading(table, true);

    $.ajax({ url: USERS_URL, type: 'GET', dataType: 'json' })
      .done(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          renderUsersTable(res.data);
          return;
        }
        table.clear().draw(false);
        showListError((res && res.message) || 'Gagal memuat data pengguna.');
      })
      .fail(function (xhr) {
        table.clear().draw(false);
        showListError(apiMessage(xhr, 'Gagal memuat data pengguna. Coba lagi ya.'));
      })
      .always(function () {
        ui.tableLoading(table, false);
      });
  }

  function runUserAction($button, options, successMessage) {
    if ($button.prop('disabled')) {
      return;
    }

    $button.prop('disabled', true);

    $.ajax($.extend({ dataType: 'json' }, options))
      .done(function (res) {
        if (res && res.success) {
          showToast(res.message || successMessage);
          loadUsers();
          return;
        }
        $button.prop('disabled', false);
        showToast((res && res.message) || 'Operasi gagal dilakukan.', true);
      })
      .fail(function (xhr) {
        $button.prop('disabled', false);
        showToast(apiMessage(xhr, 'Operasi gagal dilakukan. Coba lagi ya.'), true);
      });
  }

  $(document).on('click', '.btn-toggle-user', function () {
    const $button = $(this);
    const isActive = String($button.data('active')) === '1';
    const name = String($button.data('name'));

    confirmAction({
      title: isActive ? 'Nonaktifkan pengguna ini?' : 'Aktifkan kembali pengguna ini?',
      text: isActive
        ? 'Akun ' + name + ' tidak dapat dipakai masuk sampai diaktifkan kembali.'
        : 'Akun ' + name + ' akan dapat masuk kembali ke sistem.',
      confirmText: isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan',
      destructive: isActive
    }).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      runUserAction(
        $button,
        { url: USERS_URL + '/' + encodeURIComponent($button.data('id')) + '/toggle-active', type: 'POST' },
        isActive ? 'Pengguna berhasil dinonaktifkan.' : 'Pengguna berhasil diaktifkan kembali.'
      );
    });
  });

  $(document).on('click', '.btn-delete-user', function () {
    const $button = $(this);
    const name = String($button.data('name'));

    confirmAction({
      title: 'Hapus pengguna ini?',
      text: 'Akun ' + name + ' akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.',
      confirmText: 'Ya, hapus',
      destructive: true
    }).then(function (confirmed) {
      if (!confirmed) {
        return;
      }

      runUserAction(
        $button,
        { url: USERS_URL + '/' + encodeURIComponent($button.data('id')), type: 'DELETE' },
        'Pengguna berhasil dihapus.'
      );
    });
  });

  /* -------------------------------- Form user -------------------------------- */

  function clearFormErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(function (field) {
      field.classList.remove('is-invalid');
      field.removeAttribute('aria-invalid');
    });
    form.querySelectorAll('[id^="error-"]').forEach(function (box) {
      box.textContent = '';
      box.hidden = true;
    });
    $('#formUserError').empty();
  }

  function applyFieldErrors(form, errors) {
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

  function fillForm(form, user) {
    form.elements.name.value = user.name || '';
    form.elements.email.value = user.email || '';
    form.elements.role.value = user.role || '';
    form.elements.is_active.checked = user.is_active === true || user.is_active === 1;
    // Kata sandi sengaja dibiarkan kosong: hanya diubah bila admin mengisinya.
  }

  function loadUserIntoForm(form, userId, $submit) {
    $submit.prop('disabled', true);

    $.ajax({ url: USERS_URL, type: 'GET', dataType: 'json' })
      .done(function (res) {
        const users = res && res.success && Array.isArray(res.data) ? res.data : [];
        const user = users.filter(function (item) {
          return String(item.id) === String(userId);
        })[0];

        if (!user) {
          showFormError('Pengguna tidak ditemukan.');
          return;
        }

        fillForm(form, user);
      })
      .fail(function (xhr) {
        showFormError(apiMessage(xhr, 'Gagal memuat data pengguna.'));
      })
      .always(function () {
        $submit.prop('disabled', false);
      });
  }

  function submitUserForm(form, mode, userId, $submit) {
    clearFormErrors(form);

    const payload = {
      name: String(form.elements.name.value).trim(),
      email: String(form.elements.email.value).trim(),
      role: String(form.elements.role.value),
      is_active: form.elements.is_active.checked,
      password: String(form.elements.password.value),
      password_confirmation: String(form.elements.password_confirmation.value)
    };

    $submit.prop('disabled', true);

    $.ajax({
      url: mode === 'edit' ? USERS_URL + '/' + encodeURIComponent(userId) : USERS_URL,
      type: mode === 'edit' ? 'PUT' : 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      dataType: 'json'
    })
      .done(function (res) {
        if (res && res.success) {
          // Tombol dibiarkan nonaktif saat berhasil agar tidak terkirim dua kali.
          setFlash(res.message || 'Data pengguna berhasil disimpan.');
          window.location.href = '/users';
          return;
        }
        $submit.prop('disabled', false);
        showFormError((res && res.message) || 'Gagal menyimpan data pengguna.');
      })
      .fail(function (xhr) {
        const res = xhr.responseJSON;
        if (xhr.status === 422 && res && res.errors) {
          applyFieldErrors(form, res.errors);
          showFormError(res.message || 'Periksa kembali isian formulir.');
        } else {
          showFormError(apiMessage(xhr, 'Gagal menyimpan data pengguna. Coba lagi ya.'));
        }
        $submit.prop('disabled', false);
      });
  }

  function initUserForm() {
    const form = document.getElementById('formUser');
    const $form = $(form);
    const $submit = $('#btnSubmitUser');
    const mode = $form.data('mode') === 'edit' ? 'edit' : 'create';
    const userId = String($('#inputUserId').val() || '');

    if (mode === 'edit') {
      loadUserIntoForm(form, userId, $submit);
    }

    $form.on('submit', function (event) {
      event.preventDefault();
      submitUserForm(form, mode, userId, $submit);
    });

    // Pesan error field dibersihkan begitu pengguna memperbaiki isian.
    $form.on('input change', 'input, select', function () {
      this.classList.remove('is-invalid');
      this.removeAttribute('aria-invalid');
      const box = document.getElementById('error-' + this.name);
      if (box) {
        box.textContent = '';
        box.hidden = true;
      }
    });
  }

  if (document.getElementById('usersTableBody') !== null) {
    consumeFlash();
    loadUsers();
  } else if (document.getElementById('formUser') !== null) {
    initUserForm();
  }
});
