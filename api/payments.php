<?php
/**
 * API Payments - CRUD Tabel payments
 * URL: http://localhost/api-percetakan/api/payments.php
 * FIXED: Offline payment langsung set order ke "diproses"
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../helpers/Response.php';

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
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
    case 'by_order':
        byOrder($db);
        break;
    case 'confirm':
        confirmPayment($db);
        break;
    case 'pending':
        pendingPayments($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua pembayaran
// ============================================
function getAll($db) {
    $sql = "SELECT p.*, o.kode_order, u.nama as nama_pelanggan,
            adm.nama as nama_admin
            FROM payments p
            LEFT JOIN orders o ON p.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users adm ON p.id_admin_konfirmasi = adm.id_user
            ORDER BY p.tanggal_bayar DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_payment' => $row['id_payment'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'nama_bank' => $row['nama_bank'],
            'nomor_rekening' => $row['nomor_rekening'],
            'nama_pemilik' => $row['nama_pemilik'],
            'jumlah_bayar' => $row['jumlah_bayar'],
            'bukti_bayar' => $row['bukti_bayar'],
            'status_pembayaran' => $row['status_pembayaran'],
            'tanggal_bayar' => $row['tanggal_bayar'],
            'tanggal_konfirmasi' => $row['tanggal_konfirmasi'],
            'nama_admin' => $row['nama_admin'],
            'catatan' => $row['catatan']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'payments' => $data
    ]);
}

// ============================================
// BY ORDER - Pembayaran per order
// ============================================
function byOrder($db) {
    $id_order = $db->real_escape_string($_GET['id_order'] ?? '');
    
    if (empty($id_order)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $sql = "SELECT * FROM payments 
            WHERE id_order = '$id_order'
            ORDER BY tanggal_bayar DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_payment' => $row['id_payment'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'nama_bank' => $row['nama_bank'],
            'jumlah_bayar' => $row['jumlah_bayar'],
            'bukti_bayar' => $row['bukti_bayar'],
            'status_pembayaran' => $row['status_pembayaran'],
            'tanggal_bayar' => $row['tanggal_bayar'],
            'tanggal_konfirmasi' => $row['tanggal_konfirmasi']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'payments' => $data
    ]);
}

// ============================================
// PENDING - Pembayaran menunggu konfirmasi
// ============================================
function pendingPayments($db) {
    $sql = "SELECT p.*, o.kode_order, o.total_harga, u.nama as nama_pelanggan, u.no_telepon
            FROM payments p
            LEFT JOIN orders o ON p.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            WHERE p.status_pembayaran = 'pending'
            ORDER BY p.tanggal_bayar ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_payment' => $row['id_payment'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'no_telepon' => $row['no_telepon'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'nama_bank' => $row['nama_bank'],
            'jumlah_bayar' => $row['jumlah_bayar'],
            'total_harga' => $row['total_harga'],
            'bukti_bayar' => $row['bukti_bayar'],
            'tanggal_bayar' => $row['tanggal_bayar']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'payments' => $data
    ]);
}

// ============================================
// CONFIRM - Konfirmasi pembayaran
// ============================================
function confirmPayment($db) {
    $id = $db->real_escape_string($_POST['id_payment'] ?? '');
    $status = $db->real_escape_string($_POST['status_pembayaran'] ?? '');
    $id_admin = $db->real_escape_string($_POST['id_admin_konfirmasi'] ?? '');
    $catatan = $db->real_escape_string($_POST['catatan'] ?? '');
    
    if (empty($id) || empty($status) || empty($id_admin)) {
        Response::error('ID payment, status, dan ID admin wajib diisi', 400);
        return;
    }
    
    // Cek payment ada
    $checkSql = "SELECT id_order FROM payments WHERE id_payment = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Payment tidak ditemukan');
        return;
    }
    
    $id_order = $checkResult->fetch_assoc()['id_order'];
    
    // Update payment
    $sql = "UPDATE payments 
            SET status_pembayaran = '$status',
                id_admin_konfirmasi = '$id_admin',
                tanggal_konfirmasi = NOW(),
                catatan = '$catatan'
            WHERE id_payment = '$id'";
    
    $db->query($sql);
    
    // ✅ FIXED: Jika diterima, update status order ke "diproses" bukan "dibayar"
    if ($status === 'diterima') {
        $updateOrder = "UPDATE orders SET status_order = 'diproses' WHERE id_order = '$id_order'";
        $db->query($updateOrder);
    }
    
    Response::success([
        'id_payment' => $id,
        'status_pembayaran' => $status
    ], 'Pembayaran berhasil dikonfirmasi');
}

// ============================================
// CREATE - Tambah pembayaran (FIXED)
// ============================================
function create($db) {
    try {
        error_log("=== CREATE PAYMENT CALLED ===");
        error_log("POST data: " . print_r($_POST, true));
        
        $id_order = $db->real_escape_string($_POST['id_order'] ?? '');
        $metode = $db->real_escape_string($_POST['metode_pembayaran'] ?? '');
        $nama_bank = $db->real_escape_string($_POST['nama_bank'] ?? '');
        $nomor_rekening = $db->real_escape_string($_POST['nomor_rekening'] ?? '');
        $nama_pemilik = $db->real_escape_string($_POST['nama_pemilik'] ?? '');
        $jumlah_bayar = floatval($_POST['jumlah_bayar'] ?? 0);
        $bukti_bayar = $db->real_escape_string($_POST['bukti_bayar'] ?? '');
        
        // Validasi
        if (empty($id_order) || empty($metode) || empty($jumlah_bayar)) {
            error_log("ERROR: Validation failed - id_order: $id_order, metode: $metode, jumlah: $jumlah_bayar");
            Response::error('ID order, metode pembayaran, dan jumlah bayar wajib diisi', 400);
            return;
        }
        
        // ✅ Validasi metode pembayaran sesuai enum
        $valid_metode = ['transfer', 'qris', 'ewallet', 'cash', 'cod'];
        if (!in_array($metode, $valid_metode)) {
            error_log("ERROR: Invalid metode - $metode");
            Response::error("Metode pembayaran tidak valid. Gunakan: " . implode(', ', $valid_metode), 400);
            return;
        }
        
        // Cek order ada
        $checkOrder = "SELECT id_order, jenis_order FROM orders WHERE id_order = " . intval($id_order);
        $resultOrder = $db->query($checkOrder);
        if ($resultOrder->num_rows === 0) {
            error_log("ERROR: Order not found");
            Response::error('Order tidak ditemukan', 400);
            return;
        }
        
        $orderData = $resultOrder->fetch_assoc();
        $jenis_order = $orderData['jenis_order'];
        
        // ✅ FIXED: Kasir offline langsung set status "diterima" (sudah bayar tunai)
        $status_pembayaran = 'diterima'; // Offline = langsung lunas
        
        // Insert payment
        $sql = "INSERT INTO payments (
                    id_order, metode_pembayaran, nama_bank, nomor_rekening, 
                    nama_pemilik, jumlah_bayar, bukti_bayar, status_pembayaran
                ) VALUES (
                    " . intval($id_order) . ",
                    '$metode',
                    '$nama_bank',
                    '$nomor_rekening',
                    '$nama_pemilik',
                    $jumlah_bayar,
                    '$bukti_bayar',
                    '$status_pembayaran'
                )";
        
        error_log("SQL: $sql");
        
        if (!$db->query($sql)) {
            error_log("ERROR: Query failed - " . $db->error);
            Response::error('Database error: ' . $db->error, 500);
            return;
        }
        
        $insertId = $db->insert_id;
        error_log("Payment created! ID: $insertId");
        
        // ✅ FIXED: Update order status ke "diproses" untuk offline, bukan "dibayar"
        // Karena pembayaran offline sudah lunas dan perlu langsung dikerjakan operator
        if ($jenis_order === 'offline') {
            $updateOrderSql = "UPDATE orders SET status_order = 'diproses' WHERE id_order = " . intval($id_order);
            $db->query($updateOrderSql);
            error_log("Order status updated to 'diproses' for offline payment");
        }
        
        Response::success([
            'id_payment' => $insertId,
            'id_order' => $id_order,
            'status_pembayaran' => $status_pembayaran
        ], 'Pembayaran berhasil dicatat');
        
    } catch (Exception $e) {
        error_log("EXCEPTION: " . $e->getMessage());
        Response::error('Server error: ' . $e->getMessage(), 500);
    }
}

// ============================================
// DETAIL - Detail pembayaran
// ============================================
function detail($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID payment tidak ditemukan', 400);
        return;
    }
    
    $sql = "SELECT p.*, o.kode_order, o.total_harga, u.nama as nama_pelanggan,
            adm.nama as nama_admin
            FROM payments p
            LEFT JOIN orders o ON p.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users adm ON p.id_admin_konfirmasi = adm.id_user
            WHERE p.id_payment = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Payment tidak ditemukan');
        return;
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_payment' => $row['id_payment'],
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'nama_pelanggan' => $row['nama_pelanggan'],
        'total_harga' => $row['total_harga'],
        'metode_pembayaran' => $row['metode_pembayaran'],
        'nama_bank' => $row['nama_bank'],
        'nomor_rekening' => $row['nomor_rekening'],
        'nama_pemilik' => $row['nama_pemilik'],
        'jumlah_bayar' => $row['jumlah_bayar'],
        'bukti_bayar' => $row['bukti_bayar'],
        'status_pembayaran' => $row['status_pembayaran'],
        'tanggal_bayar' => $row['tanggal_bayar'],
        'tanggal_konfirmasi' => $row['tanggal_konfirmasi'],
        'nama_admin' => $row['nama_admin'],
        'catatan' => $row['catatan']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update pembayaran
// ============================================
function update($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID payment tidak ditemukan', 400);
        return;
    }
    
    // Cek payment ada
    $checkSql = "SELECT id_payment FROM payments WHERE id_payment = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Payment tidak ditemukan');
        return;
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['bukti_bayar'])) {
        $bukti = $db->real_escape_string($_POST['bukti_bayar']);
        $updates[] = "bukti_bayar = '$bukti'";
    }
    
    if (isset($_POST['catatan'])) {
        $catatan = $db->real_escape_string($_POST['catatan']);
        $updates[] = "catatan = '$catatan'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
        return;
    }
    
    $sql = "UPDATE payments SET " . implode(', ', $updates) . " WHERE id_payment = '$id'";
    $db->query($sql);
    
    Response::success(['id_payment' => $id], 'Payment berhasil diupdate');
}

// ============================================
// DELETE - Hapus pembayaran
// ============================================
function delete($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID payment tidak ditemukan', 400);
        return;
    }
        
    // Cek payment ada
    $checkSql = "SELECT id_payment FROM payments WHERE id_payment = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Payment tidak ditemukan');
        return;
    }
    
    // Hard delete
    $sql = "DELETE FROM payments WHERE id_payment = '$id'";
    $db->query($sql);
    
    Response::success(['id_payment' => $id], 'Payment berhasil dihapus');
}
?>