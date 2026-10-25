<?php
/**
 * Mochi Vita - Password Hasher Script
 * สคริปต์สำหรับ Hash รหัสผ่านผู้ใช้เริ่มต้นจากไฟล์ data/users.csv
 * รันเพียงครั้งเดียวหลังการ Import database/seed.sql (ผ่าน CLI เท่านั้น)
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Forbidden: สคริปต์นี้สามารถทำงานผ่าน PHP CLI เท่านั้น เพื่อความปลอดภัยของฐานข้อมูล");
}

define('APP_ROOT', dirname(__DIR__));

if (!file_exists(APP_ROOT . '/config/config.php')) {
    die("กรุณาสร้างไฟล์ config/config.php ก่อนใช้งานสคริปต์นี้");
}

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';

$csvFile = APP_ROOT . '/data/users.csv';

if (!file_exists($csvFile)) {
    die("ไม่พบไฟล์ {$csvFile}");
}

$updatedCount = 0;
$results = [];

try {
    $pdo = get_db_connection();
    
    // ตรวจสอบว่าตาราง users มีอยู่จริงหรือไม่
    $checkTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$checkTable) {
        die("ไม่พบตาราง 'users' ในฐานข้อมูล กรุณา import database/schema.sql ก่อน");
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        die("ไม่สามารถเปิดไฟล์ {$csvFile} ได้");
    }

    // ข้าม UTF-8 BOM ถ้ามี
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $headers = fgetcsv($handle);
    $idIdx = array_search('id', $headers);
    $emailIdx = array_search('email', $headers);
    $plainIdx = array_search('password_plain', $headers);
    $roleIdx = array_search('role', $headers);

    if ($plainIdx === false || ($idIdx === false && $emailIdx === false)) {
        die("โครงสร้างคอลัมน์ใน users.csv ไม่ถูกต้อง ต้องมีคอลัมน์ password_plain");
    }

    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email OR id = :id");

    while (($row = fgetcsv($handle)) !== false) {
        if (empty($row) || !isset($row[$plainIdx])) {
            continue;
        }

        $userId = $idIdx !== false ? trim($row[$idIdx]) : null;
        $email = $emailIdx !== false ? trim($row[$emailIdx]) : null;
        $plainPass = trim($row[$plainIdx]);
        $role = $roleIdx !== false ? trim($row[$roleIdx]) : 'customer';

        if (empty($plainPass)) {
            continue;
        }

        $hashed = password_hash($plainPass, PASSWORD_DEFAULT);

        $updateStmt->execute([
            ':hash' => $hashed,
            ':email' => $email,
            ':id' => $userId
        ]);

        $results[] = [
            'id' => $userId,
            'email' => $email,
            'role' => $role,
            'status' => 'Hash สำเร็จ'
        ];
        $updatedCount++;
    }

    fclose($handle);

} catch (Exception $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "========================================\n";
    echo "Mochi Vita - Password Hash Complete\n";
    echo "========================================\n";
    echo "อัปเดตรหัสผ่านสำเร็จ {$updatedCount} บัญชี\n";
    foreach ($results as $r) {
        echo "- [{$r['role']}] {$r['email']} (ID: {$r['id']})\n";
    }
    echo "\n[คำเตือนความปลอดภัย]: กรุณาลบไฟล์ setup/hash_passwords.php หรือจำกัดสิทธิ์เพื่อความปลอดภัย\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mochi Vita - แฮชรหัสผ่านผู้ใช้งาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white py-3">
                    <h4 class="mb-0">✅ อัปเดตรหัสผ่าน (Password Hash) เรียบร้อยแล้ว</h4>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted">ระบบได้อ่านรหัสผ่านเริ่มต้นจาก <code>data/users.csv</code> และเข้ารหัสด้วย <code>password_hash()</code> ลงในตาราง <code>users</code> จำนวน <strong><?= $updatedCount ?></strong> บัญชีเรียบร้อยแล้ว</p>
                    
                    <div class="alert alert-warning border-0">
                        <strong>⚠️ ข้อแนะนำด้านความปลอดภัย:</strong> เมื่อดำเนินการเสร็จสิ้นแล้ว แนะนำให้ลบไฟล์ <code>setup/hash_passwords.php</code> ออกจากเซิร์ฟเวอร์ หรือเปลี่ยนชื่อเพื่อป้องกันการถูกเรียกซ้ำ
                    </div>

                    <h5 class="mt-4 mb-3">บัญชีผู้ใช้สำหรับทดสอบ:</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>อีเมล</th>
                                    <th>สิทธิ์</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['id']) ?></td>
                                    <td><strong><?= htmlspecialchars($r['email']) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $r['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?>">
                                            <?= htmlspecialchars($r['role']) ?>
                                        </span>
                                    </td>
                                    <td class="text-success"><?= htmlspecialchars($r['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-center">
                        <a href="<?= BASE_URL ?>/public/login.php" class="btn btn-primary px-4 me-2">เข้าสู่ระบบ</a>
                        <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-outline-secondary px-4">ไปยังหน้าร้านค้า</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
