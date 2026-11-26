<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['httponly'=>true, 'secure'=>$secure, 'samesite'=>'Lax']);
    session_start();
}
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
function isAdmin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function isGuru(): bool { return ($_SESSION['role'] ?? '') === 'guru'; }
function checkAccess(string $requiredRole): void {
    if (!isLoggedIn()) {
        $nested = strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false || strpos($_SERVER['PHP_SELF'] ?? '', '/guru/') !== false;
        header('Location: ' . ($nested ? '../login.php' : 'login.php')); exit;
    }
    if (($requiredRole === 'admin' && !isAdmin()) || ($requiredRole === 'guru' && !isGuru())) {
        http_response_code(403); exit('Akses ditolak.');
    }
}
function getCurrentUserMapel() { return $_SESSION['mata_pelajaran_id'] ?? null; }
