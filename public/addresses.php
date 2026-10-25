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

$error = '';
$editAddress = null;

// ดำเนินการ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $label = trim($_POST['label'] ?? 'บ้าน');
        $recipient = trim($_POST['recipient'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address_line = trim($_POST['address_line'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postcode = trim($_POST['postcode'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (empty($recipient) || empty($phone) || empty($address_line) || empty($province) || empty($postcode)) {
            $error = 'กรุณากรอกข้อมูลที่อยู่ให้ครบถ้วน';
        } else {
            // หากตั้งเป็นค่าเริ่มต้น ให้เคลียร์อันเดิมออกก่อน
            if ($is_default) {
                $clearStmt = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = :user_id");
                $clearStmt->execute([':user_id' => $user['id']]);
            }

            if ($id) {
                // แก้ไข (ต้องเป็นของตัวเองเท่านั้น)
                $upStmt = $pdo->prepare("
                    UPDATE addresses 
                    SET label = :label, recipient = :recipient, phone = :phone, address_line = :address_line,
                        district = :district, province = :province, postcode = :postcode, is_default = :is_default
                    WHERE id = :id AND user_id = :user_id
                ");
                $upStmt->execute([
                    ':label'        => $label,
                    ':recipient'    => $recipient,
                    ':phone'        => $phone,
                    ':address_line' => $address_line,
                    ':district'     => $district,
                    ':province'     => $province,
                    ':postcode'     => $postcode,
                    ':is_default'   => $is_default,
                    ':id'           => $id,
                    ':user_id'      => $user['id']
                ]);
                set_flash('success', 'แก้ไขข้อมูลที่อยู่เรียบร้อยแล้ว');
            } else {
                // ถ้าเป็นที่อยู่แรกสุด ให้ตั้งเป็นค่าเริ่มต้นอัตโนมัติ
                $count = (int)$pdo->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = :user_id");
                $count->execute([':user_id' => $user['id']]);
                if ($count->fetchColumn() == 0) {
                    $is_default = 1;
                }

                $inStmt = $pdo->prepare("
                    INSERT INTO addresses (user_id, label, recipient, phone, address_line, district, province, postcode, is_default)
                    VALUES (:user_id, :label, :recipient, :phone, :address_line, :district, :province, :postcode, :is_default)
                ");
                $inStmt->execute([
                    ':user_id'      => $user['id'],
                    ':label'        => $label,
                    ':recipient'    => $recipient,
                    ':phone'        => $phone,
                    ':address_line' => $address_line,
                    ':district'     => $district,
                    ':province'     => $province,
                    ':postcode'     => $postcode,
                    ':is_default'   => $is_default
                ]);
                set_flash('success', 'เพิ่มที่อยู่ใหม่เรียบร้อยแล้ว');
            }
            redirect('public/addresses.php');
        }
    } elseif ($action === 'set_default') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = :uid")->execute([':uid' => $user['id']]);
        $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = :id AND user_id = :uid")->execute([':id' => $id, ':uid' => $user['id']]);
        set_flash('success', 'ตั้งเป็นที่อยู่จัดส่งเริ่มต้นเรียบร้อยแล้ว');
        redirect('public/addresses.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM addresses WHERE id = :id AND user_id = :uid")->execute([':id' => $id, ':uid' => $user['id']]);
        set_flash('success', 'ลบที่อยู่เรียบร้อยแล้ว');
        redirect('public/addresses.php');
    }
}

// ตรวจสอบการแก้ไข GET edit
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editId > 0) {
    $eStmt = $pdo->prepare("SELECT * FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1");
    $eStmt->execute([':id' => $editId, ':uid' => $user['id']]);
    $editAddress = $eStmt->fetch();
}

// ดึงรายการที่อยู่ทั้งหมดของผู้ใช้
$addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = :uid ORDER BY is_default DESC, id DESC");
$addrStmt->execute([':uid' => $user['id']]);
$addresses = $addrStmt->fetchAll();

$pageTitle = 'สมุดที่อยู่จัดส่ง';
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
                <h5 class="fw-bold mb-0"><?= e($user['name']) ?></h5>
                <p class="text-muted small mb-0"><?= e($user['email']) ?></p>
            </div>
            <div class="list-group list-group-flush border-top">
                <a href="<?= url('public/profile.php') ?>" class="list-group-item list-group-item-action">
                    <i class="bi bi-person me-2"></i> ข้อมูลส่วนตัว
                </a>
                <a href="<?= url('public/addresses.php') ?>" class="list-group-item list-group-item-action active fw-semibold">
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0 text-dark">
                <i class="bi bi-geo-alt text-success me-2"></i> สมุดที่อยู่จัดส่ง
            </h4>
            <?php if (!$editAddress): ?>
                <a href="#addressFormCard" class="btn btn-sm btn-success rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i> เพิ่มที่อยู่ใหม่
                </a>
            <?php endif; ?>
        </div>

        <!-- Address List Cards -->
        <div class="row g-3 mb-4">
            <?php if (empty($addresses)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm text-center p-5 text-muted">
                        <i class="bi bi-geo-alt fs-1 text-secondary mb-2"></i>
                        <p class="mb-0">ยังไม่มีที่อยู่จัดส่งที่บันทึกไว้ เพิ่มที่อยู่ด้านล่างเพื่อความสะดวกในการสั่งซื้อ</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($addresses as $addr): ?>
                    <div class="col-md-6">
                        <div class="card h-100 border <?= $addr['is_default'] ? 'border-success shadow-sm' : 'border-light-subtle' ?> rounded-3">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-light text-dark border fw-medium"><?= e($addr['label'] ?: 'ที่อยู่') ?></span>
                                        <?php if ($addr['is_default']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">ที่อยู่เริ่มต้น</span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark"><?= e($addr['recipient']) ?></h6>
                                    <p class="text-muted small mb-1"><i class="bi bi-telephone me-1"></i> <?= e($addr['phone']) ?></p>
                                    <p class="text-secondary small mb-0">
                                        <?= e($addr['address_line']) ?>
                                        <?= !empty($addr['district']) ? ' ' . e($addr['district']) : '' ?>
                                        <?= e($addr['province']) ?> <?= e($addr['postcode']) ?>
                                    </p>
                                </div>

                                <div class="mt-3 pt-2 border-top d-flex gap-2 align-items-center">
                                    <a href="<?= url('public/addresses.php?edit=' . $addr['id']) ?>#addressFormCard" class="btn btn-sm btn-outline-secondary py-1 px-2">
                                        <i class="bi bi-pencil"></i> แก้ไข
                                    </a>
                                    <?php if (!$addr['is_default']): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="set_default">
                                            <input type="hidden" name="id" value="<?= $addr['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2">ตั้งเป็นค่าเริ่มต้น</button>
                                        </form>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('ยืนยันลบที่อยู่นี้?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $addr['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Add / Edit Form -->
        <div class="card border-0 shadow-sm rounded-3" id="addressFormCard">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <?= $editAddress ? '<i class="bi bi-pencil-square text-success me-2"></i> แก้ไขที่อยู่จัดส่ง' : '<i class="bi bi-plus-circle text-success me-2"></i> เพิ่มที่อยู่จัดส่งใหม่' ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $editAddress['id'] ?? '' ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="label" class="form-label small fw-semibold">ป้ายชื่อที่อยู่</label>
                            <input type="text" class="form-control" id="label" name="label" value="<?= e($editAddress['label'] ?? 'บ้าน') ?>" placeholder="เช่น บ้าน, ที่ทำงาน">
                        </div>
                        <div class="col-md-4">
                            <label for="recipient" class="form-label small fw-semibold">ชื่อผู้รับ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="recipient" name="recipient" value="<?= e($editAddress['recipient'] ?? $user['name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="phone" class="form-label small fw-semibold">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($editAddress['phone'] ?? '') ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="address_line" class="form-label small fw-semibold">ที่อยู่ (บ้านเลขที่, หมู่, ซอย, ถนน) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="address_line" name="address_line" value="<?= e($editAddress['address_line'] ?? '') ?>" placeholder="123/45 ซอย 5 ถนนสุขุมวิท" required>
                        </div>

                        <div class="col-md-4">
                            <label for="district" class="form-label small fw-semibold">อำเภอ / เขต</label>
                            <input type="text" class="form-control" id="district" name="district" value="<?= e($editAddress['district'] ?? '') ?>" placeholder="เมือง">
                        </div>
                        <div class="col-md-4">
                            <label for="province" class="form-label small fw-semibold">จังหวัด <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="province" name="province" value="<?= e($editAddress['province'] ?? '') ?>" placeholder="กรุงเทพมหานคร" required>
                        </div>
                        <div class="col-md-4">
                            <label for="postcode" class="form-label small fw-semibold">รหัสไปรษณีย์ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="postcode" name="postcode" value="<?= e($editAddress['postcode'] ?? '') ?>" placeholder="10110" maxlength="10" required>
                        </div>

                        <div class="col-12">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" <?= (!empty($editAddress['is_default']) || empty($addresses)) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="is_default">
                                    ตั้งเป็นที่อยู่จัดส่งเริ่มต้น
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-success px-4 rounded-pill">
                            <?= $editAddress ? 'บันทึกการแก้ไข' : 'บันทึกที่อยู่' ?>
                        </button>
                        <?php if ($editAddress): ?>
                            <a href="<?= url('public/addresses.php') ?>" class="btn btn-outline-secondary px-4 rounded-pill">ยกเลิก</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
