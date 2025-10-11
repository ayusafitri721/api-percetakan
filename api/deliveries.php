<?php
/**
 * API Deliveries - CRUD Tabel deliveries
 * URL: http://localhost/api-percetakan/deliveries.php
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
    case 'by_kurir':
        byKurir($db);
        break;
    case 'update_status':
        updateStatus($db);
        break;
    case 'active':
        activeDeliveries($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua pengiriman
// ============================================
function getAll($db) {
    $sql = "SELECT d.*, o.kode_order, u.nama as nama_pelanggan,
            k.nama as nama_kurir
            FROM deliveries d
            LEFT JOIN orders o ON d.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users k ON d.id_kurir = k.id_user
            ORDER BY d.tanggal_kirim DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_delivery' => $row['id_delivery'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'nama_kurir' => $row['nama_kurir'],
            'metode_pengiriman' => $row['metode_pengiriman'],
            'nama_penerima' => $row['nama_penerima'],
            'no_telepon_penerima' => $row['no_telepon_penerima'],
            'kota' => $row['kota'],
            'provinsi' => $row['provinsi'],
            'ongkos_kirim' => $row['ongkos_kirim'],
            'status_pengiriman' => $row['status_pengiriman'],
            'tanggal_kirim' => $row['tanggal_kirim'],
            'tanggal_tiba' => $row['tanggal_tiba']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'deliveries' => $data
    ]);
}

// ============================================
// BY ORDER - Pengiriman per order
// ============================================
function byOrder($db) {
    $id_order = $db->escape($_GET['id_order'] ?? '');
    
    if (empty($id_order)) {
        Response::error('ID order tidak ditemukan', 400);
    }
    
    $sql = "SELECT d.*, k.nama as nama_kurir, k.no_telepon as telepon_kurir
            FROM deliveries d
            LEFT JOIN users k ON d.id_kurir = k.id_user
            WHERE d.id_order = '$id_order'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Data pengiriman tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_delivery' => $row['id_delivery'],
        'metode_pengiriman' => $row['metode_pengiriman'],
        'nama_penerima' => $row['nama_penerima'],
        'no_telepon_penerima' => $row['no_telepon_penerima'],
        'alamat_lengkap' => $row['alamat_lengkap'],
        'kota' => $row['kota'],
        'provinsi' => $row['provinsi'],
        'nama_kurir' => $row['nama_kurir'],
        'telepon_kurir' => $row['telepon_kurir'],
        'ongkos_kirim' => $row['ongkos_kirim'],
        'status_pengiriman' => $row['status_pengiriman'],
        'tanggal_kirim' => $row['tanggal_kirim'],
        'tanggal_tiba' => $row['tanggal_tiba'],
        'foto_bukti_kirim' => $row['foto_bukti_kirim']
    ];
    
    Response::success($data);
}

// ============================================
// BY KURIR - Pengiriman per kurir
// ============================================
function byKurir($db) {
    $id_kurir = $db->escape($_GET['id_kurir'] ?? '');
    
    if (empty($id_kurir)) {
        Response::error('ID kurir tidak ditemukan', 400);
    }
    
    $sql = "SELECT d.*, o.kode_order, u.nama as nama_pelanggan
            FROM deliveries d
            LEFT JOIN orders o ON d.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            WHERE d.id_kurir = '$id_kurir'
            AND d.status_pengiriman IN ('dikemas', 'dikirim', 'transit')
            ORDER BY d.tanggal_kirim DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_delivery' => $row['id_delivery'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'nama_penerima' => $row['nama_penerima'],
            'alamat_lengkap' => $row['alamat_lengkap'],
            'kota' => $row['kota'],
            'status_pengiriman' => $row['status_pengiriman'],
            'tanggal_kirim' => $row['tanggal_kirim']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'deliveries' => $data
    ]);
}

// ============================================
// ACTIVE - Pengiriman aktif (belum selesai)
// ============================================
function activeDeliveries($db) {
    $sql = "SELECT d.*, o.kode_order, u.nama as nama_pelanggan, k.nama as nama_kurir
            FROM deliveries d
            LEFT JOIN orders o ON d.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users k ON d.id_kurir = k.id_user
            WHERE d.status_pengiriman IN ('pending', 'dikemas', 'dikirim', 'transit', 'tiba')
            ORDER BY d.tanggal_kirim ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_delivery' => $row['id_delivery'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'nama_kurir' => $row['nama_kurir'],
            'metode_pengiriman' => $row['metode_pengiriman'],
            'kota' => $row['kota'],
            'status_pengiriman' => $row['status_pengiriman'],
            'tanggal_kirim' => $row['tanggal_kirim']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'deliveries' => $data
    ]);
}

// ============================================
// UPDATE STATUS - Update status pengiriman
// ============================================
function updateStatus($db) {
    $id = $db->escape($_POST['id_delivery'] ?? '');
    $status = $db->escape($_POST['status_pengiriman'] ?? '');
    $foto_bukti = $db->escape($_POST['foto_bukti_kirim'] ?? '');
    
    if (empty($id) || empty($status)) {
        Response::error('ID delivery dan status wajib diisi', 400);
    }
    
    // Cek delivery ada
    $checkSql = "SELECT id_order FROM deliveries WHERE id_delivery = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Delivery tidak ditemukan');
    }
    
    $id_order = $checkResult->fetch_assoc()['id_order'];
    
    // Update status
    $sql = "UPDATE deliveries SET status_pengiriman = '$status'";
    
    // Set tanggal sesuai status
    if ($status === 'dikirim') {
        $sql .= ", tanggal_kirim = NOW()";
    } elseif ($status === 'selesai') {
        $sql .= ", tanggal_tiba = NOW()";
        
        // Update status order jadi dikirim
        $updateOrder = "UPDATE orders SET status_order = 'dikirim' WHERE id_order = '$id_order'";
        $db->query($updateOrder);
    }
    
    // Tambah foto bukti jika ada
    if (!empty($foto_bukti)) {
        $sql .= ", foto_bukti_kirim = '$foto_bukti'";
    }
    
    $sql .= " WHERE id_delivery = '$id'";
    $db->query($sql);
    
    Response::success([
        'id_delivery' => $id,
        'status_pengiriman' => $status
    ], 'Status pengiriman berhasil diupdate');
}

// ============================================
// CREATE - Tambah pengiriman
// ============================================
function create($db) {
    $id_order = $db->escape($_POST['id_order'] ?? '');
    $id_kurir = $db->escape($_POST['id_kurir'] ?? null);
    $metode = $db->escape($_POST['metode_pengiriman'] ?? 'ambil_sendiri');
    $nama_penerima = $db->escape($_POST['nama_penerima'] ?? '');
    $no_telepon = $db->escape($_POST['no_telepon_penerima'] ?? '');
    $alamat = $db->escape($_POST['alamat_lengkap'] ?? '');
    $kode_pos = $db->escape($_POST['kode_pos'] ?? '');
    $kota = $db->escape($_POST['kota'] ?? '');
    $provinsi = $db->escape($_POST['provinsi'] ?? '');
    $ongkir = $db->escape($_POST['ongkos_kirim'] ?? 0);
    
    // Validasi
    if (empty($id_order)) {
        Response::error('ID order wajib diisi', 400);
    }
    
    // Cek order ada
    $checkOrder = "SELECT id_order FROM orders WHERE id_order = '$id_order'";
    $resultOrder = $db->query($checkOrder);
    if ($resultOrder->num_rows === 0) {
        Response::error('Order tidak ditemukan', 400);
    }
    
    // Cek sudah ada delivery
    $checkDelivery = "SELECT id_delivery FROM deliveries WHERE id_order = '$id_order'";
    $resultDelivery = $db->query($checkDelivery);
    if ($resultDelivery->num_rows > 0) {
        Response::error('Order sudah memiliki data pengiriman', 400);
    }
    
    // Insert
    $id_kurir_sql = $id_kurir ? "'$id_kurir'" : "NULL";
    $sql = "INSERT INTO deliveries (id_order, id_kurir, metode_pengiriman, nama_penerima, no_telepon_penerima, alamat_lengkap, kode_pos, kota, provinsi, ongkos_kirim, status_pengiriman) 
            VALUES ('$id_order', $id_kurir_sql, '$metode', '$nama_penerima', '$no_telepon', '$alamat', '$kode_pos', '$kota', '$provinsi', '$ongkir', 'pending')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    // Update ongkir di order
    $updateOrder = "UPDATE orders SET ongkir = '$ongkir', total_harga = subtotal - diskon + '$ongkir' WHERE id_order = '$id_order'";
    $db->query($updateOrder);
    
    Response::created([
        'id_delivery' => $insertId,
        'id_order' => $id_order
    ], 'Data pengiriman berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail pengiriman
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID delivery tidak ditemukan', 400);
    }
    
    $sql = "SELECT d.*, o.kode_order, u.nama as nama_pelanggan, u.no_telepon as telepon_pelanggan,
            k.nama as nama_kurir, k.no_telepon as telepon_kurir
            FROM deliveries d
            LEFT JOIN orders o ON d.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users k ON d.id_kurir = k.id_user
            WHERE d.id_delivery = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Delivery tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_delivery' => $row['id_delivery'],
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'nama_pelanggan' => $row['nama_pelanggan'],
        'telepon_pelanggan' => $row['telepon_pelanggan'],
        'id_kurir' => $row['id_kurir'],
        'nama_kurir' => $row['nama_kurir'],
        'telepon_kurir' => $row['telepon_kurir'],
        'metode_pengiriman' => $row['metode_pengiriman'],
        'nama_penerima' => $row['nama_penerima'],
        'no_telepon_penerima' => $row['no_telepon_penerima'],
        'alamat_lengkap' => $row['alamat_lengkap'],
        'kode_pos' => $row['kode_pos'],
        'kota' => $row['kota'],
        'provinsi' => $row['provinsi'],
        'ongkos_kirim' => $row['ongkos_kirim'],
        'status_pengiriman' => $row['status_pengiriman'],
        'tanggal_kirim' => $row['tanggal_kirim'],
        'tanggal_tiba' => $row['tanggal_tiba'],
        'catatan_pengiriman' => $row['catatan_pengiriman'],
        'foto_bukti_kirim' => $row['foto_bukti_kirim']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update pengiriman
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID delivery tidak ditemukan', 400);
    }
    
    // Cek delivery ada
    $checkSql = "SELECT id_delivery FROM deliveries WHERE id_delivery = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Delivery tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['id_kurir'])) {
        $id_kurir = $_POST['id_kurir'] ? "'" . $db->escape($_POST['id_kurir']) . "'" : "NULL";
        $updates[] = "id_kurir = $id_kurir";
    }
    
    if (isset($_POST['nama_penerima'])) {
        $nama = $db->escape($_POST['nama_penerima']);
        $updates[] = "nama_penerima = '$nama'";
    }
    
    if (isset($_POST['no_telepon_penerima'])) {
        $telepon = $db->escape($_POST['no_telepon_penerima']);
        $updates[] = "no_telepon_penerima = '$telepon'";
    }
    
    if (isset($_POST['alamat_lengkap'])) {
        $alamat = $db->escape($_POST['alamat_lengkap']);
        $updates[] = "alamat_lengkap = '$alamat'";
    }
    
    if (isset($_POST['kota'])) {
        $kota = $db->escape($_POST['kota']);
        $updates[] = "kota = '$kota'";
    }
    
    if (isset($_POST['provinsi'])) {
        $provinsi = $db->escape($_POST['provinsi']);
        $updates[] = "provinsi = '$provinsi'";
    }
    
    if (isset($_POST['catatan_pengiriman'])) {
        $catatan = $db->escape($_POST['catatan_pengiriman']);
        $updates[] = "catatan_pengiriman = '$catatan'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE deliveries SET " . implode(', ', $updates) . " WHERE id_delivery = '$id'";
    $db->query($sql);
    
    Response::success(['id_delivery' => $id], 'Delivery berhasil diupdate');
}

// ============================================
// DELETE - Hapus pengiriman
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID delivery tidak ditemukan', 400);
    }
    
    // Cek delivery ada
    $checkSql = "SELECT id_delivery FROM deliveries WHERE id_delivery = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Delivery tidak ditemukan');
    }
    
    // Hard delete
    $sql = "DELETE FROM deliveries WHERE id_delivery = '$id'";
    $db->query($sql);
    
    Response::success(['id_delivery' => $id], 'Delivery berhasil dihapus');
}
?>