<?php
require_once '../config/auth.php';
checkAccess('admin');

// Proses CRUD
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action == 'add') {
            $nis = $_POST['nis'];
            $nama_siswa = $_POST['nama_siswa'];
            $kelas = $_POST['kelas'];
            $jenis_kelamin = $_POST['jenis_kelamin'];
            
            // Cek apakah NIS sudah ada
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nis = ?");
            $stmt->execute([$nis]);
            if ($stmt->fetchColumn() > 0) {
                $error = "NIS '$nis' sudah digunakan! Gunakan NIS yang berbeda.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO siswa (nis, nama_siswa, kelas, jenis_kelamin) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nis, $nama_siswa, $kelas, $jenis_kelamin]);
                $success = "Siswa berhasil ditambahkan!";
            }
        } 
        elseif ($action == 'edit') {
            $id = $_POST['id'];
            $nis = $_POST['nis'];
            $nama_siswa = $_POST['nama_siswa'];
            $kelas = $_POST['kelas'];
            $jenis_kelamin = $_POST['jenis_kelamin'];
            
            // Cek apakah NIS sudah digunakan oleh siswa lain
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE nis = ? AND id != ?");
            $stmt->execute([$nis, $id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "NIS '$nis' sudah digunakan oleh siswa lain!";
            } else {
                $stmt = $pdo->prepare("UPDATE siswa SET nis = ?, nama_siswa = ?, kelas = ?, jenis_kelamin = ? WHERE id = ?");
                $stmt->execute([$nis, $nama_siswa, $kelas, $jenis_kelamin, $id]);
                $success = "Siswa berhasil diupdate!";
            }
        }
        elseif ($action == 'delete') {
            $id = $_POST['id'];
            
            // Cek apakah siswa memiliki nilai
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM nilai WHERE siswa_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Siswa tidak bisa dihapus karena sudah memiliki nilai! Hapus nilai terlebih dahulu.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Siswa berhasil dihapus!";
            }
        }
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $error = "NIS sudah terdaftar! Gunakan NIS yang berbeda.";
        } else {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

// Ambil data siswa
$kelas_filter = $_GET['kelas'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM siswa WHERE 1=1";
$params = [];

if ($kelas_filter) {
    $query .= " AND kelas = ?";
    $params[] = $kelas_filter;
}
if ($search) {
    $query .= " AND (nis LIKE ? OR nama_siswa LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY FIELD(kelas, 'VII', 'VIII', 'IX'), nama_siswa";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$siswa_list = $stmt->fetchAll();

// Hitung statistik
$stmt = $pdo->query("SELECT kelas, COUNT(*) as total FROM siswa GROUP BY kelas");
$stats = [];
while ($row = $stmt->fetch()) {
    $stats[$row['kelas']] = $row['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kelola Siswa - Admin</title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .container { padding: 20px; max-width: 1400px; margin: auto; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #2c3e50; margin-bottom: 10px; }
        .stat-card .number { font-size: 36px; font-weight: bold; color: #3498db; }
        .btn-add { 
            background: #27ae60; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            margin-bottom: 20px;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-add:hover { background: #219a52; }
        .btn-edit { background: #3498db; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; margin-right: 5px; }
        .btn-edit:hover { background: #2980b9; }
        .btn-delete { background: #e74c3c; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; }
        .btn-delete:hover { background: #c0392b; }
        table { width: 100%; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th { background: #34495e; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            justify-content: center; 
            align-items: center; 
            z-index: 1000;
        }
        .modal-content { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            width: 500px; 
            max-width: 90%;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .modal-content h3 { margin-bottom: 20px; color: #2c3e50; }
        .modal-content input, .modal-content select { 
            width: 100%; 
            padding: 10px; 
            margin: 10px 0; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 14px;
        }
        .modal-content input:focus, .modal-content select:focus {
            outline: none;
            border-color: #27ae60;
        }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .logout { background: #e74c3c; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; margin-right: 15px; }
        .filter { margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter input, .filter select { padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .filter button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .filter button:hover { background: #2980b9; }
        .nis-info { font-size: 11px; color: #666; margin-top: -5px; margin-bottom: 10px; }
        .action-buttons { display: flex; gap: 30px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
        .modal-buttons { display: flex; gap: 10px; margin-top: 15px; }
        .modal-buttons button { flex: 1; padding: 10px; border: none; border-radius: 5px; cursor: pointer; }
        .btn-simpan { background: #27ae60; color: white; }
        .btn-batal { background: #95a5a6; color: white; }
        @media (max-width: 768px) {
            .stats { grid-template-columns: 1fr; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
            .btn-edit, .btn-delete { padding: 3px 8px; font-size: 11px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Admin Panel - Kelola Siswa</h2>
        <div>
            <span>Halo, <?= $_SESSION['full_name'] ?></span>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>
    <div class="container">
        <div>
            <a href="../dashboard_admin.php" class="back-link">← Kembali ke Dashboard</a>
        </div>
        
        <?php if($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <h3>Kelas VII</h3>
                <div class="number"><?= $stats['VII'] ?? 0 ?></div>
                <p>Siswa</p>
            </div>
            <div class="stat-card">
                <h3>Kelas VIII</h3>
                <div class="number"><?= $stats['VIII'] ?? 0 ?></div>
                <p>Siswa</p>
            </div>
            <div class="stat-card">
                <h3>Kelas IX</h3>
                <div class="number"><?= $stats['IX'] ?? 0 ?></div>
                <p>Siswa</p>
            </div>
        </div>
        
        <div class="action-buttons">
            <button class="btn-add" onclick="showAddModal()">+ Tambah Siswa</button>
        </div>
        
        <div class="filter">
            <input type="text" id="searchInput" placeholder="Cari NIS/Nama" value="<?= htmlspecialchars($search) ?>">
            <select id="kelasFilter">
                <option value="">Semua Kelas</option>
                <option value="VII" <?= $kelas_filter == 'VII' ? 'selected' : '' ?>>Kelas VII</option>
                <option value="VIII" <?= $kelas_filter == 'VIII' ? 'selected' : '' ?>>Kelas VIII</option>
                <option value="IX" <?= $kelas_filter == 'IX' ? 'selected' : '' ?>>Kelas IX</option>
            </select>
            <button onclick="applyFilter()">🔍 Filter</button>
            <button onclick="resetFilter()" style="background: #95a5a6;">🔄 Reset</button>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Jenis Kelamin</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswa_list)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                📭 Belum ada data siswa. Silahkan tambah siswa terlebih dahulu.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($siswa_list as $siswa): ?>
                        <tr>
                            <td><?= $siswa['id'] ?></td>
                            <td><?= htmlspecialchars($siswa['nis']) ?></td>
                            <td><?= htmlspecialchars($siswa['nama_siswa']) ?></td>
                            <td><?= $siswa['kelas'] ?></td>
                            <td><?= $siswa['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                            <td>
                                <button class="btn-edit" onclick="editSiswa(<?= htmlspecialchars(json_encode($siswa)) ?>)">✏️ Edit</button>
                                <button class="btn-delete" onclick="deleteSiswa(<?= $siswa['id'] ?>, '<?= htmlspecialchars(addslashes($siswa['nama_siswa'])) ?>')">🗑️ Hapus</button>
                             </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Form -->
    <div id="siswaModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Tambah Siswa</h3>
            <form method="POST" id="siswaForm" onsubmit="return validateForm()">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="siswaId">
                
                <input type="text" name="nis" id="nis" placeholder="NIS (Nomor Induk Siswa)" required>
                <div class="nis-info">⚠️ NIS harus unik dan berupa angka</div>
                
                <input type="text" name="nama_siswa" id="namaSiswa" placeholder="Nama Lengkap" required>
                
                <select name="kelas" id="kelas" required>
                    <option value="">Pilih Kelas</option>
                    <option value="VII">VII</option>
                    <option value="VIII">VIII</option>
                    <option value="IX">IX</option>
                </select>
                
                <select name="jenis_kelamin" id="jenisKelamin" required>
                    <option value="">Pilih Jenis Kelamin</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn-simpan">💾 Simpan</button>
                    <button type="button" class="btn-batal" onclick="closeModal()">❌ Batal</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Fungsi untuk menampilkan modal tambah siswa
        function showAddModal() {
            document.getElementById('modalTitle').innerText = 'Tambah Siswa';
            document.getElementById('formAction').value = 'add';
            document.getElementById('siswaId').value = '';
            document.getElementById('nis').value = '';
            document.getElementById('nis').readOnly = false;
            document.getElementById('namaSiswa').value = '';
            document.getElementById('kelas').value = '';
            document.getElementById('jenisKelamin').value = '';
            document.getElementById('siswaModal').style.display = 'flex';
        }
        
        // Fungsi untuk edit siswa
        function editSiswa(siswa) {
            document.getElementById('modalTitle').innerText = 'Edit Siswa';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('siswaId').value = siswa.id;
            document.getElementById('nis').value = siswa.nis;
            document.getElementById('nis').readOnly = true; // NIS tidak bisa diubah saat edit
            document.getElementById('namaSiswa').value = siswa.nama_siswa;
            document.getElementById('kelas').value = siswa.kelas;
            document.getElementById('jenisKelamin').value = siswa.jenis_kelamin;
            document.getElementById('siswaModal').style.display = 'flex';
        }
        
        // Fungsi untuk hapus siswa
        function deleteSiswa(id, nama) {
            if (confirm(`Yakin ingin menghapus siswa "${nama}"?\n\n⚠️ Perhatian: Siswa yang sudah memiliki nilai TIDAK bisa dihapus!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Validasi form sebelum submit
        function validateForm() {
            const nis = document.getElementById('nis').value;
            const nama = document.getElementById('namaSiswa').value;
            const kelas = document.getElementById('kelas').value;
            const jenisKelamin = document.getElementById('jenisKelamin').value;
            
            if (!nis) {
                alert('NIS harus diisi!');
                return false;
            }
            
            if (!/^\d+$/.test(nis)) {
                alert('NIS harus berupa angka!');
                return false;
            }
            
            if (!nama) {
                alert('Nama siswa harus diisi!');
                return false;
            }
            
            if (!kelas) {
                alert('Kelas harus dipilih!');
                return false;
            }
            
            if (!jenisKelamin) {
                alert('Jenis kelamin harus dipilih!');
                return false;
            }
            
            return true;
        }
        
        // Tutup modal
        function closeModal() {
            document.getElementById('siswaModal').style.display = 'none';
        }
        
        // Filter data
        function applyFilter() {
            const kelas = document.getElementById('kelasFilter').value;
            const search = document.getElementById('searchInput').value;
            window.location.href = `manage_siswa.php?kelas=${kelas}&search=${encodeURIComponent(search)}`;
        }
        
        // Reset filter
        function resetFilter() {
            window.location.href = 'manage_siswa.php';
        }
        
        // Enter key untuk filter
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilter();
            }
        });
        
        // Tutup modal jika klik di luar modal
        window.onclick = function(event) {
            const modal = document.getElementById('siswaModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Pastikan modal tertutup saat halaman load
        document.addEventListener('DOMContentLoaded', function() {
            closeModal();
        });
    </script>
</body>
</html>