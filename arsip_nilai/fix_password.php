<?php
require_once 'config/database.php';

// Buat hash untuk password yang benar
$admin_password = 'admin123';
$guru_password = 'guru123';

$hashed_admin = password_hash($admin_password, PASSWORD_DEFAULT);
$hashed_guru = password_hash($guru_password, PASSWORD_DEFAULT);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Password</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        button { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; }
    </style>
<link rel='stylesheet' href='assets/app.css'></head>
<body>
    <div class='container'>
        <h2>🔧 Perbaikan Password</h2>";

try {
    // Cek jumlah user sebelum update
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total = $stmt->fetch();
    
    if ($total['total'] == 0) {
        echo "<div class='error'>⚠️ Belum ada user di database! Membuat user baru...</div>";
        
        // Insert admin
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hashed_admin, 'Administrator', 'admin']);
        echo "<div class='success'>✅ User admin berhasil dibuat</div>";
        
        // Insert guru untuk setiap mata pelajaran
        $guru_list = [
            ['guru_mtk', 'Guru Matematika', 1],
            ['guru_bindo', 'Guru Bahasa Indonesia', 2],
            ['guru_big', 'Guru Bahasa Inggris', 3],
            ['guru_ipa', 'Guru IPA', 4],
            ['guru_ips', 'Guru IPS', 5]
        ];
        
        foreach ($guru_list as $guru) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$guru[0], $hashed_guru, $guru[1], 'guru', $guru[2]]);
            echo "<div class='success'>✅ User {$guru[0]} berhasil dibuat</div>";
        }
    } else {
        // Update password existing users
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->execute([$hashed_admin]);
        echo "<div class='success'>✅ Password admin berhasil diupdate</div>";
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE role = 'guru'");
        $stmt->execute([$hashed_guru]);
        echo "<div class='success'>✅ Password semua guru berhasil diupdate</div>";
    }
    
    // Tampilkan semua user
    $users = $pdo->query("SELECT id, username, role FROM users")->fetchAll();
    
    echo "<h3>📋 Daftar User Saat Ini:</h3>";
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th style='padding: 8px; border: 1px solid #ddd;'>ID</th><th style='padding: 8px; border: 1px solid #ddd;'>Username</th><th style='padding: 8px; border: 1px solid #ddd;'>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$user['id']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$user['username']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$user['role']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='success' style='margin-top: 20px;'>
            <strong>✅ Perbaikan selesai!</strong><br>
            Silakan login dengan:<br>
            • Admin: username <strong>admin</strong> password <strong>admin123</strong><br>
            • Guru: username <strong>guru_mtk</strong> password <strong>guru123</strong><br>
          </div>";
    
    echo "<button onclick=\"location.href='login.php'\">🔐 Kembali ke Login</button>";
    
} catch(PDOException $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "    </div>
</body>
</html>";
?>