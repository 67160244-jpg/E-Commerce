<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
defined('APP_ROOT') or define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

// บังคับสิทธิ์แอดมินทุกหน้า
require_admin();

$currentUser = current_user();
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>แผงควบคุมระบบ | <?= STORE_NAME ?></title>
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= url('assets/css/custom.css') ?>">
    <style>
        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
        }
        .admin-nav-link:hover, .admin-nav-link.active {
            background-color: #ecfdf5;
            color: #059669;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light">

<!-- Top Header Navbar -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-2 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand navbar-brand-mochi" href="<?= url('admin/index.php') ?>">
            <i class="bi bi-capsule-pill text-success fs-3"></i>
            <span><?= STORE_NAME ?> <span class="badge bg-danger fs-6 rounded-pill fw-normal">Admin</span></span>
        </a>

        <div class="d-flex align-items-center gap-3 ms-auto">
            <a href="<?= url('public/index.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3" target="_blank">
                <i class="bi bi-box-arrow-up-right me-1"></i> ดูหน้าร้านค้า
            </a>

            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle btn-sm rounded-pill px-3 border" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle text-danger me-1"></i> <?= e($currentUser['name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li class="dropdown-header text-muted"><?= e($currentUser['email']) ?></li>
                    <li><a class="dropdown-item" href="<?= url('public/profile.php') ?>"><i class="bi bi-person me-2"></i> ข้อมูลส่วนตัว</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= url('public/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <div class="row g-4">
        <!-- Sidebar Navigation -->
        <div class="col-lg-2 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 sticky-top" style="top: 80px;">
                <div class="small text-uppercase text-muted fw-bold mb-2 px-2">เมนูจัดการระบบ</div>
                <nav class="nav flex-column">
                    <a class="admin-nav-link <?= ($currentFile === 'index.php') ? 'active' : '' ?>" href="<?= url('admin/index.php') ?>">
                        <i class="bi bi-speedometer2"></i> แดชบอร์ดสรุปผล
                    </a>
                    <a class="admin-nav-link <?= ($currentFile === 'orders.php' || $currentFile === 'order-detail.php') ? 'active' : '' ?>" href="<?= url('admin/orders.php') ?>">
                        <i class="bi bi-receipt"></i> จัดการคำสั่งซื้อ
                    </a>
                    <a class="admin-nav-link <?= ($currentFile === 'products.php' || $currentFile === 'product-form.php') ? 'active' : '' ?>" href="<?= url('admin/products.php') ?>">
                        <i class="bi bi-box-seam"></i> จัดการคลังสินค้า
                    </a>
                    <a class="admin-nav-link <?= ($currentFile === 'categories.php') ? 'active' : '' ?>" href="<?= url('admin/categories.php') ?>">
                        <i class="bi bi-tags"></i> จัดการหมวดหมู่
                    </a>
                </nav>
            </div>
        </div>

        <!-- Admin Content Area -->
        <div class="col-lg-10 col-md-9">
            <?= display_flash() ?>
