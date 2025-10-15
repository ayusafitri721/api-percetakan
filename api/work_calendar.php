<?php
/**
 * API Work Calendar - CRUD Tabel work_calendar
 * URL: http://localhost/api-percetakan/work_calendar.php
 */

error_reporting(0);
require_once '../config/database.php';
require_once '../helpers/Response.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Koneksi database
$database = new Database();
$db = $database->connect();

// Get operation
$op = $_GET['op'] ?? '';

switch ($op) {
    case 'create':
        create($db);
        break;
    case 'detail':
        detail($db);
        break;
    case 'update':
        update($db);
        break;
    case 'delete':
        delete($db);
        break;
    case 'by_month':
        byMonth($db);
        break;
    case 'by_range':
        byRange($db);
        break;
    case 'check_capacity':
        checkCapacity($db);
        break;
    case 'available_dates':
        availableDates($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua kalender
// ============================================
function getAll($db) {
    $sql = "SELECT * FROM work_calendar 
            ORDER BY tanggal DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_calendar' => $row['id_calendar'],
            'tanggal' => $row['tanggal'],
            'kapasitas_harian' => $row['kapasitas_harian'],
            'order_terjadwal' => $row['order_terjadwal'],
            'sisa_kapasitas' => $row['kapasitas_harian'] - $row['order_terjadwal'],
            'status_hari' => $row['status_hari'],
            'catatan' => $row['catatan']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'calendar' => $data
    ]);
}

// ============================================
// GET BY MONTH - Kalender per bulan
// ============================================
function byMonth($db) {
    $bulan = $db->escape($_GET['bulan'] ?? date('m'));
    $tahun = $db->escape($_GET['tahun'] ?? date('Y'));
    
    $sql = "SELECT * FROM work_calendar 
            WHERE MONTH(tanggal) = '$bulan' AND YEAR(tanggal) = '$tahun'
            ORDER BY tanggal ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'tanggal' => $row['tanggal'],
            'kapasitas_harian' => $row['kapasitas_harian'],
            'order_terjadwal' => $row['order_terjadwal'],
            'sisa_kapasitas' => $row['kapasitas_harian'] - $row['order_terjadwal'],
            'status_hari' => $row['status_hari'],
            'catatan' => $row['catatan']
        ];
    }
    
    Response::success([
        'bulan' => $bulan,
        'tahun' => $tahun,
        'total' => count($data),
        'calendar' => $data
    ]);
}

// ============================================
// GET BY RANGE - Kalender per range tanggal
// ============================================
function byRange($db) {
    $tanggal_mulai = $db->escape($_GET['tanggal_mulai'] ?? date('Y-m-d'));
    $tanggal_akhir = $db->escape($_GET['tanggal_akhir'] ?? date('Y-m-d', strtotime('+30 days')));
    
    $sql = "SELECT * FROM work_calendar 
            WHERE tanggal BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'
            ORDER BY tanggal ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'tanggal' => $row['tanggal'],
            'kapasitas_harian' => $row['kapasitas_harian'],
            'order_terjadwal' => $row['order_terjadwal'],
            'sisa_kapasitas' => $row['kapasitas_harian'] - $row['order_terjadwal'],
            'status_hari' => $row['status_hari'],
            'catatan' => $row['catatan']
        ];
    }
    
    Response::success([
        'periode' => [
            'tanggal_mulai' => $tanggal_mulai,
            'tanggal_akhir' => $tanggal_akhir
        ],
        'total' => count($data),
        'calendar' => $data
    ]);
}

// ============================================
// CHECK CAPACITY - Cek kapasitas tanggal tertentu
// ============================================
function checkCapacity($db) {
    $tanggal = $db->escape($_GET['tanggal'] ?? date('Y-m-d'));
    
    $sql = "SELECT * FROM work_calendar WHERE tanggal = '$tanggal'";
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        // Jika belum ada, buat default
        $insertSql = "INSERT INTO work_calendar (tanggal, kapasitas_harian, order_terjadwal, status_hari) 
                      VALUES ('$tanggal', 50, 0, 'buka')";
        $db->query($insertSql);
        
        Response::success([
            'tanggal' => $tanggal,
            'kapasitas_harian' => 50,
            'order_terjadwal' => 0,
            'sisa_kapasitas' => 50,
            'status_hari' => 'buka',
            'tersedia' => true
        ]);
    }
    
    $row = $result->fetch_assoc();
    $sisa = $row['kapasitas_harian'] - $row['order_terjadwal'];
    
    Response::success([
        'tanggal' => $row['tanggal'],
        'kapasitas_harian' => $row['kapasitas_harian'],
        'order_terjadwal' => $row['order_terjadwal'],
        'sisa_kapasitas' => $sisa,
        'status_hari' => $row['status_hari'],
        'tersedia' => ($sisa > 0 && $row['status_hari'] == 'buka')
    ]);
}

// ============================================
// AVAILABLE DATES - Tanggal yang tersedia
// ============================================
function availableDates($db) {
    $hari_ke_depan = $db->escape($_GET['hari'] ?? 30);
    
    $sql = "SELECT tanggal, kapasitas_harian, order_terjadwal, status_hari
            FROM work_calendar 
            WHERE tanggal BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $hari_ke_depan DAY)
            AND status_hari = 'buka'
            AND (kapasitas_harian - order_terjadwal) > 0
            ORDER BY tanggal ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'tanggal' => $row['tanggal'],
            'sisa_kapasitas' => $row['kapasitas_harian'] - $row['order_terjadwal']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'available_dates' => $data
    ]);
}

// ============================================
// CREATE - Tambah kalender
// ============================================
function create($db) {
    $tanggal = $db->escape($_POST['tanggal'] ?? '');
    $kapasitas_harian = $db->escape($_POST['kapasitas_harian'] ?? 50);
    $order_terjadwal = $db->escape($_POST['order_terjadwal'] ?? 0);
    $status_hari = $db->escape($_POST['status_hari'] ?? 'buka');
    $catatan = $db->escape($_POST['catatan'] ?? '');
    
    // Validasi
    if (empty($tanggal)) {
        Response::error('Tanggal wajib diisi', 400);
    }
    
    // Cek tanggal sudah ada
    $checkSql = "SELECT tanggal FROM work_calendar WHERE tanggal = '$tanggal'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows > 0) {
        Response::error('Tanggal sudah ada di kalender', 400);
    }
    
    // Insert
    $sql = "INSERT INTO work_calendar (tanggal, kapasitas_harian, order_terjadwal, status_hari, catatan) 
            VALUES ('$tanggal', '$kapasitas_harian', '$order_terjadwal', '$status_hari', '$catatan')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_calendar' => $insertId,
        'tanggal' => $tanggal
    ], 'Kalender berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail kalender
// ============================================
function detail($db) {
    $tanggal = $db->escape($_GET['tanggal'] ?? '');
    
    if (empty($tanggal)) {
        Response::error('Tanggal tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM work_calendar WHERE tanggal = '$tanggal'";
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Kalender tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_calendar' => $row['id_calendar'],
        'tanggal' => $row['tanggal'],
        'kapasitas_harian' => $row['kapasitas_harian'],
        'order_terjadwal' => $row['order_terjadwal'],
        'sisa_kapasitas' => $row['kapasitas_harian'] - $row['order_terjadwal'],
        'status_hari' => $row['status_hari'],
        'catatan' => $row['catatan']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update kalender
// ============================================
function update($db) {
    $tanggal = $db->escape($_GET['tanggal'] ?? '');
    
    if (empty($tanggal)) {
        Response::error('Tanggal tidak ditemukan', 400);
    }
    
    // Cek kalender ada
    $checkSql = "SELECT tanggal FROM work_calendar WHERE tanggal = '$tanggal'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Kalender tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['kapasitas_harian'])) {
        $kapasitas = $db->escape($_POST['kapasitas_harian']);
        $updates[] = "kapasitas_harian = '$kapasitas'";
    }
    
    if (isset($_POST['order_terjadwal'])) {
        $order = $db->escape($_POST['order_terjadwal']);
        $updates[] = "order_terjadwal = '$order'";
    }
    
    if (isset($_POST['status_hari'])) {
        $status = $db->escape($_POST['status_hari']);
        $updates[] = "status_hari = '$status'";
    }
    
    if (isset($_POST['catatan'])) {
        $catatan = $db->escape($_POST['catatan']);
        $updates[] = "catatan = '$catatan'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE work_calendar SET " . implode(', ', $updates) . " WHERE tanggal = '$tanggal'";
    $db->query($sql);
    
    Response::success(['tanggal' => $tanggal], 'Kalender berhasil diupdate');
}

// ============================================
// DELETE - Hapus kalender
// ============================================
function delete($db) {
    $tanggal = $db->escape($_GET['tanggal'] ?? '');
    
    if (empty($tanggal)) {
        Response::error('Tanggal tidak ditemukan', 400);
    }
    
    // Cek kalender ada
    $checkSql = "SELECT tanggal FROM work_calendar WHERE tanggal = '$tanggal'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Kalender tidak ditemukan');
    }
    
    // Delete
    $sql = "DELETE FROM work_calendar WHERE tanggal = '$tanggal'";
    $db->query($sql);
    
    Response::success(['tanggal' => $tanggal], 'Kalender berhasil dihapus');
}
?>