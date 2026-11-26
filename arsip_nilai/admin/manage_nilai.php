<?php
require_once '../config/auth.php';
require_once '../config/database.php';
checkAccess('admin');

$default_tahun = (date('n') >= 7)
    ? date('Y') . '/' . (date('Y') + 1)
    : (date('Y') - 1) . '/' . date('Y');
$kelas = $_POST['kelas'] ?? $_GET['kelas'] ?? '';
$mapel_id = $_POST['mapel_id'] ?? $_GET['mapel_id'] ?? '';
$semester = $_POST['semester'] ?? $_GET['semester'] ?? '1';
$tahun_ajaran = $_POST['tahun_ajaran'] ?? $_GET['tahun_ajaran'] ?? $default_tahun;
$error = '';
$success = '';

// Validasi parameter agar filter admin sama dengan halaman input guru.
if (!in_array($kelas, ['', 'VII', 'VIII', 'IX'], true)) $kelas = '';
if (!in_array((string)$semester, ['1', '2'], true)) $semester = '1';
if (!preg_match('/^[0-9]{4}\/[0-9]{4}$/', (string)$tahun_ajaran)) {
    $tahun_ajaran = $default_tahun;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $error = 'Format tahun ajaran tidak valid. Contoh: 2026/2027.';
}

// Pastikan kolom penilaian tersedia pada database lama.
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM nilai");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_columns = ['nilai_sumatif1','nilai_sumatif2','nilai_sumatif3','nilai_sumatif4','nilai_sumatif5','nilai_proyek','nilai_uas','rata_sumatif','nilai_raport'];
    foreach ($required_columns as $col) {
        if (!in_array($col, $existing_columns, true)) {
            $pdo->exec("ALTER TABLE nilai ADD COLUMN `$col` DECIMAL(5,2) DEFAULT NULL");
        }
    }
} catch (PDOException $e) {
    $error = 'Struktur tabel nilai belum siap: ' . $e->getMessage();
}

$mapel_list = [];
try {
    $stmt = $pdo->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel");
    $mapel_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Gagal mengambil mata pelajaran: ' . $e->getMessage();
}

// Simpan dengan struktur dan rumus yang sama seperti input nilai guru.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_nilai']) && $error === '') {
    verifyCsrf();
    if ($kelas === '' || !filter_var($mapel_id, FILTER_VALIDATE_INT) || !in_array((string)$semester, ['1','2'], true)) {
        $error = 'Kelas, mata pelajaran, dan semester harus dipilih dengan benar.';
    } else {
        try {
            $pdo->beginTransaction();
            $checkMapel = $pdo->prepare('SELECT id FROM mata_pelajaran WHERE id = ?');
            $checkMapel->execute([$mapel_id]);
            if (!$checkMapel->fetchColumn()) throw new Exception('Mata pelajaran tidak ditemukan.');

            $checkSiswa = $pdo->prepare('SELECT id FROM siswa WHERE id = ? AND kelas = ?');
            $findNilai = $pdo->prepare('SELECT id FROM nilai WHERE siswa_id = ? AND mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?');
            $updateNilai = $pdo->prepare('UPDATE nilai SET kelas = ?, nilai_sumatif1 = ?, nilai_sumatif2 = ?, nilai_sumatif3 = ?, nilai_sumatif4 = ?, nilai_sumatif5 = ?, nilai_proyek = ?, nilai_uas = ?, rata_sumatif = ?, nilai_raport = ? WHERE siswa_id = ? AND mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?');
            $insertNilai = $pdo->prepare('INSERT INTO nilai (siswa_id,mata_pelajaran_id,kelas,semester,tahun_ajaran,nilai_sumatif1,nilai_sumatif2,nilai_sumatif3,nilai_sumatif4,nilai_sumatif5,nilai_proyek,nilai_uas,rata_sumatif,nilai_raport) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

            foreach (($_POST['nilai'] ?? []) as $siswa_id => $data) {
                $siswa_id = filter_var($siswa_id, FILTER_VALIDATE_INT);
                if (!$siswa_id) continue;
                $checkSiswa->execute([$siswa_id, $kelas]);
                if (!$checkSiswa->fetchColumn()) continue;

                $getScore = static function ($value) {
                    if ($value === null || trim((string)$value) === '') return null;
                    if (!is_numeric($value)) throw new Exception('Nilai harus berupa angka.');
                    $score = (float)$value;
                    if ($score < 0 || $score > 100) throw new Exception('Nilai harus berada pada rentang 0 sampai 100.');
                    return $score;
                };
                $sum1 = $getScore($data['sum1'] ?? null);
                $sum2 = $getScore($data['sum2'] ?? null);
                $sum3 = $getScore($data['sum3'] ?? null);
                $sum4 = $getScore($data['sum4'] ?? null);
                $sum5 = $getScore($data['sum5'] ?? null);
                $proyek = $getScore($data['proyek'] ?? null);
                $uas = $getScore($data['uas'] ?? null);

                // Nilai 0 tetap dihitung sebagai nilai yang valid.
                $sumatif = array_values(array_filter([$sum1,$sum2,$sum3,$sum4,$sum5], static fn($v) => $v !== null));
                $rata = count($sumatif) ? array_sum($sumatif) / count($sumatif) : null;
                $total = 0; $bobot = 0;
                if ($rata !== null) { $total += $rata * 0.4; $bobot += 0.4; }
                if ($proyek !== null) { $total += $proyek * 0.3; $bobot += 0.3; }
                if ($uas !== null) { $total += $uas * 0.3; $bobot += 0.3; }
                $raport = $bobot > 0 ? $total / $bobot : null;

                $findNilai->execute([$siswa_id,$mapel_id,$semester,$tahun_ajaran]);
                if ($findNilai->fetchColumn()) {
                    $updateNilai->execute([$kelas,$sum1,$sum2,$sum3,$sum4,$sum5,$proyek,$uas,$rata,$raport,$siswa_id,$mapel_id,$semester,$tahun_ajaran]);
                } else {
                    $insertNilai->execute([$siswa_id,$mapel_id,$kelas,$semester,$tahun_ajaran,$sum1,$sum2,$sum3,$sum4,$sum5,$proyek,$uas,$rata,$raport]);
                }
            }
            $pdo->commit();
            auditLog($pdo, 'KELOLA_NILAI', 'Admin menyimpan nilai kelas ' . $kelas . ', mata pelajaran ID ' . $mapel_id . ', semester ' . $semester . ', tahun ajaran ' . $tahun_ajaran);
            $success = 'Nilai berhasil disimpan dan tersinkron dengan data guru.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Gagal menyimpan nilai: ' . $e->getMessage();
        }
    }
}

