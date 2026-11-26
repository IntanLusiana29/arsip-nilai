<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/auth.php';
require_once '../config/database.php';
checkAccess('guru');

// Ambil informasi mata pelajaran guru
$mapel_id = $_SESSION['mata_pelajaran_id'];
$stmt = $pdo->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = ?");
$stmt->execute([$mapel_id]);
$mapel = $stmt->fetch();

$kelas = $_GET['kelas'] ?? '';
$semester = $_GET['semester'] ?? '1';
$tahun_ajaran = '2024/2025';
$error = '';

// Ambil data siswa dan nilai
$siswa_list = [];
$nilai_list = [];

if ($kelas) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama_siswa");
        $stmt->execute([$kelas]);
        $siswa_list = $stmt->fetchAll();
        
        if (!empty($siswa_list)) {
            $stmt = $pdo->prepare("
                SELECT * FROM nilai 
                WHERE mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?
            ");
            $stmt->execute([$mapel_id, $semester, $tahun_ajaran]);
            $existing_nilai = [];
            while ($row = $stmt->fetch()) {
                $existing_nilai[$row['siswa_id']] = $row;
            }
            
            foreach ($siswa_list as $siswa) {
                $nilai_list[] = [
                    'siswa_id' => $siswa['id'],
                    'nis' => $siswa['nis'],
                    'nama_siswa' => $siswa['nama_siswa'],
                    'nilai_sumatif1' => $existing_nilai[$siswa['id']]['nilai_sumatif1'] ?? null,
                    'nilai_sumatif2' => $existing_nilai[$siswa['id']]['nilai_sumatif2'] ?? null,
                    'nilai_sumatif3' => $existing_nilai[$siswa['id']]['nilai_sumatif3'] ?? null,
                    'nilai_sumatif4' => $existing_nilai[$siswa['id']]['nilai_sumatif4'] ?? null,
                    'nilai_sumatif5' => $existing_nilai[$siswa['id']]['nilai_sumatif5'] ?? null,
                    'nilai_proyek' => $existing_nilai[$siswa['id']]['nilai_proyek'] ?? null,
                    'nilai_uas' => $existing_nilai[$siswa['id']]['nilai_uas'] ?? null,
                    'rata_sumatif' => $existing_nilai[$siswa['id']]['rata_sumatif'] ?? null,
                    'nilai_raport' => $existing_nilai[$siswa['id']]['nilai_raport'] ?? null
                ];
            }
        }
    } catch (PDOException $e) {
        $error = "Gagal mengambil data: " . $e->getMessage();
    }
}

// Hitung statistik
$statistik = [];
if (!empty($nilai_list)) {
    $nilai_raport_list = [];
    foreach ($nilai_list as $row) {
        if ($row['nilai_raport'] !== null && $row['nilai_raport'] > 0) {
            $nilai_raport_list[] = floatval($row['nilai_raport']);
        }
    }
    if (!empty($nilai_raport_list)) {
        $statistik = [
            'rata_rata' => array_sum($nilai_raport_list) / count($nilai_raport_list),
            'tertinggi' => max($nilai_raport_list),
            'terendah' => min($nilai_raport_list),
            'tuntas' => count(array_filter($nilai_raport_list, function($n) { return $n >= 75; })),
            'tidak_tuntas' => count(array_filter($nilai_raport_list, function($n) { return $n < 75; })),
            'total' => count($nilai_raport_list)
        ];
    }
}

function getPredikat($nilai) {
    if ($nilai === null || $nilai <= 0) return '-';
    if ($nilai >= 90) return 'A (Sangat Baik)';
    if ($nilai >= 80) return 'B (Baik)';
    if ($nilai >= 70) return 'C (Cukup)';
    if ($nilai >= 60) return 'D (Kurang)';
    return 'E (Sangat Kurang)';
}

