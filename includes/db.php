<?php
/**
 * Mochi Vita - Database Connection
 * การเชื่อมต่อฐานข้อมูล MySQL ผ่าน PDO
 */

defined('APP_ROOT') or define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';

/**
 * ส่งคืนอินสแตนซ์ PDO สำหรับการเชื่อมต่อฐานข้อมูล
 * @return PDO
 */
function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
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
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // ในโหมด Development แสดง Error ชัดเจน เพื่อความสะดวกของนักศึกษา
            die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
