<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

$pdo = get_db_connection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.name_th as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'ไม่พบสินค้าที่ต้องการปรับปรุงสต็อก');
    redirect('admin/products.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();

    $type = $_POST['type'] ?? 'add'; // 'add' หรือ 'subtract'
    $qty = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'ปรับยอดสต็อก');

    if ($qty <= 0) {
        $error = 'กรุณาระบุจำนวนสินค้าที่ต้องการปรับปรุงให้ถูกต้อง (มากกว่า 0)';
    } elseif ($type === 'subtract' && $qty > $product['stock_qty']) {
        $error = 'ไม่สามารถลดสต็อกมากกว่าจำนวนคงเหลือ (' . $product['stock_qty'] . ' ชิ้น) ได้';
    } else {
        $delta = ($type === 'add') ? $qty : -$qty;

        try {
            $pdo->beginTransaction();

            // อัปเดตสต็อกใน products
            $up = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + :delta WHERE id = :id");
            $up->execute([':delta' => $delta, ':id' => $id]);

            // บันทึกประวัติใน stock_log
            $log = $pdo->prepare("INSERT INTO stock_log (product_id, change_qty, reason, created_at) VALUES (:pid, :qty, :reason, NOW())");
            $log->execute([
                ':pid'    => $id,
                ':qty'    => $delta,
                ':reason' => $reason
            ]);

            $pdo->commit();

            set_flash('success', 'ปรับปรุงสต็อกสินค้าเรียบร้อยแล้ว (การเปลี่ยนแปลง: ' . ($delta > 0 ? '+' : '') . $delta . ' ชิ้น)');
            redirect('admin/stock-adjust.php?id=' . $id);

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'เกิดข้อผิดพลาดในการปรับปรุงสต็อก: ' . $e->getMessage();
        }
    }
}

// ดึงประวัติ stock_log ล่าสุดของสินค้านี้
$logsStmt = $pdo->prepare("SELECT * FROM stock_log WHERE product_id = :pid ORDER BY id DESC LIMIT 15");
$logsStmt->execute([':pid' => $id]);
$logs = $logsStmt->fetchAll();

$pageTitle = 'ปรับปรุงสต็อกสินค้า - ' . $product['name_th'];
require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= url('admin/products.php') ?>" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> กลับไปรายการสินค้า
        </a>
        <h3 class="fw-bold text-dark mt-1 mb-0">
            <i class="bi bi-plus-slash-minus text-success me-2"></i> ปรับปรุงสต็อกสินค้า (Quick Stock Adjustment)
        </h3>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Adjustment Form -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                <img src="<?= get_product_image_url($product['image']) ?>" alt="" class="rounded border" style="width: 64px; height: 64px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                <div>
                    <h6 class="fw-bold text-dark mb-1"><?= e($product['name_th']) ?></h6>
                    <small class="text-muted d-block">SKU: <?= e($product['sku']) ?> | หมวด: <?= e($product['category_name']) ?></small>
                </div>
            </div>

            <!-- Current Stock Status Badge -->
            <div class="p-3 bg-light rounded-3 text-center mb-4">
                <span class="text-muted small d-block mb-1">ระดับสต็อกคงเหลือปัจจุบัน</span>
                <span class="display-5 fw-bold <?= $product['stock_qty'] <= 0 ? 'text-danger' : ($product['stock_qty'] <= $product['low_stock_threshold'] ? 'text-warning' : 'text-success') ?>">
                    <?= $product['stock_qty'] ?>
                </span>
                <span class="text-muted small d-block mt-1">ชิ้น (เกณฑ์แจ้งเตือน: <?= $product['low_stock_threshold'] ?> ชิ้น)</span>
            </div>

            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">ประเภทการปรับปรุง</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" id="typeAdd" value="add" checked>
                            <label class="form-check-label fw-bold text-success" for="typeAdd">
                                <i class="bi bi-plus-circle me-1"></i> เพิ่มสต็อก (+)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" id="typeSub" value="subtract">
                            <label class="form-check-label fw-bold text-danger" for="typeSub">
                                <i class="bi bi-dash-circle me-1"></i> ลดสต็อก (-)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="quantity" class="form-label small fw-semibold text-secondary">จำนวนสินค้า (ชิ้น) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="10" required>
                </div>

                <div class="mb-4">
                    <label for="reason" class="form-label small fw-semibold text-secondary">เหตุผลในการปรับปรุง <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="reason" name="reason" value="รับสินค้าเข้าคลังใหม่" placeholder="เช่น เติมสินค้าจากซัพพลายเออร์, ชำรุด, นับสต็อกประจำเดือน" required>
                </div>

                <button type="submit" class="btn btn-success w-100 rounded-pill py-2 fw-semibold">
                    บันทึกการปรับปรุงสต็อก
                </button>
            </form>
        </div>
    </div>

    <!-- Stock Log History Table -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-success me-2"></i> ประวัติความเคลื่อนไหวสต็อก (Stock Log)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th>วันที่เวลา</th>
                                <th class="text-center">จำนวนที่เปลี่ยน</th>
                                <th>เหตุผล / ที่มา</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        ยังไม่มีประวัติการปรับสต็อกสำหรับสินค้านี้
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td class="small text-muted"><?= format_date_th($l['created_at']) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $l['change_qty'] > 0 ? 'bg-success' : 'bg-danger' ?> fs-6">
                                                <?= ($l['change_qty'] > 0 ? '+' : '') . $l['change_qty'] ?>
                                            </span>
                                        </td>
                                        <td class="small text-dark fw-semibold"><?= e($l['reason']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
