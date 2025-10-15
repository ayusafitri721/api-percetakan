<?php
/**
 * API Loyalty Points - CRUD Tabel loyalty_points
 * URL: http://localhost/api-percetakan/loyalty_points.php
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
    case 'by_user':
        byUser($db);
        break;
    case 'balance':
        balance($db);
        break;
    case 'use_points':
        usePoints($db);
        break;
    case 'expire_points':
        expirePoints($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua transaksi poin
// ============================================
function getAll($db) {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $offset = ($page - 1) * $limit;
    
    // Count total
    $countSql = "SELECT COUNT(*) as total FROM loyalty_points";
    $countResult = $db->query($countSql);
    $totalData = $countResult->fetch_assoc()['total'];
    
    $sql = "SELECT lp.*, u.nama as nama_user, o.kode_order 
            FROM loyalty_points lp
            LEFT JOIN users u ON lp.id_user = u.id_user
            LEFT JOIN orders o ON lp.id_order = o.id_order
            ORDER BY lp.tanggal_transaksi DESC
            LIMIT $limit OFFSET $offset";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_point' => $row['id_point'],
            'id_user' => $row['id_user'],
            'nama_user' => $row['nama_user'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'jenis' => $row['jenis'],
            'jumlah_point' => $row['jumlah_point'],
            'saldo_point' => $row['saldo_point'],
            'keterangan' => $row['keterangan'],
            'tanggal_transaksi' => $row['tanggal_transaksi'],
            'tanggal_kadaluarsa' => $row['tanggal_kadaluarsa']
        ];
    }
    
    Response::success([
        'total' => $totalData,
        'page' => (int)$page,
        'limit' => (int)$limit,
        'total_pages' => ceil($totalData / $limit),
        'points' => $data
    ]);
}

// ============================================
// GET BY USER - Riwayat poin per user
// ============================================
function byUser($db) {
    $id_user = $db->escape($_GET['id_user'] ?? '');
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    $sql = "SELECT lp.*, o.kode_order 
            FROM loyalty_points lp
            LEFT JOIN orders o ON lp.id_order = o.id_order
            WHERE lp.id_user = '$id_user'
            ORDER BY lp.tanggal_transaksi DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_point' => $row['id_point'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'jenis' => $row['jenis'],
            'jumlah_point' => $row['jumlah_point'],
            'saldo_point' => $row['saldo_point'],
            'keterangan' => $row['keterangan'],
            'tanggal_transaksi' => $row['tanggal_transaksi'],
            'tanggal_kadaluarsa' => $row['tanggal_kadaluarsa']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'points' => $data
    ]);
}

// ============================================
// BALANCE - Saldo poin user
// ============================================
function balance($db) {
    $id_user = $db->escape($_GET['id_user'] ?? '');
    
    if (empty($id_user)) {
        Response::error('ID user tidak ditemukan', 400);
    }
    
    // Get saldo terakhir
    $sql = "SELECT saldo_point 
            FROM loyalty_points 
            WHERE id_user = '$id_user'
            ORDER BY tanggal_transaksi DESC
            LIMIT 1";
    
    $result = $db->query($sql);
    
    $saldo = 0;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $saldo = $row['saldo_point'];
    }
    
    // Get total dapat dan pakai
    $statsSql = "SELECT 
                    SUM(CASE WHEN jenis = 'dapat' THEN jumlah_point ELSE 0 END) as total_dapat,
                    SUM(CASE WHEN jenis = 'pakai' THEN jumlah_point ELSE 0 END) as total_pakai,
                    SUM(CASE WHEN jenis = 'kadaluarsa' THEN jumlah_point ELSE 0 END) as total_kadaluarsa
                 FROM loyalty_points 
                 WHERE id_user = '$id_user'";
    
    $statsResult = $db->query($statsSql);
    $stats = $statsResult->fetch_assoc();
    
    Response::success([
        'id_user' => $id_user,
        'saldo_point' => $saldo,
        'total_dapat' => $stats['total_dapat'],
        'total_pakai' => $stats['total_pakai'],
        'total_kadaluarsa' => $stats['total_kadaluarsa']
    ]);
}

// ============================================
// CREATE - Tambah poin (dapat/pakai)
// ============================================
function create($db) {
    $id_user = $db->escape($_POST['id_user'] ?? '');
    $id_order = $db->escape($_POST['id_order'] ?? 'NULL');
    $jenis = $db->escape($_POST['jenis'] ?? 'dapat');
    $jumlah_point = $db->escape($_POST['jumlah_point'] ?? 0);
    $keterangan = $db->escape($_POST['keterangan'] ?? '');
    $tanggal_kadaluarsa = $db->escape($_POST['tanggal_kadaluarsa'] ?? 'NULL');
    
    // Validasi
    if (empty($id_user) || empty($jumlah_point)) {
        Response::error('ID user dan jumlah poin wajib diisi', 400);
    }
    
    if (!in_array($jenis, ['dapat', 'pakai', 'kadaluarsa'])) {
        Response::error('Jenis harus: dapat, pakai, atau kadaluarsa', 400);
    }
    
    // Get saldo terakhir
    $saldoSql = "SELECT saldo_point 
                 FROM loyalty_points 
                 WHERE id_user = '$id_user'
                 ORDER BY tanggal_transaksi DESC
                 LIMIT 1";
    $saldoResult = $db->query($saldoSql);
    
    $saldo_lama = 0;
    if ($saldoResult->num_rows > 0) {
        $saldo_lama = $saldoResult->fetch_assoc()['saldo_point'];
    }
    
    // Hitung saldo baru
    if ($jenis == 'dapat') {
        $saldo_baru = $saldo_lama + $jumlah_point;
    } else {
        $saldo_baru = $saldo_lama - $jumlah_point;
        if ($saldo_baru < 0) {
            Response::error('Saldo poin tidak mencukupi', 400);
        }
    }
    
    // Insert
    $idOrderValue = ($id_order === 'NULL') ? 'NULL' : "'$id_order'";
    $tglKadaluarsaValue = ($tanggal_kadaluarsa === 'NULL') ? 'NULL' : "'$tanggal_kadaluarsa'";
    
    $sql = "INSERT INTO loyalty_points (id_user, id_order, jenis, jumlah_point, saldo_point, keterangan, tanggal_kadaluarsa) 
            VALUES ('$id_user', $idOrderValue, '$jenis', '$jumlah_point', '$saldo_baru', '$keterangan', $tglKadaluarsaValue)";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_point' => $insertId,
        'jenis' => $jenis,
        'jumlah_point' => $jumlah_point,
        'saldo_point' => $saldo_baru
    ], 'Transaksi poin berhasil');
}

// ============================================
// USE POINTS - Pakai poin untuk diskon
// ============================================
function usePoints($db) {
    $id_user = $db->escape($_POST['id_user'] ?? '');
    $jumlah_point = $db->escape($_POST['jumlah_point'] ?? 0);
    $id_order = $db->escape($_POST['id_order'] ?? '');
    
    // Validasi
    if (empty($id_user) || empty($jumlah_point)) {
        Response::error('ID user dan jumlah poin wajib diisi', 400);
    }
    
    // Get saldo
    $saldoSql = "SELECT saldo_point 
                 FROM loyalty_points 
                 WHERE id_user = '$id_user'
                 ORDER BY tanggal_transaksi DESC
                 LIMIT 1";
    $saldoResult = $db->query($saldoSql);
    
    $saldo = 0;
    if ($saldoResult->num_rows > 0) {
        $saldo = $saldoResult->fetch_assoc()['saldo_point'];
    }
    
    if ($saldo < $jumlah_point) {
        Response::error('Saldo poin tidak mencukupi', 400);
    }
    
    $saldo_baru = $saldo - $jumlah_point;
    $idOrderValue = empty($id_order) ? 'NULL' : "'$id_order'";
    
    $sql = "INSERT INTO loyalty_points (id_user, id_order, jenis, jumlah_point, saldo_point, keterangan) 
            VALUES ('$id_user', $idOrderValue, 'pakai', '$jumlah_point', '$saldo_baru', 'Penggunaan poin untuk diskon')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::success([
        'id_point' => $insertId,
        'jumlah_dipakai' => $jumlah_point,
        'saldo_tersisa' => $saldo_baru
    ], 'Poin berhasil digunakan');
}

// ============================================
// EXPIRE POINTS - Expired poin kadaluarsa
// ============================================
function expirePoints($db) {
    // Cari poin yang kadaluarsa
    $sql = "SELECT lp.id_point, lp.id_user, lp.jumlah_point, lp.saldo_point
            FROM loyalty_points lp
            WHERE lp.jenis = 'dapat' 
            AND lp.tanggal_kadaluarsa IS NOT NULL
            AND lp.tanggal_kadaluarsa < CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM loyalty_points lp2 
                WHERE lp2.id_user = lp.id_user 
                AND lp2.jenis = 'kadaluarsa'
                AND lp2.keterangan LIKE CONCAT('%', lp.id_point, '%')
            )";
    
    $result = $db->query($sql);
    $expired_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $id_user = $row['id_user'];
        $jumlah_expired = $row['jumlah_point'];
        
        // Get saldo terakhir
        $saldoSql = "SELECT saldo_point FROM loyalty_points 
                     WHERE id_user = '$id_user'
                     ORDER BY tanggal_transaksi DESC LIMIT 1";
        $saldoResult = $db->query($saldoSql);
        $saldo_lama = $saldoResult->fetch_assoc()['saldo_point'];
        $saldo_baru = $saldo_lama - $jumlah_expired;
        
        // Insert record kadaluarsa
        $insertSql = "INSERT INTO loyalty_points (id_user, jenis, jumlah_point, saldo_point, keterangan) 
                      VALUES ('$id_user', 'kadaluarsa', '$jumlah_expired', '$saldo_baru', 
                              'Poin kadaluarsa dari transaksi ID: {$row['id_point']}')";
        $db->query($insertSql);
        $expired_count++;
    }
    
    Response::success([
        'expired_count' => $expired_count
    ], "Berhasil memproses $expired_count poin kadaluarsa");
}

// ============================================
// DETAIL - Detail transaksi poin
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID point tidak ditemukan', 400);
    }
    
    $sql = "SELECT lp.*, u.nama as nama_user, o.kode_order 
            FROM loyalty_points lp
            LEFT JOIN users u ON lp.id_user = u.id_user
            LEFT JOIN orders o ON lp.id_order = o.id_order
            WHERE lp.id_point = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Transaksi poin tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_point' => $row['id_point'],
        'id_user' => $row['id_user'],
        'nama_user' => $row['nama_user'],
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'jenis' => $row['jenis'],
        'jumlah_point' => $row['jumlah_point'],
        'saldo_point' => $row['saldo_point'],
        'keterangan' => $row['keterangan'],
        'tanggal_transaksi' => $row['tanggal_transaksi'],
        'tanggal_kadaluarsa' => $row['tanggal_kadaluarsa']
    ];
    
    Response::success($data);
}
?>