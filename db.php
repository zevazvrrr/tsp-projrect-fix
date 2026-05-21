<?php
// ============================================================
//  db.php — Koneksi Database & Inisialisasi Tabel
// ============================================================
//  File ini bertugas:
//  1. Membuka/membuat file database SQLite (database.db)
//  2. Membuat tabel-tabel yang diperlukan jika belum ada
//  3. Mengisi data awal (seeding) saat pertama kali dijalankan
//  4. Menyediakan fungsi check_auth() untuk proteksi halaman
// ============================================================

// Cek apakah sesi PHP sudah berjalan, jika belum maka mulai sesi baru.
// Sesi digunakan untuk menyimpan data login pengguna (user_id, role, dsb).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tentukan path lengkap file database SQLite.
// __DIR__ berarti folder yang sama dengan file db.php ini.
$db_file = __DIR__ . '/database.db';

// Buat koneksi ke database SQLite menggunakan PDO (PHP Data Objects).
// PDO adalah cara standar PHP untuk berinteraksi dengan berbagai database.
$pdo = new PDO("sqlite:" . $db_file);

// Atur mode error agar jika ada query yang gagal, PHP melempar Exception
// sehingga kesalahan bisa ditangkap dan ditampilkan dengan jelas.
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Atur agar hasil query otomatis dikembalikan sebagai array asosiatif
// (contoh: $row['nama'] bukan $row[0])
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


