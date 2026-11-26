<?php
/** Jalankan melalui CLI: php scripts/health_check.php */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Akses hanya diizinkan melalui CLI.\n");
}
$root = dirname(__DIR__);
$checks = [];
$checks['PHP'] = PHP_VERSION;
$checks['Konfigurasi database'] = is_file($root . '/config/database.php') ? 'TERSEDIA' : 'TIDAK ADA';
$checks['File database utama'] = is_file($root . '/database.sql') ? 'TERSEDIA' : 'TIDAK ADA';
try {
    require $root . '/config/database.php';
    $pdo->query('SELECT 1');
    $checks['Koneksi database'] = 'OK';
} catch (Throwable $e) {
    $checks['Koneksi database'] = 'GAGAL';
}
$failed = false;
foreach ($checks as $name => $value) {
    echo str_pad($name, 25) . ': ' . $value . PHP_EOL;
    if (in_array($value, ['TIDAK ADA', 'GAGAL'], true)) $failed = true;
}
exit($failed ? 1 : 0);
