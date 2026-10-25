<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

if (is_logged_in()) {
    redirect('public/index.php');
}

$error = '';
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (mb_strlen($password) < 8) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $password_confirm) {
        $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $pdo = get_db_connection();

        // ตรวจสอบอีเมลซ้ำ
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $error = 'อีเมลนี้ถูกใช้งานในระบบแล้ว กรุณาใช้อีเมลอื่นหรือเข้าสู่ระบบ';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password_hash, role, created_at)
                VALUES (:name, :email, :phone, :hash, 'customer', NOW())
            ");
            $insertStmt->execute([
                ':name'  => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':hash'  => $hashed
            ]);

            $newUserId = $pdo->lastInsertId();

            // ล็อกอินอัตโนมัติ
            login_user([
                'id'    => $newUserId,
                'name'  => $name,
                'email' => $email,
                'role'  => 'customer'
            ]);

            set_flash('success', 'สมัครสมาชิกสำเร็จ! ยินดีต้อนรับสู่ Mochi Vita');
            redirect('public/index.php');
        }
    }
}

$pageTitle = 'สมัครสมาชิก';
require_once APP_ROOT . '/includes/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="display-6 text-success mb-2">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h3 class="fw-bold text-dark">สร้างบัญชีสมาชิกใหม่</h3>
                    <p class="text-muted small">สมัครสมาชิกเพื่อรับสิทธิพิเศษและสั่งซื้อสินค้าง่ายดาย</p>
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
                        <label for="name" class="form-label small fw-semibold text-secondary">ชื่อ - นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($name) ?>" placeholder="สมชาย ใจดี" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold text-secondary">อีเมล <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" placeholder="example@email.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label small fw-semibold text-secondary">เบอร์โทรศัพท์</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="08x-xxx-xxxx">
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label small fw-semibold text-secondary">รหัสผ่าน (≥ 8 ตัว) <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8" placeholder="••••••••" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label small fw-semibold text-secondary">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2 fw-semibold rounded-pill mb-3" style="background-color: #10b981; border-color: #10b981;">
                        ยืนยันการสมัครสมาชิก
                    </button>

                    <div class="text-center small text-muted">
                        มีบัญชีอยู่แล้ว? <a href="<?= url('public/login.php') ?>" class="text-success text-decoration-none fw-semibold">เข้าสู่ระบบที่นี่</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
