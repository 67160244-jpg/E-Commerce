<?php
/**
 * Mochi Vita - Automated Verification Test Suite
 * ทดสอบตรรกะสำคัญของระบบ: Database, Auth, Catalog, Cart/Checkout Transaction, Stock Log, Security Hardening (ผ่าน CLI เท่านั้น)
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: สคริปต์ทดสอบนี้สามารถทำงานผ่าน PHP CLI เท่านั้น\n");
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

echo "========================================================\n";
echo " Mochi Vita Automated System & Security Test Suite\n";
echo "========================================================\n\n";

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

// 6. ทดสอบไฟล์ภาพและ Asset ภาพเวกเตอร์จำลองสินค้า
$sampleSvg = APP_ROOT . '/assets/products/daily-one-multivitamin.svg';
assert_test("Sample product SVG vector exists", file_exists($sampleSvg));

$fallbackSvg = APP_ROOT . '/assets/products/placeholder.svg';
assert_test("Fallback placeholder SVG exists", file_exists($fallbackSvg));

$uploadDir = APP_ROOT . '/assets/uploads';
assert_test("Uploads directory exists with .htaccess script execution protection", is_dir($uploadDir) && file_exists($uploadDir . '/.htaccess'));

// 7. ตรวจสอบระบบรักษาความปลอดภัย: ตาราง login_attempts
$loginAttemptsTable = $pdo->query("SHOW TABLES LIKE 'login_attempts'")->fetchColumn();
assert_test("Security table login_attempts exists", $loginAttemptsTable === 'login_attempts');

// บันทึกและตรวจสอบ rate limit logic
$testEmail = 'security_test@mochivita.test';
$testIp = '127.0.0.1';
$pdo->prepare("INSERT INTO login_attempts (email, ip, attempted_at, success) VALUES (:e, :ip, NOW(), 0)")
    ->execute([':e' => $testEmail, ':ip' => $testIp]);
$recentFails = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = :e AND ip = :ip AND success = 0 AND attempted_at >= (NOW() - INTERVAL 10 MINUTE)");
$recentFails->execute([':e' => $testEmail, ':ip' => $testIp]);
$failCount = (int)$recentFails->fetchColumn();
assert_test("Rate limiting login attempt logging works", $failCount >= 1);
// ล้างข้อมูลทดสอบ
$pdo->prepare("DELETE FROM login_attempts WHERE email = :e")->execute([':e' => $testEmail]);

// 8. ทดสอบตาราง stock_log และ DB Transaction
$stockLogTable = $pdo->query("SHOW TABLES LIKE 'stock_log'")->fetchColumn();
assert_test("Inventory log table stock_log exists", $stockLogTable === 'stock_log');

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

echo "\n========================================================\n";
echo "Summary: {$testsPassed} / {$totalTests} tests passed successfully.\n";
echo "========================================================\n";
