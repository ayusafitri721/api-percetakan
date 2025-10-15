<?php
/**
 * API Activity Logs - CRUD Tabel activity_logs
 * URL: http://localhost/api-percetakan/activity_logs.php
 */

error_reporting(0);
require_once '../config/database.php';
require_once '../helpers/Response.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
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
    case 'delete':
        delete($db);
        break;
    case 'by_user':
        byUser($db);
        break;
    case 'by_table':
        byTable($db);
        break;
    case 'by_date':
        byDate($db);
        break;
    case 'clear_old':
        clearOld($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua log (dengan pagination)
// ============================================
function getAll($db) {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $offset = ($page - 1) * $limit;
    
    // Count total
    $countSql = "SELECT COUNT(*) as total FROM activity_logs";
    $countResult = $db->query($countSql);
    $totalData = $countResult->fetch_assoc()['total'];
    
    $sql = "SELECT al.*, u.nama as nama_user 
            FROM activity_logs al
            LEFT JOIN users u ON al.id_user = u.id_user
            ORDER BY al.tanggal_aksi DESC
            LIMIT $limit OFFSET $offset";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_log' => $row['id_log'],
            'id_user' => $row['id_user'],
            'nama_user' => $row['nama_user'],
            'aksi' => $row['aksi'],
            'tabel_terkait' => $row['tabel_terkait'],
            'id_terkait' => $row['id_terkait'],
            'deskripsi' => $row['deskripsi'],
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'tanggal_aksi' => $row['tanggal_aksi']
        ];
    }
    
    Response::success([
        'total' => $totalData,
        'page' => (int)$page,
        'limit' => (int)$limit,
        'total_pages' => ceil($totalData / $limit),
        'logs' => $data
    ]);
}

// ============================================
// GET BY USER - Log per user
// ============================================
function byUser($db) {
    $id_user = $db->escape($_GET['id_user'] ?? '');
    $limit = $_GET['limit'] ?? 100;
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM activity_logs 
            WHERE id_user = '$id_user'
            ORDER BY tanggal_aksi DESC
            LIMIT $limit";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_log' => $row['id_log'],
            'aksi' => $row['aksi'],
            'tabel_terkait' => $row['tabel_terkait'],
            'id_terkait' => $row['id_terkait'],
            'deskripsi' => $row['deskripsi'],
            'tanggal_aksi' => $row['tanggal_aksi']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'logs' => $data
    ]);
}

// ============================================
// GET BY TABLE - Log per tabel
// ============================================
function byTable($db) {
    $tabel = $db->escape($_GET['tabel'] ?? '');
    $limit = $_GET['limit'] ?? 100;
    
    if (empty($tabel)) {
        Response::error('Nama tabel tidak ditemukan', 400);
    }
    
    $sql = "SELECT al.*, u.nama as nama_user 
            FROM activity_logs al
            LEFT JOIN users u ON al.id_user = u.id_user
            WHERE al.tabel_terkait = '$tabel'
            ORDER BY al.tanggal_aksi DESC
            LIMIT $limit";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_log' => $row['id_log'],
            'nama_user' => $row['nama_user'],
            'aksi' => $row['aksi'],
            'id_terkait' => $row['id_terkait'],
            'deskripsi' => $row['deskripsi'],
            'tanggal_aksi' => $row['tanggal_aksi']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'logs' => $data
    ]);
}

// ============================================
// GET BY DATE - Log per tanggal
// ============================================
function byDate($db) {
    $tanggal_mulai = $db->escape($_GET['tanggal_mulai'] ?? date('Y-m-d'));
    $tanggal_akhir = $db->escape($_GET['tanggal_akhir'] ?? date('Y-m-d'));
    
    $sql = "SELECT al.*, u.nama as nama_user 
            FROM activity_logs al
            LEFT JOIN users u ON al.id_user = u.id_user
            WHERE DATE(al.tanggal_aksi) BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'
            ORDER BY al.tanggal_aksi DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_log' => $row['id_log'],
            'nama_user' => $row['nama_user'],
            'aksi' => $row['aksi'],
            'tabel_terkait' => $row['tabel_terkait'],
            'deskripsi' => $row['deskripsi'],
            'tanggal_aksi' => $row['tanggal_aksi']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'periode' => [
            'tanggal_mulai' => $tanggal_mulai,
            'tanggal_akhir' => $tanggal_akhir
        ],
        'logs' => $data
    ]);
}

// ============================================
// CREATE - Tambah log aktivitas
// ============================================
function create($db) {
    $id_user = $db->escape($_POST['id_user'] ?? '');
    $aksi = $db->escape($_POST['aksi'] ?? '');
    $tabel_terkait = $db->escape($_POST['tabel_terkait'] ?? '');
    $id_terkait = $db->escape($_POST['id_terkait'] ?? 'NULL');
    $deskripsi = $db->escape($_POST['deskripsi'] ?? '');
    
    // Get IP dan User Agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Validasi
    if (empty($aksi)) {
        Response::error('Aksi wajib diisi', 400);
    }
    
    // Insert
    $idTerkaitValue = ($id_terkait === 'NULL') ? 'NULL' : "'$id_terkait'";
    $idUserValue = empty($id_user) ? 'NULL' : "'$id_user'";
    
    $sql = "INSERT INTO activity_logs (id_user, aksi, tabel_terkait, id_terkait, deskripsi, ip_address, user_agent) 
            VALUES ($idUserValue, '$aksi', '$tabel_terkait', $idTerkaitValue, '$deskripsi', '$ip_address', '$user_agent')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_log' => $insertId,
        'aksi' => $aksi
    ], 'Log aktivitas berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail log
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID log tidak ditemukan', 400);
    }
    
    $sql = "SELECT al.*, u.nama as nama_user, u.email 
            FROM activity_logs al
            LEFT JOIN users u ON al.id_user = u.id_user
            WHERE al.id_log = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Log tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_log' => $row['id_log'],
        'id_user' => $row['id_user'],
        'nama_user' => $row['nama_user'],
        'email' => $row['email'],
        'aksi' => $row['aksi'],
        'tabel_terkait' => $row['tabel_terkait'],
        'id_terkait' => $row['id_terkait'],
        'deskripsi' => $row['deskripsi'],
        'ip_address' => $row['ip_address'],
        'user_agent' => $row['user_agent'],
        'tanggal_aksi' => $row['tanggal_aksi']
    ];
    
    Response::success($data);
}

// ============================================
// DELETE - Hapus log (single)
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID log tidak ditemukan', 400);
    }
    
    // Cek log ada
    $checkSql = "SELECT id_log FROM activity_logs WHERE id_log = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Log tidak ditemukan');
    }
    
    // Delete
    $sql = "DELETE FROM activity_logs WHERE id_log = '$id'";
    $db->query($sql);
    
    Response::success(['id_log' => $id], 'Log berhasil dihapus');
}

// ============================================
// CLEAR OLD - Hapus log lama (lebih dari X hari)
// ============================================
function clearOld($db) {
    $hari = $db->escape($_GET['hari'] ?? 90); // Default 90 hari
    
    $sql = "DELETE FROM activity_logs 
            WHERE tanggal_aksi < DATE_SUB(NOW(), INTERVAL $hari DAY)";
    
    $db->query($sql);
    $affected = $db->affectedRows();
    
    Response::success([
        'deleted_count' => $affected,
        'hari' => $hari
    ], "Berhasil menghapus log lebih dari $hari hari");
}
?>