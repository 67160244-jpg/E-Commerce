<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

require_login();

$user = current_user();
$pdo = get_db_connection();

$orderNo = trim($_GET['order_no'] ?? '');

if (empty($orderNo)) {
    redirect('public/index.php');
}

// ดึงข้อมูลคำสั่งซื้อ (ต้องเป็นของตัวเอง หรือแอดมิน)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_no = :no AND user_id = :uid LIMIT 1");
$stmt->execute([':no' => $orderNo, ':uid' => $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'ไม่พบข้อมูลคำสั่งซื้อดังกล่าว');
    redirect('public/orders.php');
}

// ดึงรายการสินค้าในออเดอร์
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.image 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = :order_id
");
$itemsStmt->execute([':order_id' => $order['id']]);
$orderItems = $itemsStmt->fetchAll();

$pageTitle = 'สั่งซื้อสำเร็จ - ' . $order['order_no'];
require_once APP_ROOT . '/includes/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <!-- Header Success Banner -->
            <div class="bg-success text-white p-4 p-md-5 text-center">
                <div class="rounded-circle bg-white text-success d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 72px; height: 72px;">
                    <i class="bi bi-check-lg display-5 fw-bold"></i>
                </div>
                <h2 class="fw-bold mb-2">ขอบคุณสำหรับคำสั่งซื้อของคุณ!</h2>
                <p class="lead mb-0 opacity-90">หมายเลขคำสั่งซื้อของคุณคือ <strong class="bg-white text-success px-3 py-1 rounded-pill"><?= e($order['order_no']) ?></strong></p>
                <small class="d-block mt-2 opacity-75">เราได้รับคำสั่งซื้อเรียบร้อยแล้ว และกำลังเตรียมจัดส่งสินค้าให้คุณโดยเร็วที่สุด</small>
            </div>

            <div class="card-body p-4 p-md-5">
                <!-- Payment simulation box -->
                <?php if ($order['payment_method'] === 'promptpay'): ?>
                    <div class="card border-primary border-opacity-25 bg-primary bg-opacity-10 p-4 rounded-4 mb-4 text-center">
                        <h5 class="fw-bold text-primary mb-2"><i class="bi bi-qr-code me-2"></i> สแกนจ่ายผ่านพร้อมเพย์ (PromptPay QR)</h5>
                        <p class="small text-muted mb-3">กรุณาสแกน QR Code ด้านล่างด้วย Mobile Banking ยอดชำระ <strong><?= format_price($order['total']) ?></strong></p>
                        <div class="d-inline-block p-3 bg-white rounded-3 shadow-sm mb-2 border">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=https://mochivita.test/pay/<?= e($order['order_no']) ?>" alt="PromptPay QR Code" class="img-fluid" style="width: 180px; height: 180px;">
                        </div>
                        <small class="d-block text-muted">ระบบจำลอง: เมื่อโอนเงินแล้ว แอดมินจะตรวจสอบและเปลี่ยนสถานะเป็น 'ชำระเงินแล้ว' ให้โดยอัตโนมัติ</small>
                    </div>
                <?php elseif ($order['payment_method'] === 'credit_card'): ?>
                    <div class="alert alert-success d-flex align-items-center rounded-3 mb-4" role="alert">
                        <i class="bi bi-credit-card-2-front-fill fs-3 me-3 text-success"></i>
                        <div>
                            <strong class="d-block">ชำระผ่านบัตรเครดิตเรียบร้อย (จำลอง)</strong>
                            <small class="text-muted">ระบบได้ทำการตัดบัตรจำลองเรียบร้อยแล้ว ยอดชำระ <?= format_price($order['total']) ?></small>
                        </div>
                    </div>
                <?php elseif ($order['payment_method'] === 'cod'): ?>
                    <div class="alert alert-warning d-flex align-items-center rounded-3 mb-4" role="alert">
                        <i class="bi bi-cash-stack fs-3 me-3 text-warning"></i>
                        <div>
                            <strong class="d-block">เก็บเงินปลายทาง (Cash on Delivery)</strong>
                            <small class="text-muted">กรุณาเตรียมเงินสดจำนวน <?= format_price($order['total']) ?> สำหรับชำระกับเจ้าหน้าที่ส่งพัสดุ</small>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Order Info Details -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-geo-alt text-success me-1"></i> ที่อยู่จัดส่งพัสดุ</h6>
                        <strong class="d-block text-dark"><?= e($order['ship_name']) ?></strong>
                        <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i> <?= e($order['ship_phone']) ?></div>
                        <div class="small text-secondary"><?= e($order['ship_address']) ?></div>
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-info-circle text-success me-1"></i> รายละเอียดคำสั่งซื้อ</h6>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">วันที่สั่งซื้อ:</span>
                            <span class="fw-semibold text-dark"><?= format_date_th($order['created_at']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">สถานะ:</span>
                            <span><?= get_order_status_badge($order['status']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">ช่องทางชำระเงิน:</span>
                            <span class="fw-semibold text-dark"><?= get_payment_method_label($order['payment_method']) ?></span>
                        </div>
                        <?php if (!empty($order['tracking_no'])): ?>
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">เลขพัสดุ:</span>
                                <span class="fw-bold text-primary"><?= e($order['tracking_no']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Items Table -->
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bag-check text-success me-1"></i> สินค้าในคำสั่งซื้อ</h6>
                <div class="table-responsive mb-4">
                    <table class="table align-middle">
                        <thead class="table-light small">
                            <tr>
                                <th>สินค้า</th>
                                <th class="text-center">ราคา</th>
                                <th class="text-center">จำนวน</th>
                                <th class="text-end">รวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $it): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= get_product_image_url($it['image']) ?>" alt="" class="rounded border" style="width: 42px; height: 42px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                                            <span class="small fw-semibold"><?= e($it['product_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center small"><?= format_price($it['unit_price']) ?></td>
                                    <td class="text-center small"><?= $it['quantity'] ?></td>
                                    <td class="text-end small fw-bold text-dark"><?= format_price($it['unit_price'] * $it['quantity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td colspan="3" class="text-end small text-muted">ยอดรวมสินค้า:</td>
                                <td class="text-end small fw-bold"><?= format_price($order['subtotal']) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end small text-muted">ค่าจัดส่ง:</td>
                                <td class="text-end small fw-bold"><?= $order['shipping_fee'] == 0 ? '<span class="text-success">ฟรี</span>' : format_price($order['shipping_fee']) ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end fw-bold text-dark fs-6">ยอดสุทธิ:</td>
                                <td class="text-end fw-bold text-success fs-5"><?= format_price($order['total']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-2 justify-content-center pt-3 border-top">
                    <a href="<?= url('public/orders.php') ?>" class="btn btn-outline-success px-4 rounded-pill">
                        <i class="bi bi-clock-history me-1"></i> ประวัติคำสั่งซื้อของฉัน
                    </a>
                    <a href="<?= url('public/products.php') ?>" class="btn btn-success px-4 rounded-pill">
                        <i class="bi bi-bag-plus me-1"></i> เลือกซื้อสินค้าต่อ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