$siswa_list = [];
$nilai_list = [];
if ($kelas !== '' && filter_var($mapel_id, FILTER_VALIDATE_INT)) {
    try {
        $stmt = $pdo->prepare('SELECT id, nis, nama_siswa FROM siswa WHERE kelas = ? ORDER BY nama_siswa');
        $stmt->execute([$kelas]);
        $siswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT * FROM nilai WHERE kelas = ? AND mata_pelajaran_id = ? AND semester = ? AND tahun_ajaran = ?');
        $stmt->execute([$kelas,$mapel_id,$semester,$tahun_ajaran]);
        $existing = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $existing[$row['siswa_id']] = $row;
        foreach ($siswa_list as $siswa) {
            $row = $existing[$siswa['id']] ?? [];
            $nilai_list[] = [
                'siswa_id'=>$siswa['id'], 'nis'=>$siswa['nis'], 'nama_siswa'=>$siswa['nama_siswa'],
                'nilai_sumatif1'=>$row['nilai_sumatif1'] ?? null, 'nilai_sumatif2'=>$row['nilai_sumatif2'] ?? null,
                'nilai_sumatif3'=>$row['nilai_sumatif3'] ?? null, 'nilai_sumatif4'=>$row['nilai_sumatif4'] ?? null,
                'nilai_sumatif5'=>$row['nilai_sumatif5'] ?? null, 'nilai_proyek'=>$row['nilai_proyek'] ?? null,
                'nilai_uas'=>$row['nilai_uas'] ?? null, 'rata_sumatif'=>$row['rata_sumatif'] ?? null,
                'nilai_raport'=>$row['nilai_raport'] ?? null
            ];
        }
    } catch (PDOException $e) {
        $error = 'Gagal mengambil data nilai: ' . $e->getMessage();
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
<link rel='stylesheet' href='../assets/app.css'></head>
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
                    <label>📅 Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($tahun_ajaran) ?>" pattern="[0-9]{4}/[0-9]{4}" title="Gunakan format tahun ajaran, contoh: 2026/2027" maxlength="9" inputmode="numeric" required style="padding:10px;border:1px solid #ddd;border-radius:5px;min-width:150px">
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
                        <strong>📌 Kelas <?= htmlspecialchars($kelas) ?> - Semester <?= htmlspecialchars($semester) ?> - Tahun Ajaran <?= htmlspecialchars($tahun_ajaran) ?></strong><br>
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
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="update_nilai" value="1">
                        <input type="hidden" name="kelas" value="<?= htmlspecialchars($kelas) ?>">
                        <input type="hidden" name="mapel_id" value="<?= htmlspecialchars((string)$mapel_id) ?>">
                        <input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>">
                        <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($tahun_ajaran) ?>">
                        
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