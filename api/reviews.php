<?php
/**
 * API Reviews - CRUD Tabel reviews
 * URL: http://localhost/api-percetakan/reviews.php
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
    case 'by_order':
        byOrder($db);
        break;
    case 'by_product':
        byProduct($db);
        break;
    case 'statistics':
        statistics($db);
        break;
    default:
        getAll($db);
        break;
}

// ============================================
// GET ALL - Ambil semua ulasan
// ============================================
function getAll($db) {
    $sql = "SELECT r.*, u.nama as nama_pelanggan, o.kode_order 
            FROM reviews r
            LEFT JOIN users u ON r.id_user = u.id_user
            LEFT JOIN orders o ON r.id_order = o.id_order
            ORDER BY r.tanggal_review DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_review' => $row['id_review'],
            'id_order' => $row['id_order'],
            'kode_order' => $row['kode_order'],
            'id_user' => $row['id_user'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'rating' => $row['rating'],
            'komentar' => $row['komentar'],
            'tanggal_review' => $row['tanggal_review']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'reviews' => $data
    ]);
}

// ============================================
// GET BY ORDER - Ulasan per pesanan
// ============================================
function byOrder($db) {
    $id_order = $db->escape($_GET['id_order'] ?? '');
    
    if (empty($id_order)) {
        Response::error('ID order tidak ditemukan', 400);
    }
    
    $sql = "SELECT r.*, u.nama as nama_pelanggan 
            FROM reviews r
            LEFT JOIN users u ON r.id_user = u.id_user
            WHERE r.id_order = '$id_order'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Ulasan tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_review' => $row['id_review'],
        'id_order' => $row['id_order'],
        'nama_pelanggan' => $row['nama_pelanggan'],
        'rating' => $row['rating'],
        'komentar' => $row['komentar'],
        'tanggal_review' => $row['tanggal_review']
    ];
    
    Response::success($data);
}

// ============================================
// GET BY PRODUCT - Ulasan per produk
// ============================================
function byProduct($db) {
    $id_product = $db->escape($_GET['id_product'] ?? '');
    
    if (empty($id_product)) {
        Response::error('ID produk tidak ditemukan', 400);
    }
    
    $sql = "SELECT r.*, u.nama as nama_pelanggan, o.kode_order 
            FROM reviews r
            LEFT JOIN users u ON r.id_user = u.id_user
            LEFT JOIN orders o ON r.id_order = o.id_order
            LEFT JOIN order_items oi ON o.id_order = oi.id_order
            WHERE oi.id_product = '$id_product'
            GROUP BY r.id_review
            ORDER BY r.tanggal_review DESC";
    
    $result = $db->query($sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id_review' => $row['id_review'],
            'nama_pelanggan' => $row['nama_pelanggan'],
            'rating' => $row['rating'],
            'komentar' => $row['komentar'],
            'tanggal_review' => $row['tanggal_review']
        ];
    }
    
    Response::success([
        'total' => count($data),
        'reviews' => $data
    ]);
}

// ============================================
// STATISTICS - Statistik rating
// ============================================
function statistics($db) {
    $sql = "SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as rata_rata_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
            FROM reviews";
    
    $result = $db->query($sql);
    $data = $result->fetch_assoc();
    
    Response::success([
        'total_reviews' => $data['total_reviews'],
        'rata_rata_rating' => round($data['rata_rata_rating'], 2),
        'breakdown' => [
            'bintang_5' => $data['rating_5'],
            'bintang_4' => $data['rating_4'],
            'bintang_3' => $data['rating_3'],
            'bintang_2' => $data['rating_2'],
            'bintang_1' => $data['rating_1']
        ]
    ]);
}

// ============================================
// CREATE - Tambah ulasan
// ============================================
function create($db) {
    $id_order = $db->escape($_POST['id_order'] ?? '');
    $id_user = $db->escape($_POST['id_user'] ?? '');
    $rating = $db->escape($_POST['rating'] ?? 0);
    $komentar = $db->escape($_POST['komentar'] ?? '');
    
    // Validasi
    if (empty($id_order) || empty($id_user) || empty($rating)) {
        Response::error('ID order, user dan rating wajib diisi', 400);
    }
    
    if ($rating < 1 || $rating > 5) {
        Response::error('Rating harus antara 1-5', 400);
    }
    
    // Cek order ada dan milik user
    $checkOrder = "SELECT id_order FROM orders WHERE id_order = '$id_order' AND id_user = '$id_user'";
    $resultOrder = $db->query($checkOrder);
    if ($resultOrder->num_rows === 0) {
        Response::error('Order tidak ditemukan atau bukan milik user ini', 400);
    }
    
    // Cek sudah pernah review
    $checkReview = "SELECT id_review FROM reviews WHERE id_order = '$id_order' AND id_user = '$id_user'";
    $resultReview = $db->query($checkReview);
    if ($resultReview->num_rows > 0) {
        Response::error('Order ini sudah pernah direview', 400);
    }
    
    // Insert
    $sql = "INSERT INTO reviews (id_order, id_user, rating, komentar) 
            VALUES ('$id_order', '$id_user', '$rating', '$komentar')";
    
    $db->query($sql);
    $insertId = $db->lastInsertId();
    
    Response::created([
        'id_review' => $insertId,
        'rating' => $rating
    ], 'Ulasan berhasil ditambahkan');
}

// ============================================
// DETAIL - Detail ulasan
// ============================================
function detail($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID review tidak ditemukan', 400);
    }
    
    $sql = "SELECT r.*, u.nama as nama_pelanggan, o.kode_order 
            FROM reviews r
            LEFT JOIN users u ON r.id_user = u.id_user
            LEFT JOIN orders o ON r.id_order = o.id_order
            WHERE r.id_review = '$id'";
    
    $result = $db->query($sql);
    
    if ($result->num_rows === 0) {
        Response::notFound('Ulasan tidak ditemukan');
    }
    
    $row = $result->fetch_assoc();
    $data = [
        'id_review' => $row['id_review'],
        'id_order' => $row['id_order'],
        'kode_order' => $row['kode_order'],
        'id_user' => $row['id_user'],
        'nama_pelanggan' => $row['nama_pelanggan'],
        'rating' => $row['rating'],
        'komentar' => $row['komentar'],
        'tanggal_review' => $row['tanggal_review']
    ];
    
    Response::success($data);
}

// ============================================
// UPDATE - Update ulasan
// ============================================
function update($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID review tidak ditemukan', 400);
    }
    
    // Cek review ada
    $checkSql = "SELECT id_review FROM reviews WHERE id_review = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Ulasan tidak ditemukan');
    }
    
    // Build update query
    $updates = [];
    
    if (isset($_POST['rating'])) {
        $rating = $db->escape($_POST['rating']);
        if ($rating < 1 || $rating > 5) {
            Response::error('Rating harus antara 1-5', 400);
        }
        $updates[] = "rating = '$rating'";
    }
    
    if (isset($_POST['komentar'])) {
        $komentar = $db->escape($_POST['komentar']);
        $updates[] = "komentar = '$komentar'";
    }
    
    if (empty($updates)) {
        Response::error('Tidak ada data yang diupdate', 400);
    }
    
    $sql = "UPDATE reviews SET " . implode(', ', $updates) . " WHERE id_review = '$id'";
    $db->query($sql);
    
    Response::success(['id_review' => $id], 'Ulasan berhasil diupdate');
}

// ============================================
// DELETE - Hapus ulasan
// ============================================
function delete($db) {
    $id = $db->escape($_GET['id'] ?? '');
    
    if (empty($id)) {
        Response::error('ID review tidak ditemukan', 400);
    }
    
    // Cek review ada
    $checkSql = "SELECT id_review FROM reviews WHERE id_review = '$id'";
    $checkResult = $db->query($checkSql);
    if ($checkResult->num_rows === 0) {
        Response::notFound('Ulasan tidak ditemukan');
    }
    
    // Delete
    $sql = "DELETE FROM reviews WHERE id_review = '$id'";
    $db->query($sql);
    
    Response::success(['id_review' => $id], 'Ulasan berhasil dihapus');
}
?>