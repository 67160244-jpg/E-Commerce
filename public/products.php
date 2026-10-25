<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

$pdo = get_db_connection();

// ดึงตัวแปรค้นหาและตัวกรอง
$q = trim($_GET['q'] ?? '');
$categoryId = !empty($_GET['category']) ? (int)$_GET['category'] : null;
$minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$isFeatured = isset($_GET['is_featured']) ? (int)$_GET['is_featured'] : null;
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

// หมวดหมู่สำหรับตัวกรอง
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// ประกอบ WHERE Clauses (ซ่อนสินค้า is_active = 0 เสมอ)
$where = ["p.is_active = 1"];
$params = [];

if (!empty($q)) {
    $where[] = "(p.name_th LIKE :q OR p.name_en LIKE :q OR p.brand LIKE :q OR p.sku LIKE :q)";
    $params[':q'] = "%{$q}%";
}

if ($categoryId) {
    $where[] = "p.category_id = :category_id";
    $params[':category_id'] = $categoryId;
}

if ($minPrice !== null && $minPrice >= 0) {
    $where[] = "p.price >= :min_price";
    $params[':min_price'] = $minPrice;
}

if ($maxPrice !== null && $maxPrice > 0) {
    $where[] = "p.price <= :max_price";
    $params[':max_price'] = $maxPrice;
}

if ($isFeatured === 1) {
    $where[] = "p.is_featured = 1";
}

$whereSql = implode(' AND ', $where);

// การเรียงลำดับ
$orderBy = "p.id DESC";
if ($sort === 'price_asc') {
    $orderBy = "p.price ASC";
} elseif ($sort === 'price_desc') {
    $orderBy = "p.price DESC";
} elseif ($sort === 'name_asc') {
    $orderBy = "p.name_th ASC";
}

// นับจำนวนสินค้าทั้งหมดสำหรับ Pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// ดึงรายการสินค้า
$sql = "
    SELECT p.*, c.name_th as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE {$whereSql}
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// ฟังก์ชันสร้าง URL สำหรับ Pagination และ Filter
function build_filter_url($newParams = []) {
    $current = $_GET;
    $merged = array_merge($current, $newParams);
    return '?' . http_build_query($merged);
}

$pageTitle = 'รายการสินค้าทั้งหมด';
require_once APP_ROOT . '/includes/header.php';
?>

