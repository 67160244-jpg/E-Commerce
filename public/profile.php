<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

require_login();

$user = current_user();
$pdo = get_db_connection();

// ดึงข้อมูลผู้ใช้ปัจจุบันล่าสุด
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user['id']]);
$userData = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) {
            $error = 'กรุณากรอกชื่อ-นามสกุล';
        } else {
            $upStmt = $pdo->prepare("UPDATE users SET name = :name, phone = :phone WHERE id = :id");
            $upStmt->execute([
                ':name'  => $name,
                ':phone' => $phone,
                ':id'    => $user['id']
            ]);
            $_SESSION['user_name'] = $name;
            $userData['name'] = $name;
            $userData['phone'] = $phone;
            set_flash('success', 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว');
            redirect('public/profile.php');
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            $error = 'กรุณากรอกรหัสผ่านเดิมและรหัสผ่านใหม่';
        } elseif (!password_verify($currentPass, $userData['password_hash'])) {
            $error = 'รหัสผ่านเดิมไม่ถูกต้อง';
        } elseif (mb_strlen($newPass) < 8) {
            $error = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
        } elseif ($newPass !== $confirmPass) {
            $error = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
            $upStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $upStmt->execute([
                ':hash' => $newHashed,
                ':id'   => $user['id']
            ]);
            set_flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
            redirect('public/profile.php');
        }
    }
}

$pageTitle = 'ข้อมูลส่วนตัว';
require_once APP_ROOT . '/includes/header.php';
?>

<div class="row g-4 py-4">
    <!-- Sidebar Account Nav -->
    <div class="col-md-4 col-lg-3">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body text-center p-4">
                <div class="display-6 text-success mb-2">
                    <i class="bi bi-person-circle"></i>
                </div>
                <h5 class="fw-bold mb-0"><?= e($userData['name']) ?></h5>
                <p class="text-muted small mb-2"><?= e($userData['email']) ?></p>
                <span class="badge <?= $userData['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?> px-3 py-1">
                    <?= $userData['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ลูกค้าสมาชิก' ?>
                </span>
            </div>
            <div class="list-group list-group-flush border-top">
                <a href="<?= url('public/profile.php') ?>" class="list-group-item list-group-item-action active fw-semibold">
                    <i class="bi bi-person me-2"></i> ข้อมูลส่วนตัว
                </a>
                <a href="<?= url('public/addresses.php') ?>" class="list-group-item list-group-item-action">
                    <i class="bi bi-geo-alt me-2"></i> สมุดที่อยู่
                </a>
                <a href="<?= url('public/orders.php') ?>" class="list-group-item list-group-item-action">
                    <i class="bi bi-bag-check me-2"></i> ประวัติการสั่งซื้อ
                </a>
                <a href="<?= url('public/logout.php') ?>" class="list-group-item list-group-item-action text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="col-md-8 col-lg-9">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Edit Form -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square text-success me-2"></i> แก้ไขข้อมูลส่วนตัว</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label small fw-semibold">ชื่อ - นามสกุล</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($userData['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold">อีเมล (ไม่สามารถเปลี่ยนได้)</label>
                            <input type="email" class="form-control bg-light" id="email" value="<?= e($userData['email']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label small fw-semibold">เบอร์โทรศัพท์</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($userData['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">วันที่สมัครสมาชิก</label>
                            <input type="text" class="form-control bg-light" value="<?= format_date_th($userData['created_at']) ?>" readonly>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-success px-4 rounded-pill">
                            บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Change Form -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-lock text-success me-2"></i> เปลี่ยนรหัสผ่าน</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="current_password" class="form-label small fw-semibold">รหัสผ่านเดิม</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="col-md-4">
                            <label for="new_password" class="form-label small fw-semibold">รหัสผ่านใหม่ (≥ 8 ตัว)</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                        </div>
                        <div class="col-md-4">
                            <label for="confirm_password" class="form-label small fw-semibold">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-outline-success px-4 rounded-pill">
                            อัปเดตรหัสผ่าน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
