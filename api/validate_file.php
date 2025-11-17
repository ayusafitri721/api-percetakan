<?php
/**
 * validate_file.php
 * API Endpoint untuk File Validation
 * Location: api/validate_file.php
 * 
 * Usage:
 * POST /api/validate_file.php
 * Body: multipart/form-data
 * - file: [file to validate]
 */

// ENABLE ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include dependencies
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/validators/FileValidator.php';

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed. Use POST.', 405);
    exit();
}

// Main validation process
try {
    // Check if file exists in request
    if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
        Response::error('File tidak ditemukan dalam request. Gunakan key "file" untuk upload.', 400);
        exit();
    }
    
    $file = $_FILES['file'];
    
    // Log incoming file info
    error_log("📁 Validating file: " . $file['name']);
    error_log("📊 File size: " . round($file['size'] / 1024, 2) . " KB");
    
    // Initialize validator
    $validator = new FileValidator();
    
    // Run validation
    $validationResult = $validator->validate($file);
    
    // Get detailed file info
    $fileInfo = $validator->getFileInfo($file);
    
    // Log validation result
    error_log("✅ Validation completed");
    error_log("📊 Confidence Score: " . $validationResult['confidence_score']);
    error_log("🎯 Is Valid: " . ($validationResult['is_valid'] ? 'YES' : 'NO'));
    
    // Optional: Save validation log to database
    if (isset($_POST['id_order']) && !empty($_POST['id_order'])) {
        $id_order = intval($_POST['id_order']);
        saveValidationLog($id_order, 'file', $validationResult);
    }
    
    // Prepare response
    $responseData = [
        'file_info' => $fileInfo,
        'validation' => $validationResult
    ];
    
    // Return response based on validation result
    if ($validationResult['is_valid']) {
        Response::success($responseData, 'File validation berhasil');
    } else {
        // Still return success status but with validation details
        // Frontend will check 'is_valid' field
        Response::success($responseData, 'File validation selesai dengan errors');
    }
    
} catch (Exception $e) {
    error_log("❌ Validation error: " . $e->getMessage());
    Response::error('Terjadi kesalahan saat validasi: ' . $e->getMessage(), 500);
}

/**
 * Save validation log to database
 */
function saveValidationLog($id_order, $type, $result) {
    try {
        $database = new Database();
        $db = $database->connect();
        
        $status = $result['is_valid'] ? 'pass' : 'fail';
        $message = $result['recommendation'];
        
        // Convert arrays to JSON
        $details = json_encode([
            'confidence_score' => $result['confidence_score'],
            'checks' => $result['checks'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings']
        ]);
        
        $sql = "INSERT INTO validation_logs 
                (id_order, validation_type, status, message, details) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('issss', $id_order, $type, $status, $message, $details);
        $stmt->execute();
        
        error_log("💾 Validation log saved for order #" . $id_order);
        
    } catch (Exception $e) {
        error_log("⚠️ Failed to save validation log: " . $e->getMessage());
        // Don't throw error, just log it
    }
}
?>