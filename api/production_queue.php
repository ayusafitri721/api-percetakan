<?php
/**
 * API Production Queue - CRUD Tabel production_queue
 * URL: http://localhost/api-percetakan/production_queue.php
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
    case 'by_status':
        byStatus($db);
        break;
    case 'by_operator':
        byOperator($db);
        break;
    case 'start_production':
        startProduction($db);
        break;
    case 'finish_production':
        finishProduction($db);
        break;
    case 'hold_production':
        holdProduction($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua antrian
// ============================================
function getAll($db) {
    $sql = "SELECT pq.*, 
                   o.kode_order, o.kecepatan_pengerjaan,
                   u.nama as nama_pelanggan,
                   op.nama as nama_operator
            FROM production_queue pq
            LEFT JOIN orders o ON pq.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users op ON pq.id_operator = op.id_user
            ORDER BY pq.prioritas DESC, pq.waktu_masuk ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_queue' => $row['id_queue'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'id_operator' => $row['id_operator'],
            'nama_operator' => $row['nama_operator'],
            'prioritas' => $row['prioritas'],
            'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
            'status_produksi' => $row['status_produksi'],
            'waktu_masuk' => $row['waktu_masuk'],
            'waktu_mulai' => $row['waktu_mulai'],
            'waktu_selesai' => $row['waktu_selesai'],
            'estimasi_selesai' => $row['estimasi_selesai'],
            'catatan_produksi' => $row['catatan_produksi']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'queue' => $data
    ]);
}

// ============================================
// GET BY STATUS - Antrian per status
// ============================================
function byStatus($db) {
    $status = $db->escape($_GET['status'] ?? 'antrian');
    
    $sql = "SELECT pq.*, 
                   o.kode_order, o.kecepatan_pengerjaan,
                   u.nama as nama_pelanggan,
                   op.nama as nama_operator
            FROM production_queue pq
            LEFT JOIN orders o ON pq.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users op ON pq.id_operator = op.id_user
            WHERE pq.status_produksi = '$status'
            ORDER BY pq.prioritas DESC, pq.waktu_masuk ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_queue' => $row['id_queue'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'nama_operator' => $row['nama_operator'],
            'prioritas' => $row['prioritas'],
            'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
            'waktu_masuk' => $row['waktu_masuk'],
            'waktu_mulai' => $row['waktu_mulai'],
            'estimasi_selesai' => $row['estimasi_selesai']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'status' => $status,
        'queue' => $data
    ]);
}

// ============================================
// GET BY OPERATOR - Antrian per operator
// ============================================
function byOperator($db) {
    $id_operator = $db->escape($_GET['id_operator'] ?? '');
    
    if (empty($id_operator)) {
        Response::error('ID operator tidak ditemukan', 400);
    }
    
    $sql = "SELECT pq.*, 
                   o.kode_order,
                   u.nama as nama_pelanggan
            FROM production_queue pq
            LEFT JOIN orders o ON pq.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            WHERE pq.id_operator = '$id_operator'
            AND pq.status_produksi IN ('antrian', 'dikerjakan', 'hold')
            ORDER BY pq.prioritas DESC, pq.waktu_masuk ASC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_queue' => $row['id_queue'],
            'kode_order' => $row['kode_order'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'prioritas' => $row['prioritas'],
            'status_produksi' => $row['status_produksi'],
            'waktu_masuk' => $row['waktu_masuk'],
            'waktu_mulai' => $row['waktu_mulai'],
            'estimasi_selesai' => $row['estimasi_selesai']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'queue' => $data
    ]);
}

// ============================================
// CREATE - Tambah antrian
// ============================================
function create($db) {
    $id_order = $db->escape($_POST['id_order'] ?? '');
    $id_operator = $db->escape($_POST['id_operator'] ?? 'NULL');
    $prioritas = $db->escape($_POST['prioritas'] ?? 0);
    $estimasi_selesai = $db->escape($_POST['estimasi_selesai'] ?? 'NULL');
    $catatan = $db->escape($_POST['catatan_produksi'] ?? '');
    
    // Validasi
    if (empty($id_order)) {
        Response::error('ID order wajib diisi', 400);
    }
    
    // Cek order ada
    $checkOrder = "SELECT id_order, kecepatan_pengerjaan FROM orders WHERE id_order = '$id_order'";
    $resultOrder = $db->query($checkOrder);
    if ($resultOrder->num_rows === 0) {
        Response::error('Order tidak ditemukan', 400);
    }
    
    $order = $resultOrder->fetch_assoc();
    
    // Set prioritas berdasarkan kecepatan
    if ($prioritas == 0) {
        $prioritas = ($order['kecepatan_pengerjaan'] == 'express') ? 100 : 0;
    }
    
    // Cek sudah ada di antrian
    $checkQueue = "SELECT id_queue FROM production_queue WHERE id_order = '$id_order' AND status_produksi != 'selesai'";
    $resultQueue = $db->query($checkQueue);
    if ($resultQueue->num_rows > 0) {
        Response::error('Order sudah ada dalam antrian produksi', 400);
    }
    
    // Insert
    $idOperatorValue = ($id_operator === 'NULL') ? 'NULL' : "'$id_operator'";
    $estimasiValue = ($estimasi_selesai === 'NULL') ? 'NULL' : "'$estimasi_selesai'";
    
    $sql = "INSERT INTO production_queue (id_order, id_operator, prioritas, status_produksi, estimasi_selesai, catatan_produksi) 
            VALUES ('$id_order', $idOperatorValue, '$prioritas', 'antrian', $estimasiValue, '$catatan')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_queue' => $insertId,
        'id_order' => $id_order,
        'prioritas' => $prioritas
    ], 'Berhasil ditambahkan ke antrian produksi');
}

// ============================================
// DETAIL - Detail antrian
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID queue tidak ditemukan', 400);
    }
    
    $sql = "SELECT pq.*, 
                   o.kode_order, o.kecepatan_pengerjaan, o.total_harga,
                   u.nama as nama_pelanggan, u.no_telepon,
                   op.nama as nama_operator
            FROM production_queue pq
            LEFT JOIN orders o ON pq.id_order = o.id_order
            LEFT JOIN users u ON o.id_user = u.id_user
            LEFT JOIN users op ON pq.id_operator = op.id_user
            WHERE pq.id_queue = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Antrian tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_queue' => $row['id_queue'],
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'nama_pelanggan' => $row['nama_pelanggan'],
        'no_telepon' => $row['no_telepon'],
        'id_operator' => $row['id_operator'],
        'nama_operator' => $row['nama_operator'],
        'prioritas' => $row['prioritas'],
        'kecepatan_pengerjaan' => $row['kecepatan_pengerjaan'],
        'total_harga' => $row['total_harga'],
        'status_produksi' => $row['status_produksi'],
        'waktu_masuk' => $row['waktu_masuk'],
        'waktu_mulai' => $row['waktu_mulai'],
        'waktu_selesai' => $row['waktu_selesai'],
        'estimasi_selesai' => $row['estimasi_selesai'],
        'catatan_produksi' => $row['catatan_produksi']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update antrian
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID queue tidak ditemukan', 400);
    }
    
    // Cek antrian ada
    $checkSql = "SELECT id_queue FROM production_queue WHERE id_queue = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Antrian tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['id_operator'])) {
        $operator = $db->escape($_POST['id_operator']);
        $updates[] = "id_operator = '$operator'";
    }
    
    if (isset($_POST['prioritas'])) {
        $prioritas = $db->escape($_POST['prioritas']);
        $updates[] = "prioritas = '$prioritas'";
    }
    
    if (isset($_POST['status_produksi'])) {
        $status = $db->escape($_POST['status_produksi']);
        $updates[] = "status_produksi = '$status'";
    }
    
    if (isset($_POST['estimasi_selesai'])) {
        $estimasi = $db->escape($_POST['estimasi_selesai']);
        $updates[] = "estimasi_selesai = '$estimasi'";
    }
    
    if (isset($_POST['catatan_produksi'])) {
        $catatan = $db->escape($_POST['catatan_produksi']);
        $updates[] = "catatan_produksi = '$catatan'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE production_queue SET " . implode(', ', $updates) . " WHERE id_queue = '$id'";
    $db->query($sql);
    
    Response::success(['id_queue' => $id], 'Antrian berhasil diupdate');
}

// ============================================
// START PRODUCTION - Mulai produksi
// ============================================
function startProduction($db) {
    $id = $db->escape($_POST['id_queue'] ?? '');
    $id_operator = $db->escape($_POST['id_operator'] ?? '');
    
    if (empty($id)) {
        Response::error('ID queue wajib diisi', 400);
    }
    
    // Update status
    $sql = "UPDATE production_queue 
            SET status_produksi = 'dikerjakan', 
                waktu_mulai = NOW()";
    
    if (!empty($id_operator)) {
        $sql .= ", id_operator = '$id_operator'";
    }
    
    $sql .= " WHERE id_queue = '$id'";
    
    $db->query($sql);
    
    // Update status order
    $updateOrder = "UPDATE orders o
                    JOIN production_queue pq ON o.id_order = pq.id_order
                    SET o.status_order = 'cetak'
                    WHERE pq.id_queue = '$id'";
    $db->query($updateOrder);
    
    Response::success([
        'id_queue' => $id,
        'status' => 'dikerjakan',
        'waktu_mulai' => date('Y-m-d H:i:s')
    ], 'Produksi berhasil dimulai');
}

// ============================================
// FINISH PRODUCTION - Selesaikan produksi
// ============================================
function finishProduction($db) {
    $id = $db->escape($_POST['id_queue'] ?? '');
    $catatan = $db->escape($_POST['catatan_produksi'] ?? '');
    
    if (empty($id)) {
        Response::error('ID queue wajib diisi', 400);
    }
    
    // Cek status saat ini
    $checkSql = "SELECT status_produksi, id_order FROM production_queue WHERE id_queue = '$id'";
    $checkResult = $db->query($checkSql);
    
    if ($checkResult->num_rows === 0) {
        Response::notFound('Antrian tidak ditemukan');
    }
    
    $row = $checkResult->fetch_assoc();
    
    if ($row['status_produksi'] != 'dikerjakan') {
        Response::error('Produksi belum dimulai', 400);
    }
    
    // Update status
    $sql = "UPDATE production_queue 
            SET status_produksi = 'selesai', 
                waktu_selesai = NOW(),
                catatan_produksi = '$catatan'
            WHERE id_queue = '$id'";
    
    $db->query($sql);
    
    // Update status order
    $updateOrder = "UPDATE orders SET status_order = 'selesai' WHERE id_order = '{$row['id_order']}'";
    $db->query($updateOrder);
    
    Response::success([
        'id_queue' => $id,
        'status' => 'selesai',
        'waktu_selesai' => date('Y-m-d H:i:s')
    ], 'Produksi berhasil diselesaikan');
}

// ============================================
// HOLD PRODUCTION - Tahan produksi
// ============================================
function holdProduction($db) {
    $id = $db->escape($_POST['id_queue'] ?? '');
    $catatan = $db->escape($_POST['catatan_produksi'] ?? '');
    
    if (empty($id) || empty($catatan)) {
        Response::error('ID queue dan catatan wajib diisi', 400);
    }
    
    // Update status
    $sql = "UPDATE production_queue 
            SET status_produksi = 'hold',
                catatan_produksi = '$catatan'
            WHERE id_queue = '$id'";
    
    $db->query($sql);
    
    Response::success([
        'id_queue' => $id,
        'status' => 'hold'
    ], 'Produksi berhasil ditahan');
}

// ============================================
// DELETE - Hapus antrian
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID queue tidak ditemukan', 400);
    }
    
    // Cek antrian ada
    $checkSql = "SELECT id_queue, status_produksi FROM production_queue WHERE id_queue = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Antrian tidak ditemukan');
    }
    
    $row = $checkResult->fetch_assoc();
    
    // Tidak bisa hapus jika sedang dikerjakan
    if ($row['status_produksi'] == 'dikerjakan') {
        Response::error('Tidak dapat menghapus antrian yang sedang dikerjakan', 400);
    }
    
    // Delete
    $sql = "DELETE FROM production_queue WHERE id_queue = '$id'";
    $db->query($sql);
    
    Response::success(['id_queue' => $id], 'Antrian berhasil dihapus');
}
?>