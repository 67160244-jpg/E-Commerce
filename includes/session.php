<?php
/**
 * Mochi Vita - Secure Session Management
 * จัดการ Session และ Cookie ให้ปลอดภัยสูงสุด (HttpOnly, SameSite=Lax, Secure เมื่อเป็น HTTPS, Strict Mode)
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
               || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    
    // ตั้งค่า Cookie พารามิเตอร์ 2 ชั่วโมง
    session_set_cookie_params([
        'lifetime' => 7200,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    // Session inactivity timeout: 2 ชั่วโมง (7200 วินาที)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}
