<?php
require_once '../config/auth.php';
checkAccess('admin');

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=laporan_nilai.xls");

$kelas = $_GET['kelas'] ?? '';
$semester = $_GET['semester'] ?? '1';
$mapel_id = $_GET['mapel_id'] ?? '';

echo "<table border='1'>";
echo "<tr>";
echo "<th>NIS</th>";
echo "<th>Nama Siswa</th>";
echo "<th>Jenis Kelamin</th>";
echo "<th>Nilai Harian</th>";
echo "<th>Nilai UTS</th>";
echo "<th>Nilai UAS</th>";
echo "<th>Nilai Akhir</th>";
echo "<th>Status</th>";
echo "</tr>";

if ($mapel_id) {
    $stmt = $pdo->prepare("
        SELECT s.nis, s.nama_siswa, s.jenis_kelamin, 
               n.nilai_harian, n.nilai_uts, n.nilai_uas, n.nilai_akhir
        FROM siswa s
        LEFT JOIN nilai n ON s.id = n.siswa_id AND n.mata_pelajaran_id = ? AND n.semester = ? AND n.tahun_ajaran = '2024/2025'
        WHERE s.kelas = ?
        ORDER BY s.nama_siswa
    ");
    $stmt->execute([$mapel_id, $semester, $kelas]);
    $data = $stmt->fetchAll();
    
    foreach ($data as $row) {
        $status = ($row['nilai_akhir'] && $row['nilai_akhir'] >= 75) ? 'Tuntas' : 'Belum Tuntas';
        echo "<tr>";
        echo "<td>{$row['nis']}</td>";
        echo "<td>{$row['nama_siswa']}</td>";
        echo "<td>" . ($row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan') . "</td>";
        echo "<td>{$row['nilai_harian']}</td>";
        echo "<td>{$row['nilai_uts']}</td>";
        echo "<td>{$row['nilai_uas']}</td>";
        echo "<td>{$row['nilai_akhir']}</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
}

echo "</table>";
?>