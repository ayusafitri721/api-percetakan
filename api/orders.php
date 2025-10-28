<?php
/**
 * API Orders - CRUD Tabel orders
 * URL: http://localhost/api-percetakan/api/orders.php
 * FIXED: Hapus status_pembayaran hardcoded, pakai status_order aja
 */

// ENABLE ERROR REPORTING FOR DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../helpers/Response.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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
// GET ALL - Ambil semua pesanan (FIXED)
// ============================================
function getAll($db) {
    $sql = "SELECT o.*, 
            u.nama as nama_customer, 
            u.email as email_customer, 
            u.no_telepon as telepon_customer,
            u.alamat as alamat_pengiriman,
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
            'nama_customer' => $row['nama_customer'],
            'email_customer' => $row['email_customer'],
            'telepon_customer' => $row['telepon_customer'],
            'alamat_pengiriman' => $row['alamat_pengiriman'],
            'nama_kasir' => $row['nama_kasir'],
            'tanggal_order' => $row['tanggal_order'],
            'jenis_order' => $row['jenis_order'],
            'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
            'status_order' => $row['status_order'],
            // ✅ REMOVED: status_pembayaran (gak perlu, frontend pakai status_order)
            'subtotal' => $row['subtotal'],
            'diskon' => $row['diskon'],
            'ongkir' => $row['ongkir'],
            'total_harga' => $row['total_harga'],
            'catatan' => $row['catatan_pelanggan'],
            'catatan_internal' => $row['catatan_internal'],
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
    $id_user = $_GET['id_user'] ?? '';
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
        return;
    }
    
    $id_user = intval($id_user);
    
    $sql = "SELECT * FROM orders 
            WHERE id_user = $id_user
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
    $status = $_GET['status'] ?? '';
    
    if (empty($status)) {
        Response::error('Status tidak ditemukan', 400);
        return;
    }
    
    $status = $db->real_escape_string($status);
    
    $sql = "SELECT o.*, 
            u.nama as nama_customer
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
            'nama_customer' => $row['nama_customer'],
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
    $id = $_POST['id_order'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($id) || empty($status)) {
        Response::error('ID order dan status wajib diisi', 400);
        return;
    }
    
    $id = intval($id);
    $status = $db->real_escape_string($status);
    
    // Cek order ada
    $checkSql = "SELECT id_order FROM orders WHERE id_order = $id";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    // Update status
    $sql = "UPDATE orders SET status_order = '$status'";
    
    // Jika status selesai, set tanggal selesai
    if ($status === 'selesai') {
        $sql .= ", tanggal_selesai = NOW()";
    }
    
    $sql .= " WHERE id_order = $id";
    $db->query($sql);
    
    Response::success(['id_order' => $id, 'status' => $status], 'Status order berhasil diupdate');
}

