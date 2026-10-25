<?php
/**
 * Mochi Vita - Root Redirect to Public Storefront
 */
define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/helpers.php';

redirect('public/index.php');
