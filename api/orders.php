<?php
/**
 * API Orders - COMPLETE WITH COD & TRACKING SUPPORT
 * Status Flow: pending -> validasi -> proses -> siap -> dikirim -> selesai
 * COD Payment: Auto update to 'lunas' when status = 'selesai'
 */

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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->connect();

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
// HELPER: Validasi Status Order - More flexible
// ============================================
function validateStatusOrder($status) {
    if (empty($status)) {
        return false;
    }
    $validStatuses = ['pending', 'validasi', 'proses', 'siap', 'dikirim', 'selesai', 'dibatalkan', 'dibayar', 'diproses', 'cetak'];
    return in_array(strtolower($status), $validStatuses);
}

// ============================================
// GET ALL ORDERS - FIXED: Without id_kurir
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
        error_log("❌ Query error: " . $db->error);
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
    
    error_log("✅ Total orders fetched: " . count($data));
    
    // CONSISTENT RESPONSE STRUCTURE
    Response::success([
        'total' => count($data),
        'orders' => $data
    ]);
}

// ============================================
// BY USER - FIXED: Without id_kurir
// ============================================
function byUser($db) {
    $id_user = $_GET['id_user'] ?? '';
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
        return;
    }
    
    $id_user = intval($id_user);
    
    $sql = "SELECT o.*, 
            p.status_pembayaran,
            p.metode_pembayaran
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
            'metode_pembayaran' => $row['metode_pembayaran'],
            'total_harga' => floatval($row['total_harga'])
        ];
    }
    
    Response::success([
        'total' => count($data),
        'orders' => $data
    ]);
}

// ============================================
// BY STATUS - FIXED: Without id_kurir
// ============================================
function byStatus($db) {
    $status = $_GET['status'] ?? '';
    
    if (empty($status)) {
        Response::error('Status tidak ditemukan', 400);
        return;
    }
    
    $status = $db->real_escape_string($status);
    
    if (!validateStatusOrder($status)) {
        Response::error('Status order tidak valid', 400);
        return;
    }
    
    $sql = "SELECT o.*, 
            u.nama as nama_customer,
            p.status_pembayaran,
            p.metode_pembayaran
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
            'metode_pembayaran' => $row['metode_pembayaran'],
            'total_harga' => floatval($row['total_harga'])
        ];
    }
    
    Response::success([
        'total' => count($data),
        'orders' => $data
    ]);
}

// ============================================
// UPDATE STATUS - WITH COD AUTO PAYMENT UPDATE
// ============================================
function updateStatus($db) {
    error_log("=== UPDATE STATUS CALLED ===");
    
    $id = $_POST['id_order'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($id) || empty($status)) {
        Response::error('ID order dan status wajib diisi', 400);
        return;
    }
    
    $id = intval($id);
    $status = $db->real_escape_string($status);
    
    // Validasi status
    if (!validateStatusOrder($status)) {
        Response::error('Status order tidak valid. Gunakan: pending, validasi, proses, siap, dikirim, selesai, dibatalkan', 400);
        return;
    }
    
    error_log("Updating order $id to status: $status");
    
    // Cek order exists dan ambil info payment
    $checkSql = "SELECT o.id_order, p.id_payment, p.metode_pembayaran, p.status_pembayaran
                 FROM orders o
                 LEFT JOIN payments p ON o.id_order = p.id_order
                 WHERE o.id_order = $id";
    $checkResult = $db->query($checkSql);
    
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    $orderData = $checkResult->fetch_assoc();
    $isCOD = ($orderData['metode_pembayaran'] === 'cod');
    
    error_log("Order found - COD: " . ($isCOD ? 'YES' : 'NO'));
    error_log("Current payment status: " . $orderData['status_pembayaran']);
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // 1. Update order status
        $updateOrderSql = "UPDATE orders SET status_order = '$status'";
        
        // Set tanggal_selesai untuk status selesai
        if ($status === 'selesai') {
            $updateOrderSql .= ", tanggal_selesai = NOW()";
        }
        
        $updateOrderSql .= " WHERE id_order = $id";
        
        if (!$db->query($updateOrderSql)) {
            throw new Exception("Failed to update order status: " . $db->error);
        }
        
        error_log("✅ Order status updated to: $status");
        
        // 2. Auto update payment status untuk COD ketika selesai
        if ($isCOD && $status === 'selesai' && $orderData['status_pembayaran'] !== 'lunas') {
            error_log("💰 Auto updating COD payment to lunas...");
            
            $updatePaymentSql = "UPDATE payments 
                                SET status_pembayaran = 'lunas',
                                    tanggal_bayar = NOW()
                                WHERE id_order = $id";
            
            if (!$db->query($updatePaymentSql)) {
                throw new Exception("Failed to update payment status: " . $db->error);
            }
            
            error_log("✅ Payment status updated to: lunas");
        }
        
        // Commit transaction
        $db->commit();
        
        Response::success([
            'id_order' => $id,
            'status' => $status,
            'cod_auto_paid' => ($isCOD && $status === 'selesai')
        ], 'Status order berhasil diupdate' . ($isCOD && $status === 'selesai' ? ' dan pembayaran COD otomatis lunas' : ''));
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("❌ Transaction failed: " . $e->getMessage());
        Response::error('Update gagal: ' . $e->getMessage(), 500);
    }
}

