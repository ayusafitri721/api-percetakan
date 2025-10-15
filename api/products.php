<?php
/**
 * API Products - CRUD Tabel products
 * URL: http://localhost/api-percetakan/products.php
 */

// AKTIFKAN ERROR REPORTING UNTUK DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../helpers/Response.php';

// CORS - FIXED
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Cache-Control');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    case 'by_category':
        byCategory($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua produk
// ============================================
function getAll($db) {
    $sql = "SELECT p.*, c.nama_category 
            FROM products p
            LEFT JOIN categories c ON p.id_category = c.id_category
            WHERE p.status_aktif = 1
            ORDER BY p.id_product DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_product' => $row['id_product'],
            'id_category' => $row['id_category'],
            'nama_category' => $row['nama_category'],
            'nama_product' => $row['nama_product'],
            'deskripsi' => $row['deskripsi'],
            'media_cetak' => $row['media_cetak'],
            'ukuran_standar' => $row['ukuran_standar'],
            'satuan' => $row['satuan'],
            'harga_dasar' => $row['harga_dasar'],
            'gambar_preview' => $row['gambar_preview'],
            'tanggal_dibuat' => $row['tanggal_dibuat']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'products' => $data
    ]);
}

// ============================================
// GET BY CATEGORY - Produk per kategori
// ============================================
function byCategory($db) {
    $id_category = $db->real_escape_string($_GET['id_category'] ?? '');
    
    if (empty($id_category)) {
        Response::error('ID kategori tidak ditemukan', 400);
        return;
    }
    
    $sql = "SELECT p.*, c.nama_category 
            FROM products p
            LEFT JOIN categories c ON p.id_category = c.id_category
            WHERE p.id_category = '$id_category' AND p.status_aktif = 1
            ORDER BY p.nama_product ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_product' => $row['id_product'],
            'nama_product' => $row['nama_product'],
            'deskripsi' => $row['deskripsi'],
            'media_cetak' => $row['media_cetak'],
            'ukuran_standar' => $row['ukuran_standar'],
            'satuan' => $row['satuan'],
            'harga_dasar' => $row['harga_dasar'],
            'gambar_preview' => $row['gambar_preview']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'products' => $data
    ]);
}

// ============================================
// CREATE - Tambah produk baru
// ============================================
function create($db) {
    // Log untuk debug
    error_log("CREATE FUNCTION CALLED");
    error_log("POST DATA: " . print_r($_POST, true));
    
    $id_category = $db->real_escape_string($_POST['id_category'] ?? '');
    $nama_product = $db->real_escape_string($_POST['nama_product'] ?? '');
    $deskripsi = $db->real_escape_string($_POST['deskripsi'] ?? '');
    $media_cetak = $db->real_escape_string($_POST['media_cetak'] ?? '');
    $ukuran_standar = $db->real_escape_string($_POST['ukuran_standar'] ?? '');
    $satuan = $db->real_escape_string($_POST['satuan'] ?? 'lembar');
    $harga_dasar = $db->real_escape_string($_POST['harga_dasar'] ?? 0);
    $gambar_preview = $db->real_escape_string($_POST['gambar_preview'] ?? '');
    
    // Validasi
    if (empty($id_category) || empty($nama_product)) {
        Response::error('ID kategori dan nama produk wajib diisi', 400);
        return;
    }
    
    // Cek kategori ada
    $checkCat = "SELECT id_category FROM categories WHERE id_category = '$id_category'";
    $resultCat = $db->query($checkCat);
    if ($resultCat->num_rows === 0) {
        Response::error('Kategori tidak ditemukan', 400);
        return;
    }
    
    // Insert
    $sql = "INSERT INTO products (id_category, nama_product, deskripsi, media_cetak, ukuran_standar, satuan, harga_dasar, gambar_preview, status_aktif) 
            VALUES ('$id_category', '$nama_product', '$deskripsi', '$media_cetak', '$ukuran_standar', '$satuan', '$harga_dasar', '$gambar_preview', 1)";
    
    error_log("SQL: " . $sql);
    
    if ($db->query($sql)) {
        $insertId = $db->insert_id;
        
        Response::success([
            'id_product' => $insertId,
            'nama_product' => $nama_product
        ], 'Produk berhasil ditambahkan');
    } else {
        Response::error('Gagal menyimpan produk: ' . $db->error, 500);
    }
}

