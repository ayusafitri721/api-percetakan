<?php
/**
 * API Authentication - Login & Register
 * URL: http://localhost/api-percetakan/auth.php
 */

error_reporting(0);
require_once '../config/database.php';
require_once '../helpers/Response.php';
require_once '../helpers/Auth.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Koneksi database
$database = new Database();
$db = $database->connect();

// Get operation
$op = $_GET['op'] ?? '';

switch ($op) {
    case 'register':
        register($db);
        break;
    case 'login':
        login($db);
        break;
    case 'profile':
        profile($db);
        break;
    case 'logout':
        logout();
        break;
    default:
        Response::error('Operation tidak ditemukan', 404);
        break;
}

// ============================================
// REGISTER - Daftar akun baru
// ============================================
function register($db) {
    // Ambil data dari POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $nama = $db->escape($input['nama'] ?? '');
    $email = $db->escape($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $no_telepon = $db->escape($input['no_telepon'] ?? '');
    $alamat = $db->escape($input['alamat'] ?? '');
    
    // Validasi
    if (empty($nama) || empty($email) || empty($password)) {
        Response::error('Nama, email, dan password wajib diisi', 400);
    }
    
    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Format email tidak valid', 400);
    }
    
    // Validasi password minimal 6 karakter
    if (strlen($password) < 6) {
        Response::error('Password minimal 6 karakter', 400);
    }
    
    // Cek email sudah terdaftar
    $checkSql = "SELECT id_user FROM users WHERE email = '$email'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows > 0) {
        Response::error('Email sudah terdaftar', 400);
    }
    
    // Hash password
    $password_hash = Auth::hashPassword($password);
    
    // Insert user baru (default role: pelanggan)
    $sql = "INSERT INTO users (nama, email, password_hash, role, no_telepon, alamat, status_aktif) 
            VALUES ('$nama', '$email', '$password_hash', 'pelanggan', '$no_telepon', '$alamat', 1)";
    
    $db->query($sql);
    $userId = $db->lastInsertId();
    
    // Generate JWT token
    $token = Auth::generateToken($userId, $email, 'pelanggan');
    
    Response::created([
        'user' => [
            'id_user' => $userId,
            'nama' => $nama,
            'email' => $email,
            'role' => 'pelanggan'
        ],
        'token' => $token
    ], 'Registrasi berhasil');
}

// ============================================
// LOGIN - Login user
// ============================================
function login($db) {
    // Ambil data dari POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $email = $db->escape($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validasi
    if (empty($email) || empty($password)) {
        Response::error('Email dan password wajib diisi', 400);
    }
    
    // Cek user di database
    $sql = "SELECT id_user, nama, email, password_hash, role, no_telepon, alamat, status_aktif 
            FROM users 
            WHERE email = '$email'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::error('Email atau password salah', 401);
    }
    
    $user = $result->fetch_assoc();
    
    // Cek status aktif
    if ($user['status_aktif'] == 0) {
        Response::error('Akun Anda telah dinonaktifkan', 403);
    }
    
    // Verify password
    if (!Auth::verifyPassword($password, $user['password_hash'])) {
        Response::error('Email atau password salah', 401);
    }
    
    // Generate JWT token
    $token = Auth::generateToken($user['id_user'], $user['email'], $user['role']);
    
    Response::success([
        'user' => [
            'id_user' => $user['id_user'],
            'nama' => $user['nama'],
            'email' => $user['email'],
            'role' => $user['role'],
            'no_telepon' => $user['no_telepon'],
            'alamat' => $user['alamat']
        ],
        'token' => $token
    ], 'Login berhasil');
}

// ============================================
// PROFILE - Get user profile (butuh token)
// ============================================
function profile($db) {
    // Validasi token
    $userData = Auth::validateToken();
    
    $userId = $userData['id'];
    
    // Get user detail
    $sql = "SELECT id_user, nama, email, role, no_telepon, alamat, foto_profil, tanggal_daftar 
            FROM users 
            WHERE id_user = '$userId'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('User tidak ditemukan');
    }
    
    $user = $result->fetch_assoc();
    
    Response::success([
        'user' => [
            'id_user' => $user['id_user'],
            'nama' => $user['nama'],
            'email' => $user['email'],
            'role' => $user['role'],
            'no_telepon' => $user['no_telepon'],
            'alamat' => $user['alamat'],
            'foto_profil' => $user['foto_profil'],
            'tanggal_daftar' => $user['tanggal_daftar']
        ]
    ]);
}

// ============================================
// LOGOUT - Logout user
// ============================================
function logout() {
    // Karena JWT stateless, logout cukup hapus token di client side
    // Di sini cuma return success response
    Response::success(null, 'Logout berhasil');
}
?>