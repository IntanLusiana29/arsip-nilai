<?php
require_once 'config/database.php';

// Password yang akan digunakan
$admin_password = 'admin123';
$guru_password = 'guru123';

// Hash password menggunakan PASSWORD_DEFAULT (bcrypt)
$hashed_admin = password_hash($admin_password, PASSWORD_DEFAULT);
$hashed_guru = password_hash($guru_password, PASSWORD_DEFAULT);

try {
    // Hapus user existing
    $pdo->exec("TRUNCATE TABLE users");
    
    // Insert admin
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $hashed_admin, 'Administrator', 'admin', null]);
    echo "✅ Admin berhasil ditambahkan (username: admin, password: admin123)<br>";
    
    // Insert guru Matematika
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['guru_mtk', $hashed_guru, 'Budi Santoso, S.Pd', 'guru', 1]);
    echo "✅ Guru Matematika berhasil ditambahkan (username: guru_mtk, password: guru123)<br>";
    
    // Insert guru Bahasa Indonesia
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['guru_bindo', $hashed_guru, 'Siti Aminah, S.Pd', 'guru', 2]);
    echo "✅ Guru Bahasa Indonesia berhasil ditambahkan (username: guru_bindo, password: guru123)<br>";
    
    // Insert guru Bahasa Inggris
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['guru_big', $hashed_guru, 'John Doe, S.Pd', 'guru', 3]);
    echo "✅ Guru Bahasa Inggris berhasil ditambahkan (username: guru_big, password: guru123)<br>";
    
    // Insert guru IPA
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['guru_ipa', $hashed_guru, 'Dr. Sri Mulyani, S.Si', 'guru', 4]);
    echo "✅ Guru IPA berhasil ditambahkan (username: guru_ipa, password: guru123)<br>";
    
    // Insert guru IPS
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['guru_ips', $hashed_guru, 'Drs. Haryanto', 'guru', 5]);
    echo "✅ Guru IPS berhasil ditambahkan (username: guru_ips, password: guru123)<br>";
    
    echo "<hr>";
    echo "<h3>Setup selesai! Silakan <a href='login.php'>Login</a></h3>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>