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
$success = '';
$error = '';

// Ambil daftar siswa berdasarkan kelas
$siswa_list = [];
$nilai_list = [];

if ($kelas) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama_siswa");
        $stmt->execute([$kelas]);
        $siswa_list = $stmt->fetchAll();
        
        if (!empty($siswa_list)) {
            // Ambil nilai yang sudah ada
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
        $success = "Nilai berhasil diperbarui!";
        auditLog($pdo, 'EDIT_NILAI', "Guru memperbarui nilai kelas {$kelas}, semester {$semester}, tahun ajaran {$tahun_ajaran}.");
        
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

function getPredikat($nilai) {
    if ($nilai === null || $nilai <= 0) return '-';
    if ($nilai >= 90) return 'A (Sangat Baik)';
    if ($nilai >= 80) return 'B (Baik)';
    if ($nilai >= 70) return 'C (Cukup)';
    if ($nilai >= 60) return 'D (Kurang)';
    return 'E (Sangat Kurang)';
}

function getPredikatClass($nilai) {
    if ($nilai === null || $nilai <= 0) return '';
    if ($nilai >= 75) return 'nilai-bagus';
    if ($nilai >= 60) return 'nilai-cukup';
    return 'nilai-kurang';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Nilai - <?= htmlspecialchars($mapel['nama_mapel'] ?? '') ?></title>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #333; margin-bottom: 10px; }
        .scroll-table { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; min-width: 1200px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: middle; }
        th { background: #f39c12; color: white; position: sticky; top: 0; }
        tr:hover { background: #f5f5f5; }
        input[type="number"] { 
            width: 65px; 
            padding: 5px; 
            border: 1px solid #ddd; 
            border-radius: 3px; 
            text-align: center;
        }
        input[type="number"]:focus { outline: none; border-color: #f39c12; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .btn { background: #f39c12; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; margin-top: 20px; }
        .btn:hover { background: #e67e22; }
        .filter { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter label { margin-right: 5px; font-weight: bold; }
        .filter select, .filter button { padding: 8px 15px; border: 1px solid #ddd; border-radius: 3px; }
        .filter button { background: #3498db; color: white; border: none; cursor: pointer; }
        .filter button:hover { background: #2980b9; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #f39c12; text-decoration: none; }
        .info-mapel { background: #fff3cd; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #f39c12; }
        .rata-sumatif { background: #e3f2fd; font-weight: bold; }
        .nilai-raport { font-weight: bold; }
        .nilai-bagus { background: #d4edda; color: #155724; }
        .nilai-cukup { background: #fff3cd; color: #856404; }
        .nilai-kurang { background: #f8d7da; color: #721c24; }
        .sub-header { background: #f0f0f0; }
        @media (max-width: 768px) {
            table { font-size: 11px; }
            input[type="number"] { width: 50px; }
            th, td { padding: 5px; }
        }
    </style>
<link rel='stylesheet' href='../assets/app.css'></head>
<body>
    <div class="container">
        <h2>✏️ Edit Nilai - Sistem Penilaian Sumatif & Proyek</h2>
        <div class="info-mapel">
            <strong>Mata Pelajaran:</strong> <?= htmlspecialchars($mapel['nama_mapel'] ?? 'Tidak diketahui') ?>
        </div>
        <a href="../dashboard_guru.php" class="back-link">← Kembali ke Dashboard</a>
        
        <?php if($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="GET" class="filter">
            <div>
                <label>Kelas:</label>
                <select name="kelas" required>
                    <option value="">Pilih Kelas</option>
                    <option value="VII" <?= $kelas == 'VII' ? 'selected' : '' ?>>VII</option>
                    <option value="VIII" <?= $kelas == 'VIII' ? 'selected' : '' ?>>VIII</option>
                    <option value="IX" <?= $kelas == 'IX' ? 'selected' : '' ?>>IX</option>
                </select>
            </div>
            <div>
                <label>Semester:</label>
                <select name="semester">
                    <option value="1" <?= $semester == '1' ? 'selected' : '' ?>>Semester 1</option>
                    <option value="2" <?= $semester == '2' ? 'selected' : '' ?>>Semester 2</option>
                </select>
            </div>
            <button type="submit">Tampilkan</button>
        </form>
        
        <?php if($kelas && !empty($nilai_list)): ?>
        <form method="POST">
            <input type="hidden" name="update_nilai" value="1">
            <input type="hidden" name="tahun_ajaran" value="2024/2025">
            <input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>">
            <input type="hidden" name="kelas" value="<?= htmlspecialchars($kelas) ?>">
            
            <div class="scroll-table">
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
                        foreach($nilai_list as $row):
                            $rata_sumatif = $row['rata_sumatif'];
                            $nilai_raport = $row['nilai_raport'];
                            $predikat = getPredikat($nilai_raport);
                            $predikat_class = getPredikatClass($nilai_raport);
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['nis']) ?></td>
                            <td style="text-align: left;"><?= htmlspecialchars($row['nama_siswa']) ?></td>
                            
                            <!-- Sumatif 1-5 -->
                            <td><input type="number" step="0.01" name="nilai[<?= $row['siswa_id'] ?>][sum1]" value="<?= htmlspecialchars($row['nilai_sumatif1'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $row['siswa_id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $row['siswa_id'] ?>][sum2]" value="<?= htmlspecialchars($row['nilai_sumatif2'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $row['siswa_id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $row['siswa_id'] ?>][sum3]" value="<?= htmlspecialchars($row['nilai_sumatif3'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $row['siswa_id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $row['siswa_id'] ?>][sum4]" value="<?= htmlspecialchars($row['nilai_sumatif4'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $row['siswa_id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $row['siswa_id'] ?>][sum5]" value="<?= htmlspecialchars($row['nilai_sumatif5'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $row['siswa_id'] ?>)"></td>
                            
                            <!-- Proyek & UAS -->
                            <td><input type="number" step="0.01" name="nilai[<?= $row['siswa_id'] ?>][proyek]" value="<?= htmlspecialchars($row['nilai_proyek'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $row['siswa_id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $row['siswa_id'] ?>][uas]" value="<?= htmlspecialchars($row['nilai_uas'] ?? '') ?>" min="0" max="100" onchange="hitungSemua(<?= $row['siswa_id'] ?>)"></td>
                            
                            <!-- Rata-rata Sumatif -->
                            <td class="rata-sumatif" id="rata_sumatif_<?= $row['siswa_id'] ?>">
                                <?= $rata_sumatif !== null ? number_format($rata_sumatif, 2) : '-' ?>
                            </td>
                            
                            <!-- Nilai Raport -->
                            <td class="nilai-raport <?= $predikat_class ?>" id="nilai_raport_<?= $row['siswa_id'] ?>">
                                <?= $nilai_raport !== null ? number_format($nilai_raport, 2) : '-' ?>
                            </td>
                            
                            <!-- Predikat -->
                            <td class="<?= $predikat_class ?>" id="predikat_<?= $row['siswa_id'] ?>">
                                <?= $predikat ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <button type="submit" class="btn" onclick="return confirm('Yakin ingin menyimpan perubahan nilai?')">💾 Simpan Perubahan</button>
        </form>
        <?php elseif($kelas && empty($siswa_list)): ?>
            <div class="error" style="background: #fff3cd; color: #856404; border-left-color: #ffc107;">
                ⚠️ Tidak ada data siswa untuk kelas <?= htmlspecialchars($kelas) ?>.
            </div>
        <?php elseif($kelas && empty($nilai_list)): ?>
            <div class="error" style="background: #fff3cd; color: #856404; border-left-color: #ffc107;">
                ⚠️ Belum ada data nilai untuk kelas <?= htmlspecialchars($kelas) ?> - Semester <?= $semester ?>.
                Silahkan input nilai terlebih dahulu di menu <strong>Input Nilai</strong>.
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function hitungSemua(siswaId) {
            // Ambil semua nilai sumatif
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
            
            // Hitung rata-rata sumatif
            let rataSumatif = 0;
            if (sumatif.length > 0) {
                let totalSum = sumatif.reduce((a, b) => a + b, 0);
                rataSumatif = totalSum / sumatif.length;
            }
            
            let rataElement = document.getElementById(`rata_sumatif_${siswaId}`);
            if (rataElement) {
                rataElement.innerHTML = rataSumatif > 0 ? rataSumatif.toFixed(2) : '-';
            }
            
            // Ambil nilai proyek dan UAS
            let proyekInput = document.querySelector(`input[name="nilai[${siswaId}][proyek]"]`);
            let uasInput = document.querySelector(`input[name="nilai[${siswaId}][uas]"]`);
            
            let proyek = proyekInput && proyekInput.value !== '' ? parseFloat(proyekInput.value) : null;
            let uas = uasInput && uasInput.value !== '' ? parseFloat(uasInput.value) : null;
            
            // Hitung nilai raport
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
            
            // Update nilai raport dan predikat
            let raportElement = document.getElementById(`nilai_raport_${siswaId}`);
            let predikatElement = document.getElementById(`predikat_${siswaId}`);
            
            if (raportElement) {
                if (nilaiRaport !== null && nilaiRaport > 0) {
                    raportElement.innerHTML = nilaiRaport.toFixed(2);
                    
                    // Update predikat
                    let predikat = '';
                    let predikatClass = '';
                    if (nilaiRaport >= 90) {
                        predikat = 'A (Sangat Baik)';
                        predikatClass = 'nilai-bagus';
                    } else if (nilaiRaport >= 80) {
                        predikat = 'B (Baik)';
                        predikatClass = 'nilai-cukup';
                    } else if (nilaiRaport >= 70) {
                        predikat = 'C (Cukup)';
                        predikatClass = 'nilai-cukup';
                    } else if (nilaiRaport >= 60) {
                        predikat = 'D (Kurang)';
                        predikatClass = 'nilai-kurang';
                    } else {
                        predikat = 'E (Sangat Kurang)';
                        predikatClass = 'nilai-kurang';
                    }
                    
                    if (predikatElement) {
                        predikatElement.innerHTML = predikat;
                        predikatElement.className = predikatClass;
                    }
                    raportElement.className = 'nilai-raport ' + predikatClass;
                } else {
                    raportElement.innerHTML = '-';
                    if (predikatElement) {
                        predikatElement.innerHTML = '-';
                        predikatElement.className = '';
                    }
                    raportElement.className = 'nilai-raport';
                }
            }
        }
        
        // Hitung ulang semua saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach($nilai_list as $row): ?>
                hitungSemua(<?= $row['siswa_id'] ?>);
            <?php endforeach; ?>
        });
    </script>
</body>
</html>