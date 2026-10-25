<?php
/**
 * Mochi Vita - Automated Verification Test Suite
 * ทดสอบตรรกะสำคัญของระบบ: Database, Auth, Catalog, Cart/Checkout Transaction, Stock Log
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

echo "=== Mochi Vita Automated System Tests ===\n\n";

$pdo = get_db_connection();
$testsPassed = 0;
$totalTests = 0;

function assert_test($description, $condition) {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $testsPassed++;
    } else {
        echo " [FAIL] {$description}\n";
    }
}

// 1. ตรวจสอบการเชื่อมต่อฐานข้อมูล
$stmt = $pdo->query("SELECT DATABASE()");
$dbName = $stmt->fetchColumn();
assert_test("Database connected (mochi_vita)", $dbName === 'mochi_vita');

// 2. ตรวจสอบบัญชีแอดมินและการแฮชรหัสผ่าน
$adminStmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@mochivita.test' LIMIT 1");
$adminStmt->execute();
$adminUser = $adminStmt->fetch();
assert_test("Admin user exists", !empty($adminUser));
assert_test("Admin password verify (Admin@1234)", $adminUser && password_verify('Admin@1234', $adminUser['password_hash']));

// 3. ตรวจสอบบัญชีลูกค้าตัวอย่าง
$custStmt = $pdo->prepare("SELECT * FROM users WHERE email = 'customer1@example.com' LIMIT 1");
$custStmt->execute();
$custUser = $custStmt->fetch();
assert_test("Customer user exists", !empty($custUser));
assert_test("Customer password verify (Demo@1234)", $custUser && password_verify('Demo@1234', $custUser['password_hash']));

// 4. ตรวจสอบข้อมูลสินค้าและหมวดหมู่
$catCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
assert_test("Categories count >= 8 (actual: {$catCount})", $catCount >= 8);

$prodCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
assert_test("Products count >= 31 (actual: {$prodCount})", $prodCount >= 31);

// 5. ทดสอบคำนวณราคาและค่าจัดส่ง
$freeShipSubtotal = 1200.00;
$fee1 = ($freeShipSubtotal >= FREE_SHIPPING_THRESHOLD) ? 0.0 : DEFAULT_SHIPPING_FEE;
assert_test("Free shipping threshold (>= 999 is 0)", $fee1 === 0.0);

$paidShipSubtotal = 500.00;
$fee2 = ($paidShipSubtotal >= FREE_SHIPPING_THRESHOLD) ? 0.0 : DEFAULT_SHIPPING_FEE;
assert_test("Standard shipping fee (< 999 is 50)", $fee2 === 50.0);

// 6. ทดสอบรูปภาพสินค้าบนดิสก์
$sampleImg = APP_ROOT . '/assets/products/daily-one-multivitamin.jpg';
assert_test("Sample product image file exists", file_exists($sampleImg));

$fallbackImg = APP_ROOT . '/assets/products/placeholder.jpg';
assert_test("Fallback placeholder image exists", file_exists($fallbackImg));

// 7. ทดสอบการจำลอง DB Transaction: สั่งซื้อ -> ตัดสต็อก -> บันทึก stock_log
$testPid = 1;
$origStock = (int)$pdo->query("SELECT stock_qty FROM products WHERE id = {$testPid}")->fetchColumn();

$pdo->beginTransaction();
$pdo->prepare("UPDATE products SET stock_qty = stock_qty - 1 WHERE id = :id")->execute([':id' => $testPid]);
$pdo->prepare("INSERT INTO stock_log (product_id, change_qty, reason, created_at) VALUES (:id, -1, 'test_order', NOW())")->execute([':id' => $testPid]);
$afterStock = (int)$pdo->query("SELECT stock_qty FROM products WHERE id = {$testPid}")->fetchColumn();

// Rollback เพื่อคืนสภาพข้อมูลเดิม
$pdo->rollBack();
$revertedStock = (int)$pdo->query("SELECT stock_qty FROM products WHERE id = {$testPid}")->fetchColumn();

assert_test("Stock deduction in transaction works", $afterStock === ($origStock - 1));
assert_test("Transaction rollback preserves data integrity", $revertedStock === $origStock);

echo "\nSummary: {$testsPassed} / {$totalTests} tests passed successfully.\n";
