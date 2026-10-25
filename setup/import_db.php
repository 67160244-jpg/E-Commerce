<?php
// บังคับให้รันผ่าน PHP CLI เท่านั้น เพื่อความปลอดภัย ป้องกันการเรียกผ่านเว็บ
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: สคริปต์นี้สามารถทำงานผ่าน PHP CLI เท่านั้น");
}

require_once __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Importing schema.sql...\n";
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($schema);
    echo "Schema imported successfully.\n";

    echo "Importing seed.sql...\n";
    $pdo->exec("USE " . DB_NAME);
    $seed = file_get_contents(__DIR__ . '/../database/seed.sql');
    $pdo->exec($seed);
    echo "Seed imported successfully.\n";

    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    echo "Products: {$productCount}, Users: {$userCount}, Orders: {$orderCount}\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
