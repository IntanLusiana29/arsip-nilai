<?php
require_once '../config/auth.php';
checkAccess('admin');

$kelas = trim($_GET['kelas'] ?? '');
$semester = (string)($_GET['semester'] ?? '1');
$mapel_id = (int)($_GET['mapel_id'] ?? 0);
$tahun_ajaran = trim($_GET['tahun_ajaran'] ?? '2024/2025');

if (!in_array($semester, ['1', '2'], true)) {
    http_response_code(400);
    exit('Semester tidak valid.');
}
if ($kelas === '' || $mapel_id <= 0) {
    http_response_code(400);
    exit('Pilih kelas dan mata pelajaran sebelum mengekspor.');
}

try {
    $stmt = $pdo->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = ?");
    $stmt->execute([$mapel_id]);
    $mapel = $stmt->fetchColumn();
    if (!$mapel) {
        http_response_code(404);
        exit('Mata pelajaran tidak ditemukan.');
    }

    $sql = "
        SELECT
            s.nis, s.nama_siswa,
            n.nilai_sumatif1, n.nilai_sumatif2, n.nilai_sumatif3,
            n.nilai_sumatif4, n.nilai_sumatif5,
            n.rata_sumatif, n.nilai_proyek, n.nilai_uas, n.nilai_raport
        FROM siswa s
        LEFT JOIN nilai n
            ON n.siswa_id = s.id
            AND n.mata_pelajaran_id = :mapel_id
            AND n.semester = :semester
            AND n.tahun_ajaran = :tahun_ajaran
        WHERE s.kelas = :kelas
        ORDER BY s.nama_siswa ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':mapel_id' => $mapel_id,
        ':semester' => $semester,
        ':tahun_ajaran' => $tahun_ajaran,
        ':kelas' => $kelas
    ]);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Export Excel error: ' . $e->getMessage());
    http_response_code(500);
    exit('Gagal menyiapkan laporan. Periksa struktur tabel nilai dan konfigurasi database.');
}

