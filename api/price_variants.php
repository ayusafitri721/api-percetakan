<?php
/**
 * API Price Variants - CRUD Tabel price_variants
 * URL: http://localhost/api-percetakan/price_variants.php
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
    case 'by_product':
        byProduct($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua variasi harga
// ============================================
function getAll($db) {
    $sql = "SELECT pv.*, p.nama_product 
            FROM price_variants pv
            LEFT JOIN products p ON pv.id_product = p.id_product
            ORDER BY pv.id_product, pv.min_qty ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_variant' => $row['id_variant'],
            'id_product' => $row['id_product'],
            'nama_product' => $row['nama_product'],
            'ukuran' => $row['ukuran'],
            'min_qty' => $row['min_qty'],
            'max_qty' => $row['max_qty'],
            'harga_per_unit' => $row['harga_per_unit'],
            'kecepatan' => $row['kecepatan'],
            'markup_persen' => $row['markup_persen'],
            'tanggal_dibuat' => $row['tanggal_dibuat']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'variants' => $data
    ]);
}

// ============================================
// GET BY PRODUCT - Variasi harga per produk
// ============================================
function byProduct($db) {
    $id_product = $db->escape($_GET['id_product'] ?? '');
    
    if (empty($id_product)) {
        Response::error('ID produk tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM price_variants 
            WHERE id_product = '$id_product'
            ORDER BY min_qty ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_variant' => $row['id_variant'],
            'ukuran' => $row['ukuran'],
            'min_qty' => $row['min_qty'],
            'max_qty' => $row['max_qty'],
            'harga_per_unit' => $row['harga_per_unit'],
            'kecepatan' => $row['kecepatan'],
            'markup_persen' => $row['markup_persen']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'variants' => $data
    ]);
}

// ============================================
// CREATE - Tambah variasi harga baru
// ============================================
function create($db) {
    $id_product = $db->escape($_POST['id_product'] ?? '');
    $ukuran = $db->escape($_POST['ukuran'] ?? '');
    $min_qty = $db->escape($_POST['min_qty'] ?? 1);
    $max_qty = $db->escape($_POST['max_qty'] ?? null);
    $harga_per_unit = $db->escape($_POST['harga_per_unit'] ?? 0);
    $kecepatan = $db->escape($_POST['kecepatan'] ?? 'normal');
    $markup_persen = $db->escape($_POST['markup_persen'] ?? 0);
    
    // Validasi
    if (empty($id_product) || empty($harga_per_unit)) {
        Response::error('ID produk dan harga per unit wajib diisi', 400);
    }
    
    // Cek produk ada
    $checkProduct = "SELECT id_product FROM products WHERE id_product = '$id_product'";
    $resultProduct = $db->query($checkProduct);
    if ($resultProduct->num_rows === 0) {
        Response::error('Produk tidak ditemukan', 400);
    }
    
    // Insert
    $max_qty_sql = $max_qty ? "'$max_qty'" : "NULL";
    $sql = "INSERT INTO price_variants (id_product, ukuran, min_qty, max_qty, harga_per_unit, kecepatan, markup_persen) 
            VALUES ('$id_product', '$ukuran', '$min_qty', $max_qty_sql, '$harga_per_unit', '$kecepatan', '$markup_persen')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_variant' => $insertId,
        'id_product' => $id_product
    ], 'Variasi harga berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail variasi harga
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID variasi tidak ditemukan', 400);
    }
    
    $sql = "SELECT pv.*, p.nama_product 
            FROM price_variants pv
            LEFT JOIN products p ON pv.id_product = p.id_product
            WHERE pv.id_variant = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Variasi harga tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_variant' => $row['id_variant'],
        'id_product' => $row['id_product'],
        'nama_product' => $row['nama_product'],
        'ukuran' => $row['ukuran'],
        'min_qty' => $row['min_qty'],
        'max_qty' => $row['max_qty'],
        'harga_per_unit' => $row['harga_per_unit'],
        'kecepatan' => $row['kecepatan'],
        'markup_persen' => $row['markup_persen'],
        'tanggal_dibuat' => $row['tanggal_dibuat']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update variasi harga
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID variasi tidak ditemukan', 400);
    }
    
    // Cek variasi ada
    $checkSql = "SELECT id_variant FROM price_variants WHERE id_variant = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Variasi harga tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['ukuran'])) {
        $ukuran = $db->escape($_POST['ukuran']);
        $updates[] = "ukuran = '$ukuran'";
    }
    
    if (isset($_POST['min_qty'])) {
        $min_qty = $db->escape($_POST['min_qty']);
        $updates[] = "min_qty = '$min_qty'";
    }
    
    if (isset($_POST['max_qty'])) {
        $max_qty = $_POST['max_qty'] ? "'" . $db->escape($_POST['max_qty']) . "'" : "NULL";
        $updates[] = "max_qty = $max_qty";
    }
    
    if (isset($_POST['harga_per_unit'])) {
        $harga = $db->escape($_POST['harga_per_unit']);
        $updates[] = "harga_per_unit = '$harga'";
    }
    
    if (isset($_POST['kecepatan'])) {
        $kecepatan = $db->escape($_POST['kecepatan']);
        $updates[] = "kecepatan = '$kecepatan'";
    }
    
    if (isset($_POST['markup_persen'])) {
        $markup = $db->escape($_POST['markup_persen']);
        $updates[] = "markup_persen = '$markup'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE price_variants SET " . implode(', ', $updates) . " WHERE id_variant = '$id'";
    $db->query($sql);
    
    Response::success(['id_variant' => $id], 'Variasi harga berhasil diupdate');
}

// ============================================
// DELETE - Hapus variasi harga
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID variasi tidak ditemukan', 400);
    }
    
    // Cek variasi ada
    $checkSql = "SELECT id_variant FROM price_variants WHERE id_variant = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Variasi harga tidak ditemukan');
    }
    
    // Hard delete
    $sql = "DELETE FROM price_variants WHERE id_variant = '$id'";
    $db->query($sql);
    
    Response::success(['id_variant' => $id], 'Variasi harga berhasil dihapus');
}
?>