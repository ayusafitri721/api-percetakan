<?php
/**
 * API Authentication - Login & Register
 * URL: http://localhost/api-percetakan/api/auth.php
 */

// Enable error reporting untuk debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db_name = "percetakan_db";

$conn = new mysqli($host, $user, $pass, $db_name);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

$conn->set_charset("utf8mb4");

// Get operation
$op = $_GET['op'] ?? '';

switch ($op) {
    case 'register':
        register($conn);
        break;
    case 'login':
        login($conn);
        break;
    case 'profile':
        profile($conn);
        break;
    case 'logout':
        logout();
        break;
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Operation tidak ditemukan'
        ]);
        break;
}

$conn->close();

// ============================================
// REGISTER - Daftar akun baru
// ============================================
function register($conn) {
    // Ambil data dari POST
    $input = $_POST;
    
    // Jika kosong, coba dari JSON
    if (empty($input)) {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true) ?? [];
    }
    
    $nama = $conn->real_escape_string($input['nama'] ?? '');
    $email = $conn->real_escape_string($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $no_telepon = $conn->real_escape_string($input['no_telepon'] ?? '');
    $alamat = $conn->real_escape_string($input['alamat'] ?? '');
    
    // Validasi
    if (empty($nama) || empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Nama, email, dan password wajib diisi'
        ]);
        exit;
    }
    
    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Format email tidak valid'
        ]);
        exit;
    }
    
    // Validasi password minimal 6 karakter
    if (strlen($password) < 6) {
        echo json_encode([
            'success' => false,
            'message' => 'Password minimal 6 karakter'
        ]);
        exit;
    }
    
    // Cek email sudah terdaftar
    $checkSql = "SELECT id_user FROM users WHERE email = '$email'";
    $checkResult = $conn->query($checkSql);
    if ($checkResult->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email sudah terdaftar'
        ]);
        exit;
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user baru (default role: pelanggan)
    $sql = "INSERT INTO users (nama, email, password_hash, role, no_telepon, alamat, status_aktif) 
            VALUES ('$nama', '$email', '$password_hash', 'pelanggan', '$no_telepon', '$alamat', 1)";
    
    if ($conn->query($sql)) {
        $userId = $conn->insert_id;
        
        // Generate token
        $token = base64_encode($userId . ':' . $email . ':pelanggan:' . time());
        
        echo json_encode([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'user' => [
                    'id_user' => $userId,
                    'nama' => $nama,
                    'email' => $email,
                    'role' => 'pelanggan'
                ],
                'token' => $token
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Registrasi gagal: ' . $conn->error
        ]);
    }
    exit;
}

// ============================================
// LOGIN - Login user
// ============================================
function login($conn) {
    // Ambil data dari POST
    $input = $_POST;
    
    // Jika kosong, coba dari JSON
    if (empty($input)) {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true) ?? [];
    }
    
    $email = $conn->real_escape_string($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    // Debug log
    error_log("Login attempt - Email: $email");
    
    // Validasi
    if (empty($email) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email dan password wajib diisi'
        ]);
        exit;
    }
    
    // Cek user di database
    $sql = "SELECT id_user, nama, email, password_hash, role, no_telepon, alamat, status_aktif 
            FROM users 
            WHERE email = '$email'";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email atau password salah'
        ]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Cek status aktif
    if ($user['status_aktif'] == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Akun Anda telah dinonaktifkan'
        ]);
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Email atau password salah'
        ]);
        exit;
    }
    
    // Generate token
    $token = base64_encode($user['id_user'] . ':' . $user['email'] . ':' . $user['role'] . ':' . time());
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil',
        'data' => [
            'user' => [
                'id_user' => $user['id_user'],
                'nama' => $user['nama'],
                'email' => $user['email'],
                'role' => $user['role'],
                'no_telepon' => $user['no_telepon'],
                'alamat' => $user['alamat']
            ],
            'token' => $token
        ]
    ]);
    exit;
}

// ============================================
// PROFILE - Get user profile (butuh token)
// ============================================
function profile($conn) {
    // Get token from header
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    
    if (empty($token)) {
        echo json_encode([
            'success' => false,
            'message' => 'Token tidak ditemukan'
        ]);
        exit;
    }
    
    // Decode token (simple validation)
    $decoded = base64_decode($token);
    $parts = explode(':', $decoded);
    
    if (count($parts) < 3) {
        echo json_encode([
            'success' => false,
            'message' => 'Token tidak valid'
        ]);
        exit;
    }
    
    $userId = $parts[0];
    
    // Get user detail
    $sql = "SELECT id_user, nama, email, role, no_telepon, alamat, tanggal_daftar 
            FROM users 
            WHERE id_user = '$userId'";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'User tidak ditemukan'
        ]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'message' => 'Data user ditemukan',
        'data' => [
            'user' => $user
        ]
    ]);
    exit;
}

// ============================================
// LOGOUT - Logout user
// ============================================
function logout() {
    echo json_encode([
        'success' => true,
        'message' => 'Logout berhasil'
    ]);
    exit;
}
?>