</main>

<!-- Footer -->
<footer class="bg-white border-top mt-auto py-5">
    <div class="container">
        <!-- Legal Medical Disclaimer (ข้อความทางกฎหมายตามข้อกำหนดผลิตภัณฑ์เสริมอาหาร) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="disclaimer-box text-center">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                    <strong>คำเตือนสำหรับผู้บริโภค:</strong> "ผลิตภัณฑ์เสริมอาหารไม่ใช่ยา ไม่ได้มีไว้เพื่อวินิจฉัย รักษา หรือป้องกันโรค ควรกินอาหารหลากหลายครบ 5 หมู่ ในสัดส่วนที่เหมาะสมเป็นประจำ"
                </div>
            </div>
        </div>

        <div class="row g-4 justify-content-between">
            <div class="col-lg-4 col-md-6">
                <h5 class="fw-bold text-success mb-3">
                    <i class="bi bi-capsule-pill me-1"></i> <?= STORE_NAME ?>
                </h5>
                <p class="text-muted small">
                    <?= STORE_TAGLINE ?> เราคัดสรรวิตามินและสารอาหารมาตรฐานสูงเพื่อการดูแลสุขภาพในชีวิตประจำวัน จัดส่งทั่วไทย ปลอดภัย มั่นใจได้
                </p>
                <div class="d-flex gap-3 text-muted">
                    <span class="small"><i class="bi bi-truck me-1 text-success"></i> ส่งฟรีเมื่อช้อปครบ 999.-</span>
                    <span class="small"><i class="bi bi-shield-check me-1 text-success"></i> ของแท้ 100%</span>
                </div>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="fw-bold text-dark mb-3">หมวดหมู่ยอดนิยม</h6>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-2"><a href="<?= url('public/products.php?category=1') ?>" class="text-decoration-none text-muted">วิตามินรวม</a></li>
                    <li class="mb-2"><a href="<?= url('public/products.php?category=2') ?>" class="text-decoration-none text-muted">วิตามินซีและภูมิคุ้มกัน</a></li>
                    <li class="mb-2"><a href="<?= url('public/products.php?category=4') ?>" class="text-decoration-none text-muted">โอเมก้า-3 และน้ำมันปลา</a></li>
                    <li class="mb-2"><a href="<?= url('public/products.php?category=5') ?>" class="text-decoration-none text-muted">โพรไบโอติกและระบบย่อย</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-3 col-6">
                <h6 class="fw-bold text-dark mb-3">บริการลูกค้า</h6>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-2"><a href="<?= url('public/cart.php') ?>" class="text-decoration-none text-muted">ตะกร้าสินค้า</a></li>
                    <li class="mb-2"><a href="<?= url('public/orders.php') ?>" class="text-decoration-none text-muted">ตรวจสอบสถานะคำสั่งซื้อ</a></li>
                    <li class="mb-2"><a href="<?= url('public/addresses.php') ?>" class="text-decoration-none text-muted">สมุดที่อยู่จัดส่ง</a></li>
                    <li class="mb-2"><a href="<?= url('public/login.php') ?>" class="text-decoration-none text-muted">เข้าสู่ระบบ / สมัครสมาชิก</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold text-dark mb-3">ติดต่อร้านค้า</h6>
                <p class="small text-muted mb-2">
                    <i class="bi bi-telephone me-2 text-success"></i> 081-000-0001 (ฝ่ายบริการลูกค้า)
                </p>
                <p class="small text-muted mb-2">
                    <i class="bi bi-envelope me-2 text-success"></i> support@mochivita.test
                </p>
                <p class="small text-muted">
                    <i class="bi bi-clock me-2 text-success"></i> จันทร์ - เสาร์: 09:00 - 18:00 น.
                </p>
            </div>
        </div>

        <hr class="my-4 text-muted opacity-25">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start small text-muted">
                &copy; <?= date('Y') ?> <?= STORE_NAME ?>. All Rights Reserved. สงวนลิขสิทธิ์ตามกฎหมาย
                <div class="text-secondary small mt-1" style="font-size: 0.78rem;">
                    * เว็บไซต์นี้เป็นระบบจำลองเพื่อการศึกษาในรายวิชา E-Commerce ไม่มีการจำหน่ายสินค้าหรือตัดเงินจริง
                </div>
            </div>
            <div class="col-md-6 text-center text-md-end small text-muted mt-2 mt-md-0">
                <span class="badge bg-light text-dark border me-1"><i class="bi bi-qr-code me-1"></i> PromptPay</span>
                <span class="badge bg-light text-dark border me-1"><i class="bi bi-credit-card me-1"></i> Credit Card</span>
                <span class="badge bg-light text-dark border"><i class="bi bi-cash me-1"></i> เก็บเงินปลายทาง (COD)</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
