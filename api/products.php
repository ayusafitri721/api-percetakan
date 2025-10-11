<?php
/**
 * API Products - CRUD Tabel products
 * URL: http://localhost/api-percetakan/products.php
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
    $id_category = $db->escape($_GET['id_category'] ?? '');
    
    if (empty($id_category)) {
        Response::error('ID kategori tidak ditemukan', 400);
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
    $id_category = $db->escape($_POST['id_category'] ?? '');
    $nama_product = $db->escape($_POST['nama_product'] ?? '');
    $deskripsi = $db->escape($_POST['deskripsi'] ?? '');
    $media_cetak = $db->escape($_POST['media_cetak'] ?? '');
    $ukuran_standar = $db->escape($_POST['ukuran_standar'] ?? '');
    $satuan = $db->escape($_POST['satuan'] ?? 'lembar');
    $harga_dasar = $db->escape($_POST['harga_dasar'] ?? 0);
    $gambar_preview = $db->escape($_POST['gambar_preview'] ?? '');
    
    // Validasi
    if (empty($id_category) || empty($nama_product)) {
        Response::error('ID kategori dan nama produk wajib diisi', 400);
    }
    
    // Cek kategori ada
    $checkCat = "SELECT id_category FROM categories WHERE id_category = '$id_category'";
    $resultCat = $db->query($checkCat);
    if ($resultCat->num_rows === 0) {
        Response::error('Kategori tidak ditemukan', 400);
    }
    
    // Insert
    $sql = "INSERT INTO products (id_category, nama_product, deskripsi, media_cetak, ukuran_standar, satuan, harga_dasar, gambar_preview, status_aktif) 
            VALUES ('$id_category', '$nama_product', '$deskripsi', '$media_cetak', '$ukuran_standar', '$satuan', '$harga_dasar', '$gambar_preview', 1)";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_product' => $insertId,
        'nama_product' => $nama_product
    ], 'Produk berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail produk
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID produk tidak ditemukan', 400);
    }
    
    $sql = "SELECT p.*, c.nama_category 
            FROM products p
            LEFT JOIN categories c ON p.id_category = c.id_category
            WHERE p.id_product = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Produk tidak ditemukan');
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
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID produk tidak ditemukan', 400);
    }
    
    // Cek produk ada
    $checkSql = "SELECT id_product FROM products WHERE id_product = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Produk tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['id_category'])) {
        $id_category = $db->escape($_POST['id_category']);
        $updates[] = "id_category = '$id_category'";
    }
    
    if (isset($_POST['nama_product'])) {
        $nama_product = $db->escape($_POST['nama_product']);
        $updates[] = "nama_product = '$nama_product'";
    }
    
    if (isset($_POST['deskripsi'])) {
        $deskripsi = $db->escape($_POST['deskripsi']);
        $updates[] = "deskripsi = '$deskripsi'";
    }
    
    if (isset($_POST['media_cetak'])) {
        $media_cetak = $db->escape($_POST['media_cetak']);
        $updates[] = "media_cetak = '$media_cetak'";
    }
    
    if (isset($_POST['ukuran_standar'])) {
        $ukuran_standar = $db->escape($_POST['ukuran_standar']);
        $updates[] = "ukuran_standar = '$ukuran_standar'";
    }
    
    if (isset($_POST['satuan'])) {
        $satuan = $db->escape($_POST['satuan']);
        $updates[] = "satuan = '$satuan'";
    }
    
    if (isset($_POST['harga_dasar'])) {
        $harga_dasar = $db->escape($_POST['harga_dasar']);
        $updates[] = "harga_dasar = '$harga_dasar'";
    }
    
    if (isset($_POST['gambar_preview'])) {
        $gambar_preview = $db->escape($_POST['gambar_preview']);
        $updates[] = "gambar_preview = '$gambar_preview'";
    }
    
    if (isset($_POST['status_aktif'])) {
        $status = (int)$_POST['status_aktif'];
        $updates[] = "status_aktif = $status";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE products SET " . implode(', ', $updates) . " WHERE id_product = '$id'";
    $db->query($sql);
    
    Response::success(['id_product' => $id], 'Produk berhasil diupdate');
}

// ============================================
// DELETE - Hapus produk (soft delete)
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID produk tidak ditemukan', 400);
    }
    
    // Cek produk ada
    $checkSql = "SELECT id_product FROM products WHERE id_product = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Produk tidak ditemukan');
    }
    
    // Soft delete
    $sql = "UPDATE products SET status_aktif = 0 WHERE id_product = '$id'";
    $db->query($sql);
    
    Response::success(['id_product' => $id], 'Produk berhasil dihapus');
}
?>