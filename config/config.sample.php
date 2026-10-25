<?php
/**
 * Mochi Vita - Configuration Sample File
 * -----------------------------------------------------------------------------
 * คัดลอกไฟล์นี้เป็น config.php แล้วกรอกข้อมูลเชื่อมต่อฐานข้อมูลตามสภาพแวดล้อมที่ใช้งาน
 * - บน XAMPP (Local): ใช้ค่าเริ่มต้น DB_HOST = '127.0.0.1', BASE_URL = '/E-Commerce'
 * - บน InfinityFree / Web Hosting:
 *     1. นำค่ามาจากแผงควบคุม cPanel > MySQL Databases (เช่น sql123.infinityfree.com)
 *     2. ห้ามใช้ 127.0.0.1 หรือ localhost บนโฮสต์จริง เพราะ MySQL มักอยู่คนละเซิร์ฟเวอร์
 *     3. ชื่อฐานข้อมูลและ User มักมี Prefix เช่น if0_12345678_mochi_vita
 *     4. หากติดตั้งที่โฟลเดอร์ htdocs/ โดยตรง ให้ตั้ง BASE_URL = '' (ข้อความว่าง)
 *     5. ตั้ง APP_ENV = 'production' เพื่อปิดการแสดงข้อผิดพลาดบนหน้าเว็บ
 * -----------------------------------------------------------------------------
 */

defined('APP_ROOT') or define('APP_ROOT', dirname(__DIR__));

// สภาพแวดล้อม: 'development' (แสดง error ชัดเจน) หรือ 'production' (ซ่อน error และบันทึกลง log)
define('APP_ENV', 'development');

// ตั้งค่าฐานข้อมูล Database Credentials
define('DB_HOST', '127.0.0.1'); // บนโฮสต์จริง: ดูในแผงควบคุม เช่น sql123.infinityfree.com
define('DB_PORT', '3306');
define('DB_NAME', 'mochi_vita'); // บนโฮสต์จริง: ชื่อที่มี prefix เช่น if0_xxxx_mochi_vita
define('DB_USER', 'root');       // บนโฮสต์จริง: เช่น if0_xxxx
define('DB_PASS', '');           // บนโฮสต์จริง: รหัสผ่าน vPanel / Hosting Account Password
define('DB_CHARSET', 'utf8mb4');

// ตั้งค่า URL พื้นฐานของเว็บไซต์ (BASE_URL)
// - กรณี XAMPP ในโฟลเดอร์ย่อย: '/E-Commerce'
// - กรณีติดตั้งที่ Root ของโดเมนบนโฮสต์ (htdocs): '' (สตริงว่าง)
define('BASE_URL', '/E-Commerce');

// ตั้งค่าข้อมูลร้านค้า
define('STORE_NAME', 'Mochi Vita');
define('STORE_TAGLINE', 'ร้านวิตามินและอาหารเสริมออนไลน์เพื่อสุขภาพที่ดีของคุณ');
define('CURRENCY_SYMBOL', '฿');
define('FREE_SHIPPING_THRESHOLD', 999.00);
define('DEFAULT_SHIPPING_FEE', 50.00);

// การจัดการข้อผิดพลาด (Error Handling)
if (defined('APP_ENV') && APP_ENV === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $storageDir = APP_ROOT . '/storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0755, true);
    }
    ini_set('error_log', $storageDir . '/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
}

// ตั้ง Timezone ประเทศไทย
date_default_timezone_set('Asia/Bangkok');
