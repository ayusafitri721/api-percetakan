<?php
/**
 * API Order Items - DENGAN FILE VALIDATION
 * URL: http://localhost/api-percetakan/api/order_items.php
 * FIXED: Terintegrasi dengan FileValidator.php
 */

error_reporting(0);
require_once '../config/database.php';
require_once '../helpers/Response.php';
require_once 'validators/FileValidator.php'; // ⬅️ TAMBAH INI

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
// GET ALL
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
// BY ORDER
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
// CREATE - DENGAN FILE VALIDATION ✅
// ============================================
function create($db) {
    error_log("POST: " . print_r($_POST, true));
    error_log("FILES: " . print_r($_FILES, true));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    $id_order = $_POST['id_order'] ?? '';
    $id_product = $_POST['id_product'] ?? '';
    $ukuran = $_POST['ukuran'] ?? 'Standard';
    $jumlah = (int)($_POST['jumlah'] ?? 1);
    $harga_satuan = (float)($_POST['harga_satuan'] ?? 0);
    $keterangan = $_POST['keterangan'] ?? '';

    // VALIDASI
    if (empty($id_order) || empty($id_product)) {
        Response::error('id_order dan id_product wajib diisi', 400);
    }

    // Cek order
    $check = $db->query("SELECT id_order FROM orders WHERE id_order = '$id_order'");
    if ($check->num_rows === 0) {
        Response::error("Order ID $id_order tidak ditemukan", 404);
    }

    // Cek produk
    $prod = $db->query("SELECT nama_product, harga_dasar FROM products WHERE id_product = '$id_product'")->fetch_assoc();
    if (!$prod) {
        Response::error("Produk ID $id_product tidak ditemukan", 404);
    }

    $nama_product = $prod['nama_product'];
    $harga_satuan = $harga_satuan > 0 ? $harga_satuan : $prod['harga_dasar'];
    $subtotal = $jumlah * $harga_satuan;

    try {
        $db->autocommit(false); // Mulai transaksi

        // 1. Simpan order_items
        $sql = "INSERT INTO order_items 
                (id_order, id_product, nama_product, ukuran, jumlah, harga_satuan, subtotal, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $db->error);

        $stmt->bind_param('iissidss', $id_order, $id_product, $nama_product, $ukuran, $jumlah, $harga_satuan, $subtotal, $keterangan);
        if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

        $id_item = $stmt->insert_id;
        $stmt->close();

        $design_file_id = null;
        $file_url = null;
        $validation_result = null;

        // 2. VALIDASI & UPLOAD FILE ✅
        if (isset($_FILES['file_desain']) && $_FILES['file_desain']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_desain'];
            
            // ⭐ PANGGIL FILE VALIDATOR
            $validator = new FileValidator();
            $validation_result = $validator->validate($file);
            
            error_log("VALIDATION RESULT: " . print_r($validation_result, true));
            
            // ⭐ CEK CONFIDENCE SCORE
            if (!$validation_result['is_valid']) {
                // ROLLBACK jika file tidak valid
                throw new Exception('File tidak valid: ' . implode(', ', $validation_result['errors']));
            }
            
            // Warning jika confidence rendah (60-74)
            if ($validation_result['confidence_score'] < 75) {
                error_log("WARNING: Low confidence score - " . $validation_result['confidence_score']);
            }
            
            // File valid, lanjut upload
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uploadDir = '../uploads/design_files/';
            
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Gagal buat folder upload');
                }
            }

            $newName = "DESAIN_{$id_order}_{$id_item}_" . time() . ".{$ext}";
            $uploadPath = $uploadDir . $newName;
            $file_url = "http://localhost/api-percetakan/uploads/design_files/{$newName}";

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Gagal simpan file ke server');
            }

            // Simpan ke design_files dengan validation status
            $status_validasi = $validation_result['confidence_score'] >= 90 ? 'diterima' : 'pending';
            
            $sqlFile = "INSERT INTO design_files 
                        (id_order, nama_file, file_url, ukuran_file, tipe_file, status_validasi) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $stmtFile = $db->prepare($sqlFile);
            if (!$stmtFile) throw new Exception("Prepare design_files failed: " . $db->error);

            $stmtFile->bind_param('ississ', $id_order, $file['name'], $file_url, $file['size'], $ext, $status_validasi);
            if (!$stmtFile->execute()) throw new Exception("Execute design_files failed: " . $stmtFile->error);

            $design_file_id = $stmtFile->insert_id;
            $stmtFile->close();
            
            // ⭐ SAVE VALIDATION LOG (opsional, jika ada tabel validation_logs)
            $logSql = "INSERT INTO validation_logs 
                      (id_order, validation_type, status, message, details) 
                      VALUES (?, 'file', ?, ?, ?)";
            
            if ($stmt = $db->prepare($logSql)) {
                $log_status = $validation_result['is_valid'] ? 'pass' : 'fail';
                $log_message = $validation_result['recommendation'];
                $log_details = json_encode($validation_result);
                
                $stmt->bind_param('isss', $id_order, $log_status, $log_message, $log_details);
                $stmt->execute();
                $stmt->close();
            }
        }

        $db->commit();
        $db->autocommit(true);

        // Update total order
        updateOrderTotal($db, $id_order);

        // ⭐ RESPONSE DENGAN VALIDATION DATA
        Response::created([
            'id_item' => $id_item,
            'id_design_file' => $design_file_id,
            'file_url' => $file_url,
            'validation' => $validation_result, // ⬅️ KIRIM KE FRONTEND
            'message' => 'Item berhasil disimpan'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        $db->autocommit(true);
        error_log("CREATE ITEM ERROR: " . $e->getMessage());
        Response::error('Gagal simpan: ' . $e->getMessage(), 500);
    }
}

// ============================================
// DETAIL
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
// UPDATE
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID item tidak ditemukan', 400);
    }
    
    $checkSql = "SELECT id_order FROM order_items WHERE id_item = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Item tidak ditemukan');
    }
    
    $id_order = $checkResult->fetch_assoc()['id_order'];
    
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
    
    updateOrderTotal($db, $id_order);
    
    Response::success(['id_item' => $id], 'Item berhasil diupdate');
}

// ============================================
// DELETE
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID item tidak ditemukan', 400);
    }
    
    $checkSql = "SELECT id_order FROM order_items WHERE id_item = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Item tidak ditemukan');
    }
    
    $id_order = $checkResult->fetch_assoc()['id_order'];
    
    $sql = "DELETE FROM order_items WHERE id_item = '$id'";
    $db->query($sql);
    
    updateOrderTotal($db, $id_order);
    
    Response::success(['id_item' => $id], 'Item berhasil dihapus');
}

// ============================================
// HELPER - Update total order
// ============================================
function updateOrderTotal($db, $id_order) {
    $totalSql = "SELECT SUM(subtotal) as total FROM order_items WHERE id_order = '$id_order'";
    $totalResult = $db->query($totalSql);
    $subtotal = $totalResult->fetch_assoc()['total'] ?? 0;
    
    $orderSql = "SELECT diskon, ongkir FROM orders WHERE id_order = '$id_order'";
    $orderResult = $db->query($orderSql);
    $order = $orderResult->fetch_assoc();
    
    $diskon = $order['diskon'] ?? 0;
    $ongkir = $order['ongkir'] ?? 0;
    
    $total_harga = $subtotal - $diskon + $ongkir;
    
    $updateSql = "UPDATE orders SET subtotal = '$subtotal', total_harga = '$total_harga' WHERE id_order = '$id_order'";
    $db->query($updateSql);
}
?>