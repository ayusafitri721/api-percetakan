<?php
/**
 * API Settings - CRUD Tabel settings
 * URL: http://localhost/api-percetakan/settings.php
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
    case 'by_key':
        byKey($db);
        break;
    case 'bulk_update':
        bulkUpdate($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua pengaturan
// ============================================
function getAll($db) {
    $sql = "SELECT * FROM settings ORDER BY setting_key ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_setting' => $row['id_setting'],
            'setting_key' => $row['setting_key'],
            'setting_value' => $row['setting_value'],
            'deskripsi' => $row['deskripsi'],
            'tanggal_update' => $row['tanggal_update']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'settings' => $data
    ]);
}

// ============================================
// GET BY KEY - Ambil setting berdasarkan key
// ============================================
function byKey($db) {
    $key = $db->escape($_GET['key'] ?? '');
    
    if (empty($key)) {
        Response::error('Setting key tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM settings WHERE setting_key = '$key'";
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Setting tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_setting' => $row['id_setting'],
        'setting_key' => $row['setting_key'],
        'setting_value' => $row['setting_value'],
        'deskripsi' => $row['deskripsi'],
        'tanggal_update' => $row['tanggal_update']
    ];
    
    Response::success($data);
}

// ============================================
// CREATE - Tambah setting baru
// ============================================
function create($db) {
    $setting_key = $db->escape($_POST['setting_key'] ?? '');
    $setting_value = $db->escape($_POST['setting_value'] ?? '');
    $deskripsi = $db->escape($_POST['deskripsi'] ?? '');
    
    // Validasi
    if (empty($setting_key)) {
        Response::error('Setting key wajib diisi', 400);
    }
    
    // Cek key sudah ada
    $checkSql = "SELECT setting_key FROM settings WHERE setting_key = '$setting_key'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows > 0) {
        Response::error('Setting key sudah ada', 400);
    }
    
    // Insert
    $sql = "INSERT INTO settings (setting_key, setting_value, deskripsi) 
            VALUES ('$setting_key', '$setting_value', '$deskripsi')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_setting' => $insertId,
        'setting_key' => $setting_key
    ], 'Setting berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail setting
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID setting tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM settings WHERE id_setting = '$id'";
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Setting tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_setting' => $row['id_setting'],
        'setting_key' => $row['setting_key'],
        'setting_value' => $row['setting_value'],
        'deskripsi' => $row['deskripsi'],
        'tanggal_update' => $row['tanggal_update']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update setting
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID setting tidak ditemukan', 400);
    }
    
    // Cek setting ada
    $checkSql = "SELECT id_setting FROM settings WHERE id_setting = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Setting tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['setting_key'])) {
        $key = $db->escape($_POST['setting_key']);
        // Cek key tidak duplikat
        $dupSql = "SELECT id_setting FROM settings WHERE setting_key = '$key' AND id_setting != '$id'";
        $dupResult = $db->query($dupSql);
        if ($dupResult->num_rows > 0) {
            Response::error('Setting key sudah digunakan', 400);
        }
        $updates[] = "setting_key = '$key'";
    }
    
    if (isset($_POST['setting_value'])) {
        $value = $db->escape($_POST['setting_value']);
        $updates[] = "setting_value = '$value'";
    }
    
    if (isset($_POST['deskripsi'])) {
        $deskripsi = $db->escape($_POST['deskripsi']);
        $updates[] = "deskripsi = '$deskripsi'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE settings SET " . implode(', ', $updates) . " WHERE id_setting = '$id'";
    $db->query($sql);
    
    Response::success(['id_setting' => $id], 'Setting berhasil diupdate');
}

// ============================================
// BULK UPDATE - Update banyak setting sekaligus
// ============================================
function bulkUpdate($db) {
    // Terima data JSON dari body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (empty($data) || !is_array($data)) {
        Response::error('Data tidak valid', 400);
    }
    
    $updated = 0;
    $errors = [];
    
    foreach ($data as $item) {
        if (empty($item['setting_key'])) {
            $errors[] = 'Setting key kosong diabaikan';
            continue;
        }
        
        $key = $db->escape($item['setting_key']);
        $value = $db->escape($item['setting_value'] ?? '');
        
        // Cek apakah setting sudah ada
        $checkSql = "SELECT id_setting FROM settings WHERE setting_key = '$key'";
        $checkResult = $db->query($checkSql);
        
        if ($checkResult->num_rows > 0) {
            // Update
            $sql = "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'";
        } else {
            // Insert
            $deskripsi = $db->escape($item['deskripsi'] ?? '');
            $sql = "INSERT INTO settings (setting_key, setting_value, deskripsi) 
                    VALUES ('$key', '$value', '$deskripsi')";
        }
        
        $db->query($sql);
        $updated++;
    }
    
    Response::success([
        'updated_count' => $updated,
        'errors' => $errors
    ], "Berhasil memproses $updated setting");
}

// ============================================
// DELETE - Hapus setting
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID setting tidak ditemukan', 400);
    }
    
    // Cek setting ada
    $checkSql = "SELECT id_setting FROM settings WHERE id_setting = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Setting tidak ditemukan');
    }
    
    // Delete
    $sql = "DELETE FROM settings WHERE id_setting = '$id'";
    $db->query($sql);
    
    Response::success(['id_setting' => $id], 'Setting berhasil dihapus');
}
?>