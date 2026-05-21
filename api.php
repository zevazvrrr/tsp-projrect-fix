<?php
// ============================================================
//  api.php — Pusat Logika Backend (API Endpoint)
// ============================================================
//  File ini adalah "otak" dari aplikasi. Semua permintaan data
//  dari halaman frontend (JavaScript fetch/AJAX) dikirim ke sini.
//
//  Cara kerja:
//  - Frontend mengirim parameter "action" (GET atau POST/JSON)
//  - api.php membaca nilai "action" lalu menjalankan kode yang sesuai
//  - Hasil selalu dikembalikan dalam format JSON
//
//  Daftar action yang tersedia:
//  [UNDANGAN]  get_undangan, create_undangan, update_undangan, delete_undangan
//  [KURIR]     get_kurir, create_kurir, update_kurir, delete_kurir
//  [TSP]       get_graph, tsp_calculate, tsp_history, delete_tsp_history
//  [LAPORAN]   get_laporan
//  [KURIR APP] get_kurir_undangan, update_undangan_status
//  [AUTH]      login
// ============================================================

require_once 'db.php'; // Sambungkan ke database

// Beritahu browser bahwa semua respons dari file ini adalah JSON
header('Content-Type: application/json');


// ============================================================
//  DATA GRAF TSP (HARDCODED)
// ============================================================
//  Node = titik lokasi di peta (rumah-rumah + titik kurir)
//  x,y  = koordinat tampilan di canvas (bukan koordinat GPS nyata)
//  Edge = jalur/jalan yang menghubungkan dua node
//  Distance Matrix = tabel jarak antar semua node
//
//  PENTING: Jika ingin menambah lokasi baru, harus update:
//  1. Array $NODES — tambah entri baru dengan koordinat x,y
//  2. Array $EDGES — tambah jalur yang terhubung ke node baru
//  3. Array $DISTANCE_MATRIX — tambah baris & kolom baru untuk node baru
// ============================================================

// Konstanta untuk jarak "tidak terhubung" (tidak ada jalur langsung)
const TSP_INF = 9999;

// Daftar semua node/titik lokasi di peta
$NODES = [
    'Kurir'   => ['x' => 80,  'y' => 260, 'label' => 'Petugas Kurir'], // Titik start kurir
    'Rumah A' => ['x' => 280, 'y' => 420, 'label' => 'Rumah A'],
    'Rumah B' => ['x' => 250, 'y' => 90,  'label' => 'Rumah B'],
    'Rumah C' => ['x' => 350, 'y' => 250, 'label' => 'Rumah C'],
    'Rumah D' => ['x' => 510, 'y' => 100, 'label' => 'Rumah D'],
    'Rumah E' => ['x' => 430, 'y' => 390, 'label' => 'Rumah E'],
];

// Daftar semua jalur/edge yang ada di peta beserta jaraknya (km)
// Jalur bersifat dua arah (undirected graph)
$EDGES = [
    ['from' => 'Kurir',   'to' => 'Rumah B', 'distance' => 2],
    ['from' => 'Kurir',   'to' => 'Rumah C', 'distance' => 4],
    ['from' => 'Kurir',   'to' => 'Rumah A', 'distance' => 5],
    ['from' => 'Rumah B', 'to' => 'Rumah C', 'distance' => 3],
    ['from' => 'Rumah B', 'to' => 'Rumah D', 'distance' => 6],
    ['from' => 'Rumah C', 'to' => 'Rumah A', 'distance' => 3],
    ['from' => 'Rumah C', 'to' => 'Rumah D', 'distance' => 5],
    ['from' => 'Rumah A', 'to' => 'Rumah E', 'distance' => 1],
    ['from' => 'Rumah D', 'to' => 'Rumah E', 'distance' => 4],
];

// Matriks jarak antar node (baris = dari mana, kolom = ke mana)
// TSP_INF berarti tidak ada jalur langsung antar dua node tersebut
$DISTANCE_MATRIX = [
    //            Kurir        Rumah A      Rumah B      Rumah C      Rumah D      Rumah E
    'Kurir'   => ['Kurir'=>0,  'Rumah A'=>5,  'Rumah B'=>2,  'Rumah C'=>4,  'Rumah D'=>TSP_INF, 'Rumah E'=>TSP_INF],
    'Rumah A' => ['Kurir'=>5,  'Rumah A'=>0,  'Rumah B'=>TSP_INF, 'Rumah C'=>3,  'Rumah D'=>TSP_INF, 'Rumah E'=>1],
    'Rumah B' => ['Kurir'=>2,  'Rumah A'=>TSP_INF, 'Rumah B'=>0,  'Rumah C'=>3,  'Rumah D'=>6,   'Rumah E'=>TSP_INF],
    'Rumah C' => ['Kurir'=>4,  'Rumah A'=>3,  'Rumah B'=>3,  'Rumah C'=>0,  'Rumah D'=>5,   'Rumah E'=>TSP_INF],
    'Rumah D' => ['Kurir'=>TSP_INF, 'Rumah A'=>TSP_INF, 'Rumah B'=>6,  'Rumah C'=>5,  'Rumah D'=>0,   'Rumah E'=>4],
    'Rumah E' => ['Kurir'=>TSP_INF, 'Rumah A'=>1,  'Rumah B'=>TSP_INF, 'Rumah C'=>TSP_INF, 'Rumah D'=>4,   'Rumah E'=>0],
];


