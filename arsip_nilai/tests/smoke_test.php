<?php
/** Smoke test struktur minimum proyek; bukan pengganti pengujian fungsional. */
$root = dirname(__DIR__);
$required = [
    'login.php', 'config/database.php', 'admin/manage_nilai.php',
    'guru/input_nilai.php', 'database.sql',
    'database/migrations/001_create_audit_logs.sql'
];
$failed = [];
foreach ($required as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $failed[] = "File wajib tidak ditemukan: {$file}";
    }
}
$sql = file_get_contents($root . '/database/migrations/001_create_audit_logs.sql');
if ($sql === false || stripos($sql, 'CREATE TABLE') === false || stripos($sql, 'audit_logs') === false) {
    $failed[] = 'Migrasi audit_logs tidak berisi perintah CREATE TABLE yang diharapkan.';
}
if ($failed) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}
echo "SMOKE TEST LULUS: file utama dan migrasi audit_logs tersedia." . PHP_EOL;
