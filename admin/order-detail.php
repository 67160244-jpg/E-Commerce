<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

// ป้องกันสิทธิ์แอดมินเป็นบรรทัดแรก
require_admin();

$pdo = get_db_connection();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$orderStmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = :id
    LIMIT 1
");
$orderStmt->execute([':id' => $orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    set_flash('danger', 'ไม่พบข้อมูลคำสั่งซื้อดังกล่าว');
    redirect('admin/orders.php');
}

// อัปเดตสถานะและเลขพัสดุ
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();
    $newStatus = trim($_POST['status'] ?? '');
    $newTracking = trim($_POST['tracking_no'] ?? '');

    $allowedStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        $error = 'สถานะคำสั่งซื้อไม่ถูกต้อง';
    } else {
        try {
            $pdo->beginTransaction();

            $oldStatus = $order['status'];

            // หากสถานะเปลี่ยนเป็น cancelled และสถานะเดิมไม่ใช่ shipped และไม่ใช่ cancelled อยู่แล้ว ให้ทำการคืนสต็อกอัตโนมัติ
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                if ($oldStatus === 'shipped' || $oldStatus === 'delivered') {
                    // ออเดอร์จัดส่งไปแล้ว ไม่ควรคืนสต็อกทันที เว้นแต่ส่งของกลับ
                    // ในที่นี้ตามสเปก: "เมื่อยกเลิกออเดอร์ที่ยังไม่ shipped ให้คืนสต็อกอัตโนมัติและบันทึก stock_log (reason = cancel) โดยคืนได้ครั้งเดียว"
                } else {
                    // ดึงรายการสินค้าทั้งหมดในออเดอร์นี้เพื่อคืนสต็อก
                    $itemsToRestock = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :oid");
                    $itemsToRestock->execute([':oid' => $orderId]);
                    $itemsList = $itemsToRestock->fetchAll();

                    $restockStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + :qty WHERE id = :pid");
                    $logStmt = $pdo->prepare("INSERT INTO stock_log (product_id, change_qty, reason, created_at) VALUES (:pid, :qty, 'cancel', NOW())");

                    foreach ($itemsList as $it) {
                        $pid = $it['product_id'];
                        $qty = (int)$it['quantity'];

                        $restockStmt->execute([':qty' => $qty, ':pid' => $pid]);
                        $logStmt->execute([':pid' => $pid, ':qty' => $qty]);
                    }
                }
            }

            // อัปเดตข้อมูล orders
            $upStmt = $pdo->prepare("
                UPDATE orders 
                SET status = :status, tracking_no = :tracking_no
                WHERE id = :id
            ");
            $upStmt->execute([
                ':status'      => $newStatus,
                ':tracking_no' => !empty($newTracking) ? $newTracking : ($order['tracking_no'] ?? null),
                ':id'          => $orderId
            ]);

            $pdo->commit();

            set_flash('success', 'อัปเดตสถานะคำสั่งซื้อเป็น "' . $newStatus . '" เรียบร้อยแล้ว');
            redirect('admin/order-detail.php?id=' . $orderId);

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาดในการอัปเดตสถานะ: ' . $e->getMessage();
        }
    }
}

// ดึงรายการสินค้าในออเดอร์
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.image, p.sku, p.stock_qty as current_stock
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = :order_id
");
$itemsStmt->execute([':order_id' => $orderId]);
$orderItems = $itemsStmt->fetchAll();

