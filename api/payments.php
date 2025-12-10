<?php
/**
 * API Payments - MATCH DENGAN orderHelpers.ts
 * Logic: Cek ada bukti_pembayaran → auto-approve berdasarkan confidence score
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
    case 'update_status':
        updateStatus($db);
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
// UPDATE STATUS
// ============================================
function updateStatus($db) {
    error_log("=== UPDATE PAYMENT STATUS ===");
    
    $id_order = $_POST['id_order'] ?? '';
    $status_pembayaran = $_POST['status_pembayaran'] ?? '';
    
    if (empty($id_order) || empty($status_pembayaran)) {
        Response::error('ID order dan status pembayaran wajib diisi', 400);
        return;
    }
    
    $id_order = intval($id_order);
    $status_pembayaran = $db->real_escape_string($status_pembayaran);
    
    $validStatus = ['pending', 'lunas', 'ditolak'];
    if (!in_array($status_pembayaran, $validStatus)) {
        Response::error('Status pembayaran tidak valid', 400);
        return;
    }
    
    $checkSql = "SELECT p.id_payment FROM payments p WHERE p.id_order = $id_order";
    $checkResult = $db->query($checkSql);
    
    if ($checkResult->num_rows === 0) {
        Response::notFound('Payment tidak ditemukan');
        return;
    }
    
    $paymentData = $checkResult->fetch_assoc();
    $id_payment = $paymentData['id_payment'];
    
    $sql = "UPDATE payments SET status_pembayaran = '$status_pembayaran'";
    
    if ($status_pembayaran === 'lunas') {
        $sql .= ", tanggal_bayar = NOW()";
    }
    
    $sql .= " WHERE id_payment = $id_payment";
    
    if (!$db->query($sql)) {
        Response::error('Database error: ' . $db->error, 500);
        return;
    }
    
    Response::success([
        'id_payment' => $id_payment,
        'id_order' => $id_order,
        'status_pembayaran' => $status_pembayaran
    ], 'Status pembayaran berhasil diupdate');
}

// ============================================
// GET ALL
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
// BY ORDER
// ============================================
function byOrder($db) {
    $id_order = $db->real_escape_string($_GET['id_order'] ?? '');
    
    if (empty($id_order)) {
        Response::error('ID order tidak ditemukan', 400);
        return;
    }
    
    $sql = "SELECT * FROM payments WHERE id_order = '$id_order' ORDER BY tanggal_bayar DESC";
    
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
// PENDING PAYMENTS
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
// CONFIRM PAYMENT
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
    
    $checkSql = "SELECT id_order FROM payments WHERE id_payment = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Payment tidak ditemukan');
        return;
    }
    
    $id_order = $checkResult->fetch_assoc()['id_order'];
    
    $sql = "UPDATE payments 
            SET status_pembayaran = '$status',
                id_admin_konfirmasi = '$id_admin',
                tanggal_konfirmasi = NOW(),
                catatan = '$catatan'
            WHERE id_payment = '$id'";
    
    $db->query($sql);
    
    if ($status === 'lunas') {
        $updateOrder = "UPDATE orders SET status_order = 'diproses' WHERE id_order = '$id_order'";
        $db->query($updateOrder);
    }
    
    Response::success([
        'id_payment' => $id,
        'status_pembayaran' => $status
    ], 'Pembayaran berhasil dikonfirmasi');
}

// ============================================
// CREATE - MATCH DENGAN orderHelpers.ts
// ============================================
function create($db) {
    try {
        error_log("=== CREATE PAYMENT ===");
        error_log("POST: " . print_r($_POST, true));
        error_log("FILES: " . print_r($_FILES, true));
        
        $id_order = intval($_POST['id_order'] ?? 0);
        $metode = $db->real_escape_string($_POST['metode_pembayaran'] ?? '');
        $nama_bank = $db->real_escape_string($_POST['nama_bank'] ?? '');
        $nomor_rekening = $db->real_escape_string($_POST['nomor_rekening'] ?? '');
        $nama_pemilik = $db->real_escape_string($_POST['nama_pemilik'] ?? '');
        $jumlah_bayar = floatval($_POST['jumlah_bayar'] ?? 0);
        
        // ⭐ Status default dari POST (sudah ditentukan di frontend)
        $status_pembayaran = $db->real_escape_string($_POST['status_pembayaran'] ?? 'pending');
        
        // Validasi
        if (empty($id_order) || empty($metode) || empty($jumlah_bayar)) {
            Response::error('ID order, metode pembayaran, dan jumlah bayar wajib diisi', 400);
            return;
        }
        
        // Cek order exists
        $checkOrder = "SELECT id_order, jenis_order FROM orders WHERE id_order = $id_order";
        $resultOrder = $db->query($checkOrder);
        if ($resultOrder->num_rows === 0) {
            Response::error('Order tidak ditemukan', 400);
            return;
        }
        
        $orderData = $resultOrder->fetch_assoc();
        $jenis_order = $orderData['jenis_order'];
        
        $bukti_bayar_url = null;
        
        // ⭐ UPLOAD BUKTI BAYAR (jika ada)
        if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['bukti_pembayaran'];
            
            // Validasi file
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Format file tidak valid. Gunakan: jpg, jpeg, png, atau pdf');
            }
            
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Ukuran file maksimal 5MB');
            }
            
            // Upload file
            $uploadDir = '../uploads/payment_proofs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $newName = "BUKTI_{$id_order}_" . time() . ".{$ext}";
            $uploadPath = $uploadDir . $newName;
            $bukti_bayar_url = "http://localhost/api-percetakan/uploads/payment_proofs/{$newName}";
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Gagal upload bukti bayar');
            }
            
            error_log("✅ Bukti pembayaran uploaded: $bukti_bayar_url");
        }
        
        error_log("📋 Payment status from frontend: $status_pembayaran");
        
        // Insert payment
        $sql = "INSERT INTO payments (
                    id_order, metode_pembayaran, nama_bank, nomor_rekening, 
                    nama_pemilik, jumlah_bayar, bukti_bayar, status_pembayaran
                ) VALUES (
                    $id_order,
                    '$metode',
                    '$nama_bank',
                    '$nomor_rekening',
                    '$nama_pemilik',
                    $jumlah_bayar,
                    " . ($bukti_bayar_url ? "'$bukti_bayar_url'" : "NULL") . ",
                    '$status_pembayaran'
                )";
        
        if (!$db->query($sql)) {
            throw new Exception('Database error: ' . $db->error);
        }
        
        $insertId = $db->insert_id;
        error_log("✅ Payment created - ID: $insertId, Status: $status_pembayaran");
        
        // ⭐ TIDAK AUTO UPDATE ORDER - Biar frontend yang handle
        // Frontend sudah kirim status_order yang benar
        
        Response::success([
            'id_payment' => $insertId,
            'id_order' => $id_order,
            'metode_pembayaran' => $metode,
            'status_pembayaran' => $status_pembayaran,
            'bukti_bayar' => $bukti_bayar_url
        ], 'Pembayaran berhasil dicatat');
        
    } catch (Exception $e) {
        error_log("❌ PAYMENT ERROR: " . $e->getMessage());
        Response::error('Server error: ' . $e->getMessage(), 500);
    }
}

// ============================================
// DETAIL, UPDATE, DELETE (tidak berubah)
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
    Response::success([
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
    ]);
}

function update($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID payment tidak ditemukan', 400);
        return;
    }
    
    $checkSql = "SELECT id_payment FROM payments WHERE id_payment = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Payment tidak ditemukan');
        return;
    }
    
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

function delete($db) {
    $id = $db->real_escape_string($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID payment tidak ditemukan', 400);
        return;
    }
        
    $checkSql = "SELECT id_payment FROM payments WHERE id_payment = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Payment tidak ditemukan');
        return;
    }
    
    $sql = "DELETE FROM payments WHERE id_payment = '$id'";
    $db->query($sql);
    
    Response::success(['id_payment' => $id], 'Payment berhasil dihapus');
}
?>