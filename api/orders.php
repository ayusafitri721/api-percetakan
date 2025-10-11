<?php
/**
 * API Orders - CRUD Tabel orders
 * URL: http://localhost/api-percetakan/orders.php
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
    case 'by_user':
        byUser($db);
        break;
    case 'by_status':
        byStatus($db);
        break;
    case 'update_status':
        updateStatus($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua pesanan
// ============================================
function getAll($db) {
    $sql = "SELECT o.*, u.nama as nama_pelanggan, u.email, u.no_telepon,
            k.nama as nama_kasir
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users k ON o.id_kasir = k.id_user
            ORDER BY o.tanggal_order DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'id_user' => $row['id_user'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'email' => $row['email'],
            'no_telepon' => $row['no_telepon'],
            'nama_kasir' => $row['nama_kasir'],
            'tanggal_order' => $row['tanggal_order'],
            'jenis_order' => $row['jenis_order'],
            'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
            'status_order' => $row['status_order'],
            'subtotal' => $row['subtotal'],
            'diskon' => $row['diskon'],
            'ongkir' => $row['ongkir'],
            'total_harga' => $row['total_harga'],
            'tanggal_selesai' => $row['tanggal_selesai']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'orders' => $data
    ]);
}

// ============================================
// BY USER - Pesanan per user
// ============================================
function byUser($db) {
    $id_user = $db->escape($_GET['id_user'] ?? '');
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM orders 
            WHERE id_user = '$id_user'
            ORDER BY tanggal_order DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'tanggal_order' => $row['tanggal_order'],
            'jenis_order' => $row['jenis_order'],
            'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
            'status_order' => $row['status_order'],
            'total_harga' => $row['total_harga']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'orders' => $data
    ]);
}

// ============================================
// BY STATUS - Pesanan per status
// ============================================
function byStatus($db) {
    $status = $db->escape($_GET['status'] ?? '');
    
    if (empty($status)) {
        Response::error('Status tidak ditemukan', 400);
    }
    
    $sql = "SELECT o.*, u.nama as nama_pelanggan
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            WHERE o.status_order = '$status'
            ORDER BY o.tanggal_order DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'tanggal_order' => $row['tanggal_order'],
            'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
            'total_harga' => $row['total_harga']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'orders' => $data
    ]);
}

// ============================================
// UPDATE STATUS - Update status order
// ============================================
function updateStatus($db) {
    $id = $db->escape($_POST['id_order'] ?? '');
    $status = $db->escape($_POST['status'] ?? '');
    
    if (empty($id) || empty($status)) {
        Response::error('ID order dan status wajib diisi', 400);
    }
    
    // Cek order ada
    $checkSql = "SELECT id_order FROM orders WHERE id_order = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
    }
    
    // Update status
    $sql = "UPDATE orders SET status_order = '$status'";
    
    // Jika status selesai, set tanggal selesai
    if ($status === 'selesai') {
        $sql .= ", tanggal_selesai = NOW()";
    }
    
    $sql .= " WHERE id_order = '$id'";
    $db->query($sql);
    
    Response::success(['id_order' => $id, 'status' => $status], 'Status order berhasil diupdate');
}

// ============================================
// CREATE - Tambah order baru
// ============================================
function create($db) {
    $id_user = $db->escape($_POST['id_user'] ?? '');
    $id_kasir = $db->escape($_POST['id_kasir'] ?? null);
    $jenis_order = $db->escape($_POST['jenis_order'] ?? 'online');
    $kecepatan = $db->escape($_POST['kecepatan_pengerjaan'] ?? 'normal');
    $subtotal = $db->escape($_POST['subtotal'] ?? 0);
    $diskon = $db->escape($_POST['diskon'] ?? 0);
    $ongkir = $db->escape($_POST['ongkir'] ?? 0);
    $total_harga = $db->escape($_POST['total_harga'] ?? 0);
    $catatan_pelanggan = $db->escape($_POST['catatan_pelanggan'] ?? '');
    
    // Validasi
    if (empty($id_user)) {
        Response::error('ID user wajib diisi', 400);
    }
    
    // Cek user ada
    $checkUser = "SELECT id_user FROM users WHERE id_user = '$id_user'";
    $resultUser = $db->query($checkUser);
    if ($resultUser->num_rows === 0) {
        Response::error('User tidak ditemukan', 400);
    }
    
    // Generate kode order
    $tanggal_str = date('Ymd');
    $countSql = "SELECT COUNT(*) as total FROM orders WHERE DATE(tanggal_order) = CURDATE()";
    $countResult = $db->query($countSql);
    $counter = $countResult->fetch_assoc()['total'] + 1;
    $kode_order = 'ORD-' . $tanggal_str . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    
    // Insert
    $id_kasir_sql = $id_kasir ? "'$id_kasir'" : "NULL";
    $sql = "INSERT INTO orders (kode_order, id_user, id_kasir, jenis_order, kecepatan_pengerjaan, subtotal, diskon, ongkir, total_harga, catatan_pelanggan, status_order) 
            VALUES ('$kode_order', '$id_user', $id_kasir_sql, '$jenis_order', '$kecepatan', '$subtotal', '$diskon', '$ongkir', '$total_harga', '$catatan_pelanggan', 'pending')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_order' => $insertId,
        'kode_order' => $kode_order
    ], 'Order berhasil dibuat');
}

// ============================================
// DETAIL - Detail order
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
    }
    
    $sql = "SELECT o.*, u.nama as nama_pelanggan, u.email, u.no_telepon, u.alamat,
            k.nama as nama_kasir
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users k ON o.id_kasir = k.id_user
            WHERE o.id_order = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    
    // Get order items
    $itemsSql = "SELECT oi.*, p.nama_product, p.gambar_preview
                 FROM order_items oi
                 LEFT JOIN products p ON oi.id_product = p.id_product
                 WHERE oi.id_order = '$id'";
    $itemsResult = $db->query($itemsSql);
    
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = [
            'id_item' => $item['id_item'],
            'id_product' => $item['id_product'],
            'nama_product' => $item['nama_product'],
            'ukuran' => $item['ukuran'],
            'jumlah' => $item['jumlah'],
            'harga_satuan' => $item['harga_satuan'],
            'subtotal' => $item['subtotal'],
            'keterangan' => $item['keterangan'],
            'gambar_preview' => $item['gambar_preview']
        ];
    }
    
    $data = [
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'id_user' => $row['id_user'],
        'nama_pelanggan' => $row['nama_pelanggan'],
        'email' => $row['email'],
        'no_telepon' => $row['no_telepon'],
        'alamat' => $row['alamat'],
        'nama_kasir' => $row['nama_kasir'],
        'tanggal_order' => $row['tanggal_order'],
        'jenis_order' => $row['jenis_order'],
        'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
        'status_order' => $row['status_order'],
        'subtotal' => $row['subtotal'],
        'diskon' => $row['diskon'],
        'ongkir' => $row['ongkir'],
        'total_harga' => $row['total_harga'],
        'catatan_pelanggan' => $row['catatan_pelanggan'],
        'catatan_internal' => $row['catatan_internal'],
        'tanggal_selesai' => $row['tanggal_selesai'],
        'items' => $items
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update order
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
    }
    
    // Cek order ada
    $checkSql = "SELECT id_order FROM orders WHERE id_order = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['kecepatan_pengerjaan'])) {
        $kecepatan = $db->escape($_POST['kecepatan_pengerjaan']);
        $updates[] = "kecepatan_pengerjaan = '$kecepatan'";
    }
    
    if (isset($_POST['subtotal'])) {
        $subtotal = $db->escape($_POST['subtotal']);
        $updates[] = "subtotal = '$subtotal'";
    }
    
    if (isset($_POST['diskon'])) {
        $diskon = $db->escape($_POST['diskon']);
        $updates[] = "diskon = '$diskon'";
    }
    
    if (isset($_POST['ongkir'])) {
        $ongkir = $db->escape($_POST['ongkir']);
        $updates[] = "ongkir = '$ongkir'";
    }
    
    if (isset($_POST['total_harga'])) {
        $total = $db->escape($_POST['total_harga']);
        $updates[] = "total_harga = '$total'";
    }
    
    if (isset($_POST['catatan_internal'])) {
        $catatan = $db->escape($_POST['catatan_internal']);
        $updates[] = "catatan_internal = '$catatan'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id_order = '$id'";
    $db->query($sql);
    
    Response::success(['id_order' => $id], 'Order berhasil diupdate');
}

// ============================================
// DELETE - Hapus order (soft delete)
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
    }
    
    // Cek order ada
    $checkSql = "SELECT id_order FROM orders WHERE id_order = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
    }
    
    // Update status jadi dibatalkan
    $sql = "UPDATE orders SET status_order = 'dibatalkan' WHERE id_order = '$id'";
    $db->query($sql);
    
    Response::success(['id_order' => $id], 'Order berhasil dibatalkan');
}
?>