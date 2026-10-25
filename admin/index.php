<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

// ป้องกันสิทธิ์แอดมินเป็นบรรทัดแรก
require_admin();

$pdo = get_db_connection();

// 1. KPI Cards Queries
// ยอดขายวันนี้ (ไม่นับ cancelled)
$todaySalesStmt = $pdo->query("
    SELECT COALESCE(SUM(total), 0) 
    FROM orders 
    WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'
");
$todaySales = (float)$todaySalesStmt->fetchColumn();

// ยอดขายเดือนนี้ (ไม่นับ cancelled)
$monthSalesStmt = $pdo->query("
    SELECT COALESCE(SUM(total), 0) 
    FROM orders 
    WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) AND status != 'cancelled'
");
$monthSales = (float)$monthSalesStmt->fetchColumn();

// จำนวนออเดอร์รอดำเนินการ (pending, paid, processing)
$pendingOrdersStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM orders 
    WHERE status IN ('pending', 'paid', 'processing')
");
$pendingOrdersCount = (int)$pendingOrdersStmt->fetchColumn();

// จำนวนลูกค้าทั้งหมด
$customersCountStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM users 
    WHERE role = 'customer'
");
$customersCount = (int)$customersCountStmt->fetchColumn();

// จำนวนสินค้าสต็อกต่ำ
$lowStockStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM products 
    WHERE stock_qty <= low_stock_threshold AND is_active = 1
");
$lowStockCount = (int)$lowStockStmt->fetchColumn();

// 2. ข้อมูลกราฟยอดขายรายวัน 30 วันล่าสุด (Chart.js)
$dailySalesStmt = $pdo->query("
    SELECT DATE(created_at) as order_date, COALESCE(SUM(total), 0) as daily_total
    FROM orders
    WHERE status != 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date ASC
");
$dailySalesData = $dailySalesStmt->fetchAll();

$chartDates = [];
$chartTotals = [];
foreach ($dailySalesData as $ds) {
    $chartDates[] = date('d/m', strtotime($ds['order_date']));
    $chartTotals[] = (float)$ds['daily_total'];
}

// 3. กราฟยอดขายตามหมวดหมู่
$catSalesStmt = $pdo->query("
    SELECT c.name_th as category_name, COALESCE(SUM(oi.unit_price * oi.quantity), 0) as total_sales
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
    GROUP BY c.id
    ORDER BY total_sales DESC
");
$catSalesData = $catSalesStmt->fetchAll();

$chartCatLabels = [];
$chartCatTotals = [];
foreach ($catSalesData as $cs) {
    $chartCatLabels[] = $cs['category_name'];
    $chartCatTotals[] = (float)$cs['total_sales'];
}

// 4. สินค้าขายดี Top 5
$topProductsStmt = $pdo->query("
    SELECT oi.product_id, oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.unit_price * oi.quantity) as total_revenue, p.image, p.stock_qty
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.status != 'cancelled'
    GROUP BY oi.product_id, oi.product_name
    ORDER BY total_qty DESC
    LIMIT 5
");
$topProducts = $topProductsStmt->fetchAll();

// 5. ออเดอร์ล่าสุด 10 รายการ
$recentOrdersStmt = $pdo->query("
    SELECT o.*, u.name as customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.id DESC
    LIMIT 10
");
$recentOrders = $recentOrdersStmt->fetchAll();

// 6. รายการสินค้าสต็อกต่ำ
$lowStockProductsStmt = $pdo->query("
    SELECT p.*, c.name_th as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock_qty <= p.low_stock_threshold AND p.is_active = 1
    ORDER BY p.stock_qty ASC
    LIMIT 6
");
$lowStockProducts = $lowStockProductsStmt->fetchAll();

$pageTitle = 'แดชบอร์ดภาพรวม';
require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="bi bi-speedometer2 text-success me-2"></i> แดชบอร์ดภาพรวมธุรกิจ (Mochi Vita)
        </h3>
        <p class="text-muted small mb-0">ข้อมูลสรุปยอดขาย การสั่งซื้อ และสต็อกสินค้า ณ วันที่ <?= format_date_th(date('Y-m-d H:i:s')) ?></p>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <!-- ยอดขายวันนี้ -->
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold">ยอดขายวันนี้</span>
                    <h4 class="fw-bold text-success mt-1 mb-0"><?= format_price($todaySales) ?></h4>
                </div>
                <div class="p-3 bg-success-subtle text-success rounded-circle">
                    <i class="bi bi-cash-coin fs-4"></i>
                </div>
            </div>
            <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">(ไม่รวมออเดอร์ที่ยกเลิก)</small>
        </div>
    </div>

    <!-- ยอดขายเดือนนี้ -->
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold">ยอดขายเดือนนี้</span>
                    <h4 class="fw-bold text-primary mt-1 mb-0"><?= format_price($monthSales) ?></h4>
                </div>
                <div class="p-3 bg-primary-subtle text-primary rounded-circle">
                    <i class="bi bi-graph-up-arrow fs-4"></i>
                </div>
            </div>
            <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">เดือน <?= date('m/Y') ?></small>
        </div>
    </div>

    <!-- ออเดอร์รอดำเนินการ -->
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold">ออเดอร์รอดำเนินการ</span>
                    <h4 class="fw-bold text-warning mt-1 mb-0"><?= $pendingOrdersCount ?> รายการ</h4>
                </div>
                <div class="p-3 bg-warning-subtle text-warning rounded-circle">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
            </div>
            <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">pending, paid, processing</small>
        </div>
    </div>

    <!-- จำนวนลูกค้า -->
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold">สมาชิกลูกค้า</span>
                    <h4 class="fw-bold text-info mt-1 mb-0"><?= $customersCount ?> คน</h4>
                </div>
                <div class="p-3 bg-info-subtle text-info rounded-circle">
                    <i class="bi bi-people fs-4"></i>
                </div>
            </div>
            <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">บัญชีผู้ใช้งานทั่วไป</small>
        </div>
    </div>

    <!-- สินค้าสต็อกต่ำ -->
    <div class="col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold">สินค้าสต็อกต่ำ</span>
                    <h4 class="fw-bold text-danger mt-1 mb-0"><?= $lowStockCount ?> รายการ</h4>
                </div>
                <div class="p-3 bg-danger-subtle text-danger rounded-circle">
                    <i class="bi bi-exclamation-triangle fs-4"></i>
                </div>
            </div>
            <small class="text-muted mt-2 d-block" style="font-size: 0.75rem;">≤ เกณฑ์แจ้งเตือนสต็อก</small>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- 30-Day Sales Line Chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-bar-chart-line text-success me-2"></i> สถิติยอดขาย 30 วันล่าสุด
                </h5>
                <span class="badge bg-light text-dark border">รายวัน</span>
            </div>
            <div style="height: 300px;">
                <canvas id="dailySalesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Sales Doughnut Chart -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-pie-chart text-success me-2"></i> ยอดขายตามหมวดหมู่
            </h5>
            <div style="height: 300px;">
                <canvas id="categorySalesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Alert Box & Top Bestsellers -->
<div class="row g-4 mb-4">
    <!-- Low Stock Alert Card -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
            <div class="card-header bg-danger-subtle text-danger py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-exclamation-octagon-fill me-2"></i> แจ้งเตือนสินค้าสต็อกต่ำ / หมด</h5>
                <a href="<?= url('admin/products.php?filter=low_stock') ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                    ดูทั้งหมด
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle text-success fs-1 mb-2"></i>
                        <p class="mb-0">ระดับสต็อกสินค้าทุกรายการอยู่ในเกณฑ์ปกติ</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>สินค้า</th>
                                    <th>หมวดหมู่</th>
                                    <th class="text-center">คงเหลือ</th>
                                    <th class="text-center">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $lp): ?>
                                    <tr class="<?= $lp['stock_qty'] <= 0 ? 'table-danger' : 'table-warning' ?>">
                                        <td>
                                            <strong class="d-block text-dark small"><?= e($lp['name_th']) ?></strong>
                                            <small class="text-muted">SKU: <?= e($lp['sku']) ?></small>
                                        </td>
                                        <td class="small text-secondary"><?= e($lp['category_name']) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $lp['stock_qty'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                                <?= $lp['stock_qty'] ?> ชิ้น
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= url('admin/stock-adjust.php?id=' . $lp['id']) ?>" class="btn btn-sm btn-dark rounded-pill px-2 py-0">
                                                <i class="bi bi-plus-slash-minus me-1"></i> ปรับสต็อก
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

    <!-- Top 5 Bestsellers -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-trophy-fill text-warning me-2"></i> สินค้าขายดี 5 อันดับแรก (Top 5)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="width: 10%;">อันดับ</th>
                                <th style="width: 50%;">สินค้า</th>
                                <th class="text-center" style="width: 20%;">ขายได้ (ชิ้น)</th>
                                <th class="text-end" style="width: 20%;">ยอดรวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topProducts)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">ยังไม่มีข้อมูลยอดขาย</td></tr>
                            <?php else: ?>
                                <?php foreach ($topProducts as $rank => $tp): ?>
                                    <tr>
                                        <td class="text-center">
                                            <span class="badge rounded-circle <?= $rank === 0 ? 'bg-warning text-dark' : ($rank === 1 ? 'bg-secondary' : ($rank === 2 ? 'bg-bronze text-white' : 'bg-light text-dark border')) ?>" style="width: 26px; height: 26px; line-height: 18px;">
                                                <?= $rank + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?= get_product_image_url($tp['image']) ?>" alt="" class="rounded border" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                                                <span class="small fw-semibold text-truncate" style="max-width: 220px;" title="<?= e($tp['product_name']) ?>"><?= e($tp['product_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center fw-bold text-success small"><?= $tp['total_qty'] ?></td>
                                        <td class="text-end fw-semibold text-dark small"><?= format_price($tp['total_revenue']) ?></td>
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

<!-- Recent 10 Orders Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-success me-2"></i> คำสั่งซื้อล่าสุด 10 รายการ</h5>
        <a href="<?= url('admin/orders.php') ?>" class="btn btn-sm btn-outline-success rounded-pill px-3">
            ดูคำสั่งซื้อทั้งหมด
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-secondary">
                    <tr>
                        <th>เลขออเดอร์</th>
                        <th>ลูกค้า</th>
                        <th>วันที่สั่งซื้อ</th>
                        <th>สถานะ</th>
                        <th>วิธีชำระ</th>
                        <th class="text-end">ยอดรวม</th>
                        <th class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $ro): ?>
                        <tr>
                            <td><strong class="text-dark"><?= e($ro['order_no']) ?></strong></td>
                            <td class="small fw-semibold"><?= e($ro['customer_name']) ?></td>
                            <td class="small text-muted"><?= format_date_th($ro['created_at']) ?></td>
                            <td><?= get_order_status_badge($ro['status']) ?></td>
                            <td class="small text-muted"><?= get_payment_method_label($ro['payment_method']) ?></td>
                            <td class="text-end fw-bold text-dark"><?= format_price($ro['total']) ?></td>
                            <td class="text-center">
                                <a href="<?= url('admin/order-detail.php?id=' . $ro['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1">
                                    รายละเอียด
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Daily Sales Chart (Line)
    const ctxDaily = document.getElementById('dailySalesChart').getContext('2d');
    new Chart(ctxDaily, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartDates) ?>,
            datasets: [{
                label: 'ยอดขายรายวัน (บาท)',
                data: <?= json_encode($chartTotals) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#059669',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '฿' + value.toLocaleString(); }
                    }
                }
            }
        }
    });

    // 2. Category Sales Doughnut Chart
    const ctxCat = document.getElementById('categorySalesChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chartCatLabels) ?>,
            datasets: [{
                data: <?= json_encode($chartCatTotals) ?>,
                backgroundColor: [
                    '#10b981', '#3b82f6', '#f59e0b', '#ec4899',
                    '#8b5cf6', '#14b8a6', '#64748b', '#f97316'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 10 } }
                }
            }
        }
    });
});
</script>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
