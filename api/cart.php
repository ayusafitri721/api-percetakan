<?php
/**
 * API Cart - FIXED: Checkout langsung create payment record
 * URL: http://localhost/api-percetakan/api/cart.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../helpers/Response.php';

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Koneksi Database
$database = new Database();
$database->connect();
$db = $database;

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
        getCart($db);
        break;
    case 'add':
        addToCart($db);
        break;
    case 'update':
        updateCart($db);
        break;
    case 'delete':
        deleteCart($db);
        break;
    case 'clear':
        clearCart($db);
        break;
    case 'checkout':
        checkout($db);
        break;
    default:
        Response::error('Action tidak valid', 400);
}

// ============================================
// GET CART
// ============================================
function getCart($db) {
    $id_user = $_GET['id_user'] ?? '';
    
    if (empty($id_user)) {
        Response::error('id_user tidak ditemukan', 400);
    }
    
    $id_user_escaped = $db->escape($id_user);
    
    $sql = "SELECT id_order, kode_order, subtotal, total_harga 
            FROM orders 
            WHERE id_user = '$id_user_escaped' AND status_order = 'draft' 
            LIMIT 1";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::success([
            'order' => null,
            'items' => []
        ]);
        return;
    }
    
    $order = $result->fetch_assoc();
    $id_order = $order['id_order'];
    
    // Get items
    $itemSql = "SELECT * FROM order_items WHERE id_order = '$id_order'";
    $itemResult = $db->query($itemSql);
    
    $items = [];
    while ($row = $itemResult->fetch_assoc()) {
        $items[] = $row;
    }
    
    Response::success([
        'order' => $order,
        'items' => $items
    ]);
}

// ============================================
// ADD TO CART
// ============================================
function addToCart($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id_user = $input['id_user'] ?? '';
    $id_product = $input['id_product'] ?? '';
    $nama_product = $input['nama_product'] ?? '';
    $ukuran = $input['ukuran'] ?? 'Standard';
    $jumlah = (int)($input['jumlah'] ?? 1);
    $harga_satuan = (float)($input['harga_satuan'] ?? 0);
    $keterangan = $input['keterangan'] ?? '';
    
    if (empty($id_user) || empty($id_product)) {
        Response::error('id_user dan id_product wajib diisi', 400);
    }
    
    $subtotal = $jumlah * $harga_satuan;
    
    $id_user_escaped = $db->escape($id_user);
    $id_product_escaped = $db->escape($id_product);
    $nama_product_escaped = $db->escape($nama_product);
    $ukuran_escaped = $db->escape($ukuran);
    $keterangan_escaped = $db->escape($keterangan);
    
    // Get or create draft order
    $orderSql = "SELECT id_order FROM orders 
                 WHERE id_user = '$id_user_escaped' AND status_order = 'draft' 
                 LIMIT 1";
    $orderResult = $db->query($orderSql);
    
    if ($orderResult->num_rows === 0) {
        // Create new draft order
        $kode_order = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
        $createSql = "INSERT INTO orders (id_user, kode_order, jenis_order, status_order, tanggal_order) 
                      VALUES ('$id_user_escaped', '$kode_order', 'online', 'draft', NOW())";
        $db->query($createSql);
        $id_order = $db->lastInsertId();
    } else {
        $id_order = $orderResult->fetch_assoc()['id_order'];
    }
    
    // Add item
    $itemSql = "INSERT INTO order_items 
                (id_order, id_product, nama_product, ukuran, jumlah, harga_satuan, subtotal, keterangan) 
                VALUES ('$id_order', '$id_product_escaped', '$nama_product_escaped', '$ukuran_escaped', 
                        $jumlah, $harga_satuan, $subtotal, '$keterangan_escaped')";
    
    if (!$db->query($itemSql)) {
        Response::error('Gagal menambahkan item ke cart: ' . $db->error, 500);
    }
    
    updateOrderTotal($db, $id_order);
    
    Response::success(['id_order' => $id_order], 'Item berhasil ditambahkan');
}

// ============================================
// UPDATE CART ITEM
// ============================================
function updateCart($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id_item = (int)($input['id_item'] ?? 0);
    $jumlah = (int)($input['jumlah'] ?? 1);
    
    if ($id_item <= 0) {
        Response::error('id_item tidak valid', 400);
    }
    
    // Get current item
    $getSql = "SELECT harga_satuan FROM order_items WHERE id_item = $id_item";
    $getResult = $db->query($getSql);
    
    if ($getResult->num_rows === 0) {
        Response::error('Item tidak ditemukan', 404);
    }
    
    $item = $getResult->fetch_assoc();
    $harga_satuan = $item['harga_satuan'];
    $subtotal = $jumlah * $harga_satuan;
    
    // Update
    $sql = "UPDATE order_items 
            SET jumlah = $jumlah, subtotal = $subtotal 
            WHERE id_item = $id_item";
    
    $db->query($sql);
    
    // Get id_order for total update
    $orderSql = "SELECT id_order FROM order_items WHERE id_item = $id_item";
    $orderResult = $db->query($orderSql);
    $id_order = $orderResult->fetch_assoc()['id_order'];
    
    updateOrderTotal($db, $id_order);
    
    Response::success(['id_item' => $id_item], 'Item berhasil diupdate');
}

// ============================================
// DELETE CART ITEM
// ============================================
function deleteCart($db) {
    $id_item = (int)($_GET['id'] ?? 0);
    
    if ($id_item <= 0) {
        Response::error('id_item tidak valid', 400);
    }
    
    // Get id_order before delete
    $orderSql = "SELECT id_order FROM order_items WHERE id_item = $id_item";
    $orderResult = $db->query($orderSql);
    
    if ($orderResult->num_rows === 0) {
        Response::error('Item tidak ditemukan', 404);
    }
    
    $id_order = $orderResult->fetch_assoc()['id_order'];
    
    // Delete
    $delSql = "DELETE FROM order_items WHERE id_item = $id_item";
    $db->query($delSql);
    
    updateOrderTotal($db, $id_order);
    
    Response::success(['id_item' => $id_item], 'Item berhasil dihapus');
}

// ============================================
// CLEAR CART
// ============================================
function clearCart($db) {
    $id_user = $_GET['id_user'] ?? '';
    
    if (empty($id_user)) {
        Response::error('id_user tidak ditemukan', 400);
    }
    
    $id_user_escaped = $db->escape($id_user);
    
    $sql = "DELETE oi FROM order_items oi
            INNER JOIN orders o ON oi.id_order = o.id_order
            WHERE o.id_user = '$id_user_escaped' AND o.status_order = 'draft'";
    
    $db->query($sql);
    
    Response::success(null, 'Keranjang berhasil dikosongkan');
}

// ============================================
// CHECKOUT - FIXED: Create payment record!
// ============================================
function checkout($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id_user = $input['id_user'] ?? '';
    $catatan = $input['catatan_pelanggan'] ?? '';
    $kecepatan = $input['kecepatan_pengerjaan'] ?? 'normal';
    $metode_pembayaran = $input['metode_pembayaran'] ?? 'transfer'; // ✅ TAMBAH INI
    
    if (empty($id_user)) {
        Response::error('id_user tidak ditemukan', 400);
    }
    
    $id_user_escaped = $db->escape($id_user);
    $catatan_escaped = $db->escape($catatan);
    $kecepatan_escaped = $db->escape($kecepatan);
    $metode_escaped = $db->escape($metode_pembayaran);
    
    try {
        $db->beginTransaction();
        
        // 1. Get draft order
        $getOrderSql = "SELECT id_order, total_harga 
                        FROM orders 
                        WHERE id_user = '$id_user_escaped' AND status_order = 'draft' 
                        LIMIT 1";
        $orderResult = $db->query($getOrderSql);
        
        if ($orderResult->num_rows === 0) {
            throw new Exception('Keranjang kosong');
        }
        
        $order = $orderResult->fetch_assoc();
        $id_order = $order['id_order'];
        $total_harga = $order['total_harga'];
        
        // 2. Update order status
        $updateOrderSql = "UPDATE orders 
                SET status_order = 'pending', 
                    catatan_pelanggan = '$catatan_escaped',
                    kecepatan_pengerjaan = '$kecepatan_escaped'
                WHERE id_order = '$id_order'";
        
        if (!$db->query($updateOrderSql)) {
            throw new Exception('Gagal update order: ' . $db->error);
        }
        
        // 3. ✅ CREATE PAYMENT RECORD (ini yang missing!)
        $status_payment = 'pending'; // Online order default pending
        
        $insertPaymentSql = "INSERT INTO payments (
                    id_order, 
                    metode_pembayaran, 
                    jumlah_bayar, 
                    status_pembayaran
                ) VALUES (
                    '$id_order',
                    '$metode_escaped',
                    $total_harga,
                    '$status_payment'
                )";
        
        if (!$db->query($insertPaymentSql)) {
            throw new Exception('Gagal create payment: ' . $db->error);
        }
        
        $db->commit();
        
        // Get final order info
        $finalOrderSql = "SELECT kode_order FROM orders WHERE id_order = '$id_order'";
        $finalResult = $db->query($finalOrderSql);
        $finalOrder = $finalResult->fetch_assoc();
        
        Response::success([
            'orderId' => $id_order,
            'kodeOrder' => $finalOrder['kode_order'],
            'metode_pembayaran' => $metode_pembayaran,
            'status_pembayaran' => $status_payment
        ], 'Checkout berhasil');
        
    } catch (Exception $e) {
        $db->rollback();
        Response::error($e->getMessage(), 500);
    }
}

// ============================================
// HELPER - Update Order Total
// ============================================
function updateOrderTotal($db, $id_order) {
    $sql = "UPDATE orders o
            SET o.subtotal = (
                SELECT COALESCE(SUM(oi.subtotal), 0) 
                FROM order_items oi 
                WHERE oi.id_order = o.id_order
            ),
            o.total_harga = (
                SELECT COALESCE(SUM(oi.subtotal), 0) 
                FROM order_items oi 
                WHERE oi.id_order = o.id_order
            )
            WHERE o.id_order = '$id_order'";
    
    $db->query($sql);
}
?>