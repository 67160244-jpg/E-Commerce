<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

// หากล็อกอินอยู่แล้ว ให้เปลี่ยนเส้นทาง
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/index.php');
    } else {
        redirect('public/index.php');
    }
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // อ่าน IP จริงของผู้ใช้ (รองรับ Cloudflare / Reverse Proxy)
    $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] 
        ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
        ?? $_SERVER['REMOTE_ADDR'] 
        ?? '0.0.0.0';
    if (strpos($clientIp, ',') !== false) {
        $clientIp = trim(explode(',', $clientIp)[0]);
    }

    if (empty($email) || empty($password)) {
        $error = 'กรุณากรอกอีเมลและรหัสผ่านให้ครบถ้วน';
    } else {
        $pdo = get_db_connection();

        // ตรวจสอบ Rate Limiting: ผิดเกิน 5 ครั้งใน 10 นาทีหรือไม่ (ตาม email + IP)
        $chkAttempt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE (email = :email OR ip = :ip) 
              AND success = 0 
              AND attempted_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $chkAttempt->execute([':email' => $email, ':ip' => $clientIp]);
        $failedCount = (int)$chkAttempt->fetchColumn();

        if ($failedCount >= 5) {
            $error = 'มีการพยายามเข้าสู่ระบบไม่ถูกต้องเกินกำหนด (5 ครั้ง) เพื่อความปลอดภัย กรุณารอประมาณ 10 นาทีแล้วจึงลองใหม่อีกครั้ง';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // บันทึกความพยายามสำเร็จ
                $pdo->prepare("INSERT INTO login_attempts (email, ip, attempted_at, success) VALUES (:email, :ip, NOW(), 1)")
                    ->execute([':email' => $email, ':ip' => $clientIp]);

                login_user($user);
                set_flash('success', 'ยินดีต้อนรับคุณ ' . e($user['name']) . ' เข้าสู่ระบบเรียบร้อยแล้ว');

                // ตรวจสอบหน้าที่ต้องการไปต่อ
                $redirectUrl = $_SESSION['redirect_after_login'] ?? null;
                unset($_SESSION['redirect_after_login']);

                if ($redirectUrl) {
                    redirect($redirectUrl);
                } elseif ($user['role'] === 'admin') {
                    redirect('admin/index.php');
                } else {
                    redirect('public/index.php');
                }
            } else {
                // บันทึกความพยายามไม่สำเร็จ
                $pdo->prepare("INSERT INTO login_attempts (email, ip, attempted_at, success) VALUES (:email, :ip, NOW(), 0)")
                    ->execute([':email' => $email, ':ip' => $clientIp]);

                // ข้อความ error เดียวกันทั้งกรณีอีเมลผิดและรหัสผิด ไม่เปิดเผยว่าอีเมลมีอยู่จริงหรือไม่
                $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
            }
        }
    }
}

$pageTitle = 'เข้าสู่ระบบ';
require_once APP_ROOT . '/includes/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-5 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="display-6 text-success mb-2">
                        <i class="bi bi-person-lock"></i>
                    </div>
                    <h3 class="fw-bold text-dark">เข้าสู่ระบบ</h3>
                    <p class="text-muted small">เข้าสู่ระบบเพื่อจัดการสั่งซื้อและติดตามสถานะพัสดุ</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                        <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold text-secondary">อีเมล</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="name@example.com" required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label small fw-semibold text-secondary">รหัสผ่าน</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2 fw-semibold rounded-pill mb-3" style="background-color: #10b981; border-color: #10b981;">
                        เข้าสู่ระบบ
                    </button>

                    <div class="text-center small text-muted">
                        ยังไม่มีบัญชีสมาชิก? <a href="<?= url('public/register.php') ?>" class="text-success text-decoration-none fw-semibold">สมัครสมาชิกที่นี่</a>
                    </div>
                </form>

                <hr class="my-4 text-muted opacity-25">
                <div class="bg-light p-3 rounded-3 small text-muted">
                    <strong class="d-block text-dark mb-1"><i class="bi bi-info-circle me-1"></i> บัญชีสำหรับทดสอบ:</strong>
                    <div>แอดมิน: <code>admin@mochivita.test</code> / <code>Admin@1234</code></div>
                    <div>ลูกค้า: <code>customer1@example.com</code> / <code>Demo@1234</code></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
