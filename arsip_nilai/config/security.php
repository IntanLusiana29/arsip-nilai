<?php
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid atau sesi telah berakhir. Muat ulang halaman.');
    }
}
function auditLog(PDO $pdo, string $action, string $description): void {
    if (empty($_SESSION['user_id'])) return;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, description, ip_address)
             VALUES (:user_id, :action, :description, :ip)'
        );
        $stmt->execute([
            ':user_id' => (int) $_SESSION['user_id'],
            ':action' => mb_substr($action, 0, 60),
            ':description' => mb_substr($description, 0, 500),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Throwable $e) {
        // Kegagalan pencatatan tidak boleh membatalkan proses utama.
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
