<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

$pdo = get_db_connection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = trim($_GET['slug'] ?? '');

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name_th as category_name, c.slug as category_slug 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = :id AND p.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
} elseif (!empty($slug)) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name_th as category_name, c.slug as category_slug 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.slug = :slug AND p.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':slug' => $slug]);
} else {
    redirect('public/products.php');
}

$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'ไม่พบสินค้า';
    require_once APP_ROOT . '/includes/header.php';
    echo "
    <div class=\"text-center py-5\">
        <div class=\"card border-0 shadow-sm p-5 mx-auto\" style=\"max-width: 500px;\">
            <i class=\"bi bi-search fs-1 text-muted mb-3\"></i>
            <h3 class=\"fw-bold text-dark\">ไม่พบข้อมูลสินค้า</h3>
            <p class=\"text-muted\">สินค้านี้อาจถูกยกเลิกการจำหน่ายหรือไม่มีอยู่ในระบบ</p>
            <a href=\"" . url('public/products.php') . "\" class=\"btn btn-success rounded-pill px-4 mt-2\">กลับไปยังรายการสินค้า</a>
        </div>
    </div>";
    require_once APP_ROOT . '/includes/footer.php';
    exit;
}

// สินค้าในหมวดหมู่เดียวกัน
$relatedStmt = $pdo->prepare("
    SELECT p.*, c.name_th as category_name 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = :cat_id AND p.id != :id AND p.is_active = 1
    ORDER BY RAND()
    LIMIT 4
");
$relatedStmt->execute([
    ':cat_id' => $product['category_id'],
    ':id'     => $product['id']
]);
$relatedProducts = $relatedStmt->fetchAll();

$pageTitle = $product['name_th'];
require_once APP_ROOT . '/includes/header.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('public/index.php') ?>" class="text-decoration-none text-muted">หน้าแรก</a></li>
        <li class="breadcrumb-item"><a href="<?= url('public/products.php') ?>" class="text-decoration-none text-muted">สินค้าทั้งหมด</a></li>
        <li class="breadcrumb-item"><a href="<?= url('public/products.php?category=' . $product['category_id']) ?>" class="text-decoration-none text-muted"><?= e($product['category_name']) ?></a></li>
        <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page"><?= e($product['name_th']) ?></li>
    </ol>
</nav>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
    <div class="card-body p-4 p-lg-5">
        <div class="row g-5">
            <!-- Product Image -->
            <div class="col-lg-6">
                <div class="position-relative bg-light rounded-4 overflow-hidden p-3 text-center border">
                    <img src="<?= get_product_image_url($product['image']) ?>" 
                         alt="<?= e($product['name_th']) ?>" 
                         class="img-fluid rounded-3" 
                         style="max-height: 480px; object-fit: contain;"
                         onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                    
                    <?php if ($product['stock_qty'] <= 0): ?>
                        <span class="product-badge-out fs-6 px-3 py-1">สินค้าหมดชั่วคราว</span>
                    <?php elseif ($product['stock_qty'] <= $product['low_stock_threshold']): ?>
                        <span class="product-badge-low fs-6 px-3 py-1">สต็อกเหลือน้อย</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Details -->
            <div class="col-lg-6 d-flex flex-column">
                <div class="mb-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                        <?= e($product['brand']) ?>
                    </span>
                    <span class="badge bg-light text-secondary border px-3 py-1 rounded-pill ms-1">
                        <?= e($product['category_name']) ?>
                    </span>
                    <span class="text-muted small ms-2">SKU: <?= e($product['sku']) ?></span>
                </div>

                <h2 class="fw-bold text-dark mb-1"><?= e($product['name_th']) ?></h2>
                <?php if (!empty($product['name_en'])): ?>
                    <h5 class="text-muted fw-normal mb-3"><?= e($product['name_en']) ?></h5>
                <?php endif; ?>

                <!-- Price Section -->
                <div class="p-3 bg-light rounded-3 mb-4 d-flex align-items-baseline gap-3">
                    <span class="display-6 fw-bold text-success"><?= format_price($product['price']) ?></span>
                    <?php if (!empty($product['compare_at_price']) && $product['compare_at_price'] > $product['price']): ?>
                        <span class="text-decoration-line-through text-muted fs-5"><?= format_price($product['compare_at_price']) ?></span>
                        <span class="badge bg-danger rounded-pill">
                            ประหยัด <?= format_price($product['compare_at_price'] - $product['price']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Product Attributes -->
                <div class="mb-4">
                    <div class="row g-2 small">
                        <?php if (!empty($product['size'])): ?>
                            <div class="col-6">
                                <span class="text-muted">ขนาดบรรจุ:</span>
                                <strong class="text-dark d-block"><?= e($product['size']) ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="col-6">
                            <span class="text-muted">สถานะสต็อก:</span>
                            <?php if ($product['stock_qty'] > 0): ?>
                                <strong class="text-success d-block"><i class="bi bi-check-circle-fill me-1"></i> มีสินค้าพร้อมส่ง (<?= $product['stock_qty'] ?> ชิ้น)</strong>
                            <?php else: ?>
                                <strong class="text-danger d-block"><i class="bi bi-x-circle-fill me-1"></i> สินค้าหมด</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Product Description -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2">รายละเอียดผลิตภัณฑ์:</h6>
                    <div class="text-secondary" style="line-height: 1.8;">
                        <?= nl2br(e($product['description'] ?? 'ไม่มีรายละเอียดเพิ่มเติม')) ?>
                    </div>
                </div>

                <!-- Add to Cart Form -->
                <div class="mt-auto pt-3 border-top">
                    <?php if ($product['stock_qty'] > 0): ?>
                        <form method="POST" action="<?= url('public/cart.php') ?>" class="row g-3 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">

                            <div class="col-auto">
                                <label for="quantity" class="col-form-label small fw-semibold text-secondary">จำนวน:</label>
                            </div>
                            <div class="col-auto" style="width: 110px;">
                                <input type="number" class="form-control text-center" id="quantity" name="quantity" value="1" min="1" max="<?= $product['stock_qty'] ?>" required>
                            </div>
                            <div class="col">
                                <button type="submit" class="btn btn-success btn-lg w-100 rounded-pill fw-semibold shadow-sm">
                                    <i class="bi bi-cart-plus me-2"></i> เพิ่มลงตะกร้า
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary text-center mb-0" role="alert">
                            <i class="bi bi-exclamation-octagon me-2"></i> ขออภัย สินค้ารายการนี้หมดสต็อกชั่วคราว
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Related Products in Same Category -->
<?php if (!empty($relatedProducts)): ?>
    <section class="py-4">
        <h4 class="fw-bold text-dark mb-4"><i class="bi bi-collection-fill text-success me-2"></i> สินค้าในหมวดหมู่เดียวกัน</h4>
        <div class="row g-4">
            <?php foreach ($relatedProducts as $rel): ?>
                <div class="col-6 col-md-3">
                    <div class="product-card">
                        <div class="product-card-img-wrap">
                            <img src="<?= get_product_image_url($rel['image']) ?>" alt="<?= e($rel['name_th']) ?>" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                            <?php if ($rel['stock_qty'] <= 0): ?>
                                <span class="product-badge-out">สินค้าหมด</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <small class="text-muted mb-1"><?= e($rel['brand']) ?></small>
                            <h6 class="fw-bold mb-1 text-truncate" title="<?= e($rel['name_th']) ?>">
                                <a href="<?= url('public/product-detail.php?id=' . $rel['id']) ?>" class="text-dark text-decoration-none">
                                    <?= e($rel['name_th']) ?>
                                </a>
                            </h6>
                            <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                <span class="price-current fs-6"><?= format_price($rel['price']) ?></span>
                                <a href="<?= url('public/product-detail.php?id=' . $rel['id']) ?>" class="btn btn-sm btn-outline-success rounded-circle" style="width: 32px; height: 32px; padding: 0; line-height: 30px;">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