// ============================================
// DETAIL ORDER - FIXED: Without id_kurir initially
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
            p.metode_pembayaran,
            p.tanggal_bayar
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
                'id_item' => $item['id_item'],
                'id_produk' => $item['id_product'],
                'nama_produk' => $item['nama_product'] ?? 'Produk tidak ditemukan',
                'jumlah' => intval($item['jumlah']),
                'harga_satuan' => floatval($item['harga_satuan']),
                'subtotal' => floatval($item['subtotal']),
                'catatan_item' => $item['keterangan'] ?? '',
                'ukuran' => $item['ukuran'] ?? '',
                'gambar_preview' => $item['gambar_preview'] ?? ''
            ];
        }
    }
    
    // Build tracking history
    $trackingHistory = [];
    
    // Status tracking berdasarkan status order
    $statusMap = [
        'pending' => ['status' => 'pending', 'label' => 'Pesanan Dibuat', 'icon' => '📝'],
        'validasi' => ['status' => 'validasi', 'label' => 'Menunggu Validasi', 'icon' => '⏳'],
        'proses' => ['status' => 'proses', 'label' => 'Sedang Diproses', 'icon' => '🔨'],
        'siap' => ['status' => 'siap', 'label' => 'Siap Dikirim', 'icon' => '📦'],
        'dikirim' => ['status' => 'dikirim', 'label' => 'Dalam Pengiriman', 'icon' => '🚚'],
        'selesai' => ['status' => 'selesai', 'label' => 'Pesanan Selesai', 'icon' => '✅']
    ];
    
    $currentStatus = $row['status_order'];
    $statusOrder = ['pending', 'validasi', 'proses', 'siap', 'dikirim', 'selesai'];
    $currentIndex = array_search($currentStatus, $statusOrder);
    
    foreach ($statusOrder as $index => $status) {
        if ($index <= $currentIndex) {
            $trackingHistory[] = [
                'status' => $status,
                'label' => $statusMap[$status]['label'],
                'icon' => $statusMap[$status]['icon'],
                'timestamp' => $row['tanggal_order'], // Simplified - use order date
                'completed' => true
            ];
        }
    }
    
    $data = [
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'id_user' => $row['id_user'],
        'nama_customer' => $row['nama_customer'],
        'email_customer' => $row['email_customer'],
        'telepon_customer' => $row['telepon_customer'],
        'alamat_pengiriman' => $row['alamat_pengiriman'],
        'nama_kasir' => $row['nama_kasir'],
        'nama_kurir' => null, // Will be added later when column exists
        'telepon_kurir' => null, // Will be added later when column exists
        'tanggal_order' => $row['tanggal_order'],
        'jenis_order' => $row['jenis_order'],
        'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
        'status_order' => $row['status_order'],
        'status_pembayaran' => $row['status_pembayaran'],
        'metode_pembayaran' => $row['metode_pembayaran'],
        'tanggal_bayar' => $row['tanggal_bayar'],
        'subtotal' => floatval($row['subtotal']),
        'diskon' => floatval($row['diskon']),
        'ongkir' => floatval($row['ongkir']),
        'total_harga' => floatval($row['total_harga']),
        'catatan' => $row['catatan_pelanggan'],
        'catatan_internal' => $row['catatan_internal'],
        'tanggal_selesai' => $row['tanggal_selesai'],
        'items' => $items,
        'tracking_history' => $trackingHistory
    ];
    
    Response::success($data);
}

