<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

$pdo = get_db_connection();

$error = '';
$editCategory = null;

// จัดการคำขอ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_valid();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $name_th = trim($_POST['name_th'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name_th)) {
            $error = 'กรุณาระบุชื่อหมวดหมู่ภาษาไทย';
        } else {
            if (empty($slug)) {
                $slug = slugify($name_th);
            }

            // ตรวจสอบ slug ซ้ำ
            $slugChk = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug AND id != :id LIMIT 1");
            $slugChk->execute([':slug' => $slug, ':id' => (int)$id]);
            if ($slugChk->fetch()) {
                $slug .= '-' . rand(10, 99);
            }

            if ($id) {
                $up = $pdo->prepare("UPDATE categories SET name_th = :name, slug = :slug, description = :desc WHERE id = :id");
                $up->execute([':name' => $name_th, ':slug' => $slug, ':desc' => $description, ':id' => $id]);
                set_flash('success', 'แก้ไขหมวดหมู่เรียบร้อยแล้ว');
            } else {
                $in = $pdo->prepare("INSERT INTO categories (name_th, slug, description) VALUES (:name, :slug, :desc)");
                $in->execute([':name' => $name_th, ':slug' => $slug, ':desc' => $description]);
                set_flash('success', 'เพิ่มหมวดหมู่ใหม่เรียบร้อยแล้ว');
            }
            redirect('admin/categories.php');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        // ตรวจสอบว่ามีสินค้าผูกอยู่หรือไม่
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :cid");
        $cnt->execute([':cid' => $id]);
        $prodCount = (int)$cnt->fetchColumn();

        if ($prodCount > 0) {
            set_flash('danger', 'ไม่สามารถลบหมวดหมู่นี้ได้ เนื่องจากมีสินค้าผูกอยู่จำนวน ' . $prodCount . ' รายการ กรุณาย้ายหรือลบสินค้าก่อน');
        } else {
            $del = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $del->execute([':id' => $id]);
            set_flash('success', 'ลบหมวดหมู่เรียบร้อยแล้ว');
        }
        redirect('admin/categories.php');
    }
}

// ตรวจสอบการแก้ไข GET edit
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editId > 0) {
    $eStmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $eStmt->execute([':id' => $editId]);
    $editCategory = $eStmt->fetch();
}

// ดึงรายการหมวดหมู่ทั้งหมดพร้อมจำนวนสินค้า
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.id ASC
")->fetchAll();

$pageTitle = 'จัดการหมวดหมู่สินค้า';
require_once APP_ROOT . '/includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1">
            <i class="bi bi-tags text-success me-2"></i> จัดการหมวดหมู่สินค้า (Categories)
        </h3>
        <p class="text-muted small mb-0">เพิ่ม แก้ไข และลบหมวดหมู่สินค้าสำหรับจัดระเบียบร้านค้า</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show small" role="alert">
        <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Form Card (Add / Edit) -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white sticky-top" style="top: 80px;">
            <h5 class="fw-bold text-dark mb-3">
                <?= $editCategory ? '<i class="bi bi-pencil-square text-success me-1"></i> แก้ไขหมวดหมู่' : '<i class="bi bi-plus-circle text-success me-1"></i> เพิ่มหมวดหมู่ใหม่' ?>
            </h5>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editCategory['id'] ?? '' ?>">

                <div class="mb-3">
                    <label for="name_th" class="form-label small fw-semibold">ชื่อหมวดหมู่ (ไทย) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name_th" name="name_th" value="<?= e($editCategory['name_th'] ?? '') ?>" placeholder="เช่น วิตามินรวม" required>
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label small fw-semibold">URL Slug (ภาษาอังกฤษ)</label>
                    <input type="text" class="form-control" id="slug" name="slug" value="<?= e($editCategory['slug'] ?? '') ?>" placeholder="เช่น multivitamin">
                    <small class="text-muted" style="font-size: 0.75rem;">เว้นว่างไว้เพื่อสร้างอัตโนมัติ</small>
                </div>

                <div class="mb-4">
                    <label for="description" class="form-label small fw-semibold">คำอธิบายหมวดหมู่</label>
                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="รายละเอียดหรือกลุ่มสารอาหารในหมวดนี้"><?= e($editCategory['description'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success rounded-pill px-4 flex-grow-1">
                        <?= $editCategory ? 'บันทึกการแก้ไข' : 'เพิ่มหมวดหมู่' ?>
                    </button>
                    <?php if ($editCategory): ?>
                        <a href="<?= url('admin/categories.php') ?>" class="btn btn-outline-secondary rounded-pill px-3">
                            ยกเลิก
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories Table List -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="small text-muted">หมวดหมู่ทั้งหมด <strong><?= count($categories) ?></strong> หมวด</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th style="width: 8%;">ID</th>
                                <th style="width: 30%;">ชื่อหมวดหมู่</th>
                                <th style="width: 25%;">Slug</th>
                                <th style="width: 15%;" class="text-center">จำนวนสินค้า</th>
                                <th style="width: 22%;" class="text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr class="<?= ($editId === (int)$cat['id']) ? 'table-success' : '' ?>">
                                    <td><?= $cat['id'] ?></td>
                                    <td>
                                        <strong class="text-dark d-block small"><?= e($cat['name_th']) ?></strong>
                                        <small class="text-muted"><?= e($cat['description'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <code class="text-secondary"><?= e($cat['slug']) ?></code>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $cat['product_count'] > 0 ? 'bg-primary' : 'bg-secondary' ?> rounded-pill px-3">
                                            <?= $cat['product_count'] ?> ชิ้น
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= url('admin/categories.php?edit=' . $cat['id']) ?>" class="btn btn-outline-primary" title="แก้ไข">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($cat['product_count'] == 0): ?>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('ยืนยันลบหมวดหมู่นี้?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="ลบหมวดหมู่">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary" disabled title="ไม่สามารถลบได้เนื่องจากมีสินค้าผูกอยู่">
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/admin-footer.php'; ?>