// ============================================
// CREATE - Tambah order baru (FIXED)
// ============================================
function create($db) {
    try {
        error_log("=== CREATE ORDER CALLED ===");
        error_log("POST data: " . print_r($_POST, true));
        
        // Ambil data POST
        $id_user = $_POST['id_user'] ?? '';
        $id_kasir = $_POST['id_kasir'] ?? '';
        $jenis_order = $_POST['jenis_order'] ?? 'online';
        $kecepatan = $_POST['kecepatan_pengerjaan'] ?? 'normal';
        $subtotal = $_POST['subtotal'] ?? 0;
        $diskon = $_POST['diskon'] ?? 0;
        $ongkir = $_POST['ongkir'] ?? 0;
        $total_harga = $_POST['total_harga'] ?? 0;
        $catatan_pelanggan = $_POST['catatan_pelanggan'] ?? '';
        $catatan_internal = $_POST['catatan_internal'] ?? '';
        $status_order = $_POST['status_order'] ?? 'pending'; // ✅ BISA SET STATUS AWAL
        
        error_log("Parsed - id_user: $id_user, id_kasir: $id_kasir, status: $status_order");
        
        // Validasi
        if (empty($id_user)) {
            error_log("ERROR: ID user kosong");
            Response::error('ID user wajib diisi', 400);
            return;
        }
        
        // Cek user ada
        $checkUser = "SELECT id_user FROM users WHERE id_user = " . intval($id_user);
        $resultUser = $db->query($checkUser);
        
        if (!$resultUser || $resultUser->num_rows === 0) {
            error_log("ERROR: User $id_user tidak ditemukan");
            Response::error('User tidak ditemukan', 400);
            return;
        }
        
        error_log("User found, generating order code...");
        
        // Generate kode order
        $tanggal_str = date('YmdHis');
        $countSql = "SELECT COUNT(*) as total FROM orders WHERE DATE(tanggal_order) = CURDATE()";
        $countResult = $db->query($countSql);
        $counter = $countResult->fetch_assoc()['total'] + 1;
        $kode_order = 'ORD-' . $tanggal_str . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        
        error_log("Generated order code: $kode_order");
        
        // Prepare values
        $id_kasir_value = (!empty($id_kasir) && $id_kasir !== '') ? intval($id_kasir) : 'NULL';
        
        // Escape string values
        $jenis_order_escaped = $db->real_escape_string($jenis_order);
        $kecepatan_escaped = $db->real_escape_string($kecepatan);
        $catatan_escaped = $db->real_escape_string($catatan_pelanggan);
        $catatan_internal_escaped = $db->real_escape_string($catatan_internal);
        $status_escaped = $db->real_escape_string($status_order);
        
        // Insert
        $sql = "INSERT INTO orders (
                    kode_order, id_user, id_kasir, jenis_order, 
                    kecepatan_pengerjaan, subtotal, diskon, ongkir, 
                    total_harga, catatan_pelanggan, catatan_internal, status_order
                ) VALUES (
                    '$kode_order',
                    " . intval($id_user) . ",
                    $id_kasir_value,
                    '$jenis_order_escaped',
                    '$kecepatan_escaped',
                    " . floatval($subtotal) . ",
                    " . floatval($diskon) . ",
                    " . floatval($ongkir) . ",
                    " . floatval($total_harga) . ",
                    '$catatan_escaped',
                    '$catatan_internal_escaped',
                    '$status_escaped'
                )";
        
        error_log("SQL: $sql");
        
        if (!$db->query($sql)) {
            error_log("ERROR: Query failed - " . $db->error);
            Response::error('Database error: ' . $db->error, 500);
            return;
        }
        
        $insertId = $db->insert_id;
        error_log("Insert successful! ID: $insertId");
        
        // Response SUCCESS
        Response::success([
            'id_order' => $insertId,
            'kode_order' => $kode_order
        ], 'Order berhasil dibuat');
        
        error_log("Response sent successfully!");
        
    } catch (Exception $e) {
        error_log("EXCEPTION: " . $e->getMessage());
        Response::error('Server error: ' . $e->getMessage(), 500);
    }
}