// ============================================
// CREATE ORDER - FIXED: More tolerant validation
// ============================================
function create($db) {
    try {
        error_log("=== CREATE ORDER CALLED ===");
        error_log("POST data: " . print_r($_POST, true));
        
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
        
        error_log("Parsed data - id_user: $id_user, jenis_order: $jenis_order, status: $status_order");
        
        // Validasi status - lebih fleksibel
        if (!empty($status_order) && !validateStatusOrder($status_order)) {
            error_log("WARNING: Invalid status '$status_order', using 'pending'");
            $status_order = 'pending'; // Default ke pending jika invalid
        }
        
        // Validasi user - HANYA INI YANG REQUIRED
        if (empty($id_user) || $id_user === '0') {
            error_log("ERROR: ID user kosong atau invalid");
            Response::error('ID user wajib diisi', 400);
            return;
        }
        
        // Cek user exists - optional, tapi recommended
        $checkUser = "SELECT id_user FROM users WHERE id_user = " . intval($id_user);
        $resultUser = $db->query($checkUser);
        if (!$resultUser || $resultUser->num_rows === 0) {
            error_log("ERROR: User ID $id_user tidak ditemukan di database");
            Response::error('User tidak ditemukan', 400);
            return;
        }
        
        error_log("✅ User validated, generating order code...");
        
        // Generate kode order
        $tanggal_str = date('YmdHis');
        $countSql = "SELECT COUNT(*) as total FROM orders WHERE DATE(tanggal_order) = CURDATE()";
        $countResult = $db->query($countSql);
        $counter = $countResult->fetch_assoc()['total'] + 1;
        $kode_order = 'ORD-' . $tanggal_str . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        
        error_log("Generated kode_order: $kode_order");
        
        // Prepare values - pastikan tidak ada string kosong untuk numeric fields
        $id_kasir_value = (!empty($id_kasir) && $id_kasir !== '0') ? intval($id_kasir) : 'NULL';
        $subtotal_value = !empty($subtotal) ? floatval($subtotal) : 0;
        $diskon_value = !empty($diskon) ? floatval($diskon) : 0;
        $ongkir_value = !empty($ongkir) ? floatval($ongkir) : 0;
        $total_harga_value = !empty($total_harga) ? floatval($total_harga) : 0;
        
        // Escape strings
        $jenis_order_escaped = $db->real_escape_string($jenis_order);
        $kecepatan_escaped = $db->real_escape_string($kecepatan);
        $catatan_escaped = $db->real_escape_string($catatan_pelanggan);
        $catatan_internal_escaped = $db->real_escape_string($catatan_internal);
        $status_escaped = $db->real_escape_string($status_order);
        
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
                    $subtotal_value,
                    $diskon_value,
                    $ongkir_value,
                    $total_harga_value,
                    '$catatan_escaped',
                    '$catatan_internal_escaped',
                    '$status_escaped'
                )";
        
        error_log("SQL: $sql");
        
        if (!$db->query($sql)) {
            error_log("❌ Query failed: " . $db->error);
            Response::error('Database error: ' . $db->error, 500);
            return;
        }
        
        $insertId = $db->insert_id;
        error_log("✅ Order created with ID: $insertId");
        
        Response::success([
            'id_order' => $insertId,
            'kode_order' => $kode_order
        ], 'Order berhasil dibuat');
        
    } catch (Exception $e) {
        error_log("❌ EXCEPTION: " . $e->getMessage());
        Response::error('Server error: ' . $e->getMessage(), 500);
    }
}

