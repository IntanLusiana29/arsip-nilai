# Sistem Arsip Nilai Berbasis Web

Aplikasi PHP Native dan MySQL untuk pengelolaan data siswa, guru, mata pelajaran, serta nilai.

## Pengembangan Scrum

Pengembangan direncanakan dalam Sprint berikut. Durasi contoh: 1 minggu per Sprint.

| Sprint | Backlog utama | Kriteria selesai |
|---|---|---|
| 1 - Fondasi | Pemeriksaan kode, konfigurasi lingkungan, keamanan sesi | Aplikasi dapat login dengan konfigurasi database |
| 2 - Arsip nilai | Tahun ajaran dan semester dapat dipilih saat input nilai | Nilai tersimpan sesuai periode |
| 3 - Audit | Pencatatan aktivitas penyimpanan nilai | Admin dapat melihat riwayat aktivitas |
| 4 - Kualitas | Pemeriksaan sintaks otomatis dan pengujian manual | Tidak ada error sintaks PHP |
| 5 - Rilis | Dokumentasi, backup, dan deployment | Aplikasi terpasang di server uji |

Ritual Scrum: Sprint Planning (menentukan backlog), Daily Scrum (kemajuan dan hambatan), Sprint Review (demonstrasi hasil), dan Sprint Retrospective (perbaikan proses). Product Owner menetapkan prioritas, Scrum Master memfasilitasi proses, dan Developer mengimplementasikan serta menguji fitur.

## Penerapan DevOps

- **Plan:** backlog Scrum dan issue GitHub.
- **Code:** perubahan kode melalui branch dan Pull Request.
- **Build/Test:** GitHub Actions menjalankan `php -l` pada seluruh file PHP saat push dan Pull Request.
- **Release:** buat tag versi setelah pemeriksaan berhasil.
- **Deploy:** salin aplikasi ke server uji/hosting setelah backup database dan konfigurasi.
- **Operate/Monitor:** pantau log server, ketersediaan aplikasi, dan tabel `audit_logs`.

Workflow CI tersedia di `.github/workflows/php-ci.yml`. Workflow ini memeriksa sintaks PHP; bukan pengujian fungsional otomatis dan tidak melakukan deployment otomatis.

## Instalasi lokal (XAMPP)

1. Salin folder `arsip_nilai` ke `C:/xampp/htdocs/`.
2. Jalankan Apache dan MySQL dari XAMPP.
3. Buat database bernama `arsip_nilai` melalui phpMyAdmin.
4. Import file `database.sql`.
5. Jalankan migrasi `database/migrations/001_create_audit_logs.sql` melalui phpMyAdmin/SQL.
6. Sesuaikan koneksi database melalui environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, atau gunakan nilai default XAMPP di `config/database.php`.
7. Buka `http://localhost/arsip_nilai/login.php`.

## Catatan keamanan sebelum produksi

- Ganti seluruh password akun contoh dan jangan gunakan akun demo pada server publik.
- Hapus/nonaktifkan skrip setup dan perbaikan sekali pakai (`setup_users.php`, `fix_*.php`, `check_and_fix.php`) setelah digunakan. Skrip setup awal yang mengosongkan tabel pengguna tidak boleh dibiarkan dapat diakses publik.
- Gunakan HTTPS, user database dengan hak minimum, backup rutin, dan `display_errors=Off`.
- Batasi akses file konfigurasi dan direktori database/migrations dari web.
- Pastikan seluruh formulir perubahan data lainnya juga menerapkan token CSRF; token CSRF pada paket ini sudah ditambahkan ke login dan formulir input nilai.

## Konfigurasi database

Secara default memakai XAMPP: host `localhost`, database `arsip_nilai`, user `root`, password kosong. Untuk server produksi, atur environment variable dan gunakan password database yang kuat.

## Tambahan Scrum dan DevOps

- Template bukti pelaksanaan Scrum: `docs/FORM_SCRUM.md`. Isi dengan aktivitas dan bukti nyata setiap Sprint; template kosong tidak membuktikan kegiatan telah dilakukan.
- Panduan DevOps: `docs/DEVOPS.md`.
- CI memeriksa sintaks PHP dan menjalankan smoke test melalui `.github/workflows/php-ci.yml`.
- Deployment staging manual tersedia pada `.github/workflows/deploy-staging.yml`; perlu mengisi GitHub Actions Secrets sebelum dijalankan.
- Pemeriksaan kesehatan aplikasi melalui CLI: `php scripts/health_check.php`.
- Backup database melalui CLI: `php scripts/backup_database.php` (memerlukan `mysqldump`).
