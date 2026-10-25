<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

// ป้องกันสิทธิ์แอดมินเป็นบรรทัดแรก
require_admin();

$pdo = get_db_connection();

// ดึงตัวแปรค้นหาและตัวกรอง
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = ["1=1"];
$params = [];

if (!empty($q)) {
    $where[] = "(o.order_no LIKE :q OR u.name LIKE :q OR o.ship_name LIKE :q)";
    $params[':q'] = "%{$q}%";
}

if (!empty($status)) {
    $where[] = "o.status = :status";
    $params[':status'] = $status;
}

if (!empty($startDate)) {
    $where[] = "DATE(o.created_at) >= :start_date";
    $params[':start_date'] = $startDate;
}

if (!empty($endDate)) {
    $where[] = "DATE(o.created_at) <= :end_date";
    $params[':end_date'] = $endDate;
}

$whereSql = implode(' AND ', $where);

// นับจำนวนทั้งหมด
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// ดึงรายการคำสั่งซื้อ
$sql = "
    SELECT o.*, u.name as customer_name, u.email as customer_email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE {$whereSql}
    ORDER BY o.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

function build_admin_order_url($newParams = []) {
    $current = $_GET;
    return '?' . http_build_query(array_merge($current, $newParams));
}

$pageTitle = 'จัดการคำสั่งซื้อ';
require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="bi bi-receipt text-success me-2"></i> จัดการคำสั่งซื้อ (Orders)
        </h3>
        <p class="text-muted small mb-0">ค้นหา ตรวจสอบ และอัปเดตสถานะคำสั่งซื้อของลูกค้า</p>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="q" class="form-label small fw-semibold text-secondary">ค้นหาเลขออเดอร์ / ชื่อลูกค้า</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="MV-26..., ชื่อ...">
            </div>
        </div>

        <div class="col-md-2">
            <label for="status" class="form-label small fw-semibold text-secondary">สถานะ</label>
            <select class="form-select form-select-sm" id="status" name="status">
                <option value="">ทุกสถานะ</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>รอดำเนินการ (pending)</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>ชำระเงินแล้ว (paid)</option>
                <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>กำลังจัดเตรียม (processing)</option>
                <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>จัดส่งแล้ว (shipped)</option>
                <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>ได้รับสินค้าแล้ว (delivered)</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>ยกเลิก (cancelled)</option>
            </select>
        </div>

        <div class="col-md-2">
            <label for="start_date" class="form-label small fw-semibold text-secondary">ตั้งแต่วันที่</label>
            <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?= e($startDate) ?>">
        </div>

        <div class="col-md-2">
            <label for="end_date" class="form-label small fw-semibold text-secondary">ถึงวันที่</label>
            <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?= e($endDate) ?>">
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 flex-grow-1">
                <i class="bi bi-filter me-1"></i> กรองข้อมูล
            </button>
            <a href="<?= url('admin/orders.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                ล้าง
            </a>
        </div>
    </form>
</div>

<!-- Orders Table Card -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <span class="small text-muted">พบทั้งหมด <strong><?= $totalOrders ?></strong> รายการ (หน้า <?= $page ?> จาก <?= $totalPages ?>)</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-secondary">
                <tr>
                    <th>เลขออเดอร์</th>
                    <th>ลูกค้า</th>
                    <th>วันที่สั่งซื้อ</th>
                    <th>จำนวนชิ้น</th>
                    <th>ยอดรวมสุทธิ</th>
                    <th>วิธีชำระ</th>
                    <th>สถานะ</th>
                    <th>เลขพัสดุ</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt fs-2 mb-2 d-block"></i>
                            ไม่พบคำสั่งซื้อที่ตรงกับเงื่อนไข
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $ord): ?>
                        <tr>
                            <td>
                                <strong class="text-dark"><?= e($ord['order_no']) ?></strong>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><?= e($ord['customer_name']) ?></div>
                                <small class="text-muted"><?= e($ord['customer_email']) ?></small>
                            </td>
                            <td class="small text-muted">
                                <?= format_date_th($ord['created_at']) ?>
                            </td>
                            <td class="small text-secondary">
                                <?= $ord['item_count'] ?> รายการ
                            </td>
                            <td class="fw-bold text-dark">
                                <?= format_price($ord['total']) ?>
                            </td>
                            <td class="small text-muted">
                                <?= get_payment_method_label($ord['payment_method']) ?>
                            </td>
                            <td>
                                <?= get_order_status_badge($ord['status']) ?>
                            </td>
                            <td class="small">
                                <?= !empty($ord['tracking_no']) ? '<span class="badge bg-light text-primary border">' . e($ord['tracking_no']) . '</span>' : '-' ?>
                            </td>
                            <td class="text-center">
                                <a href="<?= url('admin/order-detail.php?id=' . $ord['id']) ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1">
                                    <i class="bi bi-eye me-1"></i> รายละเอียด
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top py-3">
            <nav aria-label="Orders pagination">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= build_admin_order_url(['page' => $page - 1]) ?>">&laquo; ก่อนหน้า</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                            <a class="page-link <?= ($page === $i) ? 'bg-success border-success' : '' ?>" href="<?= build_admin_order_url(['page' => $i]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= build_admin_order_url(['page' => $page + 1]) ?>">ถัดไป &raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
