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

$q = trim($_GET['q'] ?? '');
$categoryId = !empty($_GET['category']) ? (int)$_GET['category'] : null;
$stockFilter = trim($_GET['stock_filter'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

$where = ["1=1"];
$params = [];

if (!empty($q)) {
    $where[] = "(p.name_th LIKE :q OR p.name_en LIKE :q OR p.sku LIKE :q OR p.brand LIKE :q)";
    $params[':q'] = "%{$q}%";
}

if ($categoryId) {
    $where[] = "p.category_id = :cat_id";
    $params[':cat_id'] = $categoryId;
}

if ($stockFilter === 'low_stock') {
    $where[] = "p.stock_qty <= p.low_stock_threshold AND p.stock_qty > 0";
} elseif ($stockFilter === 'out_of_stock') {
    $where[] = "p.stock_qty <= 0";
}

$whereSql = implode(' AND ', $where);

// นับจำนวนทั้งหมด
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT p.*, c.name_th as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE {$whereSql}
    ORDER BY p.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

function build_admin_prod_url($newParams = []) {
    $current = $_GET;
    return '?' . http_build_query(array_merge($current, $newParams));
}

$pageTitle = 'จัดการสินค้าในคลัง';
require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="bi bi-box-seam text-success me-2"></i> จัดการสินค้าและคลัง (Inventory)
        </h3>
        <p class="text-muted small mb-0">เพิ่ม แก้ไข ตรวจสอบระดับสต็อก และปรับยอดสินค้า</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/product-form.php') ?>" class="btn btn-success rounded-pill px-4 shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> เพิ่มสินค้าใหม่
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
    <form method="GET" action="" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="q" class="form-label small fw-semibold text-secondary">ค้นหาชื่อ / SKU / แบรนด์</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="ชื่อวิตามิน, MV-001...">
            </div>
        </div>

        <div class="col-md-3">
            <label for="category" class="form-label small fw-semibold text-secondary">หมวดหมู่</label>
            <select class="form-select form-select-sm" id="category" name="category">
                <option value="">ทุกหมวดหมู่</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($categoryId === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name_th']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label for="stock_filter" class="form-label small fw-semibold text-secondary">สถานะสต็อก</label>
            <select class="form-select form-select-sm" id="stock_filter" name="stock_filter">
                <option value="">ทั้งหมด</option>
                <option value="low_stock" <?= $stockFilter === 'low_stock' ? 'selected' : '' ?>>สต็อกต่ำกว่าเกณฑ์ (Low Stock)</option>
                <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : '' ?>>สินค้าหมดสต็อก (Out of Stock)</option>
            </select>
        </div>

        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 flex-grow-1">
                กรอง
            </button>
            <a href="<?= url('admin/products.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                ล้าง
            </a>
        </div>
    </form>
</div>

<!-- Products Table Card -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <span class="small text-muted">พบสินค้าทั้งหมด <strong><?= $totalProducts ?></strong> รายการ (หน้า <?= $page ?> จาก <?= $totalPages ?>)</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-secondary">
                <tr>
                    <th style="width: 5%;">รูป</th>
                    <th style="width: 12%;">SKU</th>
                    <th style="width: 25%;">ชื่อสินค้า</th>
                    <th style="width: 14%;">หมวดหมู่ / แบรนด์</th>
                    <th style="width: 10%;" class="text-end">ราคา</th>
                    <th style="width: 12%;" class="text-center">สต็อกคงเหลือ</th>
                    <th style="width: 8%;" class="text-center">สถานะ</th>
                    <th style="width: 14%;" class="text-center">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam fs-2 mb-2 d-block"></i>
                            ไม่พบสินค้าที่ตรงกับเงื่อนไข
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php
                            $rowClass = '';
                            if ($p['stock_qty'] <= 0) {
                                $rowClass = 'table-danger';
                            } elseif ($p['stock_qty'] <= $p['low_stock_threshold']) {
                                $rowClass = 'table-warning';
                            }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td>
                                <img src="<?= get_product_image_url($p['image']) ?>" alt="" class="rounded border" style="width: 44px; height: 44px; object-fit: cover;" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                            </td>
                            <td>
                                <strong class="text-dark small"><?= e($p['sku']) ?></strong>
                            </td>
                            <td>
                                <div class="fw-bold text-dark small"><?= e($p['name_th']) ?></div>
                                <small class="text-muted d-block"><?= e($p['name_en'] ?? '') ?></small>
                                <?php if ($p['is_featured']): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0" style="font-size: 0.7rem;">แนะนำ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-dark d-block"><?= e($p['category_name']) ?></small>
                                <small class="text-muted"><?= e($p['brand']) ?></small>
                            </td>
                            <td class="text-end fw-bold text-success small">
                                <?= format_price($p['price']) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $p['stock_qty'] <= 0 ? 'bg-danger' : ($p['stock_qty'] <= $p['low_stock_threshold'] ? 'bg-warning text-dark' : 'bg-success') ?> fs-6 px-2 py-1">
                                    <?= $p['stock_qty'] ?>
                                </span>
                                <small class="d-block text-muted" style="font-size: 0.7rem;">เกณฑ์เตือน: <?= $p['low_stock_threshold'] ?></small>
                            </td>
                            <td class="text-center">
                                <?php if ($p['is_active']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">เปิดขาย</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">ปิดการขาย</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <a href="<?= url('admin/stock-adjust.php?id=' . $p['id']) ?>" class="btn btn-outline-dark btn-sm" title="ปรับสต็อกด่วน">
                                        <i class="bi bi-plus-slash-minus"></i>
                                    </a>
                                    <a href="<?= url('admin/product-form.php?id=' . $p['id']) ?>" class="btn btn-outline-primary btn-sm" title="แก้ไข">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="<?= url('admin/product-delete.php') ?>" class="d-inline" onsubmit="return confirm('ยืนยันดำเนินการกับสินค้านี้? (หากเคยมีประวัติการสั่งซื้อ ระบบจะปิดการขายแทน)');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="ลบหรือปิดการขาย">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
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
            <nav aria-label="Products pagination">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= build_admin_prod_url(['page' => $page - 1]) ?>">&laquo; ก่อนหน้า</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                            <a class="page-link <?= ($page === $i) ? 'bg-success border-success' : '' ?>" href="<?= build_admin_prod_url(['page' => $i]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= build_admin_prod_url(['page' => $page + 1]) ?>">ถัดไป &raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
