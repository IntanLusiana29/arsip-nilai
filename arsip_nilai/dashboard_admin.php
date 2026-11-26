<?php
require_once 'config/auth.php';
checkAccess('admin');
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard Administrator | Arsip Nilai</title><link rel="stylesheet" href="assets/app.css"><style>
.page-heading{margin:12px 0 5px}.eyebrow{color:#2563eb;font-size:12px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase}.page-heading h1{margin:7px 0;font-size:28px}.page-heading p{margin:0;color:#718096}.card-icon{width:48px;height:48px;border-radius:14px;background:#edf4ff;display:grid;place-items:center;font-size:23px;margin-bottom:18px}.card-arrow{display:flex;justify-content:space-between;align-items:center;margin-top:18px;color:#2563eb;font-size:13px;font-weight:800}
</style></head><body>
<header class="header"><h2>▤ Arsip Nilai <span style="font-weight:400;color:#c6d5e8">/ Administrator</span></h2><div><span>Halo, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator', ENT_QUOTES, 'UTF-8') ?></span> &nbsp; <a href="logout.php" class="logout">Keluar ↗</a></div></header>
<main class="container"><section class="page-heading"><div class="eyebrow">Ruang kerja administrator</div><h1>Dashboard Administrasi</h1><p>Kelola data akademik dan pantau aktivitas sistem dari satu tempat.</p></section>
<div class="menu">
<a href="admin/manage_siswa.php" class="card"><div class="card-icon">🎓</div><h3>Kelola Siswa</h3><p>Tambah, perbarui, dan kelola data siswa berdasarkan kelas.</p><div class="card-arrow">Buka pengelolaan <span>→</span></div></a>
<a href="admin/manage_guru.php" class="card"><div class="card-icon">👨‍🏫</div><h3>Kelola Guru</h3><p>Atur akun guru dan penugasan mata pelajaran.</p><div class="card-arrow">Buka pengelolaan <span>→</span></div></a>
<a href="admin/manage_nilai.php" class="card"><div class="card-icon">📊</div><h3>Kelola Semua Nilai</h3><p>Tinjau dan kelola rekap nilai seluruh siswa.</p><div class="card-arrow">Lihat data nilai <span>→</span></div></a>
<a href="admin/audit_log.php" class="card"><div class="card-icon">🧾</div><h3>Riwayat Aktivitas</h3><p>Lihat catatan aktivitas dan perubahan data pada sistem.</p><div class="card-arrow">Lihat riwayat <span>→</span></div></a>
<a href="admin/report.php" class="card"><div class="card-icon">📑</div><h3>Laporan Nilai</h3><p>Susun, tinjau, dan cetak laporan nilai berdasarkan kelas.</p><div class="card-arrow">Buka laporan <span>→</span></div></a>
</div><p style="margin-top:32px;font-size:12px;color:#9aa6b5">Arsip Nilai · Sistem Informasi Akademik</p></main></body></html>