// ============================================
// DETAIL - Detail produk
// ============================================
function detail($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID produk tidak ditemukan', 400);
        return;
    }
    
    $sql = "SELECT p.*, c.nama_category 
            FROM products p
            LEFT JOIN categories c ON p.id_category = c.id_category
            WHERE p.id_product = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Produk tidak ditemukan');
        return;
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_product' => $row['id_product'],
        'id_category' => $row['id_category'],
        'nama_category' => $row['nama_category'],
        'nama_product' => $row['nama_product'],
        'deskripsi' => $row['deskripsi'],
        'media_cetak' => $row['media_cetak'],
        'ukuran_standar' => $row['ukuran_standar'],
        'satuan' => $row['satuan'],
        'harga_dasar' => $row['harga_dasar'],
        'gambar_preview' => $row['gambar_preview'],
        'status_aktif' => $row['status_aktif'],
        'tanggal_dibuat' => $row['tanggal_dibuat']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update produk
// ============================================
function update($db) {
    error_log("UPDATE FUNCTION CALLED");
    error_log("GET ID: " . ($_GET['id'] ?? 'NONE'));
    error_log("POST DATA: " . print_r($_POST, true));
    
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID produk tidak ditemukan', 400);
        return;
    }
    
    // Cek produk ada
    $checkSql = "SELECT id_product FROM products WHERE id_product = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Produk tidak ditemukan');
        return;
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['id_category'])) {
        $id_category = $db->real_escape_string($_POST['id_category']);
        $updates[] = "id_category = '$id_category'";
    }
    
    if (isset($_POST['nama_product'])) {
        $nama_product = $db->real_escape_string($_POST['nama_product']);
        $updates[] = "nama_product = '$nama_product'";
    }
    
    if (isset($_POST['deskripsi'])) {
        $deskripsi = $db->real_escape_string($_POST['deskripsi']);
        $updates[] = "deskripsi = '$deskripsi'";
    }
    
    if (isset($_POST['media_cetak'])) {
        $media_cetak = $db->real_escape_string($_POST['media_cetak']);
        $updates[] = "media_cetak = '$media_cetak'";
    }
    
    if (isset($_POST['ukuran_standar'])) {
        $ukuran_standar = $db->real_escape_string($_POST['ukuran_standar']);
        $updates[] = "ukuran_standar = '$ukuran_standar'";
    }
    
    if (isset($_POST['satuan'])) {
        $satuan = $db->real_escape_string($_POST['satuan']);
        $updates[] = "satuan = '$satuan'";
    }
    
    if (isset($_POST['harga_dasar'])) {
        $harga_dasar = $db->real_escape_string($_POST['harga_dasar']);
        $updates[] = "harga_dasar = '$harga_dasar'";
    }
    
    if (isset($_POST['gambar_preview'])) {
        $gambar_preview = $db->real_escape_string($_POST['gambar_preview']);
        $updates[] = "gambar_preview = '$gambar_preview'";
    }
    
    if (isset($_POST['status_aktif'])) {
        $status = (int)$_POST['status_aktif'];
        $updates[] = "status_aktif = $status";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
        return;
    }
    
    $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id_product = '$id'";
    error_log("UPDATE SQL: " . $sql);
    
    if ($db->query($sql)) {
        Response::success(['id_product' => $id], 'Produk berhasil diupdate');
    } else {
        Response::error('Gagal update produk: ' . $db->error, 500);
    }
}

// ============================================
// DELETE - Hapus produk PERMANEN (HARD DELETE)
// ============================================
function delete($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID produk tidak ditemukan', 400);
        return;
    }
    
    // Cek produk ada
    $checkSql = "SELECT id_product FROM products WHERE id_product = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Produk tidak ditemukan');
        return;
    }
    
    // HARD DELETE - Hapus permanen dari database
    $sql = "DELETE FROM products WHERE id_product = '$id'";
    
    if ($db->query($sql)) {
        Response::success(['id_product' => $id], 'Produk berhasil dihapus permanen');
    } else {
        Response::error('Gagal menghapus produk: ' . $db->error, 500);
    }
}
?>