// ============================================================
//  FUNGSI: init_db($pdo)
//  Membuat semua tabel dan mengisi data awal jika database kosong.
//  Dipanggil sekali setiap kali aplikasi dijalankan.
// ============================================================
function init_db($pdo) {

    // --- TABEL: users ---
    // Menyimpan akun login untuk admin dan kurir.
    // Kolom role bisa bernilai 'admin' atau 'kurir'.
    // kurir_id diisi hanya untuk akun dengan role 'kurir',
    // menghubungkan akun login ke data kurir di tabel kurir.
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- ID unik otomatis
        username TEXT UNIQUE NOT NULL,         -- Nama pengguna (harus unik)
        password TEXT NOT NULL,                -- Password (disimpan sebagai hash MD5)
        role TEXT DEFAULT "admin",             -- Peran: "admin" atau "kurir"
        kurir_id INTEGER,                      -- Referensi ke tabel kurir (khusus role kurir)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- Waktu akun dibuat
    )');

    // --- TABEL: kurir ---
    // Menyimpan data petugas kurir pengantaran.
    // Status bisa "FREE" (siap bertugas) atau "SEDANG BERTUGAS" (sedang mengantarkan).
    // Dibatasi maksimal 3 kurir sesuai kebutuhan sistem.
    $pdo->exec('CREATE TABLE IF NOT EXISTS kurir (
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- ID unik otomatis
        nama TEXT NOT NULL,                    -- Nama lengkap kurir
        plat TEXT NOT NULL,                    -- Nomor plat kendaraan kurir
        telepon TEXT,                          -- Nomor telepon kurir (opsional)
        status TEXT DEFAULT "FREE",            -- Status: "FREE" atau "SEDANG BERTUGAS"
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP  -- Waktu data dibuat
    )');

    // --- TABEL: undangan ---
    // Tabel utama yang menyimpan semua data surat undangan yang perlu diantarkan.
    // Setiap undangan memiliki kode unik (UND-001, UND-002, dst.),
    // data penerima, lokasi node di peta TSP, dan kurir yang bertugas mengantarkan.
    // Status pengiriman: PENDING (belum), COMPLETED (selesai), FAILED (gagal).
    $pdo->exec('CREATE TABLE IF NOT EXISTS undangan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- ID unik otomatis
        kode TEXT UNIQUE NOT NULL,             -- Kode undangan unik (format: UND-XXX)
        nama_penerima TEXT NOT NULL,           -- Nama lengkap penerima undangan
        alamat TEXT NOT NULL,                  -- Alamat rumah penerima
        node_name TEXT NOT NULL,               -- Lokasi penerima di peta (Rumah A, B, C, D, E)
        kurir_id INTEGER,                      -- Kurir yang ditugaskan mengantarkan
        status TEXT DEFAULT "PENDING",         -- Status: PENDING / COMPLETED / FAILED
        catatan TEXT,                          -- Catatan tambahan (misal: tidak ada di rumah)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,   -- Waktu undangan dibuat
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,   -- Waktu terakhir status diperbarui
        FOREIGN KEY (kurir_id) REFERENCES kurir(id)       -- Relasi ke tabel kurir
    )');

    // --- TABEL: tsp_routes ---
    // Menyimpan riwayat setiap perhitungan rute TSP yang dilakukan oleh admin.
    // selected_nodes: daftar rumah yang dipilih (JSON array)
    // optimal_route:  urutan kunjungan hasil algoritma Nearest Neighbor (JSON array)
    // route_details:  detail setiap langkah perjalanan beserta jaraknya (JSON array)
    $pdo->exec('CREATE TABLE IF NOT EXISTS tsp_routes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,  -- ID unik otomatis
        kurir_id INTEGER,                      -- Kurir yang diberikan rute ini
        start_node TEXT NOT NULL,              -- Titik awal perjalanan (biasanya "Kurir")
        selected_nodes TEXT NOT NULL,          -- Node/rumah yang dipilih (format JSON)
        optimal_route TEXT NOT NULL,           -- Rute optimal hasil TSP (format JSON)
        total_distance REAL NOT NULL,          -- Total jarak tempuh (dalam km)
        route_details TEXT,                    -- Detail langkah perjalanan (format JSON)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,   -- Waktu kalkulasi dilakukan
        FOREIGN KEY (kurir_id) REFERENCES kurir(id)       -- Relasi ke tabel kurir
    )');

    // ============================================================
    //  DATA AWAL (SEEDING)
    //  Hanya dijalankan SEKALI saat database pertama kali dibuat
    //  (ketika tabel users masih kosong = belum ada data apapun).
    // ============================================================
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {

        // Buat akun admin dengan password 'admin123' (di-hash dengan MD5)
        // MD5 mengubah teks menjadi kode 32 karakter agar password tidak tersimpan as-is
        $pw = md5('admin123');
        $pdo->exec("INSERT INTO users (username, password, role) VALUES ('admin', '$pw', 'admin')");

        // --- Data awal kurir (3 kurir tetap, tidak bisa ditambah via UI) ---
        $pdo->exec("INSERT INTO kurir (nama, plat, telepon, status) VALUES 
            ('Budi',      'AE 8478 JP', '081234567890', 'SEDANG BERTUGAS'),
            ('Kurniawan', 'AE 9214 UK', '081234567891', 'FREE'),
            ('Farhan',    'AE 5802 ZR', '081234567892', 'FREE')");

        // Buat akun login untuk masing-masing kurir
        // Password kurir: 'kurir123' (di-hash MD5)
        // kurir_id menghubungkan akun login ke data kurir (id 1=Budi, 2=Kurniawan, 3=Farhan)
        $pwKurir = md5('kurir123');
        $pdo->exec("INSERT INTO users (username, password, role, kurir_id) VALUES 
            ('budi',      '$pwKurir', 'kurir', 1),
            ('kurniawan', '$pwKurir', 'kurir', 2),
            ('farhan',    '$pwKurir', 'kurir', 3)");

        // --- Data awal undangan (5 contoh penerima) ---
        // node_name harus sesuai dengan nama node yang ada di $NODES di api.php
        $pdo->exec("INSERT INTO undangan (kode, nama_penerima, alamat, node_name, kurir_id, status, catatan) VALUES 
            ('UND-001', 'Dini',   'Jl. Melati No.5',   'Rumah A', 1, 'PENDING',   ''),
            ('UND-002', 'Rio',    'Jl. Mawar No.12',   'Rumah B', 1, 'COMPLETED', ''),
            ('UND-003', 'Sari',   'Jl. Anggrek No.3',  'Rumah C', 1, 'COMPLETED', ''),
            ('UND-004', 'Lina',   'Jl. Dahlia No.8',   'Rumah D', 1, 'FAILED',    'Tidak ada di rumah'),
            ('UND-005', 'Hendra', 'Jl. Kenanga No.1',  'Rumah E', 1, 'PENDING',   '')");
    }
}

// Panggil fungsi inisialisasi setiap kali file db.php di-include.
// Fungsi ini aman dipanggil berulang karena menggunakan "CREATE TABLE IF NOT EXISTS".
init_db($pdo);


// ============================================================
//  FUNGSI: check_auth()
//  Proteksi halaman — memastikan pengguna sudah login.
//  Jika belum login, pengguna akan diarahkan ke halaman login.
//  Cara pakai: panggil check_auth() di awal setiap file halaman.
// ============================================================
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        // Redirect ke halaman login jika sesi tidak ditemukan
        header("Location: login.php");
        exit; // Hentikan eksekusi agar kode di bawah tidak dijalankan
    }
}
?>
