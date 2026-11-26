<?php
require_once '../config/auth.php';
checkAccess('admin');

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

try {
    $stmt = $pdo->query("
        SELECT a.id, a.created_at, a.action, a.description, a.ip_address,
               COALESCE(u.full_name, 'Pengguna dihapus') AS full_name,
               COALESCE(u.role, 'akun dihapus') AS role
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 500
    ");
    $logs = $stmt->fetchAll();
    $dbError = '';
} catch (PDOException $ex) {
    $logs = [];
    $dbError = 'Tabel riwayat aktivitas belum tersedia. Jalankan file database/migrations/001_create_audit_logs.sql pada database arsip_nilai.';
}
$total = count($logs);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="15">
<title>Riwayat Aktivitas | Arsip Nilai</title>
<link rel="stylesheet" href="../assets/app.css">
<style>
*{box-sizing:border-box}
body{margin:0;background:#f3f6fb;color:#17263c;font-family:Inter,'Segoe UI',Arial,sans-serif}
.page{max-width:1280px;margin:34px auto;padding:0 22px}
.top{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;margin-bottom:22px}
.heading h1{margin:0 0 7px;font-size:27px;color:#14243b}
.heading p{margin:0;color:#718096;font-size:14px}
.back{display:inline-flex;align-items:center;gap:8px;padding:11px 16px;border-radius:10px;background:#183b63;color:white;text-decoration:none;font-weight:600}
.summary{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid #e7edf5;border-radius:16px;padding:19px 22px;margin-bottom:18px;box-shadow:0 8px 24px #1b36500a}
.count{font-size:26px;font-weight:800;color:#1d4f85}.muted{color:#718096;font-size:13px}
.panel{background:white;border:1px solid #e7edf5;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px #1b36500a}
.tools{padding:16px 18px;border-bottom:1px solid #edf1f6;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.tools input{width:min(360px,100%);padding:11px 13px;border:1px solid #dbe3ee;border-radius:9px;font-size:14px;outline:none}
.tools input:focus{border-color:#3977b8;box-shadow:0 0 0 3px #3977b81a}
.scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:850px}
th{background:#f7f9fc;color:#53657c;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:14px 16px}
td{padding:14px 16px;border-top:1px solid #edf1f6;font-size:13px;vertical-align:top}
tbody tr:hover{background:#fafcff}
.action{display:inline-block;padding:5px 9px;border-radius:7px;background:#eaf2fb;color:#245b91;font-weight:700;font-size:11px}
.name{font-weight:700;color:#253b56}.detail{max-width:380px;line-height:1.55;color:#5d6d80;overflow-wrap:anywhere}
.empty,.error{padding:25px;text-align:center;color:#718096}.error{color:#a33;background:#fff2f2}
.role{font-size:11px;color:#718096;text-transform:capitalize}
@media(max-width:650px){.page{margin:22px auto;padding:0 13px}.heading h1{font-size:22px}.back{width:100%;justify-content:center}}
</style>
</head>
<body>
<main class="page">
  <div class="top">
    <div class="heading"><h1>Riwayat Aktivitas Sistem</h1><p>Pantau aktivitas administrator dan guru yang tercatat di sistem.</p></div>
    <a class="back" href="../dashboard_admin.php">← Kembali ke Dashboard</a>
  </div>
  <section class="summary">
    <div><div class="muted">Aktivitas terbaru yang ditampilkan</div><div class="count"><?= $total ?> <span class="muted">/ 500 catatan</span></div></div>
    <div class="muted">Halaman diperbarui otomatis setiap 15 detik</div>
  </section>
  <section class="panel">
    <div class="tools">
      <strong>Daftar Aktivitas</strong>
      <input type="search" id="filterLog" placeholder="Cari pengguna, aksi, atau detail...">
    </div>
    <?php if ($dbError): ?>
      <div class="error"><?= e($dbError) ?></div>
    <?php else: ?>
    <div class="scroll">
      <table id="logTable">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Peran</th><th>Aktivitas</th><th>Detail</th><th>Alamat IP</th></tr></thead>
        <tbody>
        <?php if (!$logs): ?>
          <tr><td colspan="6" class="empty">Belum ada aktivitas. Setelah pencatatan diaktifkan, aktivitas login, logout, pengelolaan siswa, guru, dan nilai akan muncul di sini.</td></tr>
        <?php else: foreach ($logs as $log): ?>
          <tr>
            <td><?= e(date('d-m-Y H:i:s', strtotime($log['created_at']))) ?></td>
            <td class="name"><?= e($log['full_name']) ?></td>
            <td><span class="role"><?= e($log['role']) ?></span></td>
            <td><span class="action"><?= e($log['action']) ?></span></td>
            <td class="detail"><?= e($log['description']) ?></td>
            <td><?= e($log['ip_address'] ?: '-') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
</main>
<script>
document.getElementById('filterLog')?.addEventListener('input', function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('#logTable tbody tr').forEach(row=>{
    row.style.display=row.innerText.toLowerCase().includes(q)?'':'none';
  });
});
</script>
</body>
</html>
