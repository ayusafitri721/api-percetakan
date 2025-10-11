<?php
/**
 * File: api/categories.php
 * API untuk CRUD Categories
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

// Ambil method HTTP dan parameter
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// Routing berdasarkan method
switch ($method) {
    case 'GET':
        if ($id) {
            getDetail($db, $id);
        } else {
            getAll($db);
        }
        break;
        
    case 'POST':
        create($db);
        break;
        
    case 'PUT':
        update($db, $id);
        break;
        
    case 'DELETE':
        delete($db, $id);
        break;
        
    default:
        Response::error('Method tidak didukung', 405);
}

// ============================================
// FUNCTIONS
// ============================================

/**
 * GET ALL Categories
 */
function getAll($db) {
    try {
        $sql = "SELECT * FROM categories WHERE status_aktif = 1 ORDER BY urutan ASC, id_category DESC";
        $result = $db->query($sql);
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'id_category' => $row['id_category'],
                'nama_category' => $row['nama_category'],
                'deskripsi' => $row['deskripsi'],
                'icon' => $row['icon'],
                'urutan' => $row['urutan'],
                'tanggal_dibuat' => $row['tanggal_dibuat']
            ];
        }
        
        Response::success([
            'total' => count($data),
            'categories' => $data
        ], 'Data kategori berhasil diambil');
        
    } catch (Exception $e) {
        Response::serverError($e->getMessage());
    }
}

/**
 * GET DETAIL Category
 */
function getDetail($db, $id) {
    try {
        if (!$id) {
            Response::error('ID kategori tidak ditemukan');
        }
        
        $id = $db->escape($id);
        $sql = "SELECT * FROM categories WHERE id_category = '$id'";
        $result = $db->query($sql);
        
        if ($result->num_rows === 0) {
            Response::notFound('Kategori tidak ditemukan');
        }
        
        $row = $result->fetch_assoc();
        $data = [
            'id_category' => $row['id_category'],
            'nama_category' => $row['nama_category'],
            'deskripsi' => $row['deskripsi'],
            'icon' => $row['icon'],
            'urutan' => $row['urutan'],
            'status_aktif' => $row['status_aktif'],
            'tanggal_dibuat' => $row['tanggal_dibuat']
        ];
        
        Response::success($data, 'Detail kategori berhasil diambil');
        
    } catch (Exception $e) {
        Response::serverError($e->getMessage());
    }
}

/**
 * CREATE Category
 */
function create($db) {
    try {
        // Ambil data dari POST
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        // Validasi
        $errors = [];
        if (empty($input['nama_category'])) {
            $errors['nama_category'] = 'Nama kategori wajib diisi';
        }
        
        if (!empty($errors)) {
            Response::validationError($errors);
        }
        
        // Escape data
        $nama = $db->escape($input['nama_category']);
        $deskripsi = $db->escape($input['deskripsi'] ?? '');
        $icon = $db->escape($input['icon'] ?? '');
        $urutan = (int)($input['urutan'] ?? 0);
        
        // Insert ke database
        $sql = "INSERT INTO categories (nama_category, deskripsi, icon, urutan, status_aktif) 
                VALUES ('$nama', '$deskripsi', '$icon', $urutan, 1)";
        
        $db->query($sql);
        $insertId = $db->lastInsertId();
        
        Response::created([
            'id_category' => $insertId,
            'nama_category' => $nama
        ], 'Kategori berhasil ditambahkan');
        
    } catch (Exception $e) {
        Response::serverError($e->getMessage());
    }
}

/**
 * UPDATE Category
 */
function update($db, $id) {
    try {
        if (!$id) {
            Response::error('ID kategori tidak ditemukan');
        }
        
        // Cek apakah kategori ada
        $id = $db->escape($id);
        $checkSql = "SELECT id_category FROM categories WHERE id_category = '$id'";
        $checkResult = $db->query($checkSql);
        
        if ($checkResult->num_rows === 0) {
            Response::notFound('Kategori tidak ditemukan');
        }
        
        // Ambil data dari PUT
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        // Build update query
        $updates = [];
        
        if (isset($input['nama_category'])) {
            $nama = $db->escape($input['nama_category']);
            $updates[] = "nama_category = '$nama'";
        }
        
        if (isset($input['deskripsi'])) {
            $deskripsi = $db->escape($input['deskripsi']);
            $updates[] = "deskripsi = '$deskripsi'";
        }
        
        if (isset($input['icon'])) {
            $icon = $db->escape($input['icon']);
            $updates[] = "icon = '$icon'";
        }
        
        if (isset($input['urutan'])) {
            $urutan = (int)$input['urutan'];
            $updates[] = "urutan = $urutan";
        }
        
        if (isset($input['status_aktif'])) {
            $status = (int)$input['status_aktif'];
            $updates[] = "status_aktif = $status";
        }
        
        if (empty($updates)) {
            Response::error('Tidak ada data yang diupdate');
        }
        
        $sql = "UPDATE categories SET " . implode(', ', $updates) . " WHERE id_category = '$id'";
        $db->query($sql);
        
        Response::success([
            'id_category' => $id
        ], 'Kategori berhasil diupdate');
        
    } catch (Exception $e) {
        Response::serverError($e->getMessage());
    }
}

/**
 * DELETE Category (Soft Delete)
 */
function delete($db, $id) {
    try {
        if (!$id) {
            Response::error('ID kategori tidak ditemukan');
        }
        
        $id = $db->escape($id);
        
        // Cek apakah kategori ada
        $checkSql = "SELECT id_category FROM categories WHERE id_category = '$id'";
        $checkResult = $db->query($checkSql);
        
        if ($checkResult->num_rows === 0) {
            Response::notFound('Kategori tidak ditemukan');
        }
        
        // Soft delete (ubah status_aktif jadi 0)
        $sql = "UPDATE categories SET status_aktif = 0 WHERE id_category = '$id'";
        $db->query($sql);
        
        Response::success([
            'id_category' => $id
        ], 'Kategori berhasil dihapus');
        
    } catch (Exception $e) {
        Response::serverError($e->getMessage());
    }
}
?>