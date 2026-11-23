<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] == 'admin' ? 'dashboard_admin.php' : 'dashboard_guru.php'));
    exit();
}

$error = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Cek password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['mata_pelajaran_id'] = $user['mata_pelajaran_id'];
            
            if ($user['role'] == 'admin') {
                header('Location: dashboard_admin.php');
            } else {
                header('Location: dashboard_guru.php');
            }
            exit();
        } else {
            $error = 'Password salah!';
            // Debug info
            $debug_info = "Password hash di DB: " . $user['password'] . "<br>";
            $debug_info .= "Password yang dimasukkan: " . $password . "<br>";
            $debug_info .= "Panjang hash: " . strlen($user['password']) . " karakter<br>";
        }
    } else {
        $error = 'Username tidak ditemukan!';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Arsip Nilai</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .login-container { background: white; border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); width: 450px; padding: 40px; text-align: center; }
        .logo-container { margin-bottom: 20px; }
        .logo-container img { max-width: 100%; height: auto; border-radius: 10px; }
        .login-container h2 { text-align: center; color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; transition: background 0.3s; }
        button:hover { background: #5a67d8; }
        .error { background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; border-left: 4px solid #c33; }
        .debug { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107; font-family: monospace; font-size: 12px; text-align: left; }
        .info { background: #e8f5e9; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 12px; color: #666; text-align: left; }
        .info h4 { margin-bottom: 10px; color: #2e7d32; }
        .info p { margin: 5px 0; }
        .btn-fix { background: #ff9800; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; margin-top: 10px; width: 100%; }
        .btn-fix:hover { background: #f57c00; }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo ditampilkan di sini -->
        <div class="logo-container">
            <img src="logo.jpg" alt="Logo Arsip Nilai">
        </div>
        <h2> Arsip Nilai</h2>
        <?php if($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if($debug_info): ?>
            <div class="debug">
                <strong>🔍 Debug Info:</strong><br>
                <?= $debug_info ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        
    </div>
</body>
</html>