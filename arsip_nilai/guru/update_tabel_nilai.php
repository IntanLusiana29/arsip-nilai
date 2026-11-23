<?php
require_once 'config/database.php';

try {
    // Cek kolom yang sudah ada
    $stmt = $pdo->query("SHOW COLUMNS FROM nilai");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $columns_to_add = [
        'nilai_sumatif1' => 'DECIMAL(5,2) DEFAULT NULL',
        'nilai_sumatif2' => 'DECIMAL(5,2) DEFAULT NULL',
        'nilai_sumatif3' => 'DECIMAL(5,2) DEFAULT NULL',
        'nilai_sumatif4' => 'DECIMAL(5,2) DEFAULT NULL',
        'nilai_sumatif5' => 'DECIMAL(5,2) DEFAULT NULL',
        'nilai_proyek' => 'DECIMAL(5,2) DEFAULT NULL',
        'nilai_uas' => 'DECIMAL(5,2) DEFAULT NULL',
        'rata_sumatif' => 'DECIMAL(5,2) DEFAULT NULL',
        'nilai_raport' => 'DECIMAL(5,2) DEFAULT NULL'
    ];
    
    $added = [];
    foreach ($columns_to_add as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            $pdo->exec("ALTER TABLE nilai ADD COLUMN $column $definition");
            $added[] = $column;
            echo "✅ Kolom '$column' berhasil ditambahkan<br>";
        } else {
            echo "ℹ️ Kolom '$column' sudah ada<br>";
        }
    }
    
    if (empty($added)) {
        echo "<br>📌 Semua kolom sudah ada. Tidak ada perubahan.<br>";
    } else {
        echo "<br>✅ Update struktur tabel selesai!";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
<br>
<a href="guru/input_nilai.php">← Kembali ke Input Nilai</a>