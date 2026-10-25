<?php
/**
 * Mochi Vita - Production Seed Generator (CLI Only)
 * -----------------------------------------------------------------------------
 * สคริปต์สำหรับแปลง database/seed.sql ให้เป็น database/production_seed.local.sql
 * โดยแทนที่ CHANGE_ME ด้วย Password Hash จริง สำหรับนำไป Import บนโฮสต์จริงผ่าน phpMyAdmin
 *
 * การใช้งานผ่าน CLI:
 *   php tools/make_production_seed.php
 * หรือระบุผ่าน Environment Variable:
 *   $env:ADMIN_PASSWORD="YourStrongPassword123!"; php tools/make_production_seed.php
 * -----------------------------------------------------------------------------
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access Denied: This script can only be run from the command line (CLI).\n");
}

echo "========================================================\n";
echo " Mochi Vita - Production Seed SQL Generator\n";
echo "========================================================\n\n";

$seedFile = dirname(__DIR__) . '/database/seed.sql';
$outputFile = dirname(__DIR__) . '/database/production_seed.local.sql';
$usersCsv = dirname(__DIR__) . '/data/users.csv';

if (!file_exists($seedFile)) {
    die("Error: seed.sql not found at {$seedFile}\n");
}

// 1. รับรหัสผ่าน Admin
$adminPassword = getenv('ADMIN_PASSWORD');
if (!$adminPassword && isset($argv[1]) && strpos($argv[1], '--admin-pass=') === 0) {
    $adminPassword = substr($argv[1], strlen('--admin-pass='));
}

if (!$adminPassword) {
    echo "กรุณากำหนดรหัสผ่านสำหรับบัญชี Admin (admin@mochivita.test):\n";
    echo "> ";
    $adminPassword = trim(fgets(STDIN));
}

if (empty($adminPassword)) {
    // หากไม่ได้ระบุ ให้สุ่มรหัสผ่านที่ปลอดภัยอย่างน้อย 16 ตัวอักษร
    $adminPassword = bin2hex(random_bytes(8)) . '!Mv26';
    echo "[!] ไม่ได้ระบุรหัสผ่าน ระบบได้สุ่มรหัสผ่านแอดมินชั่วคราวให้:\n";
    echo "    Admin Password: {$adminPassword}\n";
    echo "    (กรุณาบันทึกรหัสผ่านนี้ไว้เพื่อใช้เข้าสู่ระบบหลังบ้านครั้งแรก)\n\n";
} else {
    echo "[OK] รับรหัสผ่าน Admin เรียบร้อย\n\n";
}

// 2. คำนวณ Hash
$adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);
$customerPassword = 'Demo@1234';
$customerHash = password_hash($customerPassword, PASSWORD_DEFAULT);

echo "กำลังประมวลผลไฟล์ SQL...\n";
$sqlContent = file_get_contents($seedFile);

// นำ USE ออกหากมี
$sqlContent = preg_replace('/^\s*USE\s+[^;]+;\s*$/mi', '', $sqlContent);

// แปลงบรรทัด INSERT INTO users
// บรรทัดที่ 1 คือ admin: (1,'Store Owner','admin@mochivita.test','081-000-0001','admin','2026-08-01','CHANGE_ME')
$adminSearch = "(1,'Store Owner','admin@mochivita.test','081-000-0001','admin','2026-08-01','CHANGE_ME')";
$adminReplace = "(1,'Store Owner','admin@mochivita.test','081-000-0001','admin','2026-08-01','" . addslashes($adminHash) . "')";
$sqlContent = str_replace($adminSearch, $adminReplace, $sqlContent);

// บรรทัดที่เหลือคือ customers: แทนที่ CHANGE_ME ที่เหลือทั้งหมดด้วย customerHash
$sqlContent = str_replace("'CHANGE_ME'", "'" . addslashes($customerHash) . "'", $sqlContent);

// ตรวจสอบว่ายังมี CHANGE_ME หลงเหลือหรือไม่
if (strpos($sqlContent, 'CHANGE_ME') !== false) {
    die("Error: ยังมีข้อความ CHANGE_ME หลงเหลืออยู่ในไฟล์ SQL\n");
}

file_put_contents($outputFile, $sqlContent);

echo "========================================================\n";
echo "[SUCCESS] สร้างไฟล์เรียบร้อยแล้ว: database/production_seed.local.sql\n";
echo "ขนาดไฟล์: " . number_format(filesize($outputFile)) . " bytes\n";
echo "สถานะความปลอดภัย: บัญชีลูกค้ามีรหัส Demo@1234 และบัญชีแอดมินมีรหัสผ่านที่คุณตั้งไว้\n";
echo "หมายเหตุ: ไฟล์นี้อยู่ใน .gitignore จะไม่ถูกส่งขึ้น GitHub\n";
echo "========================================================\n";
