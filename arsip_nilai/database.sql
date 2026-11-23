-- Tabel users (guru dan admin)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'guru') NOT NULL,
    mata_pelajaran_id INT NULL, -- hanya untuk guru
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel mata_pelajaran
CREATE TABLE mata_pelajaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_mapel VARCHAR(50) NOT NULL,
    kode_mapel VARCHAR(10) UNIQUE NOT NULL
);

-- Tabel siswa
CREATE TABLE siswa (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nis VARCHAR(20) UNIQUE NOT NULL,
    nama_siswa VARCHAR(100) NOT NULL,
    kelas ENUM('VII', 'VIII', 'IX') NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel nilai
CREATE TABLE nilai (
    id INT PRIMARY KEY AUTO_INCREMENT,
    siswa_id INT NOT NULL,
    mata_pelajaran_id INT NOT NULL,
    kelas VARCHAR(10) NOT NULL,
    semester ENUM('1', '2') NOT NULL,
    tahun_ajaran VARCHAR(20) NOT NULL,
    nilai_harian DECIMAL(5,2),
    nilai_uts DECIMAL(5,2),
    nilai_uas DECIMAL(5,2),
    nilai_akhir DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    UNIQUE KEY unique_nilai (siswa_id, mata_pelajaran_id, semester, tahun_ajaran)
);

-- Insert data mata pelajaran (11 mapel)
INSERT INTO mata_pelajaran (nama_mapel, kode_mapel) VALUES
('Matematika', 'MTK'),
('Bahasa Indonesia', 'BIN'),
('Bahasa Inggris', 'BIG'),
('IPA', 'IPA'),
('IPS', 'IPS'),
('Pendidikan Agama', 'PAI'),
('PJOK', 'PJOK'),
('Seni Budaya', 'SB'),
('Prakarya', 'PRK'),
('Informatika', 'INF'),
('Bahasa Daerah', 'BDA');

-- Insert admin default
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$YourHashedPasswordHere', 'Administrator', 'admin');

-- Insert contoh guru (password: guru123)
INSERT INTO users (username, password, full_name, role, mata_pelajaran_id) VALUES
('guru_mtk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso', 'guru', 1),
('guru_bindo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Siti Aminah', 'guru', 2);