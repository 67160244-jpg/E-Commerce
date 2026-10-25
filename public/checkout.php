<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

// บังคับล็อกอินก่อนทำรายการสั่งซื้อ
require_login('public/checkout.php');

$user = current_user();
$pdo = get_db_connection();

// ตรวจสอบว่ามีสินค้าในตะกร้าหรือไม่
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    set_flash('warning', 'ตะกร้าสินค้าว่างเปล่า กรุณาเลือกสินค้าก่อนทำการสั่งซื้อ');
    redirect('public/products.php');
}

// ดึงรายการที่อยู่ในสมุดที่อยู่ของผู้ใช้
$addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = :uid ORDER BY is_default DESC, id DESC");
$addrStmt->execute([':uid' => $user['id']]);
$savedAddresses = $addrStmt->fetchAll();

// คำนวณราคาสินค้าในตะกร้า
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

$checkoutItems = [];
$subtotal = 0.0;

foreach ($productsInDb as $p) {
    $qty = (int)($_SESSION['cart'][$p['id']] ?? 0);
    if ($qty > 0 && $p['is_active'] == 1) {
        $lineTotal = (float)$p['price'] * $qty;
        $subtotal += $lineTotal;
        $checkoutItems[] = [
            'product'    => $p,
            'quantity'   => $qty,
            'line_total' => $lineTotal
        ];
    }
}

if (empty($checkoutItems)) {
    set_flash('danger', 'ไม่มีสินค้าที่พร้อมสั่งซื้อในตะกร้า');
    redirect('public/cart.php');
}

$shippingFee = ($subtotal >= FREE_SHIPPING_THRESHOLD) ? 0.0 : DEFAULT_SHIPPING_FEE;
$grandTotal = $subtotal + $shippingFee;

$error = '';

