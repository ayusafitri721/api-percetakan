<?php
/**
 * API Promotions - CRUD Tabel promotions
 * URL: http://localhost/api-percetakan/promotions.php
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
    case 'validate':
        validatePromo($db);
        break;
    case 'active':
        activePromos($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua promo
// ============================================
function getAll($db) {
    $sql = "SELECT * FROM promotions ORDER BY tanggal_dibuat DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $today = date('Y-m-d');
        $is_active = $row['status_aktif'] && 
                     $row['tanggal_mulai'] <= $today && 
                     $row['tanggal_akhir'] >= $today &&
                     ($row['max_penggunaan'] === null || $row['jumlah_terpakai'] < $row['max_penggunaan']);
        
        $data[] = [
            'id_promo' => $row['id_promo'],
            'kode_promo' => $row['kode_promo'],
            'nama_promo' => $row['nama_promo'],
            'jenis' => $row['jenis'],
            'nilai_diskon' => $row['nilai_diskon'],
            'min_pembelian' => $row['min_pembelian'],
            'max_penggunaan' => $row['max_penggunaan'],
            'jumlah_terpakai' => $row['jumlah_terpakai'],
            'tanggal_mulai' => $row['tanggal_mulai'],
            'tanggal_akhir' => $row['tanggal_akhir'],
            'status_aktif' => $row['status_aktif'],
            'is_valid' => $is_active,
            'tanggal_dibuat' => $row['tanggal_dibuat']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'promotions' => $data
    ]);
}

// ============================================
// ACTIVE PROMOS - Promo yang sedang aktif
// ============================================
function activePromos($db) {
    $today = date('Y-m-d');
    
    $sql = "SELECT * FROM promotions 
            WHERE status_aktif = 1 
            AND tanggal_mulai <= '$today' 
            AND tanggal_akhir >= '$today'
            AND (max_penggunaan IS NULL OR jumlah_terpakai < max_penggunaan)
            ORDER BY nilai_diskon DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_promo' => $row['id_promo'],
            'kode_promo' => $row['kode_promo'],
            'nama_promo' => $row['nama_promo'],
            'jenis' => $row['jenis'],
            'nilai_diskon' => $row['nilai_diskon'],
            'min_pembelian' => $row['min_pembelian'],
            'sisa_kuota' => $row['max_penggunaan'] ? ($row['max_penggunaan'] - $row['jumlah_terpakai']) : null,
            'tanggal_akhir' => $row['tanggal_akhir']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'promotions' => $data
    ]);
}

// ============================================
// VALIDATE PROMO - Validasi kode promo
// ============================================
function validatePromo($db) {
    $kode = $db->escape($_GET['kode'] ?? '');
    $subtotal = $db->escape($_GET['subtotal'] ?? 0);
    
    if (empty($kode)) {
        Response::error('Kode promo tidak ditemukan', 400);
    }
    
    $today = date('Y-m-d');
    
    $sql = "SELECT * FROM promotions 
            WHERE kode_promo = '$kode' 
            AND status_aktif = 1
            AND tanggal_mulai <= '$today' 
            AND tanggal_akhir >= '$today'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::error('Kode promo tidak valid atau sudah kadaluarsa', 400);
    }
    
    $promo = $result->fetch_assoc();
    
    // Cek min pembelian
    if ($promo['min_pembelian'] > $subtotal) {
        Response::error('Minimal pembelian Rp ' . number_format($promo['min_pembelian'], 0, ',', '.'), 400);
    }
    
    // Cek kuota
    if ($promo['max_penggunaan'] !== null && $promo['jumlah_terpakai'] >= $promo['max_penggunaan']) {
        Response::error('Kuota promo sudah habis', 400);
    }
    
    // Hitung diskon
    $nilai_diskon = 0;
    if ($promo['jenis'] === 'persentase') {
        $nilai_diskon = ($subtotal * $promo['nilai_diskon']) / 100;
    } elseif ($promo['jenis'] === 'nominal') {
        $nilai_diskon = $promo['nilai_diskon'];
    }
    
    Response::success([
        'id_promo' => $promo['id_promo'],
        'kode_promo' => $promo['kode_promo'],
        'nama_promo' => $promo['nama_promo'],
        'jenis' => $promo['jenis'],
        'nilai_diskon_setting' => $promo['nilai_diskon'],
        'nilai_diskon_rupiah' => $nilai_diskon,
        'valid' => true
    ]);
}

// ============================================
// CREATE - Tambah promo baru
// ============================================
function create($db) {
    $kode_promo = strtoupper($db->escape($_POST['kode_promo'] ?? ''));
    $nama_promo = $db->escape($_POST['nama_promo'] ?? '');
    $jenis = $db->escape($_POST['jenis'] ?? 'persentase');
    $nilai_diskon = $db->escape($_POST['nilai_diskon'] ?? 0);
    $min_pembelian = $db->escape($_POST['min_pembelian'] ?? 0);
    $max_penggunaan = $_POST['max_penggunaan'] ?? null;
    $tanggal_mulai = $db->escape($_POST['tanggal_mulai'] ?? date('Y-m-d'));
    $tanggal_akhir = $db->escape($_POST['tanggal_akhir'] ?? date('Y-m-d'));
    $status_aktif = isset($_POST['status_aktif']) ? (int)$_POST['status_aktif'] : 1;
    
    // Validasi
    if (empty($kode_promo) || empty($nama_promo)) {
        Response::error('Kode promo dan nama promo wajib diisi', 400);
    }
    
    // Cek kode sudah ada
    $checkCode = "SELECT kode_promo FROM promotions WHERE kode_promo = '$kode_promo'";
    $resultCode = $db->query($checkCode);
    if ($resultCode->num_rows > 0) {
        Response::error('Kode promo sudah digunakan', 400);
    }
    
    // Insert
    $max_sql = $max_penggunaan ? "'$max_penggunaan'" : "NULL";
    $sql = "INSERT INTO promotions (kode_promo, nama_promo, jenis, nilai_diskon, min_pembelian, max_penggunaan, tanggal_mulai, tanggal_akhir, status_aktif) 
            VALUES ('$kode_promo', '$nama_promo', '$jenis', '$nilai_diskon', '$min_pembelian', $max_sql, '$tanggal_mulai', '$tanggal_akhir', $status_aktif)";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_promo' => $insertId,
        'kode_promo' => $kode_promo
    ], 'Promo berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail promo
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID promo tidak ditemukan', 400);
    }
    
    $sql = "SELECT * FROM promotions WHERE id_promo = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Promo tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_promo' => $row['id_promo'],
        'kode_promo' => $row['kode_promo'],
        'nama_promo' => $row['nama_promo'],
        'jenis' => $row['jenis'],
        'nilai_diskon' => $row['nilai_diskon'],
        'min_pembelian' => $row['min_pembelian'],
        'max_penggunaan' => $row['max_penggunaan'],
        'jumlah_terpakai' => $row['jumlah_terpakai'],
        'tanggal_mulai' => $row['tanggal_mulai'],
        'tanggal_akhir' => $row['tanggal_akhir'],
        'status_aktif' => $row['status_aktif'],
        'tanggal_dibuat' => $row['tanggal_dibuat']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update promo
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID promo tidak ditemukan', 400);
    }
    
    // Cek promo ada
    $checkSql = "SELECT id_promo FROM promotions WHERE id_promo = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Promo tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['kode_promo'])) {
        $kode = strtoupper($db->escape($_POST['kode_promo']));
        // Cek kode duplikat
        $checkCode = "SELECT id_promo FROM promotions WHERE kode_promo = '$kode' AND id_promo != '$id'";
        $resultCode = $db->query($checkCode);
        if ($resultCode->num_rows > 0) {
            Response::error('Kode promo sudah digunakan', 400);
        }
        $updates[] = "kode_promo = '$kode'";
    }
    
    if (isset($_POST['nama_promo'])) {
        $nama = $db->escape($_POST['nama_promo']);
        $updates[] = "nama_promo = '$nama'";
    }
    
    if (isset($_POST['jenis'])) {
        $jenis = $db->escape($_POST['jenis']);
        $updates[] = "jenis = '$jenis'";
    }
    
    if (isset($_POST['nilai_diskon'])) {
        $nilai = $db->escape($_POST['nilai_diskon']);
        $updates[] = "nilai_diskon = '$nilai'";
    }
    
    if (isset($_POST['min_pembelian'])) {
        $min = $db->escape($_POST['min_pembelian']);
        $updates[] = "min_pembelian = '$min'";
    }
    
    if (isset($_POST['max_penggunaan'])) {
        $max = $_POST['max_penggunaan'] ? "'" . $db->escape($_POST['max_penggunaan']) . "'" : "NULL";
        $updates[] = "max_penggunaan = $max";
    }
    
    if (isset($_POST['tanggal_mulai'])) {
        $tgl_mulai = $db->escape($_POST['tanggal_mulai']);
        $updates[] = "tanggal_mulai = '$tgl_mulai'";
    }
    
    if (isset($_POST['tanggal_akhir'])) {
        $tgl_akhir = $db->escape($_POST['tanggal_akhir']);
        $updates[] = "tanggal_akhir = '$tgl_akhir'";
    }
    
    if (isset($_POST['status_aktif'])) {
        $status = (int)$_POST['status_aktif'];
        $updates[] = "status_aktif = $status";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE promotions SET " . implode(', ', $updates) . " WHERE id_promo = '$id'";
    $db->query($sql);
    
    Response::success(['id_promo' => $id], 'Promo berhasil diupdate');
}

// ============================================
// DELETE - Hapus promo
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID promo tidak ditemukan', 400);
    }
    
    // Cek promo ada
    $checkSql = "SELECT id_promo FROM promotions WHERE id_promo = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Promo tidak ditemukan');
    }
    
    // Hard delete
    $sql = "DELETE FROM promotions WHERE id_promo = '$id'";
    $db->query($sql);
    
    Response::success(['id_promo' => $id], 'Promo berhasil dihapus');
}
?>