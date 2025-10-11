<?php
/**
 * API Notifications - CRUD Tabel notifications
 * URL: http://localhost/api-percetakan/notifications.php
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
    case 'by_user':
        byUser($db);
        break;
    case 'unread':
        unreadNotifications($db);
        break;
    case 'mark_read':
        markAsRead($db);
        break;
    case 'mark_all_read':
        markAllAsRead($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua notifikasi
// ============================================
function getAll($db) {
    $sql = "SELECT n.*, u.nama as nama_user, u.email
            FROM notifications n
            LEFT JOIN users u ON n.id_user = u.id_user
            ORDER BY n.tanggal_kirim DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_notif' => $row['id_notif'],
            'id_user' => $row['id_user'],
            'nama_user' => $row['nama_user'],
            'email' => $row['email'],
            'judul' => $row['judul'],
            'pesan' => $row['pesan'],
            'tipe' => $row['tipe'],
            'link_terkait' => $row['link_terkait'],
            'status_baca' => $row['status_baca'],
            'tanggal_kirim' => $row['tanggal_kirim']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'notifications' => $data
    ]);
}

// ============================================
// BY USER - Notifikasi per user
// ============================================
function byUser($db) {
    $id_user = $db->escape($_GET['id_user'] ?? '');
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM notifications 
            WHERE id_user = '$id_user'
            ORDER BY tanggal_kirim DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_notif' => $row['id_notif'],
            'judul' => $row['judul'],
            'pesan' => $row['pesan'],
            'tipe' => $row['tipe'],
            'link_terkait' => $row['link_terkait'],
            'status_baca' => $row['status_baca'],
            'tanggal_kirim' => $row['tanggal_kirim']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'notifications' => $data
    ]);
}

// ============================================
// UNREAD - Notifikasi belum dibaca per user
// ============================================
function unreadNotifications($db) {
    $id_user = $db->escape($_GET['id_user'] ?? '');
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM notifications 
            WHERE id_user = '$id_user' AND status_baca = 0
            ORDER BY tanggal_kirim DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_notif' => $row['id_notif'],
            'judul' => $row['judul'],
            'pesan' => $row['pesan'],
            'tipe' => $row['tipe'],
            'link_terkait' => $row['link_terkait'],
            'tanggal_kirim' => $row['tanggal_kirim']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'notifications' => $data
    ]);
}

// ============================================
// MARK READ - Tandai notifikasi sudah dibaca
// ============================================
function markAsRead($db) {
    $id = $db->escape($_POST['id_notif'] ?? '');
    
    if (empty($id)) {
        Response::error('ID notifikasi tidak ditemukan', 400);
    }
    
    // Cek notifikasi ada
    $checkSql = "SELECT id_notif FROM notifications WHERE id_notif = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Notifikasi tidak ditemukan');
    }
    
    // Update status baca
    $sql = "UPDATE notifications SET status_baca = 1 WHERE id_notif = '$id'";
    $db->query($sql);
    
    Response::success(['id_notif' => $id], 'Notifikasi ditandai sudah dibaca');
}

// ============================================
// MARK ALL READ - Tandai semua notifikasi dibaca
// ============================================
function markAllAsRead($db) {
    $id_user = $db->escape($_POST['id_user'] ?? '');
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    // Update semua notifikasi user
    $sql = "UPDATE notifications SET status_baca = 1 WHERE id_user = '$id_user' AND status_baca = 0";
    $db->query($sql);
    
    Response::success(['id_user' => $id_user], 'Semua notifikasi ditandai sudah dibaca');
}

// ============================================
// CREATE - Kirim notifikasi
// ============================================
function create($db) {
    $id_user = $db->escape($_POST['id_user'] ?? '');
    $judul = $db->escape($_POST['judul'] ?? '');
    $pesan = $db->escape($_POST['pesan'] ?? '');
    $tipe = $db->escape($_POST['tipe'] ?? 'order');
    $link_terkait = $db->escape($_POST['link_terkait'] ?? '');
    
    // Validasi
    if (empty($id_user) || empty($judul) || empty($pesan)) {
        Response::error('ID user, judul, dan pesan wajib diisi', 400);
    }
    
    // Cek user ada
    $checkUser = "SELECT id_user FROM users WHERE id_user = '$id_user'";
    $resultUser = $db->query($checkUser);
    if ($resultUser->num_rows === 0) {
        Response::error('User tidak ditemukan', 400);
    }
    
    // Insert
    $sql = "INSERT INTO notifications (id_user, judul, pesan, tipe, link_terkait, status_baca) 
            VALUES ('$id_user', '$judul', '$pesan', '$tipe', '$link_terkait', 0)";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_notif' => $insertId,
        'id_user' => $id_user
    ], 'Notifikasi berhasil dikirim');
}

// ============================================
// DETAIL - Detail notifikasi
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID notifikasi tidak ditemukan', 400);
    }
    
    $sql = "SELECT n.*, u.nama as nama_user, u.email
            FROM notifications n
            LEFT JOIN users u ON n.id_user = u.id_user
            WHERE n.id_notif = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Notifikasi tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_notif' => $row['id_notif'],
        'id_user' => $row['id_user'],
        'nama_user' => $row['nama_user'],
        'email' => $row['email'],
        'judul' => $row['judul'],
        'pesan' => $row['pesan'],
        'tipe' => $row['tipe'],
        'link_terkait' => $row['link_terkait'],
        'status_baca' => $row['status_baca'],
        'tanggal_kirim' => $row['tanggal_kirim']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update notifikasi
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID notifikasi tidak ditemukan', 400);
    }
    
    // Cek notifikasi ada
    $checkSql = "SELECT id_notif FROM notifications WHERE id_notif = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Notifikasi tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['judul'])) {
        $judul = $db->escape($_POST['judul']);
        $updates[] = "judul = '$judul'";
    }
    
    if (isset($_POST['pesan'])) {
        $pesan = $db->escape($_POST['pesan']);
        $updates[] = "pesan = '$pesan'";
    }
    
    if (isset($_POST['link_terkait'])) {
        $link = $db->escape($_POST['link_terkait']);
        $updates[] = "link_terkait = '$link'";
    }
    
    if (isset($_POST['status_baca'])) {
        $status = (int)$_POST['status_baca'];
        $updates[] = "status_baca = $status";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE notifications SET " . implode(', ', $updates) . " WHERE id_notif = '$id'";
    $db->query($sql);
    
    Response::success(['id_notif' => $id], 'Notifikasi berhasil diupdate');
}

// ============================================
// DELETE - Hapus notifikasi
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID notifikasi tidak ditemukan', 400);
    }
    
    // Cek notifikasi ada
    $checkSql = "SELECT id_notif FROM notifications WHERE id_notif = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Notifikasi tidak ditemukan');
    }
    
    // Hard delete
    $sql = "DELETE FROM notifications WHERE id_notif = '$id'";
    $db->query($sql);
    
    Response::success(['id_notif' => $id], 'Notifikasi berhasil dihapus');
}
?>