// การประมวลผลคำสั่งซื้อเมื่อส่งฟอร์ม POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();

    $addressMode = $_POST['address_mode'] ?? 'saved'; // 'saved' หรือ 'new'
    $paymentMethod = $_POST['payment_method'] ?? 'promptpay';
    $validPaymentMethods = ['promptpay', 'credit_card', 'cod'];

    if (!in_array($paymentMethod, $validPaymentMethods, true)) {
        $paymentMethod = 'promptpay';
    }

    $shipName = '';
    $shipPhone = '';
    $shipAddress = '';

    if ($addressMode === 'saved' && !empty($savedAddresses)) {
        $selectedAddrId = (int)($_POST['saved_address_id'] ?? 0);
        $foundAddr = null;
        foreach ($savedAddresses as $a) {
            if ($a['id'] === $selectedAddrId) {
                $foundAddr = $a;
                break;
            }
        }
        if (!$foundAddr && !empty($savedAddresses[0])) {
            $foundAddr = $savedAddresses[0];
        }

        if ($foundAddr) {
            $shipName = $foundAddr['recipient'];
            $shipPhone = $foundAddr['phone'];
            $shipAddress = trim($foundAddr['address_line'] . ' ' . $foundAddr['district'] . ' ' . $foundAddr['province'] . ' ' . $foundAddr['postcode']);
        } else {
            $error = 'กรุณาเลือกที่อยู่จัดส่ง';
        }
    } else {
        // กรอกที่อยู่ใหม่
        $newRecipient = trim($_POST['new_recipient'] ?? '');
        $newPhone = trim($_POST['new_phone'] ?? '');
        $newLine = trim($_POST['new_address_line'] ?? '');
        $newDistrict = trim($_POST['new_district'] ?? '');
        $newProvince = trim($_POST['new_province'] ?? '');
        $newPostcode = trim($_POST['new_postcode'] ?? '');
        $saveToBook = isset($_POST['save_to_book']) ? 1 : 0;

        if (empty($newRecipient) || empty($newPhone) || empty($newLine) || empty($newProvince) || empty($newPostcode)) {
            $error = 'กรุณากรอกข้อมูลที่อยู่จัดส่งให้ครบถ้วนทุกช่องที่จำเป็น';
        } else {
            $shipName = $newRecipient;
            $shipPhone = $newPhone;
            $shipAddress = trim($newLine . ' ' . $newDistrict . ' ' . $newProvince . ' ' . $newPostcode);

            // หากเลือกบันทึกลงสมุดที่อยู่
            if ($saveToBook) {
                $inAddr = $pdo->prepare("
                    INSERT INTO addresses (user_id, label, recipient, phone, address_line, district, province, postcode, is_default)
                    VALUES (:uid, 'ที่อยู่จัดส่ง', :rec, :ph, :line, :dist, :prov, :post, 0)
                ");
                $inAddr->execute([
                    ':uid'  => $user['id'],
                    ':rec'  => $newRecipient,
                    ':ph'   => $newPhone,
                    ':line' => $newLine,
                    ':dist' => $newDistrict,
                    ':prov' => $newProvince,
                    ':post' => $newPostcode
                ]);
            }
        }
    }

    // หากผ่านการตรวจสอบ ให้เริ่ม DB Transaction
    if (empty($error)) {
        try {
            $pdo->beginTransaction();

            // 1. ตรวจสอบสต็อกล่าสุดของสินค้าทุกชิ้น พร้อมล็อกแถว (SELECT ... FOR UPDATE)
            foreach ($checkoutItems as $item) {
                $pid = $item['product']['id'];
                $orderQty = $item['quantity'];

                $chkStmt = $pdo->prepare("SELECT id, name_th, price, stock_qty, is_active FROM products WHERE id = :id FOR UPDATE");
                $chkStmt->execute([':id' => $pid]);
                $liveProduct = $chkStmt->fetch();

                if (!$liveProduct || $liveProduct['is_active'] != 1) {
                    throw new Exception("สินค้า \"" . $item['product']['name_th'] . "\" ไม่พร้อมจำหน่ายแล้ว");
                }

                if ($liveProduct['stock_qty'] < $orderQty) {
                    throw new Exception("สินค้า \"" . $liveProduct['name_th'] . "\" มีสต็อกคงเหลือเพียง " . $liveProduct['stock_qty'] . " ชิ้น ไม่พอสำหรับจำนวนที่สั่งซื้อ");
                }
            }

            // 2. สร้างเลขออเดอร์ MV-yymmdd-0001
            $datePrefix = date('ymd');
            $likePattern = "MV-{$datePrefix}-%";
            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_no LIKE :pattern");
            $cntStmt->execute([':pattern' => $likePattern]);
            $todayOrderCount = (int)$cntStmt->fetchColumn();
            $orderNo = sprintf("MV-%s-%04d", $datePrefix, $todayOrderCount + 1);

            // 3. สร้างรายการ orders (สถานะเริ่มต้นเป็น pending เสมอ)
            $orderInsert = $pdo->prepare("
                INSERT INTO orders (order_no, user_id, status, payment_method, subtotal, shipping_fee, total, ship_name, ship_phone, ship_address, created_at)
                VALUES (:order_no, :user_id, 'pending', :payment_method, :subtotal, :shipping_fee, :total, :ship_name, :ship_phone, :ship_address, NOW())
            ");
            $orderInsert->execute([
                ':order_no'       => $orderNo,
                ':user_id'        => $user['id'],
                ':payment_method' => $paymentMethod,
                ':subtotal'       => $subtotal,
                ':shipping_fee'   => $shippingFee,
                ':total'          => $grandTotal,
                ':ship_name'      => $shipName,
                ':ship_phone'     => $shipPhone,
                ':ship_address'   => $shipAddress
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // 4. บันทึก order_items, ตัดสต็อกสินค้า และบันทึก stock_log
            $itemInsert = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
                VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity)
            ");
            $stockDeduct = $pdo->prepare("
                UPDATE products 
                SET stock_qty = stock_qty - :quantity 
                WHERE id = :product_id
            ");
            $logInsert = $pdo->prepare("
                INSERT INTO stock_log (product_id, change_qty, reason, created_at)
                VALUES (:product_id, :change_qty, 'order', NOW())
            ");

            foreach ($checkoutItems as $item) {
                $pid = $item['product']['id'];
                $pname = $item['product']['name_th'];
                $price = $item['product']['price'];
                $qty = $item['quantity'];

                // บันทึกรายการสินค้า
                $itemInsert->execute([
                    ':order_id'     => $orderId,
                    ':product_id'   => $pid,
                    ':product_name' => $pname,
                    ':unit_price'   => $price,
                    ':quantity'     => $qty
                ]);

                // ตัดสต็อกสินค้า
                $stockDeduct->execute([
                    ':quantity'   => $qty,
                    ':product_id' => $pid
                ]);

                // บันทึก stock_log (reason = order, change_qty เป็นค่าลบ)
                $logInsert->execute([
                    ':product_id' => $pid,
                    ':change_qty' => -$qty
                ]);
            }

            // Commit Transaction
            $pdo->commit();

            // ล้างตะกร้าสินค้าใน Session
            unset($_SESSION['cart']);

            set_flash('success', 'บันทึกคำสั่งซื้อเรียบร้อยแล้ว');
            redirect('public/order-success.php?order_no=' . urlencode($orderNo));

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาดในการสั่งซื้อ: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'ชำระเงินและยืนยันคำสั่งซื้อ';
require_once APP_ROOT . '/includes/header.php';
?>

<div class="py-4">
    <div class="mb-4">
        <h3 class="fw-bold text-dark"><i class="bi bi-credit-card-2-front text-success me-2"></i> สั่งซื้อและชำระเงิน</h3>
        <p class="text-muted small">กรุณาตรวจสอบที่อยู่จัดส่งและเลือกวิธีการชำระเงิน</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- Left Side: Shipping Address & Payment Method -->
            <div class="col-lg-7">
                <!-- 1. Shipping Address Selection -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-geo-alt text-success me-2"></i> 1. ที่อยู่สำหรับจัดส่ง</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($savedAddresses)): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="address_mode" id="modeSaved" value="saved" checked onchange="toggleAddressMode()">
                                <label class="form-check-label fw-bold text-dark" for="modeSaved">
                                    เลือกจากสมุดที่อยู่ที่บันทึกไว้
                                </label>
                            </div>

                            <div id="savedAddressSection" class="ms-4 mb-4">
                                <div class="row g-2">
                                    <?php foreach ($savedAddresses as $idx => $sAddr): ?>
                                        <div class="col-12">
                                            <div class="form-check p-3 border rounded-3 mb-2 <?= $sAddr['is_default'] ? 'border-success bg-light' : '' ?>">
                                                <input class="form-check-input" type="radio" name="saved_address_id" id="addr_<?= $sAddr['id'] ?>" value="<?= $sAddr['id'] ?>" <?= ($idx === 0 || $sAddr['is_default']) ? 'checked' : '' ?>>
                                                <label class="form-check-label w-100 ms-2" for="addr_<?= $sAddr['id'] ?>">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <strong class="text-dark"><?= e($sAddr['recipient']) ?> (<?= e($sAddr['label']) ?>)</strong>
                                                        <?php if ($sAddr['is_default']): ?>
                                                            <span class="badge bg-success-subtle text-success">ที่อยู่เริ่มต้น</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i> <?= e($sAddr['phone']) ?></div>
                                                    <div class="small text-secondary">
                                                        <?= e($sAddr['address_line']) ?> <?= e($sAddr['district'] ?? '') ?> <?= e($sAddr['province']) ?> <?= e($sAddr['postcode']) ?>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="address_mode" id="modeNew" value="new" onchange="toggleAddressMode()">
                                <label class="form-check-label fw-bold text-dark" for="modeNew">
                                    กรอกที่อยู่จัดส่งใหม่
                                </label>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="address_mode" value="new">
                            <p class="text-muted small mb-3">ยังไม่มีที่อยู่ที่บันทึกไว้ กรุณากรอกที่อยู่จัดส่งด้านล่าง:</p>
                        <?php endif; ?>

                        <!-- New Address Form Section -->
                        <div id="newAddressSection" class="<?= !empty($savedAddresses) ? 'd-none ms-4' : '' ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="new_recipient" class="form-label small fw-semibold">ชื่อผู้รับ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="new_recipient" name="new_recipient" value="<?= e($user['name']) ?>" placeholder="ชื่อ - นามสกุล ผู้รับ">
                                </div>
                                <div class="col-md-6">
                                    <label for="new_phone" class="form-label small fw-semibold">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="new_phone" name="new_phone" value="" placeholder="08x-xxx-xxxx">
                                </div>
                                <div class="col-12">
                                    <label for="new_address_line" class="form-label small fw-semibold">ที่อยู่ (บ้านเลขที่, ซอย, ถนน) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="new_address_line" name="new_address_line" placeholder="123/45 หมู่ 2 ถ.มิตรภาพ">
                                </div>
                                <div class="col-md-4">
                                    <label for="new_district" class="form-label small fw-semibold">อำเภอ / เขต</label>
                                    <input type="text" class="form-control" id="new_district" name="new_district" placeholder="เมือง">
                                </div>
                                <div class="col-md-4">
                                    <label for="new_province" class="form-label small fw-semibold">จังหวัด <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="new_province" name="new_province" placeholder="กรุงเทพมหานคร">
                                </div>
                                <div class="col-md-4">
                                    <label for="new_postcode" class="form-label small fw-semibold">รหัสไปรษณีย์ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="new_postcode" name="new_postcode" placeholder="10110" maxlength="10">
                                </div>
                                <div class="col-12">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="save_to_book" name="save_to_book" value="1" checked>
                                        <label class="form-check-label small text-muted" for="save_to_book">
                                            บันทึกที่อยู่นี้ลงในสมุดที่อยู่สำหรับใช้งานครั้งต่อไป
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Payment Method -->
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-wallet2 text-success me-2"></i> 2. วิธีการชำระเงิน (จำลอง)</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <!-- PromptPay -->
                            <div class="col-12">
                                <label class="border rounded-3 p-3 d-flex align-items-center w-100 cursor-pointer">
                                    <input class="form-check-input me-3" type="radio" name="payment_method" value="promptpay" checked>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark"><i class="bi bi-qr-code text-primary me-2"></i> พร้อมเพย์ (PromptPay QR)</div>
                                        <small class="text-muted">สแกนจ่ายผ่าน QR Code ทันทีด้วย Mobile Banking ทุกธนาคาร</small>
                                    </div>
                                    <span class="badge bg-light text-dark border">แนะนำ</span>
                                </label>
                            </div>

                            <!-- Credit Card -->
                            <div class="col-12">
                                <label class="border rounded-3 p-3 d-flex align-items-center w-100 cursor-pointer">
                                    <input class="form-check-input me-3" type="radio" name="payment_method" value="credit_card">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark"><i class="bi bi-credit-card text-success me-2"></i> บัตรเครดิต / เดบิต</div>
                                        <small class="text-muted">Visa, Mastercard, JCB (ระบบจำลองการตัดบัตร)</small>
                                    </div>
                                </label>
                            </div>

                            <!-- Cash on Delivery -->
                            <div class="col-12">
                                <label class="border rounded-3 p-3 d-flex align-items-center w-100 cursor-pointer">
                                    <input class="form-check-input me-3" type="radio" name="payment_method" value="cod">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark"><i class="bi bi-cash-stack text-warning me-2"></i> เก็บเงินปลายทาง (Cash on Delivery)</div>
                                        <small class="text-muted">ชำระเงินสดกับเจ้าหน้าที่จัดส่งพัสดุเมื่อได้รับสินค้า</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Order Summary Review -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 80px;">
                    <h5 class="fw-bold mb-3 text-dark">สรุปรายการสั่งซื้อ</h5>

                    <div class="mb-3" style="max-height: 280px; overflow-y: auto;">
                        <?php foreach ($checkoutItems as $item): ?>
                            <?php $prod = $item['product']; ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div class="d-flex align-items-center gap-2" style="max-width: 70%;">
                                    <img src="<?= get_product_image_url($prod['image']) ?>" alt="" class="rounded border" style="width: 44px; height: 44px; object-fit: cover;">
                                    <div class="text-truncate">
                                        <div class="small fw-semibold text-truncate"><?= e($prod['name_th']) ?></div>
                                        <small class="text-muted">จำนวน: <?= $item['quantity'] ?> ชิ้น</small>
                                    </div>
                                </div>
                                <span class="small fw-bold text-dark"><?= format_price($item['line_total']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary small">ยอดรวมสินค้า (<?= count($checkoutItems) ?> รายการ)</span>
                        <span class="fw-semibold text-dark"><?= format_price($subtotal) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary small">ค่าจัดส่งพัสดุ</span>
                        <?php if ($shippingFee == 0): ?>
                            <span class="text-success fw-bold">ฟรี</span>
                        <?php else: ?>
                            <span class="fw-semibold text-dark"><?= format_price($shippingFee) ?></span>
                        <?php endif; ?>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-baseline mb-4">
                        <span class="fw-bold text-dark fs-5">ยอดชำระสุทธิ</span>
                        <span class="display-6 fw-bold text-success"><?= format_price($grandTotal) ?></span>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100 rounded-pill fw-bold py-3 shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> ยืนยันการสั่งซื้อ
                    </button>

                    <div class="mt-3 text-center small text-muted">
                        โดยการกดสั่งซื้อ คุณยอมรับเงื่อนไขการให้บริการของ <?= STORE_NAME ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAddressMode() {
    const isNew = document.getElementById('modeNew') && document.getElementById('modeNew').checked;
    const newSection = document.getElementById('newAddressSection');
    const savedSection = document.getElementById('savedAddressSection');
    
    if (newSection) {
        if (isNew) {
            newSection.classList.remove('d-none');
        } else {
            newSection.classList.add('d-none');
        }
    }
}
</script>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
