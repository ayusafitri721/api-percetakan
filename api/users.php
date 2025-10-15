<?php
/**
 * API Users - CRUD Tabel users
 * URL: http://localhost/api-percetakan/api/users.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1); // ✅ FIXED TYPO

require_once '../config/database.php';
require_once '../helpers/Response.php';

// CORS - FIXED: Tambah header OPTIONS dan Content-Type
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control, Pragma');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Koneksi database
$database = new Database();
$database->connect();
$db = $database;

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
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua user
// ============================================
function getAll($db) {
    // Hanya tampilkan user yang aktif (status_aktif = 1)
    $sql = "SELECT id_user, nama, email, role, no_telepon, alamat, status_aktif, tanggal_daftar 
            FROM users 
            WHERE status_aktif = 1
            ORDER BY id_user DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_user' => $row['id_user'],
            'nama' => $row['nama'],
            'email' => $row['email'],
            'role' => $row['role'],
            'no_telepon' => $row['no_telepon'],
            'alamat' => $row['alamat'],
            'status_aktif' => $row['status_aktif'],
            'tanggal_daftar' => $row['tanggal_daftar']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'users' => $data
    ]);
}

// ============================================
// CREATE - Tambah user baru
// ============================================
function create($db) {
    // Ambil data tanpa escape dulu
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'pelanggan');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    
    // Validasi - CEK DULU SEBELUM ESCAPE
    if (empty($nama) || empty($email) || empty($password)) {
        Response::error('Nama, email, dan password wajib diisi', 400);
    }
    
    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Format email tidak valid', 400);
    }
    
    // SEKARANG BARU ESCAPE SETELAH VALIDASI
    $nama_escaped = $db->escape($nama);
    $email_escaped = $db->escape($email);
    $role_escaped = $db->escape($role);
    $no_telepon_escaped = $db->escape($no_telepon);
    $alamat_escaped = $db->escape($alamat);
    
    // Cek email sudah ada
    $checkSql = "SELECT id_user FROM users WHERE email = '$email_escaped'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows > 0) {
        Response::error('Email sudah terdaftar', 400);
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert
    $sql = "INSERT INTO users (nama, email, password_hash, role, no_telepon, alamat, status_aktif) 
            VALUES ('$nama_escaped', '$email_escaped', '$password_hash', '$role_escaped', '$no_telepon_escaped', '$alamat_escaped', 1)";
    
    if (!$db->query($sql)) {
        Response::error('Gagal menambahkan user ke database', 500);
    }
    
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_user' => $insertId,
        'nama' => $nama,
        'email' => $email
    ], 'User berhasil ditambahkan');
}

// ============================================
// DETAIL - Ambil detail user
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    $sql = "SELECT id_user, nama, email, role, no_telepon, alamat, foto_profil, status_aktif, tanggal_daftar 
            FROM users 
            WHERE id_user = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('User tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_user' => $row['id_user'],
        'nama' => $row['nama'],
        'email' => $row['email'],
        'role' => $row['role'],
        'no_telepon' => $row['no_telepon'],
        'alamat' => $row['alamat'],
        'foto_profil' => $row['foto_profil'],
        'status_aktif' => $row['status_aktif'],
        'tanggal_daftar' => $row['tanggal_daftar']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update user
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    // Cek user ada
    $checkSql = "SELECT id_user FROM users WHERE id_user = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('User tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['nama'])) {
        $nama = $db->escape(trim($_POST['nama']));
        $updates[] = "nama = '$nama'";
    }
    
    if (isset($_POST['email'])) {
        $email = $db->escape(trim($_POST['email']));
        // Cek email sudah dipakai user lain
        $checkEmail = "SELECT id_user FROM users WHERE email = '$email' AND id_user != '$id'";
        $resultEmail = $db->query($checkEmail);
        if ($resultEmail->num_rows > 0) {
            Response::error('Email sudah digunakan user lain', 400);
        }
        $updates[] = "email = '$email'";
    }
    
    if (isset($_POST['password']) && !empty($_POST['password'])) {
        $password = $_POST['password'];
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $updates[] = "password_hash = '$password_hash'";
    }
    
    if (isset($_POST['role'])) {
        $role = $db->escape(trim($_POST['role']));
        $updates[] = "role = '$role'";
    }
    
    if (isset($_POST['no_telepon'])) {
        $no_telepon = $db->escape(trim($_POST['no_telepon']));
        $updates[] = "no_telepon = '$no_telepon'";
    }
    
    if (isset($_POST['alamat'])) {
        $alamat = $db->escape(trim($_POST['alamat']));
        $updates[] = "alamat = '$alamat'";
    }
    
    if (isset($_POST['status_aktif'])) {
        $status = (int)$_POST['status_aktif'];
        $updates[] = "status_aktif = $status";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id_user = '$id'";
    
    if (!$db->query($sql)) {
        Response::error('Gagal update user', 500);
    }
    
    Response::success(['id_user' => $id], 'User berhasil diupdate');
}

// ============================================
// DELETE - Hapus user PERMANEN (hard delete)
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    // Cek user ada
    $checkSql = "SELECT id_user, nama FROM users WHERE id_user = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('User tidak ditemukan');
    }
    
    $user = $checkResult->fetch_assoc();
    
    // Log sebelum delete
    error_log("Hard deleting user: ID=$id, Name={$user['nama']}");
    
    // HARD DELETE - Hapus permanen dari database
    $sql = "DELETE FROM users WHERE id_user = '$id'";
    
    if (!$db->query($sql)) {
        error_log("Delete failed: " . $db->error);
        Response::error('Gagal menghapus user: ' . $db->error, 500);
    }
    
    // Verifikasi user benar-benar terhapus
    $verifySql = "SELECT COUNT(*) as count FROM users WHERE id_user = '$id'";
    $verifyResult = $db->query($verifySql);
    $verifyData = $verifyResult->fetch_assoc();
    
    if ($verifyData['count'] == 0) {
        error_log("User successfully deleted from database");
        Response::success(['id_user' => $id], 'User berhasil dihapus permanen dari database');
    } else {
        Response::error('User gagal dihapus', 500);
    }
}
?>