<?php
/**
 * Mochi Vita - General Helpers
 * ฟังก์ชันช่วยเหลือส่วนกลาง จัดรูปแบบตัวเลข ข้อความ และระบบ Flash Message
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ป้องกัน XSS ด้วย htmlspecialchars
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * จัดรูปแบบราคาเงินบาท เช่น 1,290.00 บาท หรือ ฿ 1,290.00
 * @param float|int|string|null $amount
 * @param bool $showSymbol
 * @return string
 */
function format_price($amount, bool $showSymbol = true): string {
    $num = is_numeric($amount) ? (float)$amount : 0.0;
    $formatted = number_format($num, 2);
    return $showSymbol ? (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '฿') . ' ' . $formatted : $formatted;
}

/**
 * จัดรูปแบบวันที่เวลาภาษาไทย
 * @param string|null $datetime
 * @param bool $includeTime
 * @return string
 */
function format_date_th(?string $datetime, bool $includeTime = true): string {
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '-';

    $monthsTh = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];

    $day = date('j', $timestamp);
    $month = $monthsTh[(int)date('n', $timestamp)];
    $year = (int)date('Y', $timestamp) + 543; // ปี พ.ศ.

    if ($includeTime) {
        $time = date('H:i', $timestamp) . ' น.';
        return "{$day} {$month} {$year} {$time}";
    }
    return "{$day} {$month} {$year}";
}

/**
 * สร้าง Flash Message บันทึกลง Session
 * @param string $type ('success', 'danger', 'warning', 'info')
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * แสดง Flash Message เป็น Bootstrap Alert
 * @return string
 */
function display_flash(): string {
    if (!isset($_SESSION['flash'])) {
        return '';
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');

    return "
    <div class=\"alert alert-{$type} alert-dismissible fade show my-3 shadow-sm\" role=\"alert\">
        {$message}
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
    </div>";
}

/**
 * สร้าง URL สัมบูรณ์จาก Base URL
 * @param string $path
 * @return string
 */
function url(string $path = ''): string {
    $base = defined('BASE_URL') ? BASE_URL : '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * สลับเปลี่ยนหน้า (Redirect)
 * @param string $path
 */
function redirect(string $path): void {
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        header("Location: {$path}");
    } else {
        header("Location: " . url($path));
    }
    exit;
}

/**
 * แปลงข้อความเป็น Slug สำหรับ URL ที่ปลอดภัย
 * @param string $string
 * @return string
 */
function slugify(string $string): string {
    // ลบช่องว่างหัวท้าย
    $string = trim($string);
    // แปลงตัวอักษรพิมพ์ใหญ่เป็นพิมพ์เล็ก
    $string = mb_strtolower($string, 'UTF-8');
    // แทนที่วรรคตอนและช่องว่างด้วยขีดกลาง
    $string = preg_replace('/[^\p{L}\p{N}]+/u', '-', $string);
    return trim($string, '-');
}

/**
 * แสดงสถานะออเดอร์เป็น Badge พร้อมสีตามข้อกำหนด
 * @param string $status
 * @return string
 */
function get_order_status_badge(string $status): string {
    $badges = [
        'pending'    => ['label' => 'รอดำเนินการ', 'class' => 'bg-warning text-dark'],
        'paid'       => ['label' => 'ชำระเงินแล้ว', 'class' => 'bg-info text-dark'],
        'processing' => ['label' => 'กำลังเตรียมจัดส่ง', 'class' => 'bg-primary text-white'],
        'shipped'    => ['label' => 'จัดส่งแล้ว', 'class' => 'bg-indigo text-white', 'style' => 'background-color: #6610f2; color: #fff;'],
        'delivered'  => ['label' => 'ได้รับสินค้าแล้ว', 'class' => 'bg-success text-white'],
        'cancelled'  => ['label' => 'ยกเลิกแล้ว', 'class' => 'bg-danger text-white']
    ];

    $cfg = $badges[$status] ?? ['label' => $status, 'class' => 'bg-secondary text-white'];
    $style = isset($cfg['style']) ? " style=\"{$cfg['style']}\"" : '';
    return "<span class=\"badge {$cfg['class']}\"{$style}>" . htmlspecialchars($cfg['label']) . "</span>";
}

/**
 * ส่งคืน URL รูปภาพสินค้า พร้อมระบบ Fallback อัจฉริยะ (รองรับทั้ง SVG และ JPG)
 * @param string|null $imagePath
 * @return string
 */
function get_product_image_url(?string $imagePath): string {
    if (!empty($imagePath)) {
        $cleanPath = ltrim($imagePath, '/\\');
        $fullPath = defined('APP_ROOT') ? APP_ROOT . '/' . $cleanPath : '';
        if (!empty($fullPath) && file_exists($fullPath)) {
            return url($cleanPath);
        }

        // ลองสลับนามสกุล .jpg <-> .svg
        if (str_ends_with($cleanPath, '.jpg')) {
            $svgPath = substr($cleanPath, 0, -4) . '.svg';
            if (defined('APP_ROOT') && file_exists(APP_ROOT . '/' . $svgPath)) {
                return url($svgPath);
            }
        } elseif (str_ends_with($cleanPath, '.svg')) {
            $jpgPath = substr($cleanPath, 0, -4) . '.jpg';
            if (defined('APP_ROOT') && file_exists(APP_ROOT . '/' . $jpgPath)) {
                return url($jpgPath);
            }
        }
    }

    if (defined('APP_ROOT') && file_exists(APP_ROOT . '/assets/products/placeholder.svg')) {
        return url('assets/products/placeholder.svg');
    }
    return url('assets/products/placeholder.jpg');
}

/**
 * แสดงชื่อช่องทางการชำระเงินภาษาไทย
 * @param string $method
 * @return string
 */
function get_payment_method_label(string $method): string {
    $labels = [
        'promptpay'   => 'พร้อมเพย์ (PromptPay QR)',
        'credit_card' => 'บัตรเครดิต / เดบิต (Credit/Debit Card)',
        'cod'         => 'เก็บเงินปลายทาง (Cash on Delivery)'
    ];
    return $labels[$method] ?? $method;
}
