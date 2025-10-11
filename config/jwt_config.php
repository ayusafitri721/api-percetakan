<?php
/**
 * File: config/jwt_config.php
 * Konfigurasi JWT (JSON Web Token)
 */

// Secret key untuk encode/decode JWT
// PENTING: Ganti dengan random string yang kuat untuk production!
define('JWT_SECRET_KEY', 'percetakan_secret_key_2024_change_this_in_production');

// Issuer (nama aplikasi)
define('JWT_ISSUER', 'api-percetakan');

// Audience (siapa yang bisa pakai token)
define('JWT_AUDIENCE', 'percetakan-users');

// Token expiration time (dalam detik)
define('JWT_EXPIRATION_TIME', 86400); // 24 jam = 86400 detik

// Refresh token expiration (dalam detik)
define('JWT_REFRESH_EXPIRATION', 604800); // 7 hari = 604800 detik

// Algorithm untuk JWT
define('JWT_ALGORITHM', 'HS256');
?>