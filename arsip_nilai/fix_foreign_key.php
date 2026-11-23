<?php
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Foreign Key Constraint</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 10px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        button { background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px 5px; }
    </style>
</head>
<body>
    <div class='box'>
        <h2>🔧 Fix Foreign Key Constraint</h2>";

try {
    // 1. Cek foreign key saat ini
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME, TABLE_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_NAME = 'siswa' AND TABLE_NAME = 'nilai'
    ");
    $constraints = $stmt->fetchAll();
    
    if (count($constraints) > 0) {
        echo "<h3>Foreign Key yang ditemukan:</h3>";
        foreach ($constraints as $constraint) {
            echo "<p>• {$constraint['CONSTRAINT_NAME']} pada tabel {$constraint['TABLE_NAME']}</p>";
        }
    }
    
    // 2. Hapus foreign key constraint yang bermasalah
    echo "<h3>Menghapus foreign key constraint...</h3>";
    try {
        $pdo->exec("ALTER TABLE nilai DROP FOREIGN KEY nilai_ibfk_1");
        echo "<p class='success'>✅ Foreign key nilai_ibfk_1 berhasil dihapus</p>";
    } catch (PDOException $e) {
        echo "<p class='info'>⚠️ Foreign key tidak ditemukan atau sudah dihapus</p>";
    }
    
    // 3. Bersihkan data nilai yang tidak valid
    echo "<h3>Membersihkan data nilai yang tidak valid...</h3>";
    $stmt = $pdo->prepare("
        DELETE n FROM nilai n
        LEFT JOIN siswa s ON n.siswa_id = s.id
        WHERE s.id IS NULL
    ");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    echo "<p class='success'>✅ $deleted data nilai yang tidak valid berhasil dihapus</p>";
    
    // 4. Tambahkan kembali foreign key
    echo "<h3>Menambahkan kembali foreign key...</h3>";
    try {
        $pdo->exec("
            ALTER TABLE nilai 
            ADD CONSTRAINT fk_nilai_siswa 
            FOREIGN KEY (siswa_id) REFERENCES siswa(id) 
            ON DELETE CASCADE ON UPDATE CASCADE
        ");
        echo "<p class='success'>✅ Foreign key berhasil ditambahkan kembali</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Gagal menambahkan foreign key: " . $e->getMessage() . "</p>";
    }
    
    // 5. Tampilkan data yang bermasalah
    echo "<h3>Data yang bermasalah:</h3>";
    $stmt = $pdo->query("
        SELECT n.*, s.nama_siswa 
        FROM nilai n 
        LEFT JOIN siswa s ON n.siswa_id = s.id 
        WHERE s.id IS NULL
    ");
    $orphaned = $stmt->fetchAll();
    
    if (count($orphaned) > 0) {
        echo "<p class='error'>⚠️ Masih ada {$orphaned} data nilai yang tidak memiliki siswa</p>";
        echo "<button onclick=\"location.href='admin/manage_nilai.php'\">Kembali ke Kelola Nilai</button>";
    } else {
        echo "<p class='success'>✅ Semua data valid! Tidak ada orphaned records.</p>";
        echo "<button onclick=\"location.href='admin/manage_nilai.php'\">Kembali ke Kelola Nilai</button>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "    </div>
</body>
</html>";
?>