<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

$pdo = get_db_connection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();

    if (!$product) {
        set_flash('danger', 'ไม่พบสินค้าที่ต้องการแก้ไข');
        redirect('admin/products.php');
    }
}

// หมวดหมู่ทั้งหมด
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();

    $sku = trim($_POST['sku'] ?? '');
    $name_th = trim($_POST['name_th'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? 'Mochi Vita');
    $price = (float)($_POST['price'] ?? 0);
    $compare_at_price = !empty($_POST['compare_at_price']) ? (float)$_POST['compare_at_price'] : null;
    $stock_qty = (int)($_POST['stock_qty'] ?? 0);
    $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 10);
    $size = trim($_POST['size'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    // สร้าง slug อัตโนมัติหากไม่ได้กรอก
    if (empty($slug)) {
        $slug = !empty($name_en) ? slugify($name_en) : slugify($name_th);
        if (empty($slug)) {
            $slug = 'product-' . time();
        }
    }

    // ตรวจสอบความถูกต้อง
    if (empty($sku) || empty($name_th) || empty($category_id) || $price <= 0) {
        $error = 'กรุณากรอก SKU, ชื่อสินค้าภาษาไทย, หมวดหมู่ และราคาให้ถูกต้อง';
    } else {
        // ตรวจสอบ SKU ซ้ำ
        $skuCheck = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1");
        $skuCheck->execute([':sku' => $sku, ':id' => $id]);
        if ($skuCheck->fetch()) {
            $error = 'รหัส SKU "' . $sku . '" นี้มีอยู่ในระบบแล้ว กรุณาใช้ SKU อื่น';
        }

        // ตรวจสอบ Slug ซ้ำ
        if (empty($error)) {
            $slugCheck = $pdo->prepare("SELECT id FROM products WHERE slug = :slug AND id != :id LIMIT 1");
            $slugCheck->execute([':slug' => $slug, ':id' => $id]);
            if ($slugCheck->fetch()) {
                $slug .= '-' . rand(100, 999);
            }
        }

        // การอัปโหลดรูปภาพสินค้า
        $imagePath = $product['image'] ?? '/assets/products/placeholder.jpg';
        if (empty($error) && !empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $maxSize = 2 * 1024 * 1024; // 2MB

            if (!in_array($fileExt, $allowedExtensions, true)) {
                $error = 'ไฟล์รูปภาพต้องเป็นนามสกุล jpg, jpeg, png หรือ webp เท่านั้น';
            } elseif ($file['size'] > $maxSize) {
                $error = 'ขนาดไฟล์รูปภาพต้องไม่เกิน 2MB';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
            } else {
                // สร้างชื่อไฟล์ใหม่ที่ปลอดภัย
                $fileName = $slug . '-' . time() . '.' . $fileExt;
                $targetDir = APP_ROOT . '/assets/products/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $targetFile = $targetDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    $imagePath = '/assets/products/' . $fileName;
                } else {
                    $error = 'ไม่สามารถบันทึกไฟล์รูปภาพไปยังโฟลเดอร์ assets/products/ ได้';
                }
            }
        }

        // บันทึกลงฐานข้อมูล
        if (empty($error)) {
            if ($id > 0) {
                // แก้ไขสินค้า
                $upStmt = $pdo->prepare("
                    UPDATE products 
                    SET sku = :sku, name_th = :name_th, name_en = :name_en, slug = :slug,
                        category_id = :category_id, brand = :brand, price = :price,
                        compare_at_price = :compare_at_price, stock_qty = :stock_qty,
                        low_stock_threshold = :low_stock_threshold, size = :size,
                        description = :description, image = :image, is_featured = :is_featured,
                        is_active = :is_active
                    WHERE id = :id
                ");
                $upStmt->execute([
                    ':sku'                 => $sku,
                    ':name_th'             => $name_th,
                    ':name_en'             => $name_en,
                    ':slug'                => $slug,
                    ':category_id'         => $category_id,
                    ':brand'               => $brand,
                    ':price'               => $price,
                    ':compare_at_price'    => $compare_at_price,
                    ':stock_qty'           => $stock_qty,
                    ':low_stock_threshold' => $low_stock_threshold,
                    ':size'                => $size,
                    ':description'         => $description,
                    ':image'               => $imagePath,
                    ':is_featured'         => $is_featured,
                    ':is_active'           => $is_active,
                    ':id'                  => $id
                ]);
                set_flash('success', 'บันทึกการแก้ไขสินค้าเรียบร้อยแล้ว');
            } else {
                // เพิ่มสินค้าใหม่
                $inStmt = $pdo->prepare("
                    INSERT INTO products (sku, name_th, name_en, slug, category_id, brand, price, compare_at_price, stock_qty, low_stock_threshold, size, description, image, is_featured, is_active)
                    VALUES (:sku, :name_th, :name_en, :slug, :category_id, :brand, :price, :compare_at_price, :stock_qty, :low_stock_threshold, :size, :description, :image, :is_featured, :is_active)
                ");
                $inStmt->execute([
                    ':sku'                 => $sku,
                    ':name_th'             => $name_th,
                    ':name_en'             => $name_en,
                    ':slug'                => $slug,
                    ':category_id'         => $category_id,
                    ':brand'               => $brand,
                    ':price'               => $price,
                    ':compare_at_price'    => $compare_at_price,
                    ':stock_qty'           => $stock_qty,
                    ':low_stock_threshold' => $low_stock_threshold,
                    ':size'                => $size,
                    ':description'         => $description,
                    ':image'               => $imagePath,
                    ':is_featured'         => $is_featured,
                    ':is_active'           => $is_active
                ]);
                set_flash('success', 'เพิ่มสินค้าใหม่ลงในระบบเรียบร้อยแล้ว');
            }
            redirect('admin/products.php');
        }
    }
}

$pageTitle = ($id > 0) ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่';
require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= url('admin/products.php') ?>" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> กลับไปรายการสินค้า
        </a>
        <h3 class="fw-bold text-dark mt-1 mb-0">
            <?= ($id > 0) ? '<i class="bi bi-pencil-square text-success me-2"></i> แก้ไขสินค้า: ' . e($product['name_th']) : '<i class="bi bi-plus-circle text-success me-2"></i> เพิ่มสินค้าใหม่' ?>
        </h3>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white p-4">
    <form method="POST" action="" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- Left Column: Primary Details -->
            <div class="col-lg-8">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">ข้อมูลผลิตภัณฑ์</h5>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="sku" class="form-label small fw-semibold">รหัส SKU <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sku" name="sku" value="<?= e($_POST['sku'] ?? ($product['sku'] ?? 'MV-' . rand(100, 999))) ?>" required>
                    </div>

                    <div class="col-md-8">
                        <label for="name_th" class="form-label small fw-semibold">ชื่อสินค้า (ภาษาไทย) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name_th" name="name_th" value="<?= e($_POST['name_th'] ?? ($product['name_th'] ?? '')) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="name_en" class="form-label small fw-semibold">ชื่อสินค้า (ภาษาอังกฤษ)</label>
                        <input type="text" class="form-control" id="name_en" name="name_en" value="<?= e($_POST['name_en'] ?? ($product['name_en'] ?? '')) ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="slug" class="form-label small fw-semibold">URL Slug (เว้นว่างเพื่อสร้างอัตโนมัติ)</label>
                        <input type="text" class="form-control" id="slug" name="slug" value="<?= e($_POST['slug'] ?? ($product['slug'] ?? '')) ?>" placeholder="เช่น daily-one-multivitamin">
                    </div>

                    <div class="col-md-6">
                        <label for="category_id" class="form-label small fw-semibold">หมวดหมู่สินค้า <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">เลือกหมวดหมู่...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ((int)($_POST['category_id'] ?? ($product['category_id'] ?? 0)) === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name_th']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="brand" class="form-label small fw-semibold">แบรนด์</label>
                        <input type="text" class="form-control" id="brand" name="brand" value="<?= e($_POST['brand'] ?? ($product['brand'] ?? 'Mochi Vita')) ?>">
                    </div>

                    <div class="col-12">
                        <label for="size" class="form-label small fw-semibold">ขนาดบรรจุ</label>
                        <input type="text" class="form-control" id="size" name="size" value="<?= e($_POST['size'] ?? ($product['size'] ?? '60 เม็ด')) ?>" placeholder="เช่น 60 แคปซูล, 30 ซอง, 500 กรัม">
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label small fw-semibold">คำอธิบายผลิตภัณฑ์</label>
                        <textarea class="form-control" id="description" name="description" rows="5" placeholder="สรรพคุณ ส่วนประกอบ วิธีรับประทาน"><?= e($_POST['description'] ?? ($product['description'] ?? '')) ?></textarea>
                        <small class="text-muted" style="font-size: 0.75rem;">* ตามกฎหมาย ห้ามใส่ข้อความโอ้อวดสรรพคุณทางการแพทย์ในการรักษาโรค</small>
                    </div>
                </div>
            </div>

            <!-- Right Column: Pricing, Stock, Image & Status -->
            <div class="col-lg-4">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">ราคาและคลังสินค้า</h5>

                <div class="mb-3">
                    <label for="price" class="form-label small fw-semibold">ราคาขายปกติ (บาท) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?= e($_POST['price'] ?? ($product['price'] ?? '')) ?>" required min="1">
                </div>

                <div class="mb-3">
                    <label for="compare_at_price" class="form-label small fw-semibold">ราคาเดิมก่อนลด (Compare at price)</label>
                    <input type="number" step="0.01" class="form-control" id="compare_at_price" name="compare_at_price" value="<?= e($_POST['compare_at_price'] ?? ($product['compare_at_price'] ?? '')) ?>" placeholder="เช่น 590">
                </div>

                <div class="mb-3">
                    <label for="stock_qty" class="form-label small fw-semibold">จำนวนสต็อกสินค้า (ชิ้น)</label>
                    <input type="number" class="form-control" id="stock_qty" name="stock_qty" value="<?= e($_POST['stock_qty'] ?? ($product['stock_qty'] ?? 50)) ?>" required min="0">
                </div>

                <div class="mb-4">
                    <label for="low_stock_threshold" class="form-label small fw-semibold">เกณฑ์แจ้งเตือนสต็อกต่ำ</label>
                    <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold" value="<?= e($_POST['low_stock_threshold'] ?? ($product['low_stock_threshold'] ?? 10)) ?>" required min="1">
                    <small class="text-muted" style="font-size: 0.75rem;">แจ้งเตือนเมื่อสต็อกต่ำกว่าหรือเท่ากับค่านี้</small>
                </div>

                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">รูปภาพสินค้า</h5>

                <div class="mb-3 text-center">
                    <?php if (!empty($product['image'])): ?>
                        <img src="<?= get_product_image_url($product['image']) ?>" alt="" class="img-thumbnail mb-2" style="max-height: 140px; object-fit: contain;">
                    <?php endif; ?>
                    <input type="file" class="form-control form-control-sm" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
                    <small class="text-muted d-block text-start mt-1" style="font-size: 0.75rem;">รองรับ jpg, jpeg, png, webp (ขนาด ≤ 2MB)</small>
                </div>

                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">การมองเห็นและการแนะนำ</h5>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (!isset($product) || $product['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="is_active">เปิดการขายสินค้านี้ (Active)</label>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" <?= (!empty($product['is_featured'])) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="is_featured">ปักหมุดเป็นสินค้าแนะนำ (Featured)</label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg rounded-pill fw-semibold shadow-sm">
                        <?= ($id > 0) ? '<i class="bi bi-save me-1"></i> บันทึกการแก้ไข' : '<i class="bi bi-plus-lg me-1"></i> บันทึกเพิ่มสินค้า' ?>
                    </button>
                    <a href="<?= url('admin/products.php') ?>" class="btn btn-outline-secondary rounded-pill">
                        ยกเลิก
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
