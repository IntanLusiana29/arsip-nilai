<?php
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Cek Database</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class='box'>
        <h2>🔍 Diagnostic Database</h2>";

// Cek struktur tabel users
$stmt = $pdo->query("DESCRIBE users");
$columns = $stmt->fetchAll();
echo "<h3>Struktur Tabel users:</h3>";
echo "<ul>";
foreach ($columns as $col) {
    echo "<li>{$col['Field']} - {$col['Type']}</li>";
}
echo "</ul>";

// Cek isi tabel users
$users = $pdo->query("SELECT id, username, password, role FROM users")->fetchAll();

if (count($users) == 0) {
    echo "<div class='error'>❌ Tabel users KOSONG! Silahkan jalankan fix_password.php</div>";
} else {
    echo "<h3>Data Users:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Username</th><th>Password Hash</th><th>Role</th><th>Status</th></tr>";
    
    foreach ($users as $user) {
        $hash_length = strlen($user['password']);
        $is_valid = password_get_info($user['password'])['algo'] !== 0;
        
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td style='font-size: 11px;'>" . substr($user['password'], 0, 30) . "...</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>" . ($is_valid ? "<span class='success'>✅ Valid</span>" : "<span class='error'>❌ Invalid</span>") . "</td>";
        echo "</tr>";
        
        // Test dengan password default
        if ($user['username'] == 'admin') {
            if (password_verify('admin123', $user['password'])) {
                echo "<tr><td colspan='5' class='success'>✓ Password 'admin123' cocok untuk user admin</td></tr>";
            } else {
                echo "<tr><td colspan='5' class='error'>✗ Password 'admin123' TIDAK cocok untuk user admin</td></tr>";
            }
        }
        
        if (strpos($user['username'], 'guru') !== false) {
            if (password_verify('guru123', $user['password'])) {
                echo "<tr><td colspan='5' class='success'>✓ Password 'guru123' cocok untuk user {$user['username']}</td></tr>";
            } else {
                echo "<tr><td colspan='5' class='error'>✗ Password 'guru123' TIDAK cocok untuk user {$user['username']}</td></tr>";
            }
        }
    }
    echo "</table>";
}

echo "<br><a href='fix_password.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Jalankan Fix Password</a><br>";
echo "<a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>🔐 Kembali ke Login</a>";
echo "</div></body></html>";
?>