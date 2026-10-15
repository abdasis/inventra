<?php
/**
 * Shell form tambah dan ubah pengguna. Data dan pengiriman ditangani users.js.
 *
 * @var bool $isEdit
 * @var int|null $userId
 */

$loadUsersScript = true;
$isEdit = (bool)($isEdit ?? false);
$userId = $isEdit ? (int)($userId ?? 0) : null;
?>
<div class="row justify-content-center">
  <div class="col-12 col-xl-9">
    <div class="card border-0 shadow-sm rounded-3 mb-4">
      <div class="card-header bg-white py-3 px-4 border-bottom">
        <h5 class="fw-bold mb-0"><?= $isEdit ? 'Ubah Data Pengguna' : 'Tambah Pengguna Baru' ?></h5>
        <small class="text-muted">
          <?= $isEdit ? 'Perbarui identitas, peran, atau status akun pengguna' : 'Buat akun baru untuk administrator atau staf' ?>
        </small>
      </div>

      <div class="card-body p-4">
        <div id="formUserError" class="mb-3" role="alert" aria-live="assertive"></div>

        <form id="formUser" data-mode="<?= $isEdit ? 'edit' : 'create' ?>">
          <input type="hidden" name="id" id="inputUserId" value="<?= $userId ?? '' ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label for="inputName" class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="inputName" name="name"
                     autocomplete="name" aria-describedby="error-name" required>
              <div class="text-danger small mt-1" id="error-name" hidden></div>
              <small class="text-muted">Nama yang tampil pada riwayat aktivitas sistem.</small>
            </div>

            <div class="col-md-6">
              <label for="inputEmail" class="form-label small fw-semibold">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="inputEmail" name="email"
                     autocomplete="email" aria-describedby="error-email" required>
              <div class="text-danger small mt-1" id="error-email" hidden></div>
              <small class="text-muted">Dipakai untuk masuk ke sistem.</small>
            </div>

            <div class="col-md-6">
              <label for="selectRole" class="form-label small fw-semibold">Peran <span class="text-danger">*</span></label>
              <select class="form-select" id="selectRole" name="role" aria-describedby="error-role" required>
                <option value="admin">Administrator</option>
                <option value="staff" selected>Staf</option>
              </select>
              <div class="text-danger small mt-1" id="error-role" hidden></div>
              <small class="text-muted">Administrator dapat mengelola pengguna, staf hanya mengelola aset.</small>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-semibold d-block" for="inputIsActive">Status Akun</label>
              <div class="form-check form-switch pt-2">
                <input class="form-check-input" type="checkbox" role="switch" id="inputIsActive"
                       name="is_active" value="1" aria-describedby="error-is_active" checked>
                <label class="form-check-label small" for="inputIsActive">
                  Akun aktif dan dapat digunakan untuk masuk
                </label>
              </div>
              <div class="text-danger small mt-1" id="error-is_active" hidden></div>
            </div>
          </div>

          <hr class="my-4">

          <h6 class="fw-bold mb-1"><?= $isEdit ? 'Reset Kata Sandi' : 'Kata Sandi' ?></h6>
          <p class="text-muted small mb-3">
            <?= $isEdit
              ? 'Biarkan kosong bila kata sandi tidak ingin diubah.'
              : 'Minimal 8 karakter. Serahkan kata sandi ini kepada pengguna terkait.' ?>
          </p>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="inputPassword" class="form-label small fw-semibold">
                Kata Sandi <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
              </label>
              <input type="password" class="form-control" id="inputPassword" name="password"
                     autocomplete="new-password" aria-describedby="error-password" <?= $isEdit ? '' : 'required' ?>>
              <div class="text-danger small mt-1" id="error-password" hidden></div>
            </div>

            <div class="col-md-6">
              <label for="inputPasswordConfirmation" class="form-label small fw-semibold">
                Konfirmasi Kata Sandi <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
              </label>
              <input type="password" class="form-control" id="inputPasswordConfirmation"
                     name="password_confirmation" autocomplete="new-password"
                     <?= $isEdit ? '' : 'required' ?>>
            </div>
          </div>

          <div class="form-actions d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
            <a href="/users" class="btn btn-outline-secondary px-4">Batal</a>
            <button type="submit" class="btn btn-primary px-4" id="btnSubmitUser">
              <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Pengguna' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
