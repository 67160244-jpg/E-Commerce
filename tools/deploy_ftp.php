<?php
/**
 * Mochi Vita - CLI Direct FTP Deployment Tool
 * -----------------------------------------------------------------------------
 * สคริปต์ช่วยอัปโหลดโค้ดขึ้นเซิร์ฟเวอร์ InfinityFree / ByetHost ผ่าน FTP แบบรวดเร็ว
 * โดยอ่านไฟล์ในเครื่องแล้วอัปโหลดตรงเข้าสู่ /htdocs/ ของโฮสต์
 *
 * การใช้งาน:
 *   php tools/deploy_ftp.php
 * หรือระบุผ่าน Argument:
 *   php tools/deploy_ftp.php --host=ftpupload.net --user=if0_xxxx --pass=xxxx
 * -----------------------------------------------------------------------------
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("CLI only.\n");
}

echo "========================================================\n";
echo " Mochi Vita - InfinityFree Direct FTP Deployer\n";
echo "========================================================\n\n";

$ftpServer = 'ftpupload.net';
$ftpUser = '';
$ftpPass = '';

foreach ($argv as $arg) {
    if (strpos($arg, '--host=') === 0) $ftpServer = substr($arg, 7);
    if (strpos($arg, '--user=') === 0) $ftpUser = substr($arg, 7);
    if (strpos($arg, '--pass=') === 0) $ftpPass = substr($arg, 7);
}

if (empty($ftpUser)) {
    echo "กรุณากรอก FTP Username ของ InfinityFree (เช่น if0_12345678):\n> ";
    $ftpUser = trim(fgets(STDIN));
}

if (empty($ftpPass)) {
    echo "กรุณากรอก FTP Password (รหัสผ่านโฮสต์/vPanel):\n> ";
    $ftpPass = trim(fgets(STDIN));
}

if (empty($ftpUser) || empty($ftpPass)) {
    die("Error: กรุณาระบุ Username และ Password ให้ครบถ้วน\n");
}

echo "\nกำลังเชื่อมต่อไปยัง {$ftpServer}...\n";
$connId = @ftp_connect($ftpServer, 21, 30);
if (!$connId) {
    die("Error: ไม่สามารถเชื่อมต่อกับ FTP Server: {$ftpServer} ได้ กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต\n");
}

echo "กำลังเข้าสู่ระบบด้วยผู้ใช้ {$ftpUser}...\n";
$login = @ftp_login($connId, $ftpUser, $ftpPass);
if (!$login) {
    ftp_close($connId);
    die("Error: เข้าสู่ระบบ FTP ไม่สำเร็จ กรุณาตรวจสอบ Username และ Password ให้ถูกต้อง\n");
}

ftp_pasv($connId, true);
echo "[OK] เข้าสู่ระบบ FTP สำเร็จ\n\n";

$remoteRoot = '/htdocs';
if (!@ftp_chdir($connId, $remoteRoot)) {
    echo "กำลังสร้างไดเรกทอรี {$remoteRoot}...\n";
    @ftp_mkdir($connId, $remoteRoot);
    ftp_chdir($connId, $remoteRoot);
}

$localRoot = dirname(__DIR__);
$excludePrefixes = [
    '.git',
    '.github',
    'database',
    'data',
    'setup',
    'tools',
    'docs',
    'storage'
];

$excludeExtensions = ['sql', 'csv', 'md', 'ps1', 'log', 'tmp'];

function ftp_upload_directory($connId, $localDir, $remoteDir, $excludePrefixes, $excludeExtensions, $localRoot) {
    $items = scandir($localDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $localPath = $localDir . '/' . $item;
        $relPath = ltrim(str_replace([$localRoot, '\\'], ['', '/'], $localPath), '/');

        // ตรวจสอบ Exclude Folders
        $firstPart = explode('/', $relPath)[0];
        if (in_array($firstPart, $excludePrefixes)) continue;

        // ไม่ commit config/config.php
        if ($relPath === 'config/config.php') continue;

        // ตรวจสอบ Exclude Extensions
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (in_array($ext, $excludeExtensions)) continue;

        $targetRemote = $remoteDir . '/' . $item;

        if (is_dir($localPath)) {
            if (!@ftp_chdir($connId, $targetRemote)) {
                @ftp_mkdir($connId, $targetRemote);
            }
            ftp_upload_directory($connId, $localPath, $targetRemote, $excludePrefixes, $excludeExtensions, $localRoot);
        } else {
            echo "Uploading: {$relPath}... ";
            $mode = ($ext === 'jpg' || $ext === 'png' || $ext === 'webp' || $ext === 'ico') ? FTP_BINARY : FTP_ASCII;
            if (@ftp_put($connId, $targetRemote, $localPath, $mode)) {
                echo "[OK]\n";
            } else {
                echo "[RETRY-BIN] ";
                if (@ftp_put($connId, $targetRemote, $localPath, FTP_BINARY)) {
                    echo "[OK]\n";
                } else {
                    echo "[FAILED]\n";
                }
            }
        }
    }
}

echo "กำลังเริ่มต้นอัปโหลดไฟล์ขึ้นเซิร์ฟเวอร์...\n";
ftp_upload_directory($connId, $localRoot, $remoteRoot, $excludePrefixes, $excludeExtensions, $localRoot);

ftp_close($connId);
echo "\n========================================================\n";
echo "[SUCCESS] อัปโหลดไฟล์ขึ้น InfinityFree เรียบร้อยแล้ว!\n";
echo "ขั้นตอนต่อไป: สร้าง config/config.php ใน htdocs/config/ ผ่าน File Manager บนโฮสต์\n";
echo "========================================================\n";
