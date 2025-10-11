<?php
/**
 * File: helpers/Auth.php
 * Helper untuk JWT Authentication
 */

require_once __DIR__ . '/../config/jwt_config.php';
require_once __DIR__ . '/Response.php';

class Auth {
    
    /**
     * Generate JWT Token
     */
    public static function generateToken($userId, $email, $role) {
        $issuedAt = time();
        $expire = $issuedAt + JWT_EXPIRATION_TIME;
        
        $payload = [
            'iss' => JWT_ISSUER,
            'aud' => JWT_AUDIENCE,
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => [
                'id' => $userId,
                'email' => $email,
                'role' => $role
            ]
        ];
        
        return self::encode($payload);
    }

    /**
     * Encode payload menjadi JWT
     */
    private static function encode($payload) {
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => JWT_ALGORITHM
        ]);
        
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET_KEY, true);
        $base64Signature = self::base64UrlEncode($signature);
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    /**
     * Decode dan validasi JWT
     */
    public static function decode($jwt) {
        $tokenParts = explode('.', $jwt);
        
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signatureProvided = $tokenParts[2];
        
        // Verify signature
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET_KEY, true);
        $base64Signature = self::base64UrlEncode($signature);
        
        if ($base64Signature !== $signatureProvided) {
            return false;
        }
        
        $payloadData = json_decode($payload, true);
        
        // Check expiration
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }

    /**
     * Get token dari header Authorization
     */
    public static function getBearerToken() {
        $headers = self::getAuthorizationHeader();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * Get Authorization header
     */
    private static function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }

    /**
     * Validasi token dan return user data
     */
    public static function validateToken() {
        $token = self::getBearerToken();
        
        if (!$token) {
            Response::unauthorized('Token tidak ditemukan');
        }
        
        $decoded = self::decode($token);
        
        if (!$decoded) {
            Response::unauthorized('Token tidak valid atau sudah expired');
        }
        
        return $decoded['data'];
    }

    /**
     * Check apakah user punya role tertentu
     */
    public static function checkRole($requiredRoles = []) {
        $user = self::validateToken();
        
        if (!in_array($user['role'], $requiredRoles)) {
            Response::forbidden('Anda tidak memiliki akses ke resource ini');
        }
        
        return $user;
    }

    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Base64 URL Encode
     */
    private static function base64UrlEncode($text) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }
}
?>