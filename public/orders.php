<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

require_login();

$user = current_user();
$pdo = get_db_connection();

// ตรวจสอบว่ามีการดูรายละเอียดออเดอร์เฉพาะอันหรือไม่
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$singleOrder = null;
$singleOrderItems = [];

if ($viewId > 0) {
    $vStmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1");
    $vStmt->execute([':id' => $viewId, ':uid' => $user['id']]);
    $singleOrder = $vStmt->fetch();

    if ($singleOrder) {
        $iStmt = $pdo->prepare("
            SELECT oi.*, p.image 
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ");
        $iStmt->execute([':order_id' => $singleOrder['id']]);
        $singleOrderItems = $iStmt->fetchAll();
    }
}

// ดึงรายการคำสั่งซื้อทั้งหมดของลูกค้าคนนี้
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = :uid ORDER BY id DESC");
$stmt->execute([':uid' => $user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'ประวัติการสั่งซื้อ';
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
                <a href="<?= url('public/addresses.php') ?>" class="list-group-item list-group-item-action">
                    <i class="bi bi-geo-alt me-2"></i> สมุดที่อยู่
                </a>
                <a href="<?= url('public/orders.php') ?>" class="list-group-item list-group-item-action active fw-semibold">
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
        <?php if ($singleOrder): ?>
            <!-- Single Order Detail View -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-receipt text-success me-2"></i> รายละเอียดคำสั่งซื้อ: <?= e($singleOrder['order_no']) ?>
                        </h5>
                        <small class="text-muted">สั่งซื้อเมื่อ: <?= format_date_th($singleOrder['created_at']) ?></small>
                    </div>
                    <a href="<?= url('public/orders.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                </div>

                <div class="card-body p-4">
                    <!-- Status & Tracking -->
                    <div class="row g-3 p-3 bg-light rounded-3 mb-4 align-items-center">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block mb-1">สถานะคำสั่งซื้อ:</span>
                            <span class="fs-6"><?= get_order_status_badge($singleOrder['status']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block mb-1">หมายเลขพัสดุ (Tracking No.):</span>
                            <?php if (!empty($singleOrder['tracking_no'])): ?>
                                <strong class="text-primary fs-6"><i class="bi bi-truck me-1"></i> <?= e($singleOrder['tracking_no']) ?></strong>
                            <?php else: ?>
                                <span class="text-muted small">ยังไม่มีหมายเลขพัสดุ (จะแสดงเมื่อสถานะเป็นจัดส่งแล้ว)</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Shipping Address & Payment -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="bi bi-geo-alt text-success me-1"></i> ข้อมูลจัดส่ง</h6>
                            <strong class="d-block text-dark"><?= e($singleOrder['ship_name']) ?></strong>
                            <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i> <?= e($singleOrder['ship_phone']) ?></div>
                            <div class="small text-secondary"><?= e($singleOrder['ship_address']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="bi bi-credit-card text-success me-1"></i> การชำระเงิน</h6>
                            <div class="small text-dark mb-1">ช่องทาง: <strong><?= get_payment_method_label($singleOrder['payment_method']) ?></strong></div>
                            <div class="small text-muted">ยอดรวมทั้งสิ้น: <strong class="text-success"><?= format_price($singleOrder['total']) ?></strong></div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-box me-1 text-success"></i> สินค้าในรายการ</h6>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light small">
                                <tr>
                                    <th>สินค้า</th>
                                    <th class="text-center">ราคาต่อชิ้น</th>
                                    <th class="text-center">จำนวน</th>
                                    <th class="text-end">ราคารวม</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($singleOrderItems as $it): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?= get_product_image_url($it['image']) ?>" alt="" class="rounded border" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                                                <span class="small fw-semibold"><?= e($it['product_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center small"><?= format_price($it['unit_price']) ?></td>
                                        <td class="text-center small"><?= $it['quantity'] ?></td>
                                        <td class="text-end small fw-bold text-dark"><?= format_price($it['unit_price'] * $it['quantity']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="border-top small">
                                <tr>
                                    <td colspan="3" class="text-end text-muted">ยอดรวมสินค้า:</td>
                                    <td class="text-end fw-semibold"><?= format_price($singleOrder['subtotal']) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end text-muted">ค่าจัดส่ง:</td>
                                    <td class="text-end fw-semibold"><?= $singleOrder['shipping_fee'] == 0 ? '<span class="text-success">ฟรี</span>' : format_price($singleOrder['shipping_fee']) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end fw-bold text-dark fs-6">ยอดสุทธิ:</td>
                                    <td class="text-end fw-bold text-success fs-5"><?= format_price($singleOrder['total']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Orders Table List -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-clock-history text-success me-2"></i> รายการคำสั่งซื้อของคุณทั้งหมด
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bag-x fs-1 text-secondary mb-2"></i>
                        <p class="mb-3">คุณยังไม่มีประวัติการสั่งซื้อ</p>
                        <a href="<?= url('public/products.php') ?>" class="btn btn-sm btn-success rounded-pill px-4">
                            เลือกชมสินค้าในร้าน
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-secondary">
                                <tr>
                                    <th>เลขออเดอร์</th>
                                    <th>วันที่สั่งซื้อ</th>
                                    <th>สถานะ</th>
                                    <th>วิธีชำระ</th>
                                    <th class="text-end">ยอดรวม</th>
                                    <th class="text-center">เลขพัสดุ</th>
                                    <th class="text-center">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $ord): ?>
                                    <tr class="<?= ($viewId === (int)$ord['id']) ? 'table-success' : '' ?>">
                                        <td>
                                            <strong class="text-dark"><?= e($ord['order_no']) ?></strong>
                                        </td>
                                        <td class="small text-muted">
                                            <?= format_date_th($ord['created_at']) ?>
                                        </td>
                                        <td>
                                            <?= get_order_status_badge($ord['status']) ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= get_payment_method_label($ord['payment_method']) ?>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            <?= format_price($ord['total']) ?>
                                        </td>
                                        <td class="text-center small">
                                            <?= !empty($ord['tracking_no']) ? '<span class="badge bg-light text-primary border">' . e($ord['tracking_no']) . '</span>' : '-' ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= url('public/orders.php?view=' . $ord['id']) ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1">
                                                ดูรายละเอียด
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
