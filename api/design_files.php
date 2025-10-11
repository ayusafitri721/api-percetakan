<?php
/**
 * API Design Files - CRUD Tabel design_files
 * URL: http://localhost/api-percetakan/design_files.php
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
    case 'by_order':
        byOrder($db);
        break;
    case 'validate':
        validateFile($db);
        break;
    case 'pending':
        pendingFiles($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua file desain
// ============================================
function getAll($db) {
    $sql = "SELECT df.*, o.kode_order, u.nama as nama_pelanggan
            FROM design_files df
            LEFT JOIN orders o ON df.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            ORDER BY df.tanggal_upload DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_file' => $row['id_file'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'nama_file' => $row['nama_file'],
            'file_url' => $row['file_url'],
            'ukuran_file' => $row['ukuran_file'],
            'tipe_file' => $row['tipe_file'],
            'status_validasi' => $row['status_validasi'],
            'catatan_validasi' => $row['catatan_validasi'],
            'tanggal_upload' => $row['tanggal_upload'],
            'tanggal_validasi' => $row['tanggal_validasi']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'files' => $data
    ]);
}

// ============================================
// BY ORDER - File per order
// ============================================
function byOrder($db) {
    $id_order = $db->escape($_GET['id_order'] ?? '');
    
    if (empty($id_order)) {
        Response::error('ID order tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM design_files 
            WHERE id_order = '$id_order'
            ORDER BY tanggal_upload DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_file' => $row['id_file'],
            'nama_file' => $row['nama_file'],
            'file_url' => $row['file_url'],
            'ukuran_file' => $row['ukuran_file'],
            'tipe_file' => $row['tipe_file'],
            'status_validasi' => $row['status_validasi'],
            'catatan_validasi' => $row['catatan_validasi'],
            'tanggal_upload' => $row['tanggal_upload']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'files' => $data
    ]);
}

// ============================================
// PENDING - File menunggu validasi
// ============================================
function pendingFiles($db) {
    $sql = "SELECT df.*, o.kode_order, u.nama as nama_pelanggan
            FROM design_files df
            LEFT JOIN orders o ON df.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            WHERE df.status_validasi = 'pending'
            ORDER BY df.tanggal_upload ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_file' => $row['id_file'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'nama_file' => $row['nama_file'],
            'file_url' => $row['file_url'],
            'tipe_file' => $row['tipe_file'],
            'tanggal_upload' => $row['tanggal_upload']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'files' => $data
    ]);
}

// ============================================
// VALIDATE - Validasi file desain
// ============================================
function validateFile($db) {
    $id = $db->escape($_POST['id_file'] ?? '');
    $status = $db->escape($_POST['status_validasi'] ?? '');
    $catatan = $db->escape($_POST['catatan_validasi'] ?? '');
    
    if (empty($id) || empty($status)) {
        Response::error('ID file dan status validasi wajib diisi', 400);
    }
    
    // Cek file ada
    $checkSql = "SELECT id_file FROM design_files WHERE id_file = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
    }
    
    // Update validasi
    $sql = "UPDATE design_files 
            SET status_validasi = '$status', 
                catatan_validasi = '$catatan',
                tanggal_validasi = NOW()
            WHERE id_file = '$id'";
    
    $db->query($sql);
    
    Response::success([
        'id_file' => $id,
        'status_validasi' => $status
    ], 'File berhasil divalidasi');
}

// ============================================
// CREATE - Upload file desain
// ============================================
function create($db) {
    $id_order = $db->escape($_POST['id_order'] ?? '');
    $nama_file = $db->escape($_POST['nama_file'] ?? '');
    $file_url = $db->escape($_POST['file_url'] ?? '');
    $ukuran_file = $db->escape($_POST['ukuran_file'] ?? 0);
    $tipe_file = $db->escape($_POST['tipe_file'] ?? '');
    
    // Validasi
    if (empty($id_order) || empty($nama_file) || empty($file_url)) {
        Response::error('ID order, nama file, dan URL file wajib diisi', 400);
    }
    
    // Cek order ada
    $checkOrder = "SELECT id_order FROM orders WHERE id_order = '$id_order'";
    $resultOrder = $db->query($checkOrder);
    if ($resultOrder->num_rows === 0) {
        Response::error('Order tidak ditemukan', 400);
    }
    
    // Insert
    $sql = "INSERT INTO design_files (id_order, nama_file, file_url, ukuran_file, tipe_file, status_validasi) 
            VALUES ('$id_order', '$nama_file', '$file_url', '$ukuran_file', '$tipe_file', 'pending')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_file' => $insertId,
        'nama_file' => $nama_file
    ], 'File berhasil diupload');
}

// ============================================
// DETAIL - Detail file
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID file tidak ditemukan', 400);
    }
    
    $sql = "SELECT df.*, o.kode_order, u.nama as nama_pelanggan
            FROM design_files df
            LEFT JOIN orders o ON df.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            WHERE df.id_file = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_file' => $row['id_file'],
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'nama_pelanggan' => $row['nama_pelanggan'],
        'nama_file' => $row['nama_file'],
        'file_url' => $row['file_url'],
        'ukuran_file' => $row['ukuran_file'],
        'tipe_file' => $row['tipe_file'],
        'status_validasi' => $row['status_validasi'],
        'catatan_validasi' => $row['catatan_validasi'],
        'tanggal_upload' => $row['tanggal_upload'],
        'tanggal_validasi' => $row['tanggal_validasi']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update file
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID file tidak ditemukan', 400);
    }
    
    // Cek file ada
    $checkSql = "SELECT id_file FROM design_files WHERE id_file = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['nama_file'])) {
        $nama = $db->escape($_POST['nama_file']);
        $updates[] = "nama_file = '$nama'";
    }
    
    if (isset($_POST['file_url'])) {
        $url = $db->escape($_POST['file_url']);
        $updates[] = "file_url = '$url'";
    }
    
    if (isset($_POST['catatan_validasi'])) {
        $catatan = $db->escape($_POST['catatan_validasi']);
        $updates[] = "catatan_validasi = '$catatan'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE design_files SET " . implode(', ', $updates) . " WHERE id_file = '$id'";
    $db->query($sql);
    
    Response::success(['id_file' => $id], 'File berhasil diupdate');
}

// ============================================
// DELETE - Hapus file
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID file tidak ditemukan', 400);
    }
    
    // Cek file ada
    $checkSql = "SELECT id_file FROM design_files WHERE id_file = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
    }
    
    // Hard delete
    $sql = "DELETE FROM design_files WHERE id_file = '$id'";
    $db->query($sql);
    
    Response::success(['id_file' => $id], 'File berhasil dihapus');
}
?>