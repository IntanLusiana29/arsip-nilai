<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/auth.php';
require_once 'config/database.php';
checkAccess('guru');

$mapel_id = $_SESSION['mata_pelajaran_id'];
$mapel = null;
$error = '';

// Ambil informasi mata pelajaran guru dengan error handling
try {
    $stmt = $pdo->prepare("SELECT nama_mapel FROM mata_pelajaran WHERE id = ?");
    $stmt->execute([$mapel_id]);
    $mapel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mapel) {
        $error = "Mata pelajaran tidak ditemukan. Silahkan hubungi administrator.";
    }
} catch (PDOException $e) {
    $error = "Error mengambil data mata pelajaran: " . $e->getMessage();
}

// Ambil nama mapel dengan aman
$nama_mapel = ($mapel && isset($mapel['nama_mapel'])) ? $mapel['nama_mapel'] : 'Mata Pelajaran';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Guru - <?= htmlspecialchars($nama_mapel) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #27ae60; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .container { padding: 20px; max-width: 1200px; margin: auto; }
        .menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .card { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            text-decoration: none; 
            color: #333; 
            display: block;
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: center;
        }
        .card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .card h3 { 
            margin-bottom: 15px; 
            color: #27ae60;
            font-size: 24px;
        }
        .card p { 
            color: #666;
            font-size: 14px;
        }
        .card .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .logout { 
            background: #e74c3c; 
            color: white; 
            padding: 8px 15px; 
            border-radius: 5px; 
            text-decoration: none;
        }
        .info-mapel { 
            background: #e8f5e9; 
            padding: 15px; 
            border-radius: 10px; 
            margin-top: 20px;
            text-align: center;
        }
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
            border-left: 4px solid #dc3545;
        }
        @media (max-width: 768px) {
            .menu { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>📚 Guru Panel - <?= htmlspecialchars($nama_mapel) ?></h2>
        <div>
            <span>Halo, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Guru') ?></span>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>
    <div class="container">
        <?php if($error): ?>
            <div class="error-box">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="info-mapel">
            <strong>Mata Pelajaran:</strong> <?= htmlspecialchars($nama_mapel) ?>
        </div>
        
        <div class="menu">
            <a href="guru/input_nilai.php" class="card">
                <div class="icon">✏️</div>
                <h3>Input Nilai</h3>
                <p>Input nilai sumatif 1-5, proyek, dan UAS</p>
            </a>
            <a href="guru/lihat_nilai.php" class="card">
                <div class="icon">👁️</div>
                <h3>Lihat Nilai</h3>
                <p>Lihat semua nilai yang sudah diinput</p>
            </a>
            <a href="guru/edit_nilai.php" class="card">
                <div class="icon">✏️</div>
                <h3>Edit Nilai</h3>
                <p>Edit nilai yang sudah diinput</p>
            </a>
        </div>
    </div>
</body>
</html>