<div class="row g-4 py-3">
    <!-- Filter Sidebar (Desktop) -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <h5 class="fw-bold mb-3 text-dark d-flex align-items-center justify-content-between">
                <span><i class="bi bi-funnel text-success me-2"></i> ตัวกรองสินค้า</span>
                <a href="<?= url('public/products.php') ?>" class="text-muted small text-decoration-none">ล้างตัวกรอง</a>
            </h5>

            <form method="GET" action="<?= url('public/products.php') ?>">
                <?php if (!empty($q)): ?>
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                <?php endif; ?>

                <!-- หมวดหมู่ -->
                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary">หมวดหมู่</label>
                    <div class="d-flex flex-column gap-1">
                        <a href="<?= build_filter_url(['category' => null, 'page' => 1]) ?>" class="text-decoration-none small py-1 px-2 rounded <?= empty($categoryId) ? 'bg-success text-white fw-bold' : 'text-dark hover-bg-light' ?>">
                            ทุกหมวดหมู่
                        </a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="<?= build_filter_url(['category' => $cat['id'], 'page' => 1]) ?>" class="text-decoration-none small py-1 px-2 rounded <?= ($categoryId === (int)$cat['id']) ? 'bg-success text-white fw-bold' : 'text-dark hover-bg-light' ?>">
                                <?= e($cat['name_th']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ช่วงราคา -->
                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary">ช่วงราคา (บาท)</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" class="form-control form-control-sm" name="min_price" placeholder="ต่ำสุด" value="<?= $minPrice !== null ? e($minPrice) : '' ?>" min="0">
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control form-control-sm" name="max_price" placeholder="สูงสุด" value="<?= $maxPrice !== null ? e($maxPrice) : '' ?>" min="0">
                        </div>
                    </div>
                </div>

                <!-- สินค้าแนะนำ -->
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="filterFeatured" <?= $isFeatured === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="filterFeatured">
                            เฉพาะสินค้าแนะนำยอดนิยม
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-sm w-100 rounded-pill">
                    ค้นหาตามตัวกรอง
                </button>
            </form>
        </div>
    </div>

    <!-- Product Grid & Search Results -->
    <div class="col-lg-9">
        <!-- Top Controls: Search keyword display & Sort Dropdown -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <?php if (!empty($q)): ?>
                            ผลการค้นหาสำหรับ "<span class="text-success"><?= e($q) ?></span>"
                        <?php elseif ($categoryId): ?>
                            <?php 
                                $currentCatName = '';
                                foreach ($categories as $c) { if ($c['id'] == $categoryId) $currentCatName = $c['name_th']; }
                            ?>
                            หมวดหมู่: <span class="text-success"><?= e($currentCatName) ?></span>
                        <?php else: ?>
                            สินค้าทั้งหมด
                        <?php endif; ?>
                    </h5>
                    <small class="text-muted">พบสินค้าทั้งหมด <?= $totalProducts ?> รายการ (หน้า <?= $page ?> จาก <?= $totalPages ?>)</small>
                </div>

                <!-- Sort Dropdown -->
                <div class="d-flex align-items-center gap-2">
                    <label for="sortSelect" class="small text-muted text-nowrap">เรียงตาม:</label>
                    <select id="sortSelect" class="form-select form-select-sm" style="width: auto;" onchange="location = this.value;">
                        <option value="<?= build_filter_url(['sort' => 'newest']) ?>" <?= $sort === 'newest' ? 'selected' : '' ?>>มาใหม่ล่าสุด</option>
                        <option value="<?= build_filter_url(['sort' => 'price_asc']) ?>" <?= $sort === 'price_asc' ? 'selected' : '' ?>>ราคา: ต่ำ - สูง</option>
                        <option value="<?= build_filter_url(['sort' => 'price_desc']) ?>" <?= $sort === 'price_desc' ? 'selected' : '' ?>>ราคา: สูง - ต่ำ</option>
                        <option value="<?= build_filter_url(['sort' => 'name_asc']) ?>" <?= $sort === 'name_asc' ? 'selected' : '' ?>>ชื่อ: ก - ฮ</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Products Cards Grid -->
        <?php if (empty($products)): ?>
            <div class="card border-0 shadow-sm p-5 text-center text-muted rounded-4">
                <i class="bi bi-search fs-1 text-secondary mb-3"></i>
                <h5 class="fw-bold">ไม่พบสินค้าที่ตรงกับเงื่อนไขการค้นหา</h5>
                <p class="small mb-3">ลองปรับเปลี่ยนคีย์เวิร์ด หรือล้างตัวกรองเพื่อค้นหาใหม่อีกครั้ง</p>
                <div>
                    <a href="<?= url('public/products.php') ?>" class="btn btn-outline-success btn-sm rounded-pill px-4">
                        ดูสินค้าทั้งหมด
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3 g-md-4">
                <?php foreach ($products as $product): ?>
                    <div class="col-6 col-md-4">
                        <div class="product-card">
                            <div class="product-card-img-wrap">
                                <img src="<?= get_product_image_url($product['image']) ?>" alt="<?= e($product['name_th']) ?>" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                                <?php if ($product['stock_qty'] <= 0): ?>
                                    <span class="product-badge-out">สินค้าหมด</span>
                                <?php elseif ($product['stock_qty'] <= $product['low_stock_threshold']): ?>
                                    <span class="product-badge-low">สต็อกเหลือน้อย</span>
                                <?php elseif (!empty($product['compare_at_price']) && $product['compare_at_price'] > $product['price']): ?>
                                    <span class="product-badge-sale">ลดราคา</span>
                                <?php endif; ?>
                            </div>

                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <small class="text-muted mb-1"><?= e($product['brand']) ?> • <?= e($product['category_name']) ?></small>
                                <h6 class="fw-bold mb-1 text-truncate" title="<?= e($product['name_th']) ?>">
                                    <a href="<?= url('public/product-detail.php?id=' . $product['id']) ?>" class="text-dark text-decoration-none">
                                        <?= e($product['name_th']) ?>
                                    </a>
                                </h6>
                                <small class="text-secondary text-truncate mb-2"><?= e($product['name_en'] ?? '') ?></small>

                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="price-current"><?= format_price($product['price']) ?></span>
                                        <?php if (!empty($product['compare_at_price']) && $product['compare_at_price'] > $product['price']): ?>
                                            <span class="price-compare"><?= format_price($product['compare_at_price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= url('public/product-detail.php?id=' . $product['id']) ?>" class="btn btn-sm btn-outline-success rounded-circle" style="width: 34px; height: 34px; padding: 0; line-height: 32px;" title="ดูรายละเอียด">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-5" aria-label="Products pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= build_filter_url(['page' => $page - 1]) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo; ก่อนหน้า</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                <a class="page-link <?= ($page === $i) ? 'bg-success border-success' : '' ?>" href="<?= build_filter_url(['page' => $i]) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= build_filter_url(['page' => $page + 1]) ?>" aria-label="Next">
                                <span aria-hidden="true">ถัดไป &raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
