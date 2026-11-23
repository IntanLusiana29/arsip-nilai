<?php
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
$success = '';
$error = '';

// Ambil daftar siswa berdasarkan kelas
$siswa_list = [];
if ($kelas) {
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama_siswa");
    $stmt->execute([$kelas]);
    $siswa_list = $stmt->fetchAll();
}

// Update struktur tabel nilai (jalankan sekali saja)
try {
    // Cek kolom yang ada
    $stmt = $pdo->query("SHOW COLUMNS FROM nilai");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('nilai_sumatif1', $columns)) {
        $pdo->exec("ALTER TABLE nilai ADD COLUMN nilai_sumatif1 DECIMAL(5,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE nilai ADD COLUMN nilai_sumatif2 DECIMAL(5,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE nilai ADD COLUMN nilai_sumatif3 DECIMAL(5,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE nilai ADD COLUMN nilai_sumatif4 DECIMAL(5,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE nilai ADD COLUMN nilai_sumatif5 DECIMAL(5,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE nilai ADD COLUMN nilai_proyek DECIMAL(5,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE nilai ADD COLUMN nilai_uas DECIMAL(5,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE nilai ADD COLUMN rata_sumatif DECIMAL(5,2) DEFAULT NULL");
        $pdo->exec("ALTER TABLE nilai ADD COLUMN nilai_raport DECIMAL(5,2) DEFAULT NULL");
    }
} catch (PDOException $e) {
    // Tabel mungkin sudah memiliki kolom
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tahun_ajaran = $_POST['tahun_ajaran'] ?? '2024/2025';
    $semester = $_POST['semester'] ?? '1';
    
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['nilai'] as $siswa_id => $nilai_data) {
            // Ambil nilai sumatif 1-5
            $sum1 = !empty($nilai_data['sum1']) ? floatval($nilai_data['sum1']) : null;
            $sum2 = !empty($nilai_data['sum2']) ? floatval($nilai_data['sum2']) : null;
            $sum3 = !empty($nilai_data['sum3']) ? floatval($nilai_data['sum3']) : null;
            $sum4 = !empty($nilai_data['sum4']) ? floatval($nilai_data['sum4']) : null;
            $sum5 = !empty($nilai_data['sum5']) ? floatval($nilai_data['sum5']) : null;
            $proyek = !empty($nilai_data['proyek']) ? floatval($nilai_data['proyek']) : null;
            $uas = !empty($nilai_data['uas']) ? floatval($nilai_data['uas']) : null;
            
            // Hitung rata-rata nilai sumatif (hanya nilai yang diisi)
            $sumatif_array = array_filter([$sum1, $sum2, $sum3, $sum4, $sum5]);
            $rata_sumatif = null;
            if (!empty($sumatif_array)) {
                $rata_sumatif = array_sum($sumatif_array) / count($sumatif_array);
            }
            
            // Hitung nilai raport (40% rata-rata sumatif + 30% proyek + 30% UAS)
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
            
            // Cek apakah sudah ada data
            $stmt = $pdo->prepare("SELECT id FROM nilai WHERE siswa_id = ? AND mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?");
            $stmt->execute([$siswa_id, $mapel_id, $semester, $tahun_ajaran]);
            
            if ($stmt->rowCount() > 0) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE nilai SET 
                        nilai_sumatif1 = ?, nilai_sumatif2 = ?, nilai_sumatif3 = ?, 
                        nilai_sumatif4 = ?, nilai_sumatif5 = ?, nilai_proyek = ?, 
                        nilai_uas = ?, rata_sumatif = ?, nilai_raport = ?
                    WHERE siswa_id = ? AND mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?
                ");
                $stmt->execute([
                    $sum1, $sum2, $sum3, $sum4, $sum5, $proyek, $uas,
                    $rata_sumatif, $nilai_raport,
                    $siswa_id, $mapel_id, $semester, $tahun_ajaran
                ]);
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO nilai (
                        siswa_id, mata_pelajaran_id, kelas, semester, tahun_ajaran,
                        nilai_sumatif1, nilai_sumatif2, nilai_sumatif3, nilai_sumatif4, nilai_sumatif5,
                        nilai_proyek, nilai_uas, rata_sumatif, nilai_raport
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
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
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Input Nilai - <?= htmlspecialchars($mapel['nama_mapel'] ?? '') ?></title>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #333; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: middle; }
        th { background: #27ae60; color: white; position: sticky; top: 0; }
        tr:hover { background: #f5f5f5; }
        input[type="number"] { width: 65px; padding: 5px; border: 1px solid #ddd; border-radius: 3px; text-align: center; }
        input[type="number"]:focus { outline: none; border-color: #27ae60; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .btn { background: #27ae60; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; margin-top: 20px; }
        .btn:hover { background: #219a52; }
        .filter { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter label { margin-right: 5px; font-weight: bold; }
        .filter select, .filter button { padding: 8px 15px; border: 1px solid #ddd; border-radius: 3px; }
        .filter button { background: #3498db; color: white; border: none; cursor: pointer; }
        .filter button:hover { background: #2980b9; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
        .info-mapel { background: #e8f5e9; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #27ae60; }
        .nilai-akhir { background: #e8f5e9; font-weight: bold; }
        .nilai-raport { background: #d4edda; font-weight: bold; }
        .sub-header { background: #f0f0f0; font-size: 12px; }
        .scroll-table { overflow-x: auto; max-width: 100%; }
        @media (max-width: 768px) {
            table { font-size: 11px; }
            input[type="number"] { width: 50px; }
            th, td { padding: 5px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>✏️ Input Nilai - Sistem Penilaian Sumatif & Proyek</h2>
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
        
        <?php if($kelas && !empty($siswa_list)): ?>
        <form method="POST" id="nilaiForm">
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
                            <th>Sumatif 1</th>
                            <th>Sumatif 2</th>
                            <th>Sumatif 3</th>
                            <th>Sumatif 4</th>
                            <th>Sumatif 5</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach($siswa_list as $siswa): 
                            // Ambil nilai yang sudah ada
                            $stmt = $pdo->prepare("
                                SELECT * FROM nilai 
                                WHERE siswa_id = ? AND mata_pelajaran_id = ? 
                                AND semester = ? AND tahun_ajaran = '2024/2025'
                            ");
                            $stmt->execute([$siswa['id'], $mapel_id, $semester]);
                            $n = $stmt->fetch();
                            
                            // Nilai sumatif
                            $s1 = $n['nilai_sumatif1'] ?? '';
                            $s2 = $n['nilai_sumatif2'] ?? '';
                            $s3 = $n['nilai_sumatif3'] ?? '';
                            $s4 = $n['nilai_sumatif4'] ?? '';
                            $s5 = $n['nilai_sumatif5'] ?? '';
                            $proyek = $n['nilai_proyek'] ?? '';
                            $uas = $n['nilai_uas'] ?? '';
                            $rata_sumatif = $n['rata_sumatif'] ?? '';
                            $nilai_raport = $n['nilai_raport'] ?? '';
                            
                            // Hitung predikat
                            $predikat = '';
                            $predikat_class = '';
                            if ($nilai_raport !== null && $nilai_raport !== '') {
                                $nr = floatval($nilai_raport);
                                if ($nr >= 90) {
                                    $predikat = 'A (Sangat Baik)';
                                    $predikat_class = 'nilai-bagus';
                                } elseif ($nr >= 80) {
                                    $predikat = 'B (Baik)';
                                    $predikat_class = 'nilai-cukup';
                                } elseif ($nr >= 70) {
                                    $predikat = 'C (Cukup)';
                                    $predikat_class = 'nilai-cukup';
                                } elseif ($nr >= 60) {
                                    $predikat = 'D (Kurang)';
                                    $predikat_class = 'nilai-kurang';
                                } else {
                                    $predikat = 'E (Sangat Kurang)';
                                    $predikat_class = 'nilai-kurang';
                                }
                            }
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($siswa['nis']) ?></td>
                            <td style="text-align: left;"><?= htmlspecialchars($siswa['nama_siswa']) ?></td>
                            
                            <!-- Sumatif 1-5 -->
                            <td><input type="number" step="0.01" name="nilai[<?= $siswa['id'] ?>][sum1]" value="<?= $s1 ?>" min="0" max="100" class="sumatif" data-siswa="<?= $siswa['id'] ?>" onchange="hitungSemua(<?= $siswa['id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $siswa['id'] ?>][sum2]" value="<?= $s2 ?>" min="0" max="100" class="sumatif" data-siswa="<?= $siswa['id'] ?>" onchange="hitungSemua(<?= $siswa['id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $siswa['id'] ?>][sum3]" value="<?= $s3 ?>" min="0" max="100" class="sumatif" data-siswa="<?= $siswa['id'] ?>" onchange="hitungSemua(<?= $siswa['id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $siswa['id'] ?>][sum4]" value="<?= $s4 ?>" min="0" max="100" class="sumatif" data-siswa="<?= $siswa['id'] ?>" onchange="hitungSemua(<?= $siswa['id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $siswa['id'] ?>][sum5]" value="<?= $s5 ?>" min="0" max="100" class="sumatif" data-siswa="<?= $siswa['id'] ?>" onchange="hitungSemua(<?= $siswa['id'] ?>)"></td>
                            
                            <!-- Proyek & UAS -->
                            <td><input type="number" step="0.01" name="nilai[<?= $siswa['id'] ?>][proyek]" value="<?= $proyek ?>" min="0" max="100" onchange="hitungSemua(<?= $siswa['id'] ?>)"></td>
                            <td><input type="number" step="0.01" name="nilai[<?= $siswa['id'] ?>][uas]" value="<?= $uas ?>" min="0" max="100" onchange="hitungSemua(<?= $siswa['id'] ?>)"></td>
                            
                            <!-- Rata-rata Sumatif -->
                            <td class="rata-sumatif" id="rata_sumatif_<?= $siswa['id'] ?>">
                                <?= $rata_sumatif ? number_format($rata_sumatif, 2) : '-' ?>
                            </td>
                            
                            <!-- Nilai Raport -->
                            <td class="nilai-raport" id="nilai_raport_<?= $siswa['id'] ?>">
                                <?= $nilai_raport ? number_format($nilai_raport, 2) : '-' ?>
                            </td>
                            
                            <!-- Predikat -->
                            <td class="<?= $predikat_class ?>" id="predikat_<?= $siswa['id'] ?>">
                                <?= $predikat ?: '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <button type="submit" class="btn" onclick="return confirm('Yakin ingin menyimpan semua nilai?')">💾 Simpan Semua Nilai</button>
        </form>
        <?php elseif($kelas && empty($siswa_list)): ?>
            <div class="error" style="background: #fff3cd; color: #856404; border-left-color: #ffc107;">
                ⚠️ Tidak ada data siswa untuk kelas <?= htmlspecialchars($kelas) ?>. Silahkan tambahkan siswa terlebih dahulu.
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        .nilai-bagus { background: #d4edda; color: #155724; font-weight: bold; }
        .nilai-cukup { background: #fff3cd; color: #856404; font-weight: bold; }
        .nilai-kurang { background: #f8d7da; color: #721c24; font-weight: bold; }
        .rata-sumatif { background: #e3f2fd; font-weight: bold; }
        .nilai-raport { background: #d4edda; font-weight: bold; }
    </style>
    
    <script>
        function hitungSemua(siswaId) {
            // Ambil semua nilai sumatif
            let sumatif = [];
            for (let i = 1; i <= 5; i++) {
                let input = document.querySelector(`input[name="nilai[${siswaId}][sum${i}]"]`);
                if (input && input.value) {
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
            document.getElementById(`rata_sumatif_${siswaId}`).innerHTML = rataSumatif > 0 ? rataSumatif.toFixed(2) : '-';
            
            // Ambil nilai proyek dan UAS
            let proyekInput = document.querySelector(`input[name="nilai[${siswaId}][proyek]"]`);
            let uasInput = document.querySelector(`input[name="nilai[${siswaId}][uas]"]`);
            
            let proyek = proyekInput && proyekInput.value ? parseFloat(proyekInput.value) : null;
            let uas = uasInput && uasInput.value ? parseFloat(uasInput.value) : null;
            
            // Hitung nilai raport: 40% rata sumatif + 30% proyek + 30% UAS
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
            
            // Update nilai raport
            let raportElement = document.getElementById(`nilai_raport_${siswaId}`);
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
                
                let predikatElement = document.getElementById(`predikat_${siswaId}`);
                predikatElement.innerHTML = predikat;
                predikatElement.className = predikatClass;
            } else {
                raportElement.innerHTML = '-';
                document.getElementById(`predikat_${siswaId}`).innerHTML = '-';
            }
        }
    </script>
</body>
</html>