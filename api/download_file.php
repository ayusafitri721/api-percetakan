<?php
/**
 * download_file.php - Force download file design
 * Letakkan di: api-percetakan/api/download_file.php
 */

// Get file path from query string
$file_url = $_GET['file'] ?? '';

if (empty($file_url)) {
    http_response_code(400);
    die('File URL tidak ditemukan');
}

// Parse URL to get file path
$parsed = parse_url($file_url);
$file_path = $_SERVER['DOCUMENT_ROOT'] . $parsed['path'];

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    die('File tidak ditemukan: ' . $file_path);
}

// Get file info
$file_name = basename($file_path);
$file_size = filesize($file_path);
$file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// Set MIME type
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    'gif' => 'image/gif',
];

$mime_type = $mime_types[$file_ext] ?? 'application/octet-stream';

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Set headers for download
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($file_path);
exit;
?>