function xlsEscape($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function nilaiExcel($value): string {
    return ($value === null || $value === '') ? '-' : number_format((float)$value, 2, ',', '.');
}
function predikatExcel($value): string {
    if ($value === null || $value === '') return '-';
    $n = (float)$value;
    if ($n >= 90) return 'A - Sangat Baik';
    if ($n >= 80) return 'B - Baik';
    if ($n >= 70) return 'C - Cukup';
    if ($n >= 60) return 'D - Kurang';
    return 'E - Sangat Kurang';
}

$filename = 'Laporan_Nilai_' . preg_replace('/[^A-Za-z0-9_-]/', '', $kelas)
          . '_Semester_' . $semester . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";
?>
<html>
<head>
<meta charset="UTF-8">
<style>
body{font-family:Calibri,Arial,sans-serif;color:#243447}
h1{font-size:18pt;color:#17365d;margin-bottom:4px}
.meta{font-size:11pt;color:#40566f}
table{border-collapse:collapse;width:100%}
th{background:#1f4e78;color:#fff;font-weight:bold;text-align:center;vertical-align:middle;border:1px solid #17365d;padding:9px}
td{border:1px solid #cbd5e1;padding:7px;vertical-align:middle}
.center{text-align:center}.name{font-weight:bold}.number{text-align:center;mso-number-format:"0.00"}
.alt{background:#f1f5f9}.pass{background:#e2f0d9;color:#276221;font-weight:bold}.fail{background:#fce4d6;color:#9c0006;font-weight:bold}
.note{color:#526579;font-style:italic}
</style>
</head>
<body>
<h1>LAPORAN HASIL BELAJAR SISWA</h1>
<p class="meta">
    Kelas: <b><?= xlsEscape($kelas) ?></b> &nbsp; | &nbsp;
    Mata Pelajaran: <b><?= xlsEscape($mapel) ?></b> &nbsp; | &nbsp;
    Semester: <b><?= xlsEscape($semester) ?></b> &nbsp; | &nbsp;
    Tahun Ajaran: <b><?= xlsEscape($tahun_ajaran) ?></b>
</p>
<p class="meta">Ambang ketuntasan: 75</p>
<table>
<thead>
<tr>
    <th rowspan="2">No.</th>
    <th rowspan="2">NIS</th>
    <th rowspan="2">Nama Siswa</th>
    <th colspan="5">Nilai Sumatif</th>
    <th rowspan="2">Rata-rata<br>Sumatif</th>
    <th rowspan="2">Nilai<br>Proyek</th>
    <th rowspan="2">Nilai<br>UAS</th>
    <th rowspan="2">Nilai<br>Raport</th>
    <th rowspan="2">Predikat</th>
    <th rowspan="2">Status</th>
</tr>
<tr>
    <th>Sumatif 1</th><th>Sumatif 2</th><th>Sumatif 3</th><th>Sumatif 4</th><th>Sumatif 5</th>
</tr>
</thead>
<tbody>
<?php
$no = 1;
$totalNilai = 0;
$jumlahNilai = 0;
$tuntas = 0;
$belumTuntas = 0;
foreach ($rows as $row):
    $raport = $row['nilai_raport'];
    $adaNilai = $raport !== null && $raport !== '';
    $status = !$adaNilai ? 'BELUM ADA NILAI' : ((float)$raport >= 75 ? 'TUNTAS' : 'BELUM TUNTAS');
    $class = ($no % 2 === 0) ? 'alt' : '';
    if ($adaNilai) {
        $totalNilai += (float)$raport;
        $jumlahNilai++;
        if ((float)$raport >= 75) $tuntas++; else $belumTuntas++;
    }
?>
<tr class="<?= $class ?>">
    <td class="center"><?= $no++ ?></td>
    <td class="center" style="mso-number-format:'\@'"><?= xlsEscape($row['nis']) ?></td>
    <td class="name"><?= xlsEscape($row['nama_siswa']) ?></td>
    <td class="number"><?= nilaiExcel($row['nilai_sumatif1']) ?></td>
    <td class="number"><?= nilaiExcel($row['nilai_sumatif2']) ?></td>
    <td class="number"><?= nilaiExcel($row['nilai_sumatif3']) ?></td>
    <td class="number"><?= nilaiExcel($row['nilai_sumatif4']) ?></td>
    <td class="number"><?= nilaiExcel($row['nilai_sumatif5']) ?></td>
    <td class="number"><?= nilaiExcel($row['rata_sumatif']) ?></td>
    <td class="number"><?= nilaiExcel($row['nilai_proyek']) ?></td>
    <td class="number"><?= nilaiExcel($row['nilai_uas']) ?></td>
    <td class="number"><b><?= nilaiExcel($raport) ?></b></td>
    <td><?= xlsEscape(predikatExcel($raport)) ?></td>
    <td class="center <?= $adaNilai ? (((float)$raport >= 75) ? 'pass' : 'fail') : '' ?>"><?= $status ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?>
<tr><td colspan="14" class="center">Tidak ada data siswa pada kelas ini.</td></tr>
<?php endif; ?>
</tbody>
</table>
<br>
<table>
<tr><th colspan="2">RINGKASAN NILAI KELAS</th></tr>
<tr><td>Jumlah siswa</td><td class="center"><?= count($rows) ?></td></tr>
<tr><td>Siswa dengan nilai raport</td><td class="center"><?= $jumlahNilai ?></td></tr>
<tr><td>Rata-rata nilai raport</td><td class="center"><?= $jumlahNilai ? number_format($totalNilai / $jumlahNilai, 2, ',', '.') : '-' ?></td></tr>
<tr><td>Jumlah tuntas (nilai ≥ 75)</td><td class="center"><?= $tuntas ?></td></tr>
<tr><td>Jumlah belum tuntas</td><td class="center"><?= $belumTuntas ?></td></tr>
</table>
<br><p class="note">Keterangan: tanda "-" menunjukkan nilai belum diinput. Nilai raport mengikuti perhitungan pada sistem.</p>
</body>
</html>
