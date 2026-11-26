ARSIP NILAI - REDESAIN ANTARMUKA
================================
Pembaruan:
- Login dibuat ulang dengan layout dua panel modern dan responsif.
- Dashboard admin dan guru diperbarui dengan hierarki visual dan kartu menu.
- Tema CSS bersama tersedia di assets/app.css dan diterapkan ke halaman PHP lainnya.
- Fungsi autentikasi, query database, dan proses pengelolaan data dipertahankan.

Pemasangan:
1. Ekstrak folder arsip_nilai ke htdocs (XAMPP) atau direktori web server.
2. Pastikan konfigurasi database pada config/database.php sesuai.
3. Buka login.php melalui localhost.
4. Jangan menghapus folder assets karena berisi stylesheet utama.
Catatan: Google Fonts membutuhkan koneksi internet; jika offline, sistem memakai font cadangan.


RIWAYAT AKTIVITAS:
- Jalankan database/migrations/001_create_audit_logs.sql satu kali di database arsip_nilai.
- Catatan aktivitas login/logout, tambah/edit/hapus siswa dan guru, serta penyimpanan/perubahan nilai dicatat melalui auditLog().
- Halaman admin/audit_log.php memperbarui tampilan otomatis setiap 15 detik.
