<?php
/** Backup database menggunakan mysqldump. Jalankan dari CLI, bukan browser. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Akses hanya diizinkan melalui CLI.\n");
}
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'arsip_nilai';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Gagal membuat folder backup.\n"); exit(1);
}
$file = $backupDir . DIRECTORY_SEPARATOR . $db . '_' . date('Ymd_His') . '.sql';
$command = 'mysqldump --host=' . escapeshellarg($host) . ' --user=' . escapeshellarg($user) . ' ' . escapeshellarg($db);
$descriptors = [0 => ['pipe', 'r'], 1 => ['file', $file, 'w'], 2 => ['pipe', 'w']];
$env = array_merge($_ENV, ['MYSQL_PWD' => $pass]);
$process = proc_open($command, $descriptors, $pipes, null, $env);
if (!is_resource($process)) { fwrite(STDERR, "Tidak dapat menjalankan mysqldump.\n"); exit(1); }
fclose($pipes[0]);
$error = stream_get_contents($pipes[2]); fclose($pipes[2]);
$status = proc_close($process);
if ($status !== 0 || !is_file($file) || filesize($file) === 0) {
    @unlink($file); fwrite(STDERR, "Backup gagal: {$error}\n"); exit(1);
}
chmod($file, 0600);
echo "Backup berhasil: {$file}\n";
