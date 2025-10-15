<?php
/**
 * API Materials - CRUD Tabel materials (FIXED VERSION WITH BETTER CORS)
 * URL: http://localhost/api-percetakan/api/materials.php
 */
// CORS Headers - Lebih permisif untuk development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control, Accept, X-Requested-With, Pragma');
header('Access-Control-Max-Age: 3600');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Response Helper Function
class Response {
    public static function success($data = [], $message = 'Success') {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    public static function created($data = [], $message = 'Created') {
        http_response_code(201);
        echo json_encode([
            'status' => 'created',
            'message' => $message,
            'data' => $data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    public static function error($message = 'Error', $code = 400) {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    public static function notFound($message = 'Not Found') {
        self::error($message, 404);
    }
}

// Database Connection
try {
    $host = 'localhost';
    $dbname = 'percetakan_db';
    $username = 'root';
    $password = '';
    
    $db = new mysqli($host, $username, $password, $dbname);
    
    if ($db->connect_error) {
        Response::error('Database connection failed: ' . $db->connect_error, 500);
    }
    
    $db->set_charset('utf8mb4');
} catch (Exception $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
}

// Get operation
$op = $_GET['op'] ?? '';

// Route operations
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
    case 'by_jenis':
        byJenis($db);
        break;
    case 'low_stock':
        lowStock($db);
        break;
    case 'add_stock':
        addStock($db);
        break;
    case 'reduce_stock':
        reduceStock($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua bahan
// ============================================
function getAll($db) {
    $sql = "SELECT * FROM materials ORDER BY nama_bahan ASC";
    $result = $db->query($sql);
    
    if (!$result) {
        Response::error('Query error: ' . $db->error, 500);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_material' => $row['id_material'],
            'nama_bahan' => $row['nama_bahan'],
            'jenis_bahan' => $row['jenis_bahan'],
            'stok_awal' => (int)$row['stok_awal'],
            'stok_sisa' => (int)$row['stok_sisa'],
            'stok_minimum' => (int)$row['stok_minimum'],
            'satuan' => $row['satuan'],
            'harga_per_unit' => (float)$row['harga_per_unit'],
            'supplier' => $row['supplier'],
            'status_stok' => ((int)$row['stok_sisa'] <= (int)$row['stok_minimum']) ? 'rendah' : 'normal',
            'tanggal_update' => $row['tanggal_update']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'materials' => $data
    ]);
}

// ============================================
// CREATE - Tambah bahan baru
// ============================================
function create($db) {
    $nama_bahan = $db->real_escape_string($_POST['nama_bahan'] ?? '');
    $jenis_bahan = $db->real_escape_string($_POST['jenis_bahan'] ?? 'lainnya');
    $stok_awal = (int)($_POST['stok_awal'] ?? 0);
    $stok_sisa = (int)($_POST['stok_sisa'] ?? $stok_awal);
    $stok_minimum = (int)($_POST['stok_minimum'] ?? 10);
    $satuan = $db->real_escape_string($_POST['satuan'] ?? 'pcs');
    $harga_per_unit = (float)($_POST['harga_per_unit'] ?? 0);
    $supplier = $db->real_escape_string($_POST['supplier'] ?? '');
    
    // Validasi
    if (empty($nama_bahan)) {
        Response::error('Nama bahan wajib diisi', 400);
    }
    
    if ($stok_minimum <= 0) {
        Response::error('Stok minimum harus lebih dari 0', 400);
    }
    
    // Insert
    $sql = "INSERT INTO materials (
        nama_bahan, jenis_bahan, stok_awal, stok_sisa, 
        stok_minimum, satuan, harga_per_unit, supplier
    ) VALUES (
        '$nama_bahan', '$jenis_bahan', $stok_awal, $stok_sisa, 
        $stok_minimum, '$satuan', $harga_per_unit, '$supplier'
    )";
    
    if (!$db->query($sql)) {
        Response::error('Gagal menambahkan bahan: ' . $db->error, 500);
    }
    
    $insertId = $db->insert_id;
    
    Response::created([
        'id_material' => $insertId,
        'nama_bahan' => $nama_bahan
    ], 'Bahan berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail bahan
// ============================================
function detail($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID material tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM materials WHERE id_material = '$id'";
    $result = $db->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        Response::notFound('Bahan tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_material' => $row['id_material'],
        'nama_bahan' => $row['nama_bahan'],
        'jenis_bahan' => $row['jenis_bahan'],
        'stok_awal' => (int)$row['stok_awal'],
        'stok_sisa' => (int)$row['stok_sisa'],
        'stok_minimum' => (int)$row['stok_minimum'],
        'satuan' => $row['satuan'],
        'harga_per_unit' => (float)$row['harga_per_unit'],
        'supplier' => $row['supplier'],
        'status_stok' => ((int)$row['stok_sisa'] <= (int)$row['stok_minimum']) ? 'rendah' : 'normal',
        'tanggal_update' => $row['tanggal_update']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update bahan
// ============================================
function update($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID material tidak ditemukan', 400);
    }
    
    // Cek bahan ada
    $checkSql = "SELECT id_material FROM materials WHERE id_material = '$id'";
    $checkResult = $db->query($checkSql);
    if (!$checkResult || $checkResult->num_rows === 0) {
        Response::notFound('Bahan tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['nama_bahan'])) {
        $nama = $db->real_escape_string($_POST['nama_bahan']);
        if (!empty($nama)) {
            $updates[] = "nama_bahan = '$nama'";
        }
    }
    
    if (isset($_POST['jenis_bahan'])) {
        $jenis = $db->real_escape_string($_POST['jenis_bahan']);
        $updates[] = "jenis_bahan = '$jenis'";
    }
    
    if (isset($_POST['stok_awal'])) {
        $stok_awal = (int)$_POST['stok_awal'];
        $updates[] = "stok_awal = $stok_awal";
    }
    
    if (isset($_POST['stok_sisa'])) {
        $stok_sisa = (int)$_POST['stok_sisa'];
        $updates[] = "stok_sisa = $stok_sisa";
    }
    
    if (isset($_POST['stok_minimum'])) {
        $stok_min = (int)$_POST['stok_minimum'];
        if ($stok_min > 0) {
            $updates[] = "stok_minimum = $stok_min";
        }
    }
    
    if (isset($_POST['satuan'])) {
        $satuan = $db->real_escape_string($_POST['satuan']);
        $updates[] = "satuan = '$satuan'";
    }
    
    if (isset($_POST['harga_per_unit'])) {
        $harga = (float)$_POST['harga_per_unit'];
        $updates[] = "harga_per_unit = $harga";
    }
    
    if (isset($_POST['supplier'])) {
        $supplier = $db->real_escape_string($_POST['supplier']);
        $updates[] = "supplier = '$supplier'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE materials SET " . implode(', ', $updates) . " WHERE id_material = '$id'";
    
    if (!$db->query($sql)) {
        Response::error('Gagal update bahan: ' . $db->error, 500);
    }
    
    Response::success(['id_material' => $id], 'Bahan berhasil diupdate');
}

// ============================================
// DELETE - Hapus bahan
// ============================================
function delete($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID material tidak ditemukan', 400);
    }
    
    // Cek bahan ada
    $checkSql = "SELECT id_material, nama_bahan FROM materials WHERE id_material = '$id'";
    $checkResult = $db->query($checkSql);
    
    if (!$checkResult || $checkResult->num_rows === 0) {
        Response::notFound('Bahan tidak ditemukan');
    }
    
    $row = $checkResult->fetch_assoc();
    
    // Delete
    $sql = "DELETE FROM materials WHERE id_material = '$id'";
    
    if (!$db->query($sql)) {
        Response::error('Gagal menghapus bahan: ' . $db->error, 500);
    }
    
    Response::success([
        'id_material' => $id,
        'nama_bahan' => $row['nama_bahan']
    ], 'Bahan berhasil dihapus');
}

// ============================================
// BY JENIS - Bahan per jenis
// ============================================
function byJenis($db) {
    $jenis = $db->real_escape_string($_GET['jenis'] ?? '');
    
    if (empty($jenis)) {
        Response::error('Jenis bahan tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM materials WHERE jenis_bahan = '$jenis' ORDER BY nama_bahan ASC";
    $result = $db->query($sql);
    
    if (!$result) {
        Response::error('Query error: ' . $db->error, 500);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_material' => $row['id_material'],
            'nama_bahan' => $row['nama_bahan'],
            'stok_sisa' => (int)$row['stok_sisa'],
            'stok_minimum' => (int)$row['stok_minimum'],
            'satuan' => $row['satuan'],
            'harga_per_unit' => (float)$row['harga_per_unit'],
            'status_stok' => ((int)$row['stok_sisa'] <= (int)$row['stok_minimum']) ? 'rendah' : 'normal'
        ];
    }
    
    Response::success([
        'total' => count($data),
        'materials' => $data
    ]);
}

// ============================================
// LOW STOCK - Bahan dengan stok rendah
// ============================================
function lowStock($db) {
    $sql = "SELECT * FROM materials WHERE stok_sisa <= stok_minimum ORDER BY stok_sisa ASC";
    $result = $db->query($sql);
    
    if (!$result) {
        Response::error('Query error: ' . $db->error, 500);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_material' => $row['id_material'],
            'nama_bahan' => $row['nama_bahan'],
            'jenis_bahan' => $row['jenis_bahan'],
            'stok_sisa' => (int)$row['stok_sisa'],
            'stok_minimum' => (int)$row['stok_minimum'],
            'satuan' => $row['satuan'],
            'supplier' => $row['supplier'],
            'selisih' => (int)$row['stok_minimum'] - (int)$row['stok_sisa']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'low_stock_materials' => $data
    ]);
}

// ============================================
// ADD STOCK - Tambah stok
// ============================================
function addStock($db) {
    $id = $db->real_escape_string($_POST['id_material'] ?? '');
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    
    if (empty($id) || $jumlah <= 0) {
        Response::error('ID material dan jumlah valid wajib diisi', 400);
    }
    
    $getSql = "SELECT stok_sisa, nama_bahan FROM materials WHERE id_material = '$id'";
    $getResult = $db->query($getSql);
    
    if (!$getResult || $getResult->num_rows === 0) {
        Response::notFound('Bahan tidak ditemukan');
    }
    
    $row = $getResult->fetch_assoc();
    $stok_baru = (int)$row['stok_sisa'] + $jumlah;
    
    $sql = "UPDATE materials SET stok_sisa = $stok_baru WHERE id_material = '$id'";
    
    if (!$db->query($sql)) {
        Response::error('Gagal menambah stok: ' . $db->error, 500);
    }
    
    Response::success([
        'id_material' => $id,
        'nama_bahan' => $row['nama_bahan'],
        'stok_lama' => (int)$row['stok_sisa'],
        'ditambah' => $jumlah,
        'stok_baru' => $stok_baru
    ], 'Stok berhasil ditambahkan');
}

// ============================================
// REDUCE STOCK - Kurangi stok
// ============================================
function reduceStock($db) {
    $id = $db->real_escape_string($_POST['id_material'] ?? '');
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    
    if (empty($id) || $jumlah <= 0) {
        Response::error('ID material dan jumlah valid wajib diisi', 400);
    }
    
    $getSql = "SELECT stok_sisa, stok_minimum, nama_bahan FROM materials WHERE id_material = '$id'";
    $getResult = $db->query($getSql);
    
    if (!$getResult || $getResult->num_rows === 0) {
        Response::notFound('Bahan tidak ditemukan');
    }
    
    $row = $getResult->fetch_assoc();
    
    if ((int)$row['stok_sisa'] < $jumlah) {
        Response::error('Stok tidak mencukupi', 400);
    }
    
    $stok_baru = (int)$row['stok_sisa'] - $jumlah;
    
    $sql = "UPDATE materials SET stok_sisa = $stok_baru WHERE id_material = '$id'";
    
    if (!$db->query($sql)) {
        Response::error('Gagal mengurangi stok: ' . $db->error, 500);
    }
    
    $warning = ($stok_baru <= (int)$row['stok_minimum']) ? 'Peringatan: Stok sudah mencapai batas minimum!' : null;
    
    Response::success([
        'id_material' => $id,
        'nama_bahan' => $row['nama_bahan'],
        'stok_lama' => (int)$row['stok_sisa'],
        'dikurangi' => $jumlah,
        'stok_baru' => $stok_baru,
        'warning' => $warning
    ], 'Stok berhasil dikurangi');
}
?>