<?php
/**
 * API Orders - CRUD Tabel orders
 * FIXED: Support status 'siap' untuk kurir dan handle FormData
 * ENUM: pending, dibayar, diproses, validasi, cetak, selesai, dikirim, dibatalkan, siap
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
    case 'statistics':
        statistics($db);
        break;
    case 'sales_report':
        salesReport($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// HELPER: Validasi Status Order
// ============================================
function validateStatusOrder($status) {
    // ✅ FIXED: Tambah 'siap' untuk kurir
    $validStatuses = ['pending', 'dibayar', 'diproses', 'validasi', 'cetak', 'selesai', 'dikirim', 'dibatalkan', 'siap'];
    return in_array($status, $validStatuses) ? $status : false;
}

// ============================================
// STATISTICS - Untuk Dashboard Laporan
// ============================================
function statistics($db) {
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    $whereDate = '';
    if (!empty($startDate) && !empty($endDate)) {
        $startDate = $db->real_escape_string($startDate);
        $endDate = $db->real_escape_string($endDate);
        $whereDate = "WHERE DATE(o.tanggal_order) BETWEEN '$startDate' AND '$endDate'";
    } else {
        // Default: bulan ini
        $whereDate = "WHERE MONTH(o.tanggal_order) = MONTH(CURRENT_DATE()) 
                      AND YEAR(o.tanggal_order) = YEAR(CURRENT_DATE())";
    }
    
    // Total Penjualan (exclude dibatalkan dan pending)
    $totalSql = "SELECT 
                    COALESCE(SUM(total_harga), 0) as total_penjualan,
                    COUNT(*) as jumlah_transaksi
                 FROM orders o
                 $whereDate 
                 AND status_order NOT IN ('dibatalkan', 'pending')";
    
    $totalResult = $db->query($totalSql);
    $totalData = $totalResult->fetch_assoc();
    
    // Total Item Terjual
    $itemSql = "SELECT COALESCE(SUM(oi.jumlah), 0) as total_item
                FROM order_items oi
                INNER JOIN orders o ON oi.id_order = o.id_order
                $whereDate
                AND o.status_order NOT IN ('dibatalkan', 'pending')";
    
    $itemResult = $db->query($itemSql);
    $itemData = $itemResult->fetch_assoc();
    
    // Produk Terlaris
    $topProductSql = "SELECT 
                        p.nama_product,
                        SUM(oi.jumlah) as total_terjual,
                        SUM(oi.subtotal) as total_pendapatan
                      FROM order_items oi
                      INNER JOIN orders o ON oi.id_order = o.id_order
                      INNER JOIN products p ON oi.id_product = p.id_product
                      $whereDate
                      AND o.status_order NOT IN ('dibatalkan', 'pending')
                      GROUP BY oi.id_product
                      ORDER BY total_terjual DESC
                      LIMIT 5";
    
    $topProductResult = $db->query($topProductSql);
    $topProducts = [];
    while ($row = $topProductResult->fetch_assoc()) {
        $topProducts[] = $row;
    }
    
    Response::success([
        'total_penjualan' => floatval($totalData['total_penjualan']),
        'jumlah_transaksi' => intval($totalData['jumlah_transaksi']),
        'total_item' => intval($itemData['total_item']),
        'produk_terlaris' => $topProducts,
        'periode' => [
            'start_date' => $startDate ?: date('Y-m-01'),
            'end_date' => $endDate ?: date('Y-m-d')
        ]
    ]);
}

// ============================================
// SALES REPORT - Laporan Penjualan Detail
// ============================================
function salesReport($db) {
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    $whereDate = '';
    if (!empty($startDate) && !empty($endDate)) {
        $startDate = $db->real_escape_string($startDate);
        $endDate = $db->real_escape_string($endDate);
        $whereDate = "AND DATE(o.tanggal_order) BETWEEN '$startDate' AND '$endDate'";
    }
    
    $sql = "SELECT 
                o.id_order,
                o.kode_order,
                o.tanggal_order,
                o.status_order,
                o.total_harga,
                u.nama as nama_customer,
                u.email as email_customer,
                (SELECT COUNT(*) FROM order_items WHERE id_order = o.id_order) as jumlah_item
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            WHERE o.status_order NOT IN ('dibatalkan')
            $whereDate
            ORDER BY o.tanggal_order DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    $totalPenjualan = 0;
    $totalTransaksi = 0;
    $totalItem = 0;
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'tanggal_order' => $row['tanggal_order'],
            'nama_customer' => $row['nama_customer'],
            'email_customer' => $row['email_customer'],
            'status_order' => $row['status_order'],
            'total_harga' => floatval($row['total_harga']),
            'jumlah_item' => intval($row['jumlah_item'])
        ];
        
        if ($row['status_order'] !== 'pending') {
            $totalPenjualan += floatval($row['total_harga']);
            $totalTransaksi++;
            $totalItem += intval($row['jumlah_item']);
        }
    }
    
    Response::success([
        'total_penjualan' => $totalPenjualan,
        'jumlah_transaksi' => $totalTransaksi,
        'total_item' => $totalItem,
        'orders' => $data
    ]);
}

// ============================================
// GET ALL - Ambil semua pesanan
// ============================================
function getAll($db) {
    error_log("=== GET ALL ORDERS ===");
    
    $sql = "SELECT o.*, 
            u.nama as nama_customer, 
            u.email as email_customer, 
            u.no_telepon as telepon_customer,
            u.alamat as alamat_pengiriman,
            k.nama as nama_kasir,
            p.status_pembayaran,
            p.metode_pembayaran,
            (SELECT COUNT(*) FROM order_items WHERE id_order = o.id_order) as jumlah_item
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users k ON o.id_kasir = k.id_user
            LEFT JOIN payments p ON o.id_order = p.id_order
            ORDER BY o.tanggal_order DESC";
    
    $result = $db->query($sql);
    
    if (!$result) {
        error_log("Query error: " . $db->error);
        Response::error('Database query failed: ' . $db->error, 500);
        return;
    }
    
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
            'status_pembayaran' => $row['status_pembayaran'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'subtotal' => floatval($row['subtotal']),
            'diskon' => floatval($row['diskon']),
            'ongkir' => floatval($row['ongkir']),
            'total_harga' => floatval($row['total_harga']),
            'catatan' => $row['catatan_pelanggan'],
            'catatan_internal' => $row['catatan_internal'],
            'tanggal_selesai' => $row['tanggal_selesai'],
            'jumlah_item' => intval($row['jumlah_item'])
        ];
    }
    
    error_log("Total orders: " . count($data));
    
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
    
    $sql = "SELECT o.*, p.status_pembayaran 
            FROM orders o
            LEFT JOIN payments p ON o.id_order = p.id_order
            WHERE o.id_user = $id_user
            ORDER BY o.tanggal_order DESC";
    
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
            'status_pembayaran' => $row['status_pembayaran'],
            'total_harga' => floatval($row['total_harga'])
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
    
    // Validasi status
    if (!validateStatusOrder($status)) {
        Response::error('Status order tidak valid. Gunakan: pending, dibayar, diproses, validasi, cetak, selesai, dikirim, dibatalkan, siap', 400);
        return;
    }
    
    $sql = "SELECT o.*, 
            u.nama as nama_customer,
            p.status_pembayaran
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN payments p ON o.id_order = p.id_order
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
            'status_pembayaran' => $row['status_pembayaran'],
            'total_harga' => floatval($row['total_harga'])
        ];
    }
    
    Response::success([
        'total' => count($data),
        'orders' => $data
    ]);
}

// ============================================
// UPDATE STATUS - Update status order (FIXED)
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
    
    // ✅ Validasi status sesuai ENUM
    if (!validateStatusOrder($status)) {
        Response::error('Status order tidak valid. Gunakan: pending, dibayar, diproses, validasi, cetak, selesai, dikirim, dibatalkan, siap', 400);
        return;
    }
    
    // Cek order ada
    $checkSql = "SELECT id_order FROM orders WHERE id_order = $id";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    // Update status
    $sql = "UPDATE orders SET status_order = '$status'";
    
    // Jika status dikirim (diserahkan ke customer), set tanggal selesai
    if ($status === 'dikirim' || $status === 'selesai') {
        $sql .= ", tanggal_selesai = NOW()";
    }
    
    $sql .= " WHERE id_order = $id";
    
    if (!$db->query($sql)) {
        Response::error('Database error: ' . $db->error, 500);
        return;
    }
    
    Response::success(['id_order' => $id, 'status' => $status], 'Status order berhasil diupdate');
}

// ============================================
// CREATE - Tambah order baru
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
        $status_order = $_POST['status_order'] ?? 'pending';
        
        // ✅ Validasi status order
        if (!validateStatusOrder($status_order)) {
            error_log("ERROR: Status order tidak valid: $status_order");
            Response::error('Status order tidak valid. Gunakan: pending, dibayar, diproses, validasi, cetak, selesai, dikirim, dibatalkan, siap', 400);
            return;
        }
        
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
// DETAIL - Detail order
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
            k.nama as nama_kasir,
            p.status_pembayaran,
            p.metode_pembayaran
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users k ON o.id_kasir = k.id_user
            LEFT JOIN payments p ON o.id_order = p.id_order
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
        'status_pembayaran' => $row['status_pembayaran'],
        'metode_pembayaran' => $row['metode_pembayaran'],
        'subtotal' => floatval($row['subtotal']),
        'diskon' => floatval($row['diskon']),
        'ongkir' => floatval($row['ongkir']),
        'total_harga' => floatval($row['total_harga']),
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
// UPDATE - Update order (FIXED untuk FormData)
// ============================================
function update($db) {
    error_log("=== UPDATE ORDER CALLED ===");
    error_log("GET params: " . print_r($_GET, true));
    error_log("POST params: " . print_r($_POST, true));
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $id = intval($id);
    
    // Cek order ada dan ambil jenis_order
    $checkSql = "SELECT id_order, jenis_order FROM orders WHERE id_order = $id";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    $orderData = $checkResult->fetch_assoc();
    $jenis_order = $orderData['jenis_order'];
    error_log("Order found: ID=$id, jenis_order=$jenis_order");
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['status_order'])) {
        $status = $db->real_escape_string($_POST['status_order']);
        
        // ✅ Validasi status
        if (!validateStatusOrder($status)) {
            error_log("ERROR: Invalid status - $status");
            Response::error('Status order tidak valid. Gunakan: pending, dibayar, diproses, validasi, cetak, selesai, dikirim, dibatalkan, siap', 400);
            return;
        }
        
        $updates[] = "status_order = '$status'";
        
        if ($status === 'selesai' || $status === 'dikirim') {
            $updates[] = "tanggal_selesai = NOW()";
        }
        
        error_log("✅ Status order will be updated to: $status for jenis_order: $jenis_order");
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
    
    error_log("✅ Update successful!");
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