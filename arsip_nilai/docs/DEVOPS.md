# Panduan Operasional DevOps

## Continuous Integration
Workflow `.github/workflows/php-ci.yml` memeriksa sintaks semua file PHP dan menjalankan `tests/smoke_test.php` pada push dan pull request. Hasil aktual harus diperiksa pada tab Actions repositori.

## Continuous Delivery / Deployment
Workflow `.github/workflows/deploy-staging.yml` menyediakan deployment staging manual melalui `workflow_dispatch`. Sebelum digunakan, siapkan GitHub Actions Secrets: `STAGING_FTP_SERVER`, `STAGING_FTP_USERNAME`, `STAGING_FTP_PASSWORD`, dan `STAGING_FTP_SERVER_DIR`. Uji dahulu pada server staging, bukan server produksi. Pastikan server menggunakan HTTPS, konfigurasi database terpisah, dan backup tersedia. Workflow ini bukan deployment otomatis ke produksi.

## Monitoring
Jalankan `php scripts/health_check.php` melalui terminal untuk memeriksa versi PHP, keberadaan file penting, dan koneksi database. Fitur `admin/audit_log.php` merupakan audit trail aktivitas pengguna, bukan pemantauan metrik server atau uptime.

## Backup
Jalankan `php scripts/backup_database.php` dari CLI setelah mengatur `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`. Pastikan `mysqldump` tersedia. Folder `backups` harus dilindungi dari akses web dan backup disimpan di lokasi aman lain secara berkala. Uji pemulihan backup secara berkala.

## Batasan
Smoke test saat ini memeriksa keberadaan file dan migrasi, bukan menguji seluruh alur bisnis. Sebelum menyatakan continuous testing lengkap, tambahkan unit/integration test untuk autentikasi, hak akses, input/perubahan nilai, pencarian, audit log, dan validasi database.
