<?php
/**
 * API Design Files - FIXED COMPLETE
 * Support is_result dan keterangan untuk memisahkan file design dan file hasil
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../helpers/Response.php';

$database = new Database();
$db = $database->connect();

$op = $_GET['op'] ?? '';

error_log("=== DESIGN_FILES API CALLED ===");
error_log("Operation: $op");

switch ($op) {
    case 'by_order':
        byOrder($db);
        break;
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
// BY ORDER - FIXED DENGAN is_result & keterangan
// ============================================
function byOrder($db) {
    $id_order = $_GET['id_order'] ?? '';
    
    error_log("=== BY_ORDER CALLED ===");
    error_log("id_order: $id_order");
    
    if (empty($id_order)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $id_order = $db->real_escape_string($id_order);
    
    // ✅ SELECT dengan COALESCE untuk handle NULL
    $sql = "SELECT 
                id_file,
                id_order,
                nama_file,
                file_url,
                ukuran_file,
                tipe_file,
                COALESCE(keterangan, '') as keterangan,
                COALESCE(is_result, 0) as is_result,
                status_validasi,
                catatan_validasi,
                tanggal_upload
            FROM design_files 
            WHERE id_order = '$id_order'
            ORDER BY is_result ASC, tanggal_upload DESC";
    
    error_log("SQL: $sql");
    
    $result = $db->query($sql);
    
    if (!$result) {
        error_log("ERROR: " . $db->error);
        Response::error('Query failed: ' . $db->error, 500);
        return;
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_file' => $row['id_file'],
            'id_order' => $row['id_order'],
            'nama_file' => $row['nama_file'],
            'file_url' => $row['file_url'],
            'ukuran_file' => (int)$row['ukuran_file'],
            'tipe_file' => $row['tipe_file'],
            'keterangan' => $row['keterangan'] ?: '',
            'is_result' => (int)$row['is_result'],
            'status_validasi' => $row['status_validasi'],
            'catatan_validasi' => $row['catatan_validasi'],
            'tanggal_upload' => $row['tanggal_upload']
        ];
    }
    
    error_log("Files found: " . count($data));
    error_log("Data: " . json_encode($data));
    
    Response::success([
        'total' => count($data),
        'files' => $data
    ]);
}

// ============================================
// GET ALL
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
            'ukuran_file' => (int)$row['ukuran_file'],
            'tipe_file' => $row['tipe_file'],
            'keterangan' => $row['keterangan'] ?? '',
            'is_result' => (int)($row['is_result'] ?? 0),
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
// CREATE - DENGAN is_result & keterangan
// ============================================
function create($db) {
    $id_order = $_POST['id_order'] ?? '';
    $nama_file = $_POST['nama_file'] ?? '';
    $file_url = $_POST['file_url'] ?? '';
    $ukuran_file = $_POST['ukuran_file'] ?? 0;
    $tipe_file = $_POST['tipe_file'] ?? '';
    $is_result = $_POST['is_result'] ?? 0;
    $keterangan = $_POST['keterangan'] ?? '';
    
    if (empty($id_order) || empty($nama_file) || empty($file_url)) {
        Response::error('ID order, nama file, dan URL file wajib diisi', 400);
        return;
    }
    
    $id_order = $db->real_escape_string($id_order);
    $nama_file = $db->real_escape_string($nama_file);
    $file_url = $db->real_escape_string($file_url);
    $ukuran_file = (int)$ukuran_file;
    $tipe_file = $db->real_escape_string($tipe_file);
    $is_result = (int)$is_result;
    $keterangan = $db->real_escape_string($keterangan);
    
    // Cek order
    $checkOrder = "SELECT id_order FROM orders WHERE id_order = '$id_order'";
    $resultOrder = $db->query($checkOrder);
    if ($resultOrder->num_rows === 0) {
        Response::error('Order tidak ditemukan', 400);
        return;
    }
    
    $sql = "INSERT INTO design_files (
                id_order, nama_file, file_url, ukuran_file, tipe_file, 
                is_result, keterangan, status_validasi
            ) VALUES (
                '$id_order', '$nama_file', '$file_url', $ukuran_file, '$tipe_file',
                $is_result, '$keterangan', 'approved'
            )";
    
    if (!$db->query($sql)) {
        Response::error('Database error: ' . $db->error, 500);
        return;
    }
    
    $insertId = $db->insert_id;
    
    Response::created([
        'id_file' => $insertId,
        'nama_file' => $nama_file,
        'is_result' => $is_result,
        'keterangan' => $keterangan
    ], 'File berhasil diupload');
}

// ============================================
// PENDING FILES
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
// VALIDATE FILE
// ============================================
function validateFile($db) {
    $id = $_POST['id_file'] ?? '';
    $status = $_POST['status_validasi'] ?? '';
    $catatan = $_POST['catatan_validasi'] ?? '';
    
    if (empty($id) || empty($status)) {
        Response::error('ID file dan status validasi wajib diisi', 400);
        return;
    }
    
    $id = $db->real_escape_string($id);
    $status = $db->real_escape_string($status);
    $catatan = $db->real_escape_string($catatan);
    
    $checkSql = "SELECT id_file FROM design_files WHERE id_file = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
        return;
    }
    
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
// DETAIL
// ============================================
function detail($db) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID file tidak ditemukan', 400);
        return;
    }
    
    $id = $db->real_escape_string($id);
    
    $sql = "SELECT df.*, o.kode_order, u.nama as nama_pelanggan
            FROM design_files df
            LEFT JOIN orders o ON df.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            WHERE df.id_file = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
        return;
    }
    
    $row = $result->fetch_assoc();
    
    Response::success([
        'id_file' => $row['id_file'],
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'nama_pelanggan' => $row['nama_pelanggan'],
        'nama_file' => $row['nama_file'],
        'file_url' => $row['file_url'],
        'ukuran_file' => (int)$row['ukuran_file'],
        'tipe_file' => $row['tipe_file'],
        'keterangan' => $row['keterangan'] ?? '',
        'is_result' => (int)($row['is_result'] ?? 0),
        'status_validasi' => $row['status_validasi'],
        'catatan_validasi' => $row['catatan_validasi'],
        'tanggal_upload' => $row['tanggal_upload'],
        'tanggal_validasi' => $row['tanggal_validasi']
    ]);
}

// ============================================
// UPDATE
// ============================================
function update($db) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID file tidak ditemukan', 400);
        return;
    }
    
    $id = $db->real_escape_string($id);
    
    $checkSql = "SELECT id_file FROM design_files WHERE id_file = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
        return;
    }
    
    $updates = [];
    
    if (isset($_POST['nama_file'])) {
        $nama = $db->real_escape_string($_POST['nama_file']);
        $updates[] = "nama_file = '$nama'";
    }
    
    if (isset($_POST['file_url'])) {
        $url = $db->real_escape_string($_POST['file_url']);
        $updates[] = "file_url = '$url'";
    }
    
    if (isset($_POST['catatan_validasi'])) {
        $catatan = $db->real_escape_string($_POST['catatan_validasi']);
        $updates[] = "catatan_validasi = '$catatan'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
        return;
    }
    
    $sql = "UPDATE design_files SET " . implode(', ', $updates) . " WHERE id_file = '$id'";
    $db->query($sql);
    
    Response::success(['id_file' => $id], 'File berhasil diupdate');
}

// ============================================
// DELETE
// ============================================
function delete($db) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID file tidak ditemukan', 400);
        return;
    }
    
    $id = $db->real_escape_string($id);
    
    $checkSql = "SELECT id_file FROM design_files WHERE id_file = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
        return;
    }
    
    $sql = "DELETE FROM design_files WHERE id_file = '$id'";
    $db->query($sql);
    
    Response::success(['id_file' => $id], 'File berhasil dihapus');
}
?>