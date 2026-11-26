<?php
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Nilai Error</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0; }
        button { background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #34495e; color: white; }
    </style>
<link rel='stylesheet' href='assets/app.css'></head>
<body>
    <div class='container'>
        <h2>🔧 Perbaikan Error Foreign Key</h2>";

try {
    // 1. Nonaktifkan foreign key check
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<div class='success'>✅ Foreign key check dinonaktifkan</div>";
    
    // 2. Cek data nilai yang orphan (tidak memiliki siswa)
    $stmt = $pdo->query("
        SELECT n.*, s.id as siswa_exists 
        FROM nilai n 
        LEFT JOIN siswa s ON n.siswa_id = s.id 
        WHERE s.id IS NULL
    ");
    $orphaned = $stmt->fetchAll();
    
    if (count($orphaned) > 0) {
        echo "<div class='warning'>⚠️ Ditemukan " . count($orphaned) . " data nilai yang tidak memiliki siswa:</div>";
        echo "<table>";
        echo "<tr><th>ID Nilai</th><th>Kelas</th><th>Mata Pelajaran ID</th><th>Semester</th><th>Aksi</th></tr>";
        foreach ($orphaned as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['kelas']}</td>";
            echo "<td>{$row['mata_pelajaran_id']}</td>";
            echo "<td>{$row['semester']}</td>";
            echo "<td><button onclick=\"deleteNilai({$row['id']})\">Hapus</button></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Hapus otomatis data orphan
        $stmt = $pdo->prepare("DELETE FROM nilai WHERE siswa_id NOT IN (SELECT id FROM siswa)");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        echo "<div class='success'>✅ $deleted data nilai orphan berhasil dihapus</div>";
    } else {
        echo "<div class='success'>✅ Tidak ada data nilai orphan</div>";
    }
    
    // 3. Cek data siswa
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM siswa");
    $siswa_count = $stmt->fetch();
    
    if ($siswa_count['total'] == 0) {
        echo "<div class='warning'>⚠️ Belum ada data siswa! Silahkan tambahkan siswa terlebih dahulu.</div>";
        echo "<button onclick=\"location.href='admin/manage_siswa.php'\">+ Tambah Siswa</button>";
    } else {
        echo "<div class='success'>✅ Terdapat {$siswa_count['total']} siswa dalam database</div>";
        
        // Tampilkan daftar siswa
        $stmt = $pdo->query("SELECT id, nis, nama_siswa, kelas FROM siswa ORDER BY kelas, nama_siswa");
        $siswa_list = $stmt->fetchAll();
        
        echo "<h3>📋 Daftar Siswa:</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>NIS</th><th>Nama</th><th>Kelas</th></tr>";
        foreach ($siswa_list as $siswa) {
            echo "<tr>";
            echo "<td>{$siswa['id']}</td>";
            echo "<td>{$siswa['nis']}</td>";
            echo "<td>{$siswa['nama_siswa']}</td>";
            echo "<td>{$siswa['kelas']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Aktifkan kembali foreign key check
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<div class='success'>✅ Foreign key check diaktifkan kembali</div>";
    
    echo "<hr>";
    echo "<button onclick=\"location.href='admin/manage_nilai.php'\">📊 Kembali ke Kelola Nilai</button>";
    echo "<button onclick=\"location.href='admin/manage_siswa.php'\">👨‍🎓 Kelola Siswa</button>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "    </div>
    <script>
        function deleteNilai(id) {
            if (confirm('Yakin ingin menghapus data nilai ini?')) {
                window.location.href = 'delete_nilai.php?id=' + id;
            }
        }
    </script>
</body>
</html>";
?>