// ============================================================
//  ALGORITMA TSP — NEAREST NEIGHBOR (Tetangga Terdekat)
// ============================================================
//  Algoritma ini mencari rute pengantaran yang relatif pendek
//  dengan strategi sederhana: dari posisi saat ini, selalu
//  pergi ke rumah terdekat yang belum dikunjungi.
//
//  Parameter:
//  - $start     : Nama node awal (biasanya 'Kurir')
//  - $nodes     : Array nama node yang harus dikunjungi
//  - $dist_matrix: Matriks jarak antar semua node
//
//  Return: [$route, $total_distance, $details]
// ============================================================
function nearest_neighbor_tsp($start, $nodes, $dist_matrix) {

    // Jika tidak ada node yang dipilih, langsung kembali ke start
    if (empty($nodes)) return [[$start, $start], 0, []];

    $unvisited = $nodes; // Daftar node yang belum dikunjungi
    $route     = [$start]; // Rute dimulai dari titik awal
    $current   = $start;   // Posisi kurir saat ini
    $total     = 0;        // Akumulasi total jarak
    $details   = [];       // Rincian setiap langkah perjalanan

    // Terus bergerak selama masih ada node yang belum dikunjungi
    while (!empty($unvisited)) {
        $best_node = null;
        $best_dist = TSP_INF;

        // Cari node terdekat dari posisi saat ini
        foreach ($unvisited as $node) {
            // Ambil jarak dari posisi saat ini ke node ini
            $d = isset($dist_matrix[$current][$node]) ? $dist_matrix[$current][$node] : TSP_INF;

            // Simpan jika lebih dekat dari yang sebelumnya
            if ($d < $best_dist) {
                $best_dist = $d;
                $best_node = $node;
            }
        }

        // Jika tidak ada node yang bisa dijangkau, hentikan
        if ($best_node === null || $best_dist >= TSP_INF) break;

        // Catat langkah ini ke dalam detail perjalanan
        $details[] = ['from' => $current, 'to' => $best_node, 'distance' => $best_dist];
        $total    += $best_dist;
        $route[]   = $best_node;

        // Hapus node ini dari daftar yang belum dikunjungi
        $key = array_search($best_node, $unvisited);
        if ($key !== false) unset($unvisited[$key]);

        // Pindah ke node terpilih
        $current = $best_node;
    }

    // Setelah semua dikunjungi, kembali ke titik awal (membentuk siklus)
    $ret = isset($dist_matrix[$current][$start]) ? $dist_matrix[$current][$start] : TSP_INF;
    if ($ret < TSP_INF) {
        $details[] = ['from' => $current, 'to' => $start, 'distance' => $ret];
        $total    += $ret;
    }
    $route[] = $start; // Tambahkan start di akhir untuk menutup siklus

    return [$route, round($total, 2), $details];
}


// ============================================================
//  ROUTING — Baca Parameter "action"
// ============================================================
//  Sistem membaca parameter "action" dari:
//  1. $_GET   — jika dikirim via URL (?action=xxx)
//  2. $_POST  — jika dikirim via form POST
//  3. JSON body — jika dikirim via fetch() dengan body JSON
// ============================================================

$action    = $_GET['action'] ?? $_POST['action'] ?? '';
$inputData = json_decode(file_get_contents('php://input'), true) ?: [];

// Jika action belum ditemukan di GET/POST, coba ambil dari body JSON
if (empty($action) && isset($inputData['action'])) {
    $action = $inputData['action'];
}


// ============================================================
//  SWITCH: Jalankan kode sesuai nilai "action"
// ============================================================
switch ($action) {

    // ----------------------------------------------------------
    //  [AUTH] LOGIN
    //  Memproses form login dari halaman login.php
    //  Mengecek username, password, dan role ke tabel users.
    //  Jika cocok, simpan data pengguna ke session.
    // ----------------------------------------------------------
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = md5($_POST['password'] ?? ''); // Hash password dengan MD5
        $role     = $_POST['role'] ?? 'admin';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ? AND role = ?");
        $stmt->execute([$username, $password, $role]);
        $user = $stmt->fetch();

        if ($user) {
            // Login berhasil — simpan info pengguna ke session
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // Jika kurir, simpan juga kurir_id untuk filter data per kurir
            if ($user['role'] == 'kurir') {
                $_SESSION['kurir_id'] = $user['kurir_id'];
            }
            echo json_encode(['success' => true, 'role' => $user['role']]);
        } else {
            // Login gagal
            echo json_encode(['success' => false, 'message' => 'Username atau password salah!']);
        }
        break;


    // ----------------------------------------------------------
    //  [UNDANGAN] GET — Ambil semua data undangan
    //  Mendukung pencarian (search) berdasarkan nama, kode, lokasi
    // ----------------------------------------------------------
    case 'get_undangan':
        if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }

        $q = $_GET['search'] ?? ''; // Kata kunci pencarian

        // JOIN dengan tabel kurir untuk mendapatkan nama kurir pengantarnya
        $stmt = $pdo->prepare("
            SELECT u.*, k.nama as kurir_nama FROM undangan u
            LEFT JOIN kurir k ON u.kurir_id=k.id
            WHERE u.nama_penerima LIKE ? OR u.kode LIKE ? OR u.node_name LIKE ?
            ORDER BY u.created_at DESC
        ");
        $stmt->execute(["%$q%", "%$q%", "%$q%"]);
        echo json_encode($stmt->fetchAll());
        break;


    // ----------------------------------------------------------
    //  [UNDANGAN] CREATE — Tambah undangan baru
    //  Kode undangan dibuat otomatis (UND-001, UND-002, dst.)
    //  dengan mengambil nomor MAX yang sudah ada agar tidak duplikat.
    // ----------------------------------------------------------
    case 'create_undangan':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

        // Ambil nomor tertinggi dari kode yang ada, lalu +1
        // Lebih aman dari COUNT(*) karena tidak terganggu jika ada data yang dihapus
        $maxNum = $pdo->query("SELECT MAX(CAST(SUBSTRING(kode, 5) AS UNSIGNED)) FROM undangan")->fetchColumn();
        $kode   = sprintf("UND-%03d", ($maxNum ?? 0) + 1);

        try {
            $stmt = $pdo->prepare("INSERT INTO undangan (kode,nama_penerima,alamat,node_name,kurir_id,status,catatan) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $kode,
                $inputData['nama_penerima'],
                $inputData['alamat'],
                $inputData['node_name'],
                $inputData['kurir_id'] ?? null,
                $inputData['status'] ?? 'PENDING',
                $inputData['catatan'] ?? ''
            ]);
            echo json_encode(['success' => true, 'kode' => $kode]);
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


    // ----------------------------------------------------------
    //  [UNDANGAN] UPDATE — Edit data undangan yang sudah ada
    // ----------------------------------------------------------
    case 'update_undangan':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

        try {
            $stmt = $pdo->prepare("UPDATE undangan SET nama_penerima=?,alamat=?,node_name=?,kurir_id=?,status=?,catatan=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([
                $inputData['nama_penerima'],
                $inputData['alamat'],
                $inputData['node_name'],
                $inputData['kurir_id'] ?? null,
                $inputData['status'],
                $inputData['catatan'] ?? '',
                $inputData['id']
            ]);
            echo json_encode(['success' => true]);
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


    // ----------------------------------------------------------
    //  [UNDANGAN] DELETE — Hapus undangan berdasarkan ID
    // ----------------------------------------------------------
    case 'delete_undangan':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

        try {
            $stmt = $pdo->prepare("DELETE FROM undangan WHERE id=?");
            $stmt->execute([$inputData['id']]);
            echo json_encode(['success' => true]);
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


    // ----------------------------------------------------------
    //  [KURIR] GET — Ambil semua data kurir
    // ----------------------------------------------------------
    case 'get_kurir':
        if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }
        echo json_encode($pdo->query("SELECT * FROM kurir ORDER BY created_at DESC")->fetchAll());
        break;


    // ----------------------------------------------------------
    //  [KURIR] CREATE — Tambah data kurir baru
    //  (Catatan: tombol tambah kurir dinonaktifkan di UI,
    //   maksimal 3 kurir sesuai kebutuhan sistem)
    // ----------------------------------------------------------
    case 'create_kurir':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }
        try {
            $stmt = $pdo->prepare("INSERT INTO kurir (nama,plat,telepon,status) VALUES (?,?,?,?)");
            $stmt->execute([
                $inputData['nama'],
                $inputData['plat'],
                $inputData['telepon'] ?? '',
                $inputData['status'] ?? 'FREE'
            ]);
            echo json_encode(['success' => true]);
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


    // ----------------------------------------------------------
    //  [KURIR] UPDATE — Edit data kurir (nama, plat, status, dsb.)
    // ----------------------------------------------------------
    case 'update_kurir':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }
        try {
            $stmt = $pdo->prepare("UPDATE kurir SET nama=?,plat=?,telepon=?,status=? WHERE id=?");
            $stmt->execute([
                $inputData['nama'],
                $inputData['plat'],
                $inputData['telepon'] ?? '',
                $inputData['status'],
                $inputData['id']
            ]);
            echo json_encode(['success' => true]);
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


    // ----------------------------------------------------------
    //  [KURIR] DELETE — Hapus data kurir berdasarkan ID
    // ----------------------------------------------------------
    case 'delete_kurir':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }
        try {
            $stmt = $pdo->prepare("DELETE FROM kurir WHERE id=?");
            $stmt->execute([$inputData['id']]);
            echo json_encode(['success' => true]);
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


    // ----------------------------------------------------------
    //  [TSP] GET GRAPH — Kirim data graf ke frontend
    //  Digunakan oleh canvas untuk menggambar peta node dan edge
    // ----------------------------------------------------------
    case 'get_graph':
        echo json_encode([
            'nodes'           => $NODES,
            'edges'           => $EDGES,
            'distance_matrix' => $DISTANCE_MATRIX
        ]);
        break;


    // ----------------------------------------------------------
    //  [TSP] CALCULATE — Hitung rute optimal dengan Nearest Neighbor
    //
    //  Alur:
    //  1. Terima daftar node yang dipilih admin + kurir yang dipilih
    //  2. Jalankan algoritma TSP Nearest Neighbor
    //  3. Simpan hasil ke tabel tsp_routes (riwayat)
    //  4. Reset status undangan di node-node terpilih ke PENDING
    //     dan assign kurir yang dipilih ke undangan tersebut
    //  5. Kembalikan rute optimal + data graf ke frontend
    // ----------------------------------------------------------
    case 'tsp_calculate':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

        $start    = $inputData['start_node']     ?? 'Kurir';
        $nodes    = $inputData['selected_nodes'] ?? [];
        $kurir_id = $inputData['kurir_id']       ?? null;

        // Jalankan algoritma TSP
        list($route, $total, $details) = nearest_neighbor_tsp($start, $nodes, $DISTANCE_MATRIX);

        try {
            // Simpan hasil kalkulasi ke riwayat
            $stmt = $pdo->prepare("INSERT INTO tsp_routes (kurir_id,start_node,selected_nodes,optimal_route,total_distance,route_details) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                $kurir_id,
                $start,
                json_encode($nodes),  // Simpan array sebagai string JSON
                json_encode($route),
                $total,
                json_encode($details)
            ]);

            // Setelah rute ditetapkan, otomatis reset status undangan
            // di lokasi yang dipilih menjadi PENDING dan assign ke kurir terpilih
            if (!empty($nodes) && $kurir_id) {
                // Buat placeholder (?,?,?) sesuai jumlah node
                $placeholders = implode(',', array_fill(0, count($nodes), '?'));
                $params       = array_merge(['PENDING', $kurir_id], $nodes);
                $updateStmt   = $pdo->prepare("UPDATE undangan SET status = ?, kurir_id = ?, updated_at = CURRENT_TIMESTAMP WHERE node_name IN ($placeholders)");
                $updateStmt->execute($params);
            }

            // Kembalikan semua data yang dibutuhkan frontend
            echo json_encode([
                'route'           => $route,
                'total_distance'  => $total,
                'route_details'   => $details,
                'nodes'           => $NODES,
                'edges'           => $EDGES,
                'distance_matrix' => $DISTANCE_MATRIX
            ]);
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


    // ----------------------------------------------------------
    //  [TSP] HISTORY — Ambil riwayat kalkulasi TSP
    //  JSON yang tersimpan di database di-decode kembali ke array
    // ----------------------------------------------------------
    case 'tsp_history':
        if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }

        $rows = $pdo->query("SELECT r.*, k.nama as kurir_nama FROM tsp_routes r LEFT JOIN kurir k ON r.kurir_id=k.id ORDER BY r.created_at DESC LIMIT 50")->fetchAll();

        $res = [];
        foreach ($rows as $r) {
            // Decode kolom JSON kembali ke array PHP
            $r['optimal_route']   = json_decode($r['optimal_route'],   true);
            $r['selected_nodes']  = json_decode($r['selected_nodes'],  true);
            $r['route_details']   = json_decode($r['route_details'],   true) ?: [];
            $res[] = $r;
        }
        echo json_encode($res);
        break;


    // ----------------------------------------------------------
    //  [TSP] DELETE HISTORY — Hapus satu riwayat kalkulasi TSP
    // ----------------------------------------------------------
    case 'delete_tsp_history':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }
        try {
            $stmt = $pdo->prepare("DELETE FROM tsp_routes WHERE id = ?");
            $stmt->execute([$inputData['id']]);
            echo json_encode(['success' => true]);
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


    // ----------------------------------------------------------
    //  [LAPORAN] GET — Ambil data untuk halaman laporan
    //  Mengembalikan: statistik umum, statistik per kurir,
    //  dan daftar lengkap semua pengantaran
    // ----------------------------------------------------------
    case 'get_laporan':
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'Unauthorized']); exit; }

        // Statistik keseluruhan
        $stats = [
            'total'     => $pdo->query("SELECT COUNT(*) FROM undangan")->fetchColumn(),
            'completed' => $pdo->query("SELECT COUNT(*) FROM undangan WHERE status='COMPLETED'")->fetchColumn(),
            'pending'   => $pdo->query("SELECT COUNT(*) FROM undangan WHERE status='PENDING'")->fetchColumn(),
            'failed'    => $pdo->query("SELECT COUNT(*) FROM undangan WHERE status='FAILED'")->fetchColumn(),
        ];

        // Statistik per kurir: total, selesai, pending, gagal
        $kurir_stats = $pdo->query("
            SELECT k.nama,
                COUNT(u.id) as total,
                SUM(CASE WHEN u.status='COMPLETED' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN u.status='PENDING'   THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN u.status='FAILED'    THEN 1 ELSE 0 END) as failed
            FROM kurir k LEFT JOIN undangan u ON k.id=u.kurir_id
            GROUP BY k.id, k.nama
        ")->fetchAll();

        // Pastikan nilai angka tidak NULL (bisa terjadi jika kurir belum punya undangan)
        foreach ($kurir_stats as &$ks) {
            $ks['total']     = (int) $ks['total'];
            $ks['completed'] = (int) $ks['completed'];
            $ks['pending']   = (int) $ks['pending'];
            $ks['failed']    = (int) $ks['failed'];
        }

        // Seluruh data pengantaran untuk tabel laporan
        $deliveries = $pdo->query("
            SELECT u.*, k.nama as kurir_nama FROM undangan u
            LEFT JOIN kurir k ON u.kurir_id=k.id
            ORDER BY u.updated_at DESC
        ")->fetchAll();

        echo json_encode([
            'stats'       => $stats,
            'kurir_stats' => $kurir_stats,
            'deliveries'  => $deliveries
        ]);
        break;


    // ----------------------------------------------------------
    //  [KURIR APP] GET UNDANGAN KURIR
    //  Hanya untuk role 'kurir' — mengambil undangan milik kurir
    //  yang sedang login berdasarkan kurir_id di session
    // ----------------------------------------------------------
    case 'get_kurir_undangan':
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kurir') {
            echo json_encode([]); exit;
        }
        $kurir_id = $_SESSION['kurir_id'];
        $stmt = $pdo->prepare("SELECT * FROM undangan WHERE kurir_id = ? ORDER BY created_at DESC");
        $stmt->execute([$kurir_id]);
        echo json_encode($stmt->fetchAll());
        break;


    // ----------------------------------------------------------
    //  [KURIR APP] UPDATE STATUS UNDANGAN
    //  Digunakan kurir untuk menandai undangan sebagai
    //  COMPLETED atau FAILED saat proses pengantaran.
    //  Hanya bisa mengubah undangan milik kurir sendiri.
    // ----------------------------------------------------------
    case 'update_undangan_status':
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kurir') {
            echo json_encode(['success' => false]); exit;
        }
        $id     = $_POST['id']     ?? 0;
        $status = $_POST['status'] ?? 'PENDING';

        // Pastikan hanya bisa update undangan yang memang milik kurir ini
        $stmt = $pdo->prepare("UPDATE undangan SET status=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND kurir_id=?");
        if ($stmt->execute([$status, $id, $_SESSION['kurir_id']])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;


    // ----------------------------------------------------------
    //  DEFAULT — Action tidak dikenali
    // ----------------------------------------------------------
    default:
        echo json_encode(['error' => 'Invalid action: ' . $action]);
        break;
}