// ============================================
// DETAIL - Detail order (FIXED)
// ============================================
function detail($db) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $id = intval($id);
    
    $sql = "SELECT o.*, 
            u.nama as nama_customer, 
            u.email as email_customer, 
            u.no_telepon as telepon_customer, 
            u.alamat as alamat_pengiriman,
            k.nama as nama_kasir
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users k ON o.id_kasir = k.id_user
            WHERE o.id_order = $id";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    $row = $result->fetch_assoc();
    
    // Get order items
    $itemsSql = "SELECT oi.id_item, oi.id_product, oi.ukuran, oi.jumlah, 
                 oi.harga_satuan, oi.subtotal, oi.keterangan,
                 p.nama_product, p.gambar_preview
                 FROM order_items oi
                 LEFT JOIN products p ON oi.id_product = p.id_product
                 WHERE oi.id_order = $id";
    $itemsResult = $db->query($itemsSql);
    
    $items = [];
    if ($itemsResult) {
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = [
                'id_item' => $item['id_item'] ?? '',
                'id_produk' => $item['id_product'] ?? '',
                'nama_produk' => $item['nama_product'] ?? 'Produk tidak ditemukan',
                'jumlah' => intval($item['jumlah'] ?? 0),
                'harga_satuan' => floatval($item['harga_satuan'] ?? 0),
                'subtotal' => floatval($item['subtotal'] ?? 0),
                'catatan_item' => $item['keterangan'] ?? '',
                'ukuran' => $item['ukuran'] ?? '',
                'gambar_preview' => $item['gambar_preview'] ?? ''
            ];
        }
    }
    
    error_log("Items fetched: " . count($items) . " items");
    
    $data = [
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'id_user' => $row['id_user'],
        'nama_customer' => $row['nama_customer'],
        'email_customer' => $row['email_customer'],
        'telepon_customer' => $row['telepon_customer'],
        'alamat_pengiriman' => $row['alamat_pengiriman'],
        'nama_kasir' => $row['nama_kasir'],
        'tanggal_order' => $row['tanggal_order'],
        'jenis_order' => $row['jenis_order'],
        'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
        'status_order' => $row['status_order'],
        // ✅ REMOVED: status_pembayaran (frontend pakai status_order)
        'subtotal' => $row['subtotal'],
        'diskon' => $row['diskon'],
        'ongkir' => $row['ongkir'],
        'total_harga' => $row['total_harga'],
        'catatan' => $row['catatan_pelanggan'],
        'catatan_internal' => $row['catatan_internal'],
        'tanggal_selesai' => $row['tanggal_selesai'],
        'items' => $items,
        'payment' => null,
        'delivery' => null
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update order (FIXED)
// ============================================
function update($db) {
    error_log("=== UPDATE ORDER CALLED ===");
    error_log("GET params: " . print_r($_GET, true));
    error_log("POST params: " . print_r($_POST, true));
    
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $id = intval($id);
    
    // Cek order ada
    $checkSql = "SELECT id_order FROM orders WHERE id_order = $id";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['status_order'])) {
        $status = $db->real_escape_string($_POST['status_order']);
        $updates[] = "status_order = '$status'";
        
        if ($status === 'selesai') {
            $updates[] = "tanggal_selesai = NOW()";
        }
        
        error_log("Status order will be updated to: $status");
    }
    
    if (isset($_POST['kecepatan_pengerjaan'])) {
        $kecepatan = $db->real_escape_string($_POST['kecepatan_pengerjaan']);
        $updates[] = "kecepatan_pengerjaan = '$kecepatan'";
    }
    
    if (isset($_POST['subtotal'])) {
        $subtotal = floatval($_POST['subtotal']);
        $updates[] = "subtotal = $subtotal";
    }
    
    if (isset($_POST['diskon'])) {
        $diskon = floatval($_POST['diskon']);
        $updates[] = "diskon = $diskon";
    }
    
    if (isset($_POST['ongkir'])) {
        $ongkir = floatval($_POST['ongkir']);
        $updates[] = "ongkir = $ongkir";
    }
    
    if (isset($_POST['total_harga'])) {
        $total = floatval($_POST['total_harga']);
        $updates[] = "total_harga = $total";
    }
    
    if (isset($_POST['catatan_internal'])) {
        $catatan = $db->real_escape_string($_POST['catatan_internal']);
        $updates[] = "catatan_internal = '$catatan'";
    }
    
    if (empty($updates)) {
        error_log("ERROR: No data to update");
        Response::error('Tidak ada data yang diupdate', 400);
        return;
    }
    
    $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id_order = $id";
    error_log("SQL: $sql");
    
    if (!$db->query($sql)) {
        error_log("ERROR: Query failed - " . $db->error);
        Response::error('Database error: ' . $db->error, 500);
        return;
    }
    
    error_log("Update successful!");
    Response::success(['id_order' => $id], 'Order berhasil diupdate');
}

// ============================================
// DELETE - Hapus order (soft delete)
// ============================================
function delete($db) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $id = intval($id);
    
    // Cek order ada
    $checkSql = "SELECT id_order FROM orders WHERE id_order = $id";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    // Update status jadi dibatalkan
    $sql = "UPDATE orders SET status_order = 'dibatalkan' WHERE id_order = $id";
    $db->query($sql);
    
    Response::success(['id_order' => $id], 'Order berhasil dibatalkan');
}
?>