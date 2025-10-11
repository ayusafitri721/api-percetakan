<?php
/**
 * File: config/cors.php
 * Handle CORS untuk akses dari React Native / Frontend
 */

// Allow dari semua origin (untuk development)
// Untuk production, ganti * dengan domain spesifik
header('Access-Control-Allow-Origin: *');

// Allow methods
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// Allow headers
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Allow credentials
header('Access-Control-Allow-Credentials: true');

// Max age untuk preflight request
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set content type default
header('Content-Type: application/json; charset=UTF-8');
?>