<?php
/**
 * API Users - CRUD Tabel users
 * URL: http://localhost/api-percetakan/users.php
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
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua user
// ============================================
function getAll($db) {
    $sql = "SELECT id_user, nama, email, role, no_telepon, alamat, status_aktif, tanggal_daftar 
            FROM users 
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
    $nama = $db->escape($_POST['nama'] ?? '');
    $email = $db->escape($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $db->escape($_POST['role'] ?? 'pelanggan');
    $no_telepon = $db->escape($_POST['no_telepon'] ?? '');
    $alamat = $db->escape($_POST['alamat'] ?? '');
    
    // Validasi
    if (empty($nama) || empty($email) || empty($password)) {
        Response::error('Nama, email, dan password wajib diisi', 400);
    }
    
    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Format email tidak valid', 400);
    }
    
    // Cek email sudah ada
    $checkSql = "SELECT id_user FROM users WHERE email = '$email'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows > 0) {
        Response::error('Email sudah terdaftar', 400);
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert
    $sql = "INSERT INTO users (nama, email, password_hash, role, no_telepon, alamat, status_aktif) 
            VALUES ('$nama', '$email', '$password_hash', '$role', '$no_telepon', '$alamat', 1)";
    
    $db->query($sql);
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
        $nama = $db->escape($_POST['nama']);
        $updates[] = "nama = '$nama'";
    }
    
    if (isset($_POST['email'])) {
        $email = $db->escape($_POST['email']);
        // Cek email sudah dipakai user lain
        $checkEmail = "SELECT id_user FROM users WHERE email = '$email' AND id_user != '$id'";
        $resultEmail = $db->query($checkEmail);
        if ($resultEmail->num_rows > 0) {
            Response::error('Email sudah digunakan user lain', 400);
        }
        $updates[] = "email = '$email'";
    }
    
    if (isset($_POST['password'])) {
        $password = $_POST['password'];
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $updates[] = "password_hash = '$password_hash'";
    }
    
    if (isset($_POST['role'])) {
        $role = $db->escape($_POST['role']);
        $updates[] = "role = '$role'";
    }
    
    if (isset($_POST['no_telepon'])) {
        $no_telepon = $db->escape($_POST['no_telepon']);
        $updates[] = "no_telepon = '$no_telepon'";
    }
    
    if (isset($_POST['alamat'])) {
        $alamat = $db->escape($_POST['alamat']);
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
    $db->query($sql);
    
    Response::success(['id_user' => $id], 'User berhasil diupdate');
}

// ============================================
// DELETE - Hapus user (soft delete)
// ============================================
function delete($db) {
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
    
    // Soft delete (ubah status_aktif jadi 0)
    $sql = "UPDATE users SET status_aktif = 0 WHERE id_user = '$id'";
    $db->query($sql);
    
    Response::success(['id_user' => $id], 'User berhasil dihapus');
}
?>