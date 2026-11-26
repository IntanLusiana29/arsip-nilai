<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/auth.php';
require_once 'config/database.php';
checkAccess('guru');

$mapel_id = $_SESSION['mata_pelajaran_id'];
$mapel = null;
$error = '';

// Ambil informasi mata pelajaran guru dengan error handling
try {
    $stmt = $pdo->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = ?");
    $stmt->execute([$mapel_id]);
    $mapel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mapel) {
        $error = "Mata pelajaran tidak ditemukan. Silahkan hubungi administrator.";
    }
} catch (PDOException $e) {
    $error = "Error mengambil data mata pelajaran: " . $e->getMessage();
}

// Ambil nama mapel dengan aman
$nama_mapel = ($mapel && isset($mapel['nama_mapel'])) ? $mapel['nama_mapel'] : 'Mata Pelajaran';
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard Guru - <?= htmlspecialchars($nama_mapel) ?></title><link rel="stylesheet" href="assets/app.css"><style>
.page-heading{margin:12px 0 5px}.eyebrow{color:#2563eb;font-size:12px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase}.page-heading h1{margin:7px 0;font-size:28px}.page-heading p{margin:0;color:#718096}.card-icon{width:52px;height:52px;border-radius:15px;background:#edf4ff;display:grid;place-items:center;font-size:24px;margin:0 auto 18px}.menu .card{text-align:center;padding:30px!important}.card-arrow{margin-top:18px;color:#2563eb;font-size:13px;font-weight:800}
</style></head><body>
<header class="header"><h2>▤ Arsip Nilai <span style="font-weight:400;color:#c6d5e8">/ Panel Guru</span></h2><div><span>Halo, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Guru', ENT_QUOTES, 'UTF-8') ?></span> &nbsp; <a href="logout.php" class="logout">Keluar ↗</a></div></header>
<main class="container"><section class="page-heading"><div class="eyebrow">Ruang kerja guru</div><h1>Selamat datang, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Guru', ENT_QUOTES, 'UTF-8') ?></h1><p>Kelola penilaian siswa untuk mata pelajaran yang Anda ampu.</p></section>
<?php if($error): ?><div class="error-box">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="info-mapel"><strong>Mata Pelajaran</strong><div style="font-size:19px;font-weight:800;margin-top:4px"><?= htmlspecialchars($nama_mapel) ?></div></div>
<div class="menu">
<a href="guru/input_nilai.php" class="card"><div class="card-icon">✏️</div><h3>Input Nilai</h3><p>Masukkan nilai sumatif, proyek, dan UAS siswa.</p><div class="card-arrow">Mulai input nilai →</div></a>
<a href="guru/lihat_nilai.php" class="card"><div class="card-icon">📈</div><h3>Lihat Nilai</h3><p>Tinjau nilai siswa dan hasil rekapitulasi kelas.</p><div class="card-arrow">Lihat rekap nilai →</div></a>
<a href="guru/edit_nilai.php" class="card"><div class="card-icon">📝</div><h3>Edit Nilai</h3><p>Perbarui nilai yang sudah tersimpan dalam sistem.</p><div class="card-arrow">Perbarui nilai →</div></a>
</div><p style="margin-top:32px;font-size:12px;color:#9aa6b5">Arsip Nilai · Sistem Informasi Akademik</p></main></body></html>
