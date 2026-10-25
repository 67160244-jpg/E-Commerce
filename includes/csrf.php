<?php
/**
 * Mochi Vita - CSRF Protection Helper
 * ระบบป้องกันการโจมตีแบบ Cross-Site Request Forgery (CSRF)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * สร้างหรือส่งคืน CSRF Token ประจำ Session
 * @return string
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * แสดง HTML Hidden Input สำหรับใส่ในฟอร์ม POST
 * @return string
 */
function csrf_field(): string {
    $token = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * ตรวจสอบความถูกต้องของ CSRF Token จากคำขอ POST
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * บังคับตรวจสอบ CSRF Token หากไม่ถูกต้องจะหยุดการทำงานพร้อม HTTP 403
 */
function require_csrf_valid(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            http_response_code(403);
            die('คำขอไม่ถูกต้องหรือหมดอายุ (CSRF Token Invalid) กรุณาลองใหม่อีกครั้ง');
        }
    }
}
