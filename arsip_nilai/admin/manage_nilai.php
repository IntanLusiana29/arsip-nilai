<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$kelas = $_GET['kelas'] ?? '';
$mapel_id = $_GET['mapel_id'] ?? '';
$semester = $_GET['semester'] ?? '1';
$tahun_ajaran = '2024/2025';
$error = '';
$success = '';

// Cek dan tambah kolom jika diperlukan
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM nilai");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = [
        'nilai_sumatif1', 'nilai_sumatif2', 'nilai_sumatif3', 
        'nilai_sumatif4', 'nilai_sumatif5', 'nilai_proyek', 
        'nilai_uas', 'rata_sumatif', 'nilai_raport'
    ];
    
    foreach ($required_columns as $col) {
        if (!in_array($col, $existing_columns)) {
            $pdo->exec("ALTER TABLE nilai ADD COLUMN $col DECIMAL(5,2) DEFAULT NULL");
        }
    }
} catch (PDOException $e) {
    // Tabel mungkin belum ada atau kolom sudah ada
}

// Ambil data mata pelajaran
$mapel_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY id");
    $mapel_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Gagal mengambil data mata pelajaran: " . $e->getMessage();
}

// Ambil data siswa
$siswa_list = [];
$nilai_list = [];

if ($kelas && $mapel_id) {
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

// Update nilai
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_nilai'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['nilai'] as $siswa_id => $nilai_data) {
            $sum1 = !empty($nilai_data['sum1']) ? floatval($nilai_data['sum1']) : null;
            $sum2 = !empty($nilai_data['sum2']) ? floatval($nilai_data['sum2']) : null;
            $sum3 = !empty($nilai_data['sum3']) ? floatval($nilai_data['sum3']) : null;
            $sum4 = !empty($nilai_data['sum4']) ? floatval($nilai_data['sum4']) : null;
            $sum5 = !empty($nilai_data['sum5']) ? floatval($nilai_data['sum5']) : null;
            $proyek = !empty($nilai_data['proyek']) ? floatval($nilai_data['proyek']) : null;
            $uas = !empty($nilai_data['uas']) ? floatval($nilai_data['uas']) : null;
            
            // Hitung rata-rata sumatif
            $sumatif_array = array_filter([$sum1, $sum2, $sum3, $sum4, $sum5]);
            $rata_sumatif = null;
            if (!empty($sumatif_array)) {
                $rata_sumatif = array_sum($sumatif_array) / count($sumatif_array);
            }
            
            // Hitung nilai raport: 40% rata sumatif + 30% proyek + 30% UAS
            $nilai_raport = null;
            $bobot_sum = 0;
            $total_nilai = 0;
            
            if ($rata_sumatif !== null) {
                $total_nilai += $rata_sumatif * 0.4;
                $bobot_sum += 0.4;
            }
            if ($proyek !== null) {
                $total_nilai += $proyek * 0.3;
                $bobot_sum += 0.3;
            }
            if ($uas !== null) {
                $total_nilai += $uas * 0.3;
                $bobot_sum += 0.3;
            }
            
            if ($bobot_sum > 0) {
                $nilai_raport = $total_nilai / $bobot_sum;
            }
            
            // Cek existing data
            $stmt = $pdo->prepare("SELECT id FROM nilai WHERE siswa_id = ? AND mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?");
            $stmt->execute([$siswa_id, $mapel_id, $semester, $tahun_ajaran]);
            
            if ($stmt->rowCount() > 0) {
                $sql = "
                    UPDATE nilai SET 
                        nilai_sumatif1 = ?, nilai_sumatif2 = ?, nilai_sumatif3 = ?, 
                        nilai_sumatif4 = ?, nilai_sumatif5 = ?, nilai_proyek = ?, 
                        nilai_uas = ?, rata_sumatif = ?, nilai_raport = ?
                    WHERE siswa_id = ? AND mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $sum1, $sum2, $sum3, $sum4, $sum5, $proyek, $uas,
                    $rata_sumatif, $nilai_raport,
                    $siswa_id, $mapel_id, $semester, $tahun_ajaran
                ]);
            } else {
                $sql = "
                    INSERT INTO nilai (
                        siswa_id, mata_pelajaran_id, kelas, semester, tahun_ajaran,
                        nilai_sumatif1, nilai_sumatif2, nilai_sumatif3, nilai_sumatif4, nilai_sumatif5,
                        nilai_proyek, nilai_uas, rata_sumatif, nilai_raport
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $siswa_id, $mapel_id, $kelas, $semester, $tahun_ajaran,
                    $sum1, $sum2, $sum3, $sum4, $sum5, $proyek, $uas,
                    $rata_sumatif, $nilai_raport
                ]);
            }
        }
        
        $pdo->commit();
        $success = "Nilai berhasil disimpan!";
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama_siswa");
        $stmt->execute([$kelas]);
        $siswa_list = $stmt->fetchAll();
        
        if (!empty($siswa_list)) {
            $stmt = $pdo->prepare("SELECT * FROM nilai WHERE mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?");
            $stmt->execute([$mapel_id, $semester, $tahun_ajaran]);
            $existing_nilai = [];
            while ($row = $stmt->fetch()) {
                $existing_nilai[$row['siswa_id']] = $row;
            }
            
            $nilai_list = [];
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
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kelola Semua Nilai - Admin</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .container { padding: 20px; max-width: 1400px; margin: auto; }
        .filter-card { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .filter-form { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: bold; margin-bottom: 5px; color: #555; }
        .filter-group select, .filter-group button { padding: 10px; border: 1px solid #ddd; border-radius: 5px; min-width: 150px; }
        .filter-group button { background: #3498db; color: white; border: none; cursor: pointer; font-weight: bold; }
        .filter-group button:hover { background: #2980b9; }
        .nilai-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow-x: auto; }
        .info-mapel { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #27ae60; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1000px; }
        th { background: #34495e; color: white; padding: 10px; text-align: center; border: 1px solid #466085; }
        td { padding: 8px; border-bottom: 1px solid #ddd; text-align: center; }
        tr:hover { background: #f5f5f5; }
        input[type="number"] { width: 65px; padding: 5px; border: 1px solid #ddd; border-radius: 3px; text-align: center; }
        input[type="number"]:focus { outline: none; border-color: #27ae60; }
        .btn-save { background: #27ae60; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; font-size: 16px; font-weight: bold; }
        .btn-save:hover { background: #219a52; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
        .logout { background: #e74c3c; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
        .rata-sumatif { background: #e3f2fd; font-weight: bold; }
        .nilai-raport { background: #d4edda; font-weight: bold; }
        .sub-header { background: #f0f0f0; }
    </style>
</head>
<body>
    <div class="header">
        <h2>📊 Admin Panel - Kelola Semua Nilai (Sumatif & Proyek)</h2>
        <div>
            <span>Halo, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></span>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>
    <div class="container">
        <a href="../dashboard_admin.php" class="back-link">← Kembali ke Dashboard</a>
        
        <?php if($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
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
                    <label>📖 Mata Pelajaran</label>
                    <select name="mapel_id" required>
                        <option value="">-- Pilih Mapel --</option>
                        <?php foreach($mapel_list as $mapel): ?>
                            <option value="<?= $mapel['id'] ?>" <?= $mapel_id == $mapel['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mapel['nama_mapel']) ?>
                            </option>
                        <?php endforeach; ?>
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
        
        <?php if($kelas && $mapel_id): ?>
            <?php if(empty($siswa_list)): ?>
                <div class="warning">
                    ⚠️ Belum ada data siswa untuk kelas <?= htmlspecialchars($kelas) ?>. 
                    <a href="manage_siswa.php">Klik disini untuk tambah siswa</a>
                </div>
            <?php elseif(!empty($nilai_list)): ?>
                <div class="nilai-card">
                    <div class="info-mapel">
                        <strong>📌 Kelas <?= htmlspecialchars($kelas) ?> - Semester <?= $semester ?></strong><br>
                        Mata Pelajaran: <?php 
                            $mapel_nama = '';
                            foreach($mapel_list as $m) {
                                if($m['id'] == $mapel_id) $mapel_nama = $m['nama_mapel'];
                            }
                            echo htmlspecialchars($mapel_nama);
                        ?>
                        <br>
                        <small>🎯 Bobot: Rata-rata Sumatif (40%) + Proyek (30%) + UAS (30%) = Nilai Raport</small>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="update_nilai" value="1">
                        
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
                                    </tr>
                                    <tr class="sub-header">
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
                                    foreach($nilai_list as $nilai):
                                        $rata_sumatif = $nilai['rata_sumatif'];
                                        $nilai_raport = $nilai['nilai_raport'];
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($nilai['nis']) ?></td>
                                        <td style="text-align: left;"><?= htmlspecialchars($nilai['nama_siswa']) ?>\]</td>
                                        
                                        <!-- Sumatif 1-5 -->
                                        <td><input type="number" step="0.01" name="nilai[<?= $nilai['siswa_id'] ?>][sum1]" value="<?= htmlspecialchars($nilai['nilai_sumatif1'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $nilai['siswa_id'] ?>)"></td>
                                        <td><input type="number" step="0.01" name="nilai[<?= $nilai['siswa_id'] ?>][sum2]" value="<?= htmlspecialchars($nilai['nilai_sumatif2'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $nilai['siswa_id'] ?>)"></td>
                                        <td><input type="number" step="0.01" name="nilai[<?= $nilai['siswa_id'] ?>][sum3]" value="<?= htmlspecialchars($nilai['nilai_sumatif3'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $nilai['siswa_id'] ?>)"></td>
                                        <td><input type="number" step="0.01" name="nilai[<?= $nilai['siswa_id'] ?>][sum4]" value="<?= htmlspecialchars($nilai['nilai_sumatif4'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $nilai['siswa_id'] ?>)"></td>
                                        <td><input type="number" step="0.01" name="nilai[<?= $nilai['siswa_id'] ?>][sum5]" value="<?= htmlspecialchars($nilai['nilai_sumatif5'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $nilai['siswa_id'] ?>)"></td>
                                        
                                        <!-- Proyek & UAS -->
                                        <td><input type="number" step="0.01" name="nilai[<?= $nilai['siswa_id'] ?>][proyek]" value="<?= htmlspecialchars($nilai['nilai_proyek'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $nilai['siswa_id'] ?>)"></td>
                                        <td><input type="number" step="0.01" name="nilai[<?= $nilai['siswa_id'] ?>][uas]" value="<?= htmlspecialchars($nilai['nilai_uas'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $nilai['siswa_id'] ?>)"></td>
                                        
                                        <!-- Rata-rata Sumatif -->
                                        <td class="rata-sumatif" id="rata_sumatif_<?= $nilai['siswa_id'] ?>">
                                            <?= $rata_sumatif && $rata_sumatif > 0 ? number_format($rata_sumatif, 2) : '-' ?>
                                        </td>
                                        
                                        <!-- Nilai Raport -->
                                        <td class="nilai-raport" id="nilai_raport_<?= $nilai['siswa_id'] ?>">
                                            <?= $nilai_raport && $nilai_raport > 0 ? number_format($nilai_raport, 2) : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="submit" class="btn-save" onclick="return confirm('Yakin ingin menyimpan semua nilai?')">💾 Simpan Semua Nilai</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="warning">
                ⚠️ Silahkan pilih Kelas dan Mata Pelajaran terlebih dahulu.
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function hitungSemua(siswaId) {
            let sumatif = [];
            for (let i = 1; i <= 5; i++) {
                let input = document.querySelector(`input[name="nilai[${siswaId}][sum${i}]"]`);
                if (input && input.value !== '') {
                    let val = parseFloat(input.value);
                    if (!isNaN(val) && val >= 0) {
                        sumatif.push(val);
                    }
                }
            }
            
            let rataSumatif = 0;
            if (sumatif.length > 0) {
                let totalSum = sumatif.reduce((a, b) => a + b, 0);
                rataSumatif = totalSum / sumatif.length;
            }
            
            let rataElement = document.getElementById(`rata_sumatif_${siswaId}`);
            if (rataElement) {
                rataElement.innerHTML = rataSumatif > 0 ? rataSumatif.toFixed(2) : '-';
            }
            
            let proyekInput = document.querySelector(`input[name="nilai[${siswaId}][proyek]"]`);
            let uasInput = document.querySelector(`input[name="nilai[${siswaId}][uas]"]`);
            
            let proyek = proyekInput && proyekInput.value !== '' ? parseFloat(proyekInput.value) : null;
            let uas = uasInput && uasInput.value !== '' ? parseFloat(uasInput.value) : null;
            
            let totalBobot = 0;
            let totalNilai = 0;
            let nilaiRaport = null;
            
            if (rataSumatif > 0) {
                totalNilai += rataSumatif * 0.4;
                totalBobot += 0.4;
            }
            if (proyek !== null && !isNaN(proyek)) {
                totalNilai += proyek * 0.3;
                totalBobot += 0.3;
            }
            if (uas !== null && !isNaN(uas)) {
                totalNilai += uas * 0.3;
                totalBobot += 0.3;
            }
            
            if (totalBobot > 0) {
                nilaiRaport = totalNilai / totalBobot;
            }
            
            let raportElement = document.getElementById(`nilai_raport_${siswaId}`);
            if (raportElement) {
                if (nilaiRaport !== null && nilaiRaport > 0) {
                    raportElement.innerHTML = nilaiRaport.toFixed(2);
                } else {
                    raportElement.innerHTML = '-';
                }
            }
        }
        
        // Hitung ulang semua saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach($nilai_list as $nilai): ?>
                hitungSemua(<?= $nilai['siswa_id'] ?>);
            <?php endforeach; ?>
        });
    </script>
</body>
</html>