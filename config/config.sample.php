<?php
/**
 * Mochi Vita - Configuration Sample File
 * Copy this file to config.php and update your database credentials.
 */

// ป้องกันการเข้าถึงไฟล์โดยตรงถ้าไม่ได้เรียกผ่าน PHP
defined('APP_ROOT') or define('APP_ROOT', dirname(__DIR__));

// ตั้งค่าฐานข้อมูล Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'mochi_vita');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ตั้งค่า URL พื้นฐานของเว็บไซต์
// หากรันใน XAMPP แนะนำใช้ /E-Commerce หรือตั้งค่าตาม URL ที่ใช้งานจริง
define('BASE_URL', '/E-Commerce');

// ตั้งค่าข้อมูลร้านค้า
define('STORE_NAME', 'Mochi Vita');
define('STORE_TAGLINE', 'ร้านวิตามินและอาหารเสริมออนไลน์เพื่อสุขภาพที่ดีของคุณ');
define('CURRENCY_SYMBOL', '฿');
define('FREE_SHIPPING_THRESHOLD', 999.00);
define('DEFAULT_SHIPPING_FEE', 50.00);

// การแสดงผลข้อผิดพลาด (ปิดในโปรดักชัน)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ตั้ง Timezone ประเทศไทย
date_default_timezone_set('Asia/Bangkok');
