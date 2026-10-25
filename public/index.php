<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

$pdo = get_db_connection();

// ดึงหมวดหมู่ทั้งหมดพร้อมจำนวนสินค้าที่เปิดขาย
$categoriesStmt = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
    GROUP BY c.id
    ORDER BY c.id ASC
");
$categories = $categoriesStmt->fetchAll();

// ดึงสินค้าแนะนำ is_featured = 1 และ is_active = 1
$featuredStmt = $pdo->query("
    SELECT p.*, c.name_th as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_featured = 1 AND p.is_active = 1
    ORDER BY p.id ASC
    LIMIT 8
");
$featuredProducts = $featuredStmt->fetchAll();

// ดึงสินค้ามาใหม่ล่าสุด
$newestStmt = $pdo->query("
    SELECT p.*, c.name_th as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.id DESC
    LIMIT 4
");
$newestProducts = $newestStmt->fetchAll();

$pageTitle = 'หน้าแรก';
require_once APP_ROOT . '/includes/header.php';
?>

<!-- Hero Banner Section (สีฟ้าและเขียวอ่อนละมุน อ่านสบายตา คอนทราสต์ชัดเจน) -->
<section class="hero-banner-soft p-4 p-md-5 my-4">
    <div class="container py-2">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge hero-badge-pill px-3 py-2 rounded-pill mb-3">
                    ✨ คุณภาพระดับพรีเมียม มาตรฐานสากล
                </span>
                <h1 class="display-4 hero-title mb-3">
                    สุขภาพดีเริ่มต้นได้ทุกวัน กับ <span class="brand-highlight"><?= STORE_NAME ?></span>
                </h1>
                <p class="hero-subtitle mb-4">
                    ครบครันด้วยวิตามินรวม แร่ธาตุ โอเมก้า-3 โพรไบโอติก และสารอาหารเสริมสำหรับทุกคนในครอบครัว
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= url('public/products.php') ?>" class="btn hero-btn-primary btn-lg px-4 py-2 rounded-pill fw-bold">
                        <i class="bi bi-bag-check me-2"></i> ช้อปสินค้าทั้งหมด
                    </a>
                    <a href="<?= url('public/products.php?is_featured=1') ?>" class="btn hero-btn-secondary btn-lg px-4 py-2 rounded-pill fw-semibold">
                        <i class="bi bi-star-fill text-warning me-1"></i> สินค้าแนะนำยอดนิยม
                    </a>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <div class="p-4 hero-card-promo">
                    <div class="d-inline-flex p-3 rounded-circle mb-2" style="background-color: #e0f2fe; color: #0284c7;">
                        <i class="bi bi-shield-heart fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-2" style="color: #0369a1;">จัดส่งฟรีทั่วไทย!</h4>
                    <p class="mb-0 text-muted">เมื่อมียอดสั่งซื้อตั้งแต่ <strong style="color: #059669;"><?= format_price(FREE_SHIPPING_THRESHOLD) ?></strong> ขึ้นไป</p>
                    <hr class="my-3 text-muted opacity-25">
                    <div class="d-flex justify-content-around text-start">
                        <div>
                            <small class="text-muted d-block">จัดส่งรวดเร็ว</small>
                            <strong style="color: #0c4a6e;"><i class="bi bi-truck text-primary me-1"></i> 1 - 3 วันทำการ</strong>
                        </div>
                        <div>
                            <small class="text-muted d-block">รับประกันคุณภาพ</small>
                            <strong style="color: #059669;"><i class="bi bi-patch-check-fill me-1"></i> ของแท้ 100%</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Grid Section -->
<section class="py-4">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-grid-3x3-gap-fill text-success me-2"></i> หมวดหมู่สินค้า</h3>
            <p class="text-muted small mb-0">เลือกช้อปสารอาหารที่ตรงกับความต้องการของคุณ</p>
        </div>
        <a href="<?= url('public/products.php') ?>" class="text-success text-decoration-none fw-semibold small">
            ดูทั้งหมด <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <div class="row g-3">
        <?php foreach ($categories as $cat): ?>
            <div class="col-6 col-md-3">
                <a href="<?= url('public/products.php?category=' . $cat['id']) ?>" class="card h-100 border-0 shadow-sm text-decoration-none text-dark product-card p-3 text-center">
                    <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mx-auto mb-2" style="width: 54px; height: 54px;">
                        <i class="bi bi-capsule fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1"><?= e($cat['name_th']) ?></h6>
                    <small class="text-muted"><?= $cat['product_count'] ?> รายการ</small>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Featured Products Section -->
<section class="py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill mb-1 fw-bold">BEST PICKS</span>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-star-fill text-warning me-2"></i> สินค้าแนะนำยอดนิยม</h3>
            <p class="text-muted small mb-0">ผลิตภัณฑ์วิตามินและสารอาหารขายดีที่ลูกค้าไว้วางใจ</p>
        </div>
        <a href="<?= url('public/products.php?is_featured=1') ?>" class="text-success text-decoration-none fw-semibold small">
            ดูทั้งหมด <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <div class="row g-4">
        <?php foreach ($featuredProducts as $product): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="product-card">
                    <div class="product-card-img-wrap">
                        <img src="<?= get_product_image_url($product['image']) ?>" alt="<?= e($product['name_th']) ?>" onerror="this.onerror=null;this.src='<?= url('assets/products/placeholder.jpg') ?>'">
                        <?php if ($product['stock_qty'] <= 0): ?>
                            <span class="product-badge-out">สินค้าหมด</span>
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
</section>

<!-- Promo / Health Highlights Banner -->
<section class="py-4 my-3">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 rounded-4 h-100 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle bg-success-subtle text-success fs-3">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">มาตรฐานความปลอดภัย</h6>
                        <small class="text-muted">คัดสรรเฉพาะสารอาหารที่มีมาตรฐาน ผ่านการตรวจสอบคุณภาพ</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 rounded-4 h-100 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle bg-primary-subtle text-primary fs-3">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">จัดส่งควบคุมอุณหภูมิ</h6>
                        <small class="text-muted">บรรจุหีบห่ออย่างดี ปกป้องคุณภาพวิตามินและสารอาหาร</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 rounded-4 h-100 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle bg-warning-subtle text-warning fs-3">
                        <i class="bi bi-chat-heart"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">ทีมงานพร้อมให้คำแนะนำ</h6>
                        <small class="text-muted">ปรึกษาวิธีรับประทานและสอบถามข้อมูลได้ทุกวันทำการ</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
