<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
defined('APP_ROOT') or define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

// ดึงหมวดหมู่ทั้งหมดสำหรับแสดงในเมนู
$headerCategories = [];
try {
    $pdoHeader = get_db_connection();
    $headerCategories = $pdoHeader->query("SELECT id, name_th, slug FROM categories ORDER BY id ASC")->fetchAll();
} catch (Exception $e) {
    $headerCategories = [];
}

// นับจำนวนสินค้าในตะกร้า
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $itemQty) {
        $cartCount += (int)$itemQty;
    }
}

$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' . STORE_NAME : STORE_NAME . ' | ' . STORE_TAGLINE ?></title>
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= url('assets/css/custom.css') ?>">
</head>
<body>

<!-- Header Navigation -->
<nav class="navbar navbar-expand-lg navbar-mochi sticky-top">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand navbar-brand-mochi" href="<?= url('public/index.php') ?>">
            <i class="bi bi-capsule-pill fs-3" style="color: #059669;"></i>
            <span><strong style="color: #0c4a6e;">Mochi</strong> <span style="color: #059669; font-weight: 600;">Vita</span></span>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'public') !== false) ? 'active fw-bold' : '' ?>" style="<?= (basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'public') !== false) ? 'color: #059669 !important;' : '' ?>" href="<?= url('public/index.php') ?>">หน้าแรก</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'products.php') ? 'active fw-bold' : '' ?>" style="<?= (basename($_SERVER['PHP_SELF']) === 'products.php') ? 'color: #059669 !important;' : '' ?>" href="<?= url('public/products.php') ?>">สินค้าทั้งหมด</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        หมวดหมู่สินค้า
                    </a>
                    <ul class="dropdown-menu border-0 shadow-sm">
                        <?php foreach ($headerCategories as $cat): ?>
                            <li><a class="dropdown-item py-2" href="<?= url('public/products.php?category=' . $cat['id']) ?>"><?= e($cat['name_th']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>

            <!-- Search Form -->
            <form class="d-flex me-3 my-2 my-lg-0" action="<?= url('public/products.php') ?>" method="GET" style="max-width: 280px; width: 100%;">
                <div class="input-group">
                    <input class="form-control form-control-sm border-end-0" type="search" name="q" placeholder="ค้นหาวิตามิน, แบรนด์..." value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search" style="border-color: #bae6fd;">
                    <button class="btn btn-sm border-start-0 bg-white" type="submit" style="border-color: #bae6fd; color: #0284c7;">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            <!-- Right Actions: Cart & User -->
            <div class="d-flex align-items-center gap-2">
                <!-- Shopping Cart Button -->
                <a href="<?= url('public/cart.php') ?>" class="btn btn-sm rounded-pill px-3 py-1 me-2 position-relative shadow-sm" style="background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
                    <i class="bi bi-cart3 fs-6 me-1"></i> ตะกร้า
                    <?php if ($cartCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $cartCount ?>
                            <span class="visually-hidden">จำนวนสินค้าในตะกร้า</span>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown -->
                <?php if ($currentUser): ?>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle btn-sm rounded-pill px-3 py-1 border" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: #bae6fd !important;">
                            <i class="bi bi-person-circle me-1 text-success"></i> <?= e($currentUser['name']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm">
                            <li class="dropdown-header text-muted">
                                <?= e($currentUser['email']) ?>
                                <?php if (is_admin()): ?>
                                    <span class="badge bg-danger ms-1">ผู้ดูแลระบบ</span>
                                <?php endif; ?>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (is_admin()): ?>
                                <li>
                                    <a class="dropdown-item text-danger fw-semibold" href="<?= url('admin/index.php') ?>">
                                        <i class="bi bi-speedometer2 me-2"></i> แผงควบคุมแอดมิน
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= url('public/profile.php') ?>"><i class="bi bi-person me-2"></i> ข้อมูลส่วนตัว</a></li>
                            <li><a class="dropdown-item" href="<?= url('public/addresses.php') ?>"><i class="bi bi-geo-alt me-2"></i> สมุดที่อยู่</a></li>
                            <li><a class="dropdown-item" href="<?= url('public/orders.php') ?>"><i class="bi bi-bag-check me-2"></i> คำสั่งซื้อของฉัน</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= url('public/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= url('public/login.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1">เข้าสู่ระบบ</a>
                    <a href="<?= url('public/register.php') ?>" class="btn btn-sm rounded-pill px-3 py-1 text-white shadow-sm" style="background: linear-gradient(135deg, #059669 0%, #0284c7 100%); border: none;">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="py-3">
    <div class="container">
        <?= display_flash() ?>
    </div>
