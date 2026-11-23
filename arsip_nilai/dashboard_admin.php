<?php
require_once 'config/auth.php';
checkAccess('admin');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 20px; }
        .menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; color: #333; transition: transform 0.2s; display: block; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .card h3 { margin-bottom: 10px; color: #2c3e50; }
        .logout { background: #e74c3c; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Admin Panel - Arsip Nilai</h2>
        <div>
            <span>Halo, <?= $_SESSION['full_name'] ?></span>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>
    <div class="container">
        <h2>Menu Administrasi</h2>
        <div class="menu">
            <a href="admin/manage_siswa.php" class="card">
                <h3>📚 Kelola Siswa</h3>
                <p>Tambah, edit, hapus data siswa</p>
            </a>
            <a href="admin/manage_guru.php" class="card">
                <h3>👨‍🏫 Kelola Guru</h3>
                <p>Kelola akun guru mata pelajaran</p>
            </a>
            <a href="admin/manage_nilai.php" class="card">
                <h3>📊 Kelola Semua Nilai</h3>
                <p>Lihat dan edit semua nilai siswa</p>
            </a>
            <a href="admin/report.php" class="card">
                <h3>📑 Laporan Nilai</h3>
                <p>Cetak laporan nilai per kelas</p>
            </a>
        </div>
    </div>
</body>
</html>