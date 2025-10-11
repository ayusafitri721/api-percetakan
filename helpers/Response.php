<?php
/**
 * File: helpers/Response.php
 * Helper untuk response JSON yang konsisten
 */

class Response {
    
    // Response sukses
    public static function success($data = null, $message = 'Berhasil', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'status' => 'success',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Response error
    public static function error($message = 'Terjadi kesalahan', $code = 400, $errors = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Response unauthorized (401)
    public static function unauthorized($message = 'Unauthorized - Token tidak valid atau sudah expired') {
        self::error($message, 401);
    }

    // Response forbidden (403)
    public static function forbidden($message = 'Forbidden - Anda tidak memiliki akses') {
        self::error($message, 403);
    }

    // Response not found (404)
    public static function notFound($message = 'Data tidak ditemukan') {
        self::error($message, 404);
    }

    // Response validation error (422)
    public static function validationError($errors, $message = 'Validasi gagal') {
        self::error($message, 422, $errors);
    }

    // Response server error (500)
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }

    // Response method not allowed (405)
    public static function methodNotAllowed($message = 'Method tidak diizinkan') {
        self::error($message, 405);
    }

    // Response created (201)
    public static function created($data = null, $message = 'Data berhasil dibuat') {
        self::success($data, $message, 201);
    }

    // Response no content (204)
    public static function noContent() {
        http_response_code(204);
        exit;
    }
}
?>