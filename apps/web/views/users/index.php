<?php
/**
 * Shell halaman kelola pengguna. Daftar user diisi lewat AJAX oleh users.js.
 */

$loadUsersScript = true;
?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
  <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="fw-bold mb-0">Kelola Pengguna</h5>
      <small class="text-muted">Atur akun administrator dan staf yang dapat mengakses sistem</small>
    </div>
    <a href="/users/create" class="btn btn-primary btn-sm px-3">
      <i class="bi bi-person-plus me-1"></i> Tambah Pengguna
    </a>
  </div>

  <div id="usersError" role="alert" aria-live="assertive"></div>

  <div class="table-responsive">
    <table class="table table-modern table-hover align-middle mb-0" id="tableUsers">
      <thead>
        <tr>
          <th>Nama &amp; Email</th>
          <th>Peran</th>
          <th>Status</th>
          <th>Login Terakhir</th>
          <th class="text-center" style="width: 260px;">Aksi</th>
        </tr>
      </thead>
      <!-- Baris diisi DataTables lewat users.js; keadaan kosong ditangani DataTables. -->
      <tbody id="usersTableBody"></tbody>
    </table>
  </div>
</div>
