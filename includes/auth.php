<?php
/**
 * Mochi Vita - Authentication & Authorization System
 * การจัดการ Session สิทธิ์ผู้ใช้งาน และการล็อกอิน/ออกจากระบบ
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

defined('APP_ROOT') or define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';

/**
 * ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * ส่งคืนข้อมูลผู้ใช้ปัจจุบันที่ล็อกอินอยู่
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role'] ?? 'customer'
    ];
}

/**
 * ตรวจสอบว่าผู้ใช้ปัจจุบันเป็น Admin หรือไม่
 * @return bool
 */
function is_admin(): bool {
    return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'admin');
}

/**
 * เข้าสู่ระบบ บันทึกข้อมูลลง Session และ Regenerate Session ID เพื่อป้องกัน Session Fixation
 * @param array $user
 */
function login_user(array $user): void {
    // Regenerate session id เพื่อความปลอดภัย
    session_regenerate_id(true);

    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
}

/**
 * ออกจากระบบ ล้าง Session ทั้งหมด
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * บังคับให้ต้องล็อกอินก่อนเข้าถึงหน้า หากไม่ได้ล็อกอินจะถูกส่งไปหน้า Login
 * @param string|null $redirectAfter
 */
function require_login(?string $redirectAfter = null): void {
    if (!is_logged_in()) {
        if ($redirectAfter === null) {
            $redirectAfter = $_SERVER['REQUEST_URI'];
        }
        $_SESSION['redirect_after_login'] = $redirectAfter;
        set_flash('warning', 'กรุณาเข้าสู่ระบบก่อนทำรายการ');
        redirect('public/login.php');
    }
}

/**
 * บังคับสิทธิ์แอดมินเท่านั้น (role = admin)
 */
function require_admin(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        set_flash('warning', 'กรุณาเข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบ');
        redirect('public/login.php');
    }

    if (!is_admin()) {
        http_response_code(403);
        die("
        <!DOCTYPE html>
        <html lang=\"th\">
        <head>
            <meta charset=\"UTF-8\">
            <title>403 ไม่ได้รับอนุญาต</title>
            <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">
            <link href=\"https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap\" rel=\"stylesheet\">
            <style>body { font-family: 'Prompt', sans-serif; }</style>
        </head>
        <body class=\"bg-light d-flex align-items-center min-vh-100\">
            <div class=\"container text-center py-5\">
                <div class=\"card shadow-sm mx-auto p-4\" style=\"max-width: 500px;\">
                    <h1 class=\"display-4 text-danger\">403</h1>
                    <h4 class=\"mb-3\">ปฏิเสธการเข้าถึง</h4>
                    <p class=\"text-muted\">หน้านี้สงวนสิทธิ์เฉพาะผู้ดูแลระบบ (Admin) เท่านั้น</p>
                    <a href=\"" . url('public/index.php') . "\" class=\"btn btn-primary mt-2\">กลับสู่หน้าแรก</a>
                </div>
            </div>
        </body>
        </html>");
    }
}