// ดึงประวัติ stock_log ที่เกี่ยวข้องกับสินค้านี้จาก order_id (ถ้ามี)
$logsStmt = $pdo->prepare("
    SELECT sl.*, p.name_th as product_name
    FROM stock_log sl
    JOIN products p ON sl.product_id = p.id
    WHERE sl.product_id IN (SELECT product_id FROM order_items WHERE order_id = :oid)
    ORDER BY sl.id DESC
    LIMIT 6
");
$logsStmt->execute([':oid' => $orderId]);
$stockLogs = $logsStmt->fetchAll();

$pageTitle = 'คำสั่งซื้อ ' . $order['order_no'];
require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= url('admin/orders.php') ?>" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> กลับไปหน้ารายการคำสั่งซื้อ
        </a>
        <h3 class="fw-bold text-dark mt-1 mb-0">
            คำสั่งซื้อหมายเลข: <?= e($order['order_no']) ?>
        </h3>
    </div>
    <div>
        <?= get_order_status_badge($order['status']) ?>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Left Column: Order Items & Delivery Address -->
    <div class="col-lg-8">
        <!-- Order Items Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam text-success me-2"></i> รายการสินค้าที่สั่งซื้อ</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th>สินค้า</th>
                                <th class="text-center">ราคาต่อหน่วย</th>
                                <th class="text-center">จำนวน</th>
                                <th class="text-end">ราคารวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $it): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= get_product_image_url($it['image']) ?>" alt="" class="rounded border" style="width: 48px; height: 48px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                                            <div>
                                                <strong class="text-dark d-block small"><?= e($it['product_name']) ?></strong>
                                                <small class="text-muted">SKU: <?= e($it['sku'] ?? '-') ?> (คงเหลือปัจจุบัน: <?= $it['current_stock'] ?>)</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center small"><?= format_price($it['unit_price']) ?></td>
                                    <td class="text-center small fw-bold"><?= $it['quantity'] ?></td>
                                    <td class="text-end small fw-bold text-dark"><?= format_price($it['unit_price'] * $it['quantity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top small bg-light">
                            <tr>
                                <td colspan="3" class="text-end text-muted">ยอดรวมสินค้า:</td>
                                <td class="text-end fw-semibold"><?= format_price($order['subtotal']) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end text-muted">ค่าจัดส่ง:</td>
                                <td class="text-end fw-semibold"><?= $order['shipping_fee'] == 0 ? '<span class="text-success">ฟรี</span>' : format_price($order['shipping_fee']) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end fw-bold text-dark fs-6">ยอดสุทธิที่ต้องชำระ:</td>
                                <td class="text-end fw-bold text-success fs-5"><?= format_price($order['total']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Customer & Shipping Info Card -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-geo-alt text-success me-1"></i> ที่อยู่สำหรับจัดส่งพัสดุ</h6>
                    <div class="mb-2">
                        <small class="text-muted d-block">ผู้รับสินค้า:</small>
                        <strong class="text-dark"><?= e($order['ship_name']) ?></strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">เบอร์โทรศัพท์ติดต่อ:</small>
                        <span class="text-dark"><i class="bi bi-telephone me-1 text-success"></i> <?= e($order['ship_phone']) ?></span>
                    </div>
                    <div>
                        <small class="text-muted d-block">สถานที่จัดส่ง:</small>
                        <span class="text-secondary small"><?= e($order['ship_address']) ?></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-person text-success me-1"></i> ข้อมูลบัญชีผู้สั่งซื้อ</h6>
                    <div class="mb-2">
                        <small class="text-muted d-block">ชื่อลูกค้า:</small>
                        <strong class="text-dark"><?= e($order['customer_name']) ?></strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">อีเมลสมาชิก:</small>
                        <span class="text-dark"><?= e($order['customer_email']) ?></span>
                    </div>
                    <div>
                        <small class="text-muted d-block">เบอร์โทรสมาชิก:</small>
                        <span class="text-dark"><?= e($order['customer_phone'] ?? '-') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Status Update & Payment -->
    <div class="col-lg-4">
        <!-- Status Update Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-arrow-repeat text-success me-2"></i> อัปเดตสถานะออเดอร์</h5>

            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="status" class="form-label small fw-semibold text-secondary">สถานะคำสั่งซื้อ</label>
                    <select class="form-select" id="statusSelect" name="status" onchange="toggleTrackingField(this.value)">
                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>รอดำเนินการ (pending)</option>
                        <option value="paid" <?= $order['status'] === 'paid' ? 'selected' : '' ?>>ชำระเงินแล้ว (paid)</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>กำลังเตรียมจัดส่ง (processing)</option>
                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>จัดส่งแล้ว (shipped)</option>
                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>ส่งมอบสำเร็จ (delivered)</option>
                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>ยกเลิกออเดอร์ (cancelled)</option>
                    </select>
                </div>

                <div class="mb-3 <?= ($order['status'] === 'shipped' || $order['status'] === 'delivered') ? '' : 'd-none' ?>" id="trackingField">
                    <label for="tracking_no" class="form-label small fw-semibold text-secondary">หมายเลขพัสดุ (Tracking No.)</label>
                    <input type="text" class="form-control" id="tracking_no" name="tracking_no" value="<?= e($order['tracking_no'] ?? '') ?>" placeholder="TH0123456789">
                    <small class="text-muted" style="font-size: 0.75rem;">กรอกเมื่อเปลี่ยนสถานะเป็น "จัดส่งแล้ว"</small>
                </div>

                <?php if ($order['status'] !== 'cancelled'): ?>
                    <div class="alert alert-warning-subtle text-dark border-0 small p-2 rounded-3 mb-3" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle me-1 text-warning"></i> หมายเหตุ: หากเลือก <strong>ยกเลิกออเดอร์ (cancelled)</strong> ระบบจะทำการคืนสต็อกสินค้าอัตโนมัติ 1 ครั้ง (เฉพาะกรณีที่ยังไม่ได้จัดส่ง)
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-success w-100 rounded-pill fw-semibold py-2">
                    บันทึกการเปลี่ยนแปลง
                </button>
            </form>
        </div>

        <!-- Payment Info Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-wallet2 text-success me-1"></i> ช่องทางชำระเงิน</h6>
            <div class="mb-2">
                <small class="text-muted d-block">วิธีการ:</small>
                <strong class="text-dark"><?= get_payment_method_label($order['payment_method']) ?></strong>
            </div>
            <div class="mb-2">
                <small class="text-muted d-block">วันที่ทำรายการ:</small>
                <span class="text-secondary small"><?= format_date_th($order['created_at']) ?></span>
            </div>
            <div>
                <small class="text-muted d-block">หมายเลขพัสดุปัจจุบัน:</small>
                <span class="fw-bold text-primary"><?= !empty($order['tracking_no']) ? e($order['tracking_no']) : '-' ?></span>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTrackingField(status) {
    const trackingField = document.getElementById('trackingField');
    if (status === 'shipped' || status === 'delivered') {
        trackingField.classList.remove('d-none');
    } else {
        trackingField.classList.add('d-none');
    }
}
</script>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
