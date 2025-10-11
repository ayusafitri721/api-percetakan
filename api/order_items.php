<?php
/**
 * API Order Items - CRUD Tabel order_items
 * URL: http://localhost/api-percetakan/order_items.php
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
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua item
// ============================================
function getAll($db) {
    $sql = "SELECT oi.*, o.kode_order, p.nama_product, p.gambar_preview
            FROM order_items oi
            LEFT JOIN orders o ON oi.id_order = o.id_order
            LEFT JOIN products p ON oi.id_product = p.id_product
            ORDER BY oi.id_item DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_item' => $row['id_item'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'id_product' => $row['id_product'],
            'nama_product' => $row['nama_product'],
            'ukuran' => $row['ukuran'],
            'jumlah' => $row['jumlah'],
            'harga_satuan' => $row['harga_satuan'],
            'subtotal' => $row['subtotal'],
            'keterangan' => $row['keterangan'],
            'gambar_preview' => $row['gambar_preview']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'items' => $data
    ]);
}

// ============================================
// BY ORDER - Item per order
// ============================================
function byOrder($db) {
    $id_order = $db->escape($_GET['id_order'] ?? '');
    
    if (empty($id_order)) {
        Response::error('ID order tidak ditemukan', 400);
    }
    
    $sql = "SELECT oi.*, p.nama_product, p.gambar_preview, p.satuan
            FROM order_items oi
            LEFT JOIN products p ON oi.id_product = p.id_product
            WHERE oi.id_order = '$id_order'";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_item' => $row['id_item'],
            'id_product' => $row['id_product'],
            'nama_product' => $row['nama_product'],
            'ukuran' => $row['ukuran'],
            'jumlah' => $row['jumlah'],
            'satuan' => $row['satuan'],
            'harga_satuan' => $row['harga_satuan'],
            'subtotal' => $row['subtotal'],
            'keterangan' => $row['keterangan'],
            'gambar_preview' => $row['gambar_preview']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'items' => $data
    ]);
}

// ============================================
// CREATE - Tambah item ke order
// ============================================
function create($db) {
    $id_order = $db->escape($_POST['id_order'] ?? '');
    $id_product = $db->escape($_POST['id_product'] ?? '');
    $ukuran = $db->escape($_POST['ukuran'] ?? '');
    $jumlah = $db->escape($_POST['jumlah'] ?? 1);
    $harga_satuan = $db->escape($_POST['harga_satuan'] ?? 0);
    $keterangan = $db->escape($_POST['keterangan'] ?? '');
    
    // Validasi
    if (empty($id_order) || empty($id_product)) {
        Response::error('ID order dan ID product wajib diisi', 400);
    }
    
    // Cek order ada
    $checkOrder = "SELECT id_order FROM orders WHERE id_order = '$id_order'";
    $resultOrder = $db->query($checkOrder);
    if ($resultOrder->num_rows === 0) {
        Response::error('Order tidak ditemukan', 400);
    }
    
    // Cek product ada
    $checkProduct = "SELECT nama_product FROM products WHERE id_product = '$id_product'";
    $resultProduct = $db->query($checkProduct);
    if ($resultProduct->num_rows === 0) {
        Response::error('Product tidak ditemukan', 400);
    }
    
    $nama_product = $resultProduct->fetch_assoc()['nama_product'];
    
    // Hitung subtotal
    $subtotal = $jumlah * $harga_satuan;
    
    // Insert
    $sql = "INSERT INTO order_items (id_order, id_product, nama_product, ukuran, jumlah, harga_satuan, subtotal, keterangan) 
            VALUES ('$id_order', '$id_product', '$nama_product', '$ukuran', '$jumlah', '$harga_satuan', '$subtotal', '$keterangan')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    // Update total order
    updateOrderTotal($db, $id_order);
    
    Response::created([
        'id_item' => $insertId,
        'id_order' => $id_order,
        'subtotal' => $subtotal
    ], 'Item berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail item
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID item tidak ditemukan', 400);
    }
    
    $sql = "SELECT oi.*, o.kode_order, p.nama_product, p.gambar_preview, p.satuan
            FROM order_items oi
            LEFT JOIN orders o ON oi.id_order = o.id_order
            LEFT JOIN products p ON oi.id_product = p.id_product
            WHERE oi.id_item = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Item tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_item' => $row['id_item'],
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'id_product' => $row['id_product'],
        'nama_product' => $row['nama_product'],
        'ukuran' => $row['ukuran'],
        'jumlah' => $row['jumlah'],
        'satuan' => $row['satuan'],
        'harga_satuan' => $row['harga_satuan'],
        'subtotal' => $row['subtotal'],
        'keterangan' => $row['keterangan'],
        'gambar_preview' => $row['gambar_preview']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update item
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID item tidak ditemukan', 400);
    }
    
    // Cek item ada
    $checkSql = "SELECT id_order FROM order_items WHERE id_item = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Item tidak ditemukan');
    }
    
    $id_order = $checkResult->fetch_assoc()['id_order'];
    
    // Build update query
    $updates = [];
    $recalculate = false;
    
    if (isset($_POST['ukuran'])) {
        $ukuran = $db->escape($_POST['ukuran']);
        $updates[] = "ukuran = '$ukuran'";
    }
    
    if (isset($_POST['jumlah'])) {
        $jumlah = $db->escape($_POST['jumlah']);
        $updates[] = "jumlah = '$jumlah'";
        $recalculate = true;
    }
    
    if (isset($_POST['harga_satuan'])) {
        $harga = $db->escape($_POST['harga_satuan']);
        $updates[] = "harga_satuan = '$harga'";
        $recalculate = true;
    }
    
    if (isset($_POST['keterangan'])) {
        $keterangan = $db->escape($_POST['keterangan']);
        $updates[] = "keterangan = '$keterangan'";
    }
    
    // Recalculate subtotal jika jumlah atau harga berubah
    if ($recalculate) {
        $getSql = "SELECT jumlah, harga_satuan FROM order_items WHERE id_item = '$id'";
        $getResult = $db->query($getSql);
        $current = $getResult->fetch_assoc();
        
        $new_jumlah = isset($_POST['jumlah']) ? $_POST['jumlah'] : $current['jumlah'];
        $new_harga = isset($_POST['harga_satuan']) ? $_POST['harga_satuan'] : $current['harga_satuan'];
        $new_subtotal = $new_jumlah * $new_harga;
        
        $updates[] = "subtotal = '$new_subtotal'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE order_items SET " . implode(', ', $updates) . " WHERE id_item = '$id'";
    $db->query($sql);
    
    // Update total order
    updateOrderTotal($db, $id_order);
    
    Response::success(['id_item' => $id], 'Item berhasil diupdate');
}

// ============================================
// DELETE - Hapus item
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID item tidak ditemukan', 400);
    }
    
    // Cek item ada
    $checkSql = "SELECT id_order FROM order_items WHERE id_item = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Item tidak ditemukan');
    }
    
    $id_order = $checkResult->fetch_assoc()['id_order'];
    
    // Hard delete
    $sql = "DELETE FROM order_items WHERE id_item = '$id'";
    $db->query($sql);
    
    // Update total order
    updateOrderTotal($db, $id_order);
    
    Response::success(['id_item' => $id], 'Item berhasil dihapus');
}

// ============================================
// HELPER - Update total order
// ============================================
function updateOrderTotal($db, $id_order) {
    // Hitung total dari semua items
    $totalSql = "SELECT SUM(subtotal) as total FROM order_items WHERE id_order = '$id_order'";
    $totalResult = $db->query($totalSql);
    $subtotal = $totalResult->fetch_assoc()['total'] ?? 0;
    
    // Get diskon dan ongkir
    $orderSql = "SELECT diskon, ongkir FROM orders WHERE id_order = '$id_order'";
    $orderResult = $db->query($orderSql);
    $order = $orderResult->fetch_assoc();
    
    $diskon = $order['diskon'] ?? 0;
    $ongkir = $order['ongkir'] ?? 0;
    
    // Hitung total akhir
    $total_harga = $subtotal - $diskon + $ongkir;
    
    // Update order
    $updateSql = "UPDATE orders SET subtotal = '$subtotal', total_harga = '$total_harga' WHERE id_order = '$id_order'";
    $db->query($updateSql);
}
?>