<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

// ป้องกันสิทธิ์แอดมินเป็นบรรทัดแรก
require_admin();

// ป้องกัน CSRF: บังคับคำขอต้องเป็น POST + CSRF Token เท่านั้น ห้ามทำผ่าน GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    set_flash('danger', 'ไม่อนุญาตให้ดำเนินการผ่านวิธีนี้ (Method Not Allowed)');
    redirect('admin/products.php');
}

require_csrf_valid();

$pdo = get_db_connection();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, name_th FROM products WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'ไม่พบสินค้าที่ต้องการลบ');
    redirect('admin/products.php');
}

// ตรวจสอบว่าสินค้าเคยอยู่ในออเดอร์ (order_items) หรือไม่
$chk = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :pid");
$chk->execute([':pid' => $id]);
$orderCount = (int)$chk->fetchColumn();

if ($orderCount > 0) {
    // ปิดการขาย Soft Delete แทนการลบจริง เพื่อรักษาประวัติการสั่งซื้อ
    $up = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
    $up->execute([':id' => $id]);
    set_flash('warning', 'เนื่องจากสินค้า "' . e($product['name_th']) . '" เคยมีประวัติการสั่งซื้อ ' . $orderCount . ' ครั้ง ระบบจึงทำการ "ปิดการขาย (is_active=0)" แทนการลบจริง เพื่อรักษาประวัติคำสั่งซื้อเดิม');
} else {
    // ไม่เคยอยู่ในออเดอร์ สามารถลบจริงได้
    $del = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $del->execute([':id' => $id]);
    set_flash('success', 'ลบสินค้า "' . e($product['name_th']) . '" ออกจากระบบเรียบร้อยแล้ว');
}

redirect('admin/products.php');