function getStatusClass($nilai) {
    if ($nilai === null || $nilai <= 0) return '';
    if ($nilai >= 75) return 'nilai-bagus';
    if ($nilai >= 60) return 'nilai-cukup';
    return 'nilai-kurang';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lihat Nilai - <?= htmlspecialchars($mapel['nama_mapel'] ?? '') ?></title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #27ae60; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .container { padding: 20px; max-width: 1400px; margin: auto; }
        .filter-card { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .filter-form { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: bold; margin-bottom: 5px; color: #555; }
        .filter-group select, .filter-group button { padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-width: 150px; }
        .filter-group button { background: #27ae60; color: white; border: none; cursor: pointer; font-weight: bold; }
        .filter-group button:hover { background: #219a52; }
        .laporan-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow-x: auto; }
        .info-mapel { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #27ae60; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); color: white; padding: 15px; border-radius: 10px; text-align: center; }
        .stat-box h3 { font-size: 13px; margin-bottom: 8px; opacity: 0.9; text-transform: uppercase; }
        .stat-box .value { font-size: 28px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1200px; }
        th { background: #34495e; color: white; padding: 10px; text-align: center; border: 1px solid #466085; position: sticky; top: 0; }
        td { padding: 8px; border-bottom: 1px solid #ddd; text-align: center; }
        tr:hover { background: #f5f5f5; }
        .logout { background: #e74c3c; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #27ae60; text-decoration: none; }
        .btn-print { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; background: #27ae60; color: white; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
        .rata-sumatif { background: #e3f2fd; font-weight: bold; }
        .nilai-raport { font-weight: bold; }
        .nilai-bagus { background: #d4edda; color: #155724; }
        .nilai-cukup { background: #fff3cd; color: #856404; }
        .nilai-kurang { background: #f8d7da; color: #721c24; }
        @media print {
            .header, .filter-card, .btn-print, .back-link, .logout { display: none; }
            .laporan-card { box-shadow: none; padding: 0; }
            .stat-box { background: #27ae60; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        @media (max-width: 768px) {
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-group select, .filter-group button { width: 100%; }
            table { font-size: 11px; }
        }
    </style>
<link rel='stylesheet' href='../assets/app.css'></head>
<body>
    <div class="header">
        <h2>👁️ Lihat Nilai - <?= htmlspecialchars($mapel['nama_mapel'] ?? 'Mata Pelajaran') ?></h2>
        <div>
            <span>Halo, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Guru') ?></span>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>
    <div class="container">
        <a href="../dashboard_guru.php" class="back-link">← Kembali ke Dashboard</a>
        
        <?php if($error): ?>
            <div class="warning">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Filter Form -->
        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>📚 Kelas</label>
                    <select name="kelas" required>
                        <option value="">-- Pilih Kelas --</option>
                        <option value="VII" <?= $kelas == 'VII' ? 'selected' : '' ?>>Kelas VII</option>
                        <option value="VIII" <?= $kelas == 'VIII' ? 'selected' : '' ?>>Kelas VIII</option>
                        <option value="IX" <?= $kelas == 'IX' ? 'selected' : '' ?>>Kelas IX</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📅 Semester</label>
                    <select name="semester">
                        <option value="1" <?= $semester == '1' ? 'selected' : '' ?>>Semester 1</option>
                        <option value="2" <?= $semester == '2' ? 'selected' : '' ?>>Semester 2</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit">🔍 Tampilkan Nilai</button>
                </div>
            </form>
        </div>
        
        <?php if($kelas): ?>
            <?php if(empty($siswa_list)): ?>
                <div class="warning">
                    ⚠️ Belum ada data siswa untuk kelas <?= htmlspecialchars($kelas) ?>.
                </div>
            <?php elseif(empty($nilai_list)): ?>
                <div class="warning">
                    ⚠️ Belum ada data nilai untuk kelas <?= htmlspecialchars($kelas) ?> - Semester <?= $semester ?>.
                    Silahkan input nilai terlebih dahulu.
                </div>
            <?php else: ?>
                <div class="laporan-card">
                    <div class="info-mapel">
                        <div>
                            <strong>📌 LAPORAN NILAI</strong><br>
                            Kelas: <?= htmlspecialchars($kelas) ?> | Semester: <?= $semester ?> | Tahun Ajaran: <?= $tahun_ajaran ?><br>
                            Mata Pelajaran: <?= htmlspecialchars($mapel['nama_mapel']) ?>
                        </div>
                        <div>
                            <button class="btn-print" onclick="window.print()">🖨️ Cetak Laporan</button>
                        </div>
                    </div>
                    
                    <!-- Statistik -->
                    <?php if(!empty($statistik)): ?>
                    <div class="stats">
                        <div class="stat-box">
                            <h3>Rata-rata Kelas</h3>
                            <div class="value"><?= number_format($statistik['rata_rata'], 2) ?></div>
                        </div>
                        <div class="stat-box">
                            <h3>Nilai Tertinggi</h3>
                            <div class="value"><?= number_format($statistik['tertinggi'], 2) ?></div>
                        </div>
                        <div class="stat-box">
                            <h3>Nilai Terendah</h3>
                            <div class="value"><?= number_format($statistik['terendah'], 2) ?></div>
                        </div>
                        <div class="stat-box">
                            <h3>Siswa Tuntas (≥75)</h3>
                            <div class="value"><?= $statistik['tuntas'] ?> / <?= $statistik['total'] ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Tabel Laporan -->
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th rowspan="2">No</th>
                                    <th rowspan="2">NIS</th>
                                    <th rowspan="2">Nama Siswa</th>
                                    <th colspan="5">Nilai Sumatif</th>
                                    <th rowspan="2">Nilai Proyek<br>(30%)</th>
                                    <th rowspan="2">Nilai UAS<br>(30%)</th>
                                    <th rowspan="2">Rata-rata<br>Sumatif</th>
                                    <th rowspan="2">Nilai Raport<br>(100%)</th>
                                    <th rowspan="2">Predikat</th>
                                    <th rowspan="2">Status</th>
                                </tr>
                                <tr style="background: #f0f0f0;">
                                    <th>Sum1</th>
                                    <th>Sum2</th>
                                    <th>Sum3</th>
                                    <th>Sum4</th>
                                    <th>Sum5</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach($nilai_list as $row):
                                    $rata_sumatif = $row['rata_sumatif'];
                                    $nilai_raport = $row['nilai_raport'];
                                    $predikat = getPredikat($nilai_raport);
                                    $status_class = getStatusClass($nilai_raport);
                                    $status_text = '';
                                    if ($nilai_raport !== null && $nilai_raport > 0) {
                                        $status_text = $nilai_raport >= 75 ? '✅ TUNTAS' : '❌ BELUM TUNTAS';
                                    } else {
                                        $status_text = '-';
                                    }
                                ?>
                                <tr class="<?= $status_class ?>">
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nis']) ?></td>
                                    <td style="text-align: left;"><?= htmlspecialchars($row['nama_siswa']) ?></td>
                                    
                                    <!-- Sumatif 1-5 -->
                                    <td><?= $row['nilai_sumatif1'] !== null ? number_format($row['nilai_sumatif1'], 2) : '-' ?></td>
                                    <td><?= $row['nilai_sumatif2'] !== null ? number_format($row['nilai_sumatif2'], 2) : '-' ?></td>
                                    <td><?= $row['nilai_sumatif3'] !== null ? number_format($row['nilai_sumatif3'], 2) : '-' ?></td>
                                    <td><?= $row['nilai_sumatif4'] !== null ? number_format($row['nilai_sumatif4'], 2) : '-' ?></td>
                                    <td><?= $row['nilai_sumatif5'] !== null ? number_format($row['nilai_sumatif5'], 2) : '-' ?></td>
                                    
                                    <!-- Proyek & UAS -->
                                    <td><?= $row['nilai_proyek'] !== null ? number_format($row['nilai_proyek'], 2) : '-' ?></td>
                                    <td><?= $row['nilai_uas'] !== null ? number_format($row['nilai_uas'], 2) : '-' ?></td>
                                    
                                    <!-- Rata Sumatif -->
                                    <td class="rata-sumatif"><?= $rata_sumatif !== null ? number_format($rata_sumatif, 2) : '-' ?></td>
                                    
                                    <!-- Nilai Raport -->
                                    <td class="nilai-raport"><?= $nilai_raport !== null ? number_format($nilai_raport, 2) : '-' ?></td>
                                    
                                    <!-- Predikat -->
                                    <td><?= $predikat ?></td>
                                    
                                    <!-- Status -->
                                    <td><?= $status_text ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Keterangan Bobot -->
                    <div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 5px; font-size: 12px;">
                        <strong>📌 Keterangan:</strong><br>
                        • Nilai Raport = (40% × Rata-rata Sumatif) + (30% × Nilai Proyek) + (30% × Nilai UAS)<br>
                        • Predikat: A (≥90) = Sangat Baik | B (80-89) = Baik | C (70-79) = Cukup | D (60-69) = Kurang | E ({"<60"}) = Sangat Kurang
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="warning">
                ⚠️ Silahkan pilih Kelas terlebih dahulu untuk melihat nilai.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>