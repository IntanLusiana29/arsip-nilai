<?php
require_once '../config/auth.php';
checkAccess('admin');

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = $_POST['full_name'];
        $mata_pelajaran_id = $_POST['mata_pelajaran_id'];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES (?, ?, ?, 'guru', ?)");
        $stmt->execute([$username, $password, $full_name, $mata_pelajaran_id]);
        $success = "Guru berhasil ditambahkan!";
        auditLog($pdo, 'TAMBAH_GURU', "Menambahkan akun guru {$full_name} (username: {$username}).");
    } 
    elseif ($action == 'edit') {
        $id = $_POST['id'];
        $username = $_POST['username'];
        $full_name = $_POST['full_name'];
        $mata_pelajaran_id = $_POST['mata_pelajaran_id'];
        
        $query = "UPDATE users SET username = ?, full_name = ?, mata_pelajaran_id = ?";
        $params = [$username, $full_name, $mata_pelajaran_id];
        
        if (!empty($_POST['password'])) {
            $query .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        $query .= " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $success = "Guru berhasil diupdate!";
        auditLog($pdo, 'EDIT_GURU', "Mengubah akun guru {$full_name} (username: {$username}).");
    }
    elseif ($action == 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'guru'");
        $stmt->execute([$id]);
        $success = "Guru berhasil dihapus!";
        auditLog($pdo, 'HAPUS_GURU', "Menghapus akun guru dengan ID {$id}.");
    }
}

// Ambil data guru
$stmt = $pdo->query("
    SELECT u.*, m.nama_mapel 
    FROM users u 
    LEFT JOIN mata_pelajaran m ON u.mata_pelajaran_id = m.id 
    WHERE u.role = 'guru' 
    ORDER BY u.id
");
$guru_list = $stmt->fetchAll();

// Ambil daftar mata pelajaran
$stmt = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY id");
$mapel_list = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Kelola Guru - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 20px; max-width: 1200px; margin: auto; }
        .btn-add { background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
        .btn-edit { background: #3498db; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; }
        .btn-delete { background: #e74c3c; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; }
        .btn-reset { background: #f39c12; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; }
        table { width: 100%; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th { background: #34495e; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; width: 500px; max-width: 90%; }
        .modal-content input, .modal-content select { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .logout { background: #e74c3c; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #3498db; text-decoration: none; }
    </style>
<link rel='stylesheet' href='../assets/app.css'></head>
<body>
    <div class="header">
        <h2>Admin Panel - Kelola Guru</h2>
        <div>
            <span>Halo, <?= $_SESSION['full_name'] ?></span>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>
    <div class="container">
        <a href="../dashboard_admin.php" class="back-link">← Kembali ke Dashboard</a>
        
        <?php if(isset($success)): ?>
            <div class="success">✅ <?= $success ?></div>
        <?php endif; ?>
        
        <button class="btn-add" onclick="showModal('add')">+ Tambah Guru</button>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Mata Pelajaran</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($guru_list as $guru): ?>
                <tr>
                    <td><?= $guru['id'] ?></td>
                    <td><?= htmlspecialchars($guru['username']) ?></td>
                    <td><?= htmlspecialchars($guru['full_name']) ?></td>
                    <td><?= htmlspecialchars($guru['nama_mapel'] ?? '-') ?></td>
                    <td>
                        <button class="btn-edit" onclick="editGuru(<?= htmlspecialchars(json_encode($guru)) ?>)">Edit</button>
                        <button class="btn-delete" onclick="deleteGuru(<?= $guru['id'] ?>, '<?= htmlspecialchars($guru['full_name']) ?>')">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Modal Form -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">Tambah Guru</h3>
            <form method="POST" id="guru-form">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="id" id="guru-id">
                <input type="text" name="username" id="username" placeholder="Username" required>
                <input type="password" name="password" id="password" placeholder="Password (kosongkan jika tidak diubah untuk edit)">
                <input type="text" name="full_name" id="full_name" placeholder="Nama Lengkap" required>
                <select name="mata_pelajaran_id" id="mata_pelajaran_id" required>
                    <option value="">Pilih Mata Pelajaran</option>
                    <?php foreach($mapel_list as $mapel): ?>
                        <option value="<?= $mapel['id'] ?>"><?= htmlspecialchars($mapel['nama_mapel']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="background: #27ae60; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer;">Simpan</button>
                <button type="button" onclick="closeModal()" style="background: #95a5a6; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer;">Batal</button>
            </form>
        </div>
    </div>
    
    <script>
        function showModal(action) {
            document.getElementById('modal').style.display = 'flex';
            if (action === 'add') {
                document.getElementById('modal-title').innerText = 'Tambah Guru';
                document.getElementById('form-action').value = 'add';
                document.getElementById('guru-id').value = '';
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
                document.getElementById('password').placeholder = 'Password (minimal 6 karakter)';
                document.getElementById('full_name').value = '';
                document.getElementById('mata_pelajaran_id').value = '';
                document.getElementById('password').required = true;
            }
        }
        
        function editGuru(guru) {
            document.getElementById('modal').style.display = 'flex';
            document.getElementById('modal-title').innerText = 'Edit Guru';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('guru-id').value = guru.id;
            document.getElementById('username').value = guru.username;
            document.getElementById('password').value = '';
            document.getElementById('password').placeholder = 'Password (kosongkan jika tidak diubah)';
            document.getElementById('password').required = false;
            document.getElementById('full_name').value = guru.full_name;
            document.getElementById('mata_pelajaran_id').value = guru.mata_pelajaran_id;
        }
        
        function deleteGuru(id, nama) {
            if (confirm(`Yakin ingin menghapus guru "${nama}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
    </script>
</body>
</html>