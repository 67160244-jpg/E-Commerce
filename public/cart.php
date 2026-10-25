<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pdo = get_db_connection();

// จัดการการส่งคำขอ POST สำหรับตะกร้าสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    // ดึงข้อมูลสต็อกปัจจุบันของสินค้า
    $stockStmt = $pdo->prepare("SELECT id, name_th, price, stock_qty, is_active FROM products WHERE id = :id LIMIT 1");
    $stockStmt->execute([':id' => $productId]);
    $prod = $stockStmt->fetch();

    if ($action === 'add') {
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if (!$prod || $prod['is_active'] != 1) {
            set_flash('danger', 'สินค้านี้ไม่พร้อมจำหน่าย');
        } elseif ($prod['stock_qty'] <= 0) {
            set_flash('danger', 'ขออภัย สินค้า "' . e($prod['name_th']) . '" หมดสต็อกแล้ว');
        } else {
            $currentInCart = $_SESSION['cart'][$productId] ?? 0;
            $newTotal = $currentInCart + $quantity;

            if ($newTotal > $prod['stock_qty']) {
                $newTotal = $prod['stock_qty'];
                set_flash('warning', 'เพิ่มสินค้าได้สูงสุด ' . $prod['stock_qty'] . ' ชิ้น ตามจำนวนสต็อกที่มีอยู่');
            } else {
                set_flash('success', 'เพิ่ม "' . e($prod['name_th']) . '" ลงในตะกร้าเรียบร้อยแล้ว');
            }
            $_SESSION['cart'][$productId] = $newTotal;
        }
        redirect('public/cart.php');
    } elseif ($action === 'update') {
        $quantity = (int)($_POST['quantity'] ?? 1);

        if ($quantity <= 0) {
            unset($_SESSION['cart'][$productId]);
            set_flash('info', 'นำสินค้าออกจากตะกร้าเรียบร้อยแล้ว');
        } else {
            if (!$prod || $prod['is_active'] != 1) {
                unset($_SESSION['cart'][$productId]);
                set_flash('danger', 'สินค้านี้ไม่พร้อมจำหน่าย ระบบจึงนำออกจากตะกร้า');
            } elseif ($quantity > $prod['stock_qty']) {
                $_SESSION['cart'][$productId] = $prod['stock_qty'];
                set_flash('warning', 'ปรับจำนวนสินค้าได้สูงสุด ' . $prod['stock_qty'] . ' ชิ้น ตามสต็อกคงเหลือ');
            } else {
                $_SESSION['cart'][$productId] = $quantity;
                set_flash('success', 'อัปเดตจำนวนสินค้าเรียบร้อยแล้ว');
            }
        }
        redirect('public/cart.php');
    } elseif ($action === 'remove') {
        unset($_SESSION['cart'][$productId]);
        set_flash('info', 'นำสินค้าออกจากตะกร้าเรียบร้อยแล้ว');
        redirect('public/cart.php');
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        set_flash('info', 'ล้างตะกร้าสินค้าทั้งหมดแล้ว');
        redirect('public/cart.php');
    }
}

// ดึงข้อมูลสินค้าทั้งหมดที่อยู่ในตะกร้าปัจจุบัน
$cartItems = [];
$subtotal = 0.0;

if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $inClause = implode(',', array_fill(0, count($productIds), '?'));
    $itemsStmt = $pdo->prepare("
        SELECT p.*, c.name_th as category_name 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ({$inClause})
    ");
    $itemsStmt->execute($productIds);
    $productsInDb = $itemsStmt->fetchAll();

    foreach ($productsInDb as $p) {
        $qty = $_SESSION['cart'][$p['id']] ?? 0;
        // ตรวจสอบสต็อกอีกครั้ง หากสต็อกลดลงกะทันหัน
        if ($qty > $p['stock_qty']) {
            $qty = max(0, $p['stock_qty']);
            $_SESSION['cart'][$p['id']] = $qty;
        }

        if ($qty > 0 && $p['is_active'] == 1) {
            $lineTotal = (float)$p['price'] * $qty;
            $subtotal += $lineTotal;
            $cartItems[] = [
                'product'    => $p,
                'quantity'   => $qty,
                'line_total' => $lineTotal
            ];
        } else {
            unset($_SESSION['cart'][$p['id']]);
        }
    }
}

// คำนวณค่าจัดส่ง: ฟรีเมื่อครบ 999 บาท มิฉะนั้นคิด 50 บาท
$shippingFee = ($subtotal >= FREE_SHIPPING_THRESHOLD || empty($cartItems)) ? 0.0 : DEFAULT_SHIPPING_FEE;
$grandTotal = $subtotal + $shippingFee;

$pageTitle = 'ตะกร้าสินค้า';
require_once APP_ROOT . '/includes/header.php';
?>

<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0 text-dark">
            <i class="bi bi-cart3 text-success me-2"></i> ตะกร้าสินค้าของคุณ
        </h3>
        <?php if (!empty($cartItems)): ?>
            <form method="POST" action="" class="d-inline" onsubmit="return confirm('ยืนยันล้างรายการสินค้าทั้งหมดในตะกร้า?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    <i class="bi bi-trash3 me-1"></i> ล้างตะกร้า
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="card border-0 shadow-sm p-5 text-center text-muted rounded-4 my-4">
            <div class="mb-3">
                <i class="bi bi-cart-x fs-1 text-secondary"></i>
            </div>
            <h4 class="fw-bold text-dark">ยังไม่มีสินค้าในตะกร้า</h4>
            <p class="small text-muted mb-4">เลือกช้อปวิตามินและผลิตภัณฑ์ดูแลสุขภาพที่ตรงใจคุณได้เลย</p>
            <div>
                <a href="<?= url('public/products.php') ?>" class="btn btn-success rounded-pill px-4 py-2">
                    <i class="bi bi-bag-plus me-1"></i> ไปเลือกซื้อสินค้า
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Items Table List -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small text-secondary">
                                <tr>
                                    <th scope="col" style="width: 45%;">สินค้า</th>
                                    <th scope="col" class="text-center" style="width: 15%;">ราคาต่อชิ้น</th>
                                    <th scope="col" class="text-center" style="width: 20%;">จำนวน</th>
                                    <th scope="col" class="text-end" style="width: 15%;">รวม</th>
                                    <th scope="col" class="text-center" style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                    <?php $prod = $item['product']; ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?= get_product_image_url($prod['image']) ?>" 
                                                     alt="<?= e($prod['name_th']) ?>" 
                                                     class="rounded-3 border" 
                                                     style="width: 64px; height: 64px; object-fit: cover;"
                                                     onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                                                <div>
                                                    <small class="text-muted d-block"><?= e($prod['brand']) ?></small>
                                                    <a href="<?= url('public/product-detail.php?id=' . $prod['id']) ?>" class="text-dark fw-bold text-decoration-none">
                                                        <?= e($prod['name_th']) ?>
                                                    </a>
                                                    <?php if (!empty($prod['size'])): ?>
                                                        <small class="text-secondary d-block"><?= e($prod['size']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center fw-semibold text-secondary">
                                            <?= format_price($prod['price']) ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" action="" class="d-inline-flex align-items-center justify-content-center">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                                <div class="input-group input-group-sm" style="max-width: 110px;">
                                                    <input type="number" 
                                                           name="quantity" 
                                                           value="<?= $item['quantity'] ?>" 
                                                           min="1" 
                                                           max="<?= $prod['stock_qty'] ?>" 
                                                           class="form-control text-center" 
                                                           onchange="this.form.submit()">
                                                </div>
                                            </form>
                                            <div class="small text-muted mt-1" style="font-size: 0.75rem;">คงเหลือ <?= $prod['stock_qty'] ?></div>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            <?= format_price($item['line_total']) ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" action="">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
                                                <button type="submit" class="btn btn-sm text-danger p-0" title="ลบสินค้าชิ้นนี้">
                                                    <i class="bi bi-x-circle fs-5"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3 text-start">
                    <a href="<?= url('public/products.php') ?>" class="text-success text-decoration-none fw-semibold small">
                        <i class="bi bi-arrow-left me-1"></i> เลือกซื้อสินค้าต่อ
                    </a>
                </div>
            </div>

            <!-- Order Summary Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3 text-dark">สรุปคำสั่งซื้อ</h5>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">ยอดรวมย่อย</span>
                        <span class="fw-semibold text-dark"><?= format_price($subtotal) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">ค่าจัดส่ง</span>
                        <?php if ($shippingFee == 0): ?>
                            <span class="text-success fw-bold">ฟรี</span>
                        <?php else: ?>
                            <span class="fw-semibold text-dark"><?= format_price($shippingFee) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($subtotal < FREE_SHIPPING_THRESHOLD): ?>
                        <div class="alert alert-warning-subtle text-dark border-0 small py-2 px-3 rounded-3 my-2" style="font-size: 0.8rem;">
                            <i class="bi bi-truck me-1 text-warning"></i> ช้อปเพิ่มอีก <strong><?= format_price(FREE_SHIPPING_THRESHOLD - $subtotal) ?></strong> เพื่อรับสิทธิ์ <strong>จัดส่งฟรี!</strong>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success-subtle text-success border-0 small py-2 px-3 rounded-3 my-2" style="font-size: 0.8rem;">
                            <i class="bi bi-check-circle-fill me-1"></i> คุณได้รับสิทธิ์ <strong>จัดส่งฟรี!</strong>
                        </div>
                    <?php endif; ?>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-baseline mb-4">
                        <span class="fw-bold fs-5 text-dark">ยอดสุทธิ</span>
                        <span class="display-6 fw-bold text-success"><?= format_price($grandTotal) ?></span>
                    </div>

                    <a href="<?= url('public/checkout.php') ?>" class="btn btn-success btn-lg w-100 rounded-pill fw-bold shadow-sm py-3">
                        ดำเนินการสั่งซื้อ <i class="bi bi-arrow-right ms-1"></i>
                    </a>

                    <div class="mt-3 text-center small text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i> ชำระเงินสะดวก ปลอดภัย จำลองระบบ
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