// ============================================
// UPDATE ORDER
// ============================================
function update($db) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $id = intval($id);
    
    $checkSql = "SELECT id_order FROM orders WHERE id_order = $id";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    $updates = [];
    
    if (isset($_POST['status_order'])) {
        $status = $db->real_escape_string($_POST['status_order']);
        if (!validateStatusOrder($status)) {
            Response::error('Status order tidak valid', 400);
            return;
        }
        $updates[] = "status_order = '$status'";
        
        if ($status === 'selesai') {
            $updates[] = "tanggal_selesai = NOW()";
        }
    }
    
    if (isset($_POST['id_kurir'])) {
        $id_kurir = intval($_POST['id_kurir']);
        $updates[] = "id_kurir = $id_kurir";
    }
    
    if (isset($_POST['kecepatan_pengerjaan'])) {
        $updates[] = "kecepatan_pengerjaan = '" . $db->real_escape_string($_POST['kecepatan_pengerjaan']) . "'";
    }
    
    if (isset($_POST['catatan_internal'])) {
        $updates[] = "catatan_internal = '" . $db->real_escape_string($_POST['catatan_internal']) . "'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
        return;
    }
    
    $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id_order = $id";
    
    if (!$db->query($sql)) {
        Response::error('Database error: ' . $db->error, 500);
        return;
    }
    
    Response::success(['id_order' => $id], 'Order berhasil diupdate');
}

// ============================================
// DELETE ORDER (soft delete)
// ============================================
function delete($db) {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $id = intval($id);
    
    $checkSql = "SELECT id_order FROM orders WHERE id_order = $id";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Order tidak ditemukan');
        return;
    }
    
    $sql = "UPDATE orders SET status_order = 'dibatalkan' WHERE id_order = $id";
    $db->query($sql);
    
    Response::success(['id_order' => $id], 'Order berhasil dibatalkan');
}

// ============================================
// STATISTICS
// ============================================
function statistics($db) {
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    $whereDate = '';
    if (!empty($startDate) && !empty($endDate)) {
        $whereDate = "WHERE DATE(o.tanggal_order) BETWEEN '$startDate' AND '$endDate'";
    } else {
        $whereDate = "WHERE MONTH(o.tanggal_order) = MONTH(CURRENT_DATE()) 
                      AND YEAR(o.tanggal_order) = YEAR(CURRENT_DATE())";
    }
    
    $totalSql = "SELECT 
                    COALESCE(SUM(total_harga), 0) as total_penjualan,
                    COUNT(*) as jumlah_transaksi
                 FROM orders o
                 $whereDate 
                 AND status_order NOT IN ('dibatalkan', 'pending')";
    
    $totalResult = $db->query($totalSql);
    $totalData = $totalResult->fetch_assoc();
    
    Response::success([
        'total_penjualan' => floatval($totalData['total_penjualan']),
        'jumlah_transaksi' => intval($totalData['jumlah_transaksi'])
    ]);
}

// ============================================
// SALES REPORT
// ============================================
function salesReport($db) {
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    $whereDate = '';
    if (!empty($startDate) && !empty($endDate)) {
        $whereDate = "AND DATE(o.tanggal_order) BETWEEN '$startDate' AND '$endDate'";
    }
    
    $sql = "SELECT 
                o.id_order,
                o.kode_order,
                o.tanggal_order,
                o.status_order,
                o.total_harga,
                u.nama as nama_customer,
                p.metode_pembayaran,
                p.status_pembayaran
            FROM orders o
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN payments p ON o.id_order = p.id_order
            WHERE o.status_order NOT IN ('dibatalkan')
            $whereDate
            ORDER BY o.tanggal_order DESC";
    
    $result = $db->query($sql);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'tanggal_order' => $row['tanggal_order'],
            'nama_customer' => $row['nama_customer'],
            'status_order' => $row['status_order'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'status_pembayaran' => $row['status_pembayaran'],
            'total_harga' => floatval($row['total_harga'])
        ];
    }
    
    Response::success(['orders' => $data]);
}
?>