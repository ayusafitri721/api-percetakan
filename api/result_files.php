<?php
/**
 * API Result Files - File Hasil Operator
 * Terpisah dari design_files (file awal customer)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../helpers/Response.php';

$database = new Database();
$db = $database->connect();

$op = $_GET['op'] ?? '';

switch ($op) {
    case 'by_order':
        getByOrder($db);
        break;
    case 'create':
        create($db);
        break;
    case 'delete':
        delete($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET FILE HASIL BY ORDER
// ============================================
function getByOrder($db) {
    $id_order = $_GET['id_order'] ?? '';
    
    if (empty($id_order)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $id_order = $db->real_escape_string($id_order);
    
    $sql = "SELECT 
                id_result,
                id_order,
                nama_file,
                file_url,
                ukuran_file,
                tipe_file,
                keterangan,
                uploaded_by,
                tanggal_upload
            FROM result_files 
            WHERE id_order = '$id_order'
            ORDER BY tanggal_upload DESC";
    
    $result = $db->query($sql);
    
    if (!$result) {
        Response::error('Query failed: ' . $db->error, 500);
        return;
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_result' => $row['id_result'],
            'id_order' => $row['id_order'],
            'nama_file' => $row['nama_file'],
            'file_url' => $row['file_url'],
            'ukuran_file' => (int)$row['ukuran_file'],
            'tipe_file' => $row['tipe_file'],
            'keterangan' => $row['keterangan'],
            'uploaded_by' => $row['uploaded_by'],
            'tanggal_upload' => $row['tanggal_upload']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'files' => $data
    ]);
}

// ============================================
// GET ALL FILE HASIL
// ============================================
function getAll($db) {
    $sql = "SELECT rf.*, o.kode_order, o.nama_customer
            FROM result_files rf
            LEFT JOIN orders o ON rf.id_order = o.id_order
            ORDER BY rf.tanggal_upload DESC";
    
    $result = $db->query($sql);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_result' => $row['id_result'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'nama_customer' => $row['nama_customer'],
            'nama_file' => $row['nama_file'],
            'file_url' => $row['file_url'],
            'ukuran_file' => (int)$row['ukuran_file'],
            'tipe_file' => $row['tipe_file'],
            'keterangan' => $row['keterangan'],
            'uploaded_by' => $row['uploaded_by'],
            'tanggal_upload' => $row['tanggal_upload']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'files' => $data
    ]);
}

// ============================================
// CREATE - Upload File Hasil Operator
// ============================================
function create($db) {
    $id_order = $_POST['id_order'] ?? '';
    $nama_file = $_POST['nama_file'] ?? '';
    $file_url = $_POST['file_url'] ?? '';
    $ukuran_file = $_POST['ukuran_file'] ?? 0;
    $tipe_file = $_POST['tipe_file'] ?? '';
    $keterangan = $_POST['keterangan'] ?? 'File hasil dari operator';
    
    if (empty($id_order) || empty($nama_file) || empty($file_url)) {
        Response::error('ID order, nama file, dan URL file wajib diisi', 400);
        return;
    }
    
    $id_order = $db->real_escape_string($id_order);
    $nama_file = $db->real_escape_string($nama_file);
    $file_url = $db->real_escape_string($file_url);
    $ukuran_file = (int)$ukuran_file;
    $tipe_file = $db->real_escape_string($tipe_file);
    $keterangan = $db->real_escape_string($keterangan);
    
    // Cek order
    $checkOrder = "SELECT id_order FROM orders WHERE id_order = '$id_order'";
    $resultOrder = $db->query($checkOrder);
    if ($resultOrder->num_rows === 0) {
        Response::error('Order tidak ditemukan', 400);
        return;
    }
    
    $sql = "INSERT INTO result_files (
                id_order, nama_file, file_url, ukuran_file, tipe_file, 
                keterangan, uploaded_by
            ) VALUES (
                '$id_order', '$nama_file', '$file_url', $ukuran_file, '$tipe_file',
                '$keterangan', 'operator'
            )";
    
    if (!$db->query($sql)) {
        Response::error('Database error: ' . $db->error, 500);
        return;
    }
    
    $insertId = $db->insert_id;
    
    // Update status order ke selesai
    $updateOrder = "UPDATE orders 
                    SET status_order = 'selesai' 
                    WHERE id_order = '$id_order' 
                    AND status_order IN ('cetak', 'diproses', 'validasi')";
    $db->query($updateOrder);
    
    Response::created([
        'id_result' => $insertId,
        'nama_file' => $nama_file,
        'message' => 'File hasil operator berhasil diupload dan siap diserahkan ke customer'
    ], 'File hasil berhasil diupload');
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
    
    $checkSql = "SELECT id_result FROM result_files WHERE id_result = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('File tidak ditemukan');
        return;
    }
    
    $sql = "DELETE FROM result_files WHERE id_result = '$id'";
    $db->query($sql);
    
    Response::success(['id_result' => $id], 'File berhasil dihapus');
}
?>