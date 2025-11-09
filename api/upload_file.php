<?php
/**
 * Generic File Upload Handler
 * Handles file uploads to various folders
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../helpers/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
    exit();
}

// Check if file exists
if (!isset($_FILES['file'])) {
    Response::error('No file uploaded', 400);
    exit();
}

$file = $_FILES['file'];
$folder = $_POST['folder'] ?? 'uploads';

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    Response::error('File upload error: ' . $file['error'], 400);
    exit();
}

// File size limit: 20MB
$maxSize = 20 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    Response::error('File too large. Maximum size is 20MB', 400);
    exit();
}

// Allowed file types
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    Response::error('Invalid file type. Only JPG, PNG, and PDF allowed', 400);
    exit();
}

// Create upload directory if not exists
$uploadDir = "../uploads/$folder/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$fileName = uniqid() . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $fileName;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    Response::error('Failed to save file', 500);
    exit();
}

// Return success with file URL
$fileUrl = "http://" . $_SERVER['HTTP_HOST'] . "/api-percetakan/uploads/$folder/" . $fileName;

Response::success([
    'file_name' => $fileName,
    'file_url' => $fileUrl,
    'file_size' => $file['size'],
    'file_type' => $mimeType
], 'File uploaded successfully');
?>