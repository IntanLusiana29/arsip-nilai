<?php
// Jalankan sekali melalui localhost, lalu HAPUS file ini.
require_once __DIR__ . '/config/database.php';

// Batasi hanya komputer lokal agar tidak dapat dipakai dari jaringan luar.
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Akses ditolak. Jalankan dari komputer/server lokal.');
}

$message = '';
$error = '';
try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $pdo->beginTransaction();

    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute(['admin']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        $update = $pdo->prepare("UPDATE users SET password = ?, role = 'admin', full_name = ? WHERE id = ?");
        $update->execute([$adminHash, 'Administrator', $admin['id']]);
    } else {
        $insert = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, 'admin', NULL)");
        $insert->execute(['admin', $adminHash, 'Administrator']);
    }

    // Reset akun guru yang memang sudah ada, tanpa menghapus akun/data lain.
    $guruHash = password_hash('guru123', PASSWORD_DEFAULT);
    $guruUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE role = 'guru'");
    $guruUpdate->execute([$guruHash]);

    $pdo->commit();

    $check = $pdo->prepare('SELECT password FROM users WHERE username = ? LIMIT 1');
    $check->execute(['admin']);
    $savedHash = $check->fetchColumn();
    if (!$savedHash || !password_verify('admin123', $savedHash)) {
        throw new RuntimeException('Verifikasi password admin setelah penyimpanan gagal.');
    }

    $message = 'Password admin berhasil dibuat dan diverifikasi. ' . $guruUpdate->rowCount() . ' akun guru diperbarui.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Login Arsip Nilai</title>
<style>body{font-family:Arial,sans-serif;background:#f3f4f6;padding:24px}.card{max-width:620px;margin:30px auto;background:white;padding:24px;border-radius:12px;box-shadow:0 4px 18px #0001}.ok{background:#e7f7ec;color:#176534;padding:14px;border-radius:8px}.err{background:#fdecec;color:#9b1c1c;padding:14px;border-radius:8px}code{background:#eee;padding:3px 6px;border-radius:4px}</style><link rel='stylesheet' href='assets/app.css'></head>
<body><main class="card"><h2>Reset akun Arsip Nilai</h2>
<p>Database yang sedang digunakan: <code><?= htmlspecialchars((string)($db ?? 'tidak diketahui'), ENT_QUOTES, 'UTF-8') ?></code></p>
<?php if ($message): ?><div class="ok"><strong>Berhasil.</strong><br><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<p>Login administrator:</p><ul><li>Username: <code>admin</code></li><li>Password: <code>admin123</code></li></ul>
<p>Password semua akun dengan role guru yang sudah ada direset menjadi <code>guru123</code>.</p>
<p><strong>PENTING:</strong> Hapus file <code>reset_login.php</code> sekarang, lalu buka <a href="login.php">halaman login</a>.</p>
<?php else: ?><div class="err"><strong>Reset gagal:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><p>Pastikan tabel <code>users</code> sudah dibuat di database yang ditampilkan di atas.</p><?php endif; ?>
</main></body></html>
