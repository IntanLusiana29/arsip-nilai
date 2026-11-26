<?php
require_once 'config/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] == 'admin' ? 'dashboard_admin.php' : 'dashboard_guru.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Cek password
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['mata_pelajaran_id'] = $user['mata_pelajaran_id'];
            auditLog($pdo, 'LOGIN', 'Pengguna berhasil masuk ke sistem.');
            
            if ($user['role'] == 'admin') {
                header('Location: dashboard_admin.php');
            } else {
                header('Location: dashboard_guru.php');
            }
            exit();
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Username tidak ditemukan!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk | Arsip Nilai</title>
<link rel="stylesheet" href="assets/app.css">
<style>
body.login-page{margin:0;min-height:100vh;background:#eef3fa!important;display:grid;place-items:center;padding:28px;position:relative;overflow:hidden}
.login-page:before{content:"";position:fixed;inset:0 48% 0 0;background:linear-gradient(145deg,#14243b 0%,#1e4774 100%);clip-path:polygon(0 0,100% 0,82% 100%,0 100%);z-index:0}
.login-shell{position:relative;z-index:1;width:min(100%,1000px);min-height:590px;display:grid;grid-template-columns:1fr 1fr;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 28px 80px rgba(16,35,61,.18)}
.login-brand{padding:55px 48px;background:linear-gradient(145deg,rgba(20,36,59,.97),rgba(30,71,116,.96));color:#fff;display:flex;flex-direction:column;justify-content:space-between;position:relative}
.brand-mark{width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.14);display:grid;place-items:center;border:1px solid rgba(255,255,255,.2);font-size:30px;margin-bottom:34px}
.login-brand h1{color:#fff;font-size:34px;line-height:1.2;margin:0 0 16px;letter-spacing:-1px}
.login-brand p{color:#d5e2f2;font-size:15px;line-height:1.8;max-width:360px}
.brand-foot{font-size:12px;color:#a9bfd9;letter-spacing:.4px}
.login-form-panel{padding:60px 55px;display:flex;flex-direction:column;justify-content:center}
.login-kicker{font-size:12px;font-weight:800;letter-spacing:1.6px;color:#2563eb;text-transform:uppercase;margin-bottom:10px}
.login-form-panel h2{font-size:29px;letter-spacing:-.8px;margin:0 0 8px;color:#14243b}
.login-sub{font-size:14px;color:#77869a;margin:0 0 30px}
.login-form-panel .form-group{margin-bottom:20px;text-align:left}
.login-form-panel label{display:block;margin-bottom:8px;font-size:13px;color:#34445b}
.login-form-panel input{width:100%;height:48px;padding:0 14px!important}
.login-form-panel button{width:100%;height:49px;margin-top:8px;font-size:14px;letter-spacing:.2px}
.login-error{background:#fff0f0;border:1px solid #ffd7d7;color:#a52232;padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:18px}
.login-note{margin-top:24px;padding-top:20px;border-top:1px solid #edf0f5;color:#8a97a8;font-size:12px;line-height:1.7}
.login-logo{max-width:74px;max-height:74px;object-fit:contain;border-radius:14px;background:#fff;padding:6px;margin-bottom:26px}
@media(max-width:760px){body.login-page{padding:14px}.login-page:before{inset:0 0 65% 0;clip-path:none}.login-shell{grid-template-columns:1fr;min-height:unset}.login-brand{padding:28px;min-height:230px}.login-brand h1{font-size:26px}.login-brand p{margin-bottom:20px}.brand-mark{display:none}.login-form-panel{padding:35px 26px}.login-form-panel h2{font-size:25px}}
</style>
</head>
<body class="login-page">
<main class="login-shell">
<section class="login-brand">
<div>
<div class="logo-container">
    <img
        class="login-logo"
        src="logo.jpg"
        alt="Logo UPT SMPN SATAP PANGGUNGREJO"
        onerror="this.style.display='none'"
    >
</div>
<h1>Arsip Nilai<br>UPT SMPN SATAP PANGGUNGREJO</h1>
<p>Platform pengelolaan nilai siswa yang terintegrasi untuk mendukung administrasi akademik sekolah secara efektif, tertib, dan akurat.</p>
</div>
<div class="brand-foot">SISTEM INFORMASI AKADEMIK • ARSIP NILAI • UPT SMPN SATAP PANGGUNGREJO</div>
</section>
<section class="login-form-panel">
<div class="login-kicker">Selamat datang kembali</div>
<h2>Masuk ke akun Anda</h2>
<p class="login-sub">Silakan masukkan username dan password untuk melanjutkan.</p>
<?php if($error): ?><div class="login-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<div class="form-group"><label for="username">Username</label><input id="username" type="text" name="username" placeholder="Masukkan username" required autofocus autocomplete="username"></div>
<div class="form-group"><label for="password">Password</label><input id="password" type="password" name="password" placeholder="Masukkan password" required autocomplete="current-password"></div>
<button type="submit">Masuk ke Dashboard <span aria-hidden="true">→</span></button>
</form>
<div class="login-note">Akses ini hanya diperuntukkan bagi administrator dan guru yang telah terdaftar. Jaga kerahasiaan informasi akun Anda.</div>
</section>
</main>
</body></html>
