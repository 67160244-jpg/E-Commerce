<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

http_response_code(404);
$pageTitle = 'ไม่พบหน้าที่คุณต้องการ (404 Not Found)';
require_once APP_ROOT . '/includes/header.php';
?>

<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card border-0 shadow-sm rounded-4 p-5 bg-white">
                <div class="mb-4">
                    <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2 fw-semibold fs-6">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> 404 Not Found
                    </span>
                </div>

                <div class="display-1 fw-bold text-success mb-3" style="font-size: 5rem;">
                    4<i class="bi bi-heart-pulse-fill text-danger fs-1 align-middle mx-1"></i>4
                </div>

                <h2 class="fw-bold text-dark mb-3">ขออภัย ไม่พบหน้าที่คุณค้นหา</h2>
                <p class="text-secondary mb-4 leading-relaxed">
                    หน้าเว็บที่คุณพยายามเข้าถึงอาจถูกย้าย เปลี่ยนชื่อ หรือไม่มีอยู่อีกต่อไป<br>
                    กลับสู่หน้าแรกเพื่อเลือกชมวิตามินและผลิตภัณฑ์ดูแลสุขภาพที่คุณต้องการได้เลยครับ
                </p>

                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="<?= url('public/index.php') ?>" class="btn btn-success rounded-pill px-4 py-2 fw-semibold shadow-sm">
                        <i class="bi bi-house-door me-1"></i> กลับสู่หน้าหลัก
                    </a>
                    <a href="<?= url('public/products.php') ?>" class="btn btn-outline-success rounded-pill px-4 py-2 fw-semibold">
                        <i class="bi bi-grid me-1"></i> ดูสินค้าทั้งหมด
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
