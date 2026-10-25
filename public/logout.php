<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';

logout_user();
set_flash('info', 'คุณได้ออกจากระบบเรียบร้อยแล้ว');
redirect('public/login.php');
