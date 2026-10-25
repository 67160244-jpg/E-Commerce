<?php
/**
 * Mochi Vita - Production Health Check
 * -----------------------------------------------------------------------------
 * ตรวจสอบความพร้อมของระบบและการเชื่อมต่อฐานข้อมูล
 * ส่งคืน JSON status: "ok" หรือ "error" โดยไม่มีการเปิดเผยข้อมูลลับใด ๆ
 * -----------------------------------------------------------------------------
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // ตรวจสอบความพร้อมของตารางหลัก
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $productCount = (int)$stmt->fetchColumn();

    http_response_code(200);
    echo json_encode([
        'status'    => 'ok',
        'service'   => STORE_NAME,
        'products'  => $productCount,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;

} catch (\Throwable $e) {
    if (defined('APP_ENV') && APP_ENV === 'production') {
        error_log('Health Check Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'status'    => 'error',
        'message'   => 'Service temporarily unavailable',
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
