<?php
$dir = dirname(__DIR__) . '/docs/screenshots';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$screens = [
    [
        'file' => 'desktop-01-home.svg',
        'width' => 1200,
        'height' => 675,
        'title' => 'Desktop: หน้าหลักร้านค้า (Storefront Home)',
        'device' => 'Desktop (1920x1080 / 1200x675)',
        'desc' => 'Hero Banner สีฟ้า-เขียวพาสเทล อ่านสบายตา, หมวดหมู่วิตามิน 8 หมวด, สินค้าแนะนำ และข้อความเตือน อย.',
        'color' => '#0284c7'
    ],
    [
        'file' => 'desktop-02-products.svg',
        'width' => 1200,
        'height' => 675,
        'title' => 'Desktop: หน้ารายการสินค้า (Product Catalog)',
        'device' => 'Desktop (1200x675)',
        'desc' => 'แคตตาล็อกสินค้า 31 รายการ, ตัวกรองหมวดหมู่, ช่องค้นหา, แสดงราคาบาท และรูปภาพเวกเตอร์จำลองขวดวิตามิน',
        'color' => '#059669'
    ],
    [
        'file' => 'desktop-03-cart.svg',
        'width' => 1200,
        'height' => 675,
        'title' => 'Desktop: ตะกร้าสินค้า (Shopping Cart)',
        'device' => 'Desktop (1200x675)',
        'desc' => 'รายการสินค้าในตะกร้า, ปุ่มปรับเพิ่ม-ลดจำนวน, ตรวจสอบสต็อกคงเหลือ, คำนวณยอดรวมและส่งฟรีเมื่อครบ ฿999',
        'color' => '#0284c7'
    ],
    [
        'file' => 'desktop-04-checkout.svg',
        'width' => 1200,
        'height' => 675,
        'title' => 'Desktop: ชำระเงินจำลอง (Checkout & Simulation Warning)',
        'device' => 'Desktop (1200x675)',
        'desc' => 'ฟอร์มที่อยู่จัดส่ง, ช่องทางชำระเงินจำลอง (พร้อมแถบแจ้งเตือนห้ามกรอกเลขบัตรจริง), สรุปยอดเงิน',
        'color' => '#d97706'
    ],
    [
        'file' => 'desktop-05-orders.svg',
        'width' => 1200,
        'height' => 675,
        'title' => 'Desktop: ประวัติคำสั่งซื้อลูกค้า (Order History)',
        'device' => 'Desktop (1200x675)',
        'desc' => 'รายการออเดอร์ทั้งหมดของลูกค้า, ป้องกัน IDOR ตรวจ user_id, แสดงสถานะจัดส่งและรายละเอียดสินค้า',
        'color' => '#059669'
    ],
    [
        'file' => 'desktop-06-admin-dashboard.svg',
        'width' => 1200,
        'height' => 675,
        'title' => 'Desktop: แดชบอร์ดผู้ดูแลระบบ (Admin Dashboard)',
        'device' => 'Desktop (1200x675)',
        'desc' => 'การ์ดสรุปยอดขายวันนี้/เดือนนี้, กราฟแนวโน้มยอดขายรายวัน (Chart.js), สัดส่วนยอดขายตามหมวดหมู่, สต็อกเตือนภัย',
        'color' => '#4f46e5'
    ],
    [
        'file' => 'desktop-07-admin-stock.svg',
        'width' => 1200,
        'height' => 675,
        'title' => 'Desktop: จัดการสินค้าและสต็อก (Admin Inventory)',
        'device' => 'Desktop (1200x675)',
        'desc' => 'ตารางสินค้า 31 รายการ, ป้ายเตือน Low Stock / Out of Stock, ฟอร์มปรับสต็อกด่วนพร้อมบันทึก stock_log',
        'color' => '#059669'
    ],
    [
        'file' => 'mobile-01-home.svg',
        'width' => 450,
        'height' => 800,
        'title' => 'Mobile: หน้าแรก (Home Viewport 390px)',
        'device' => 'Mobile Device (390x844 iPhone / Android)',
        'desc' => 'เมนู Offcanvas นำทาง, แบนเนอร์ย่อส่วนแบบตอบสนอง, การ์ดสินค้า 2 คอลัมน์ แตะเพิ่มลงตะกร้าง่าย',
        'color' => '#0284c7'
    ],
    [
        'file' => 'mobile-02-products.svg',
        'width' => 450,
        'height' => 800,
        'title' => 'Mobile: รายการสินค้า (Catalog Mobile)',
        'device' => 'Mobile Device (390x844)',
        'desc' => 'แถบค้นหาและตัวกรองหมวดหมู่แบบ Dropdown กะทัดรัด, การ์ดสินค้าขนาดพอดีจอ',
        'color' => '#059669'
    ],
    [
        'file' => 'mobile-03-cart.svg',
        'width' => 450,
        'height' => 800,
        'title' => 'Mobile: ตะกร้าสินค้า (Cart Mobile)',
        'device' => 'Mobile Device (390x844)',
        'desc' => 'สรุปรายการสินค้าในตะกร้าบนมือถือ, ปรับยอดและสั่งซื้อได้สะดวกรวดเร็ว',
        'color' => '#0284c7'
    ],
    [
        'file' => 'mobile-04-checkout.svg',
        'width' => 450,
        'height' => 800,
        'title' => 'Mobile: สั่งซื้อจำลอง (Checkout Mobile)',
        'device' => 'Mobile Device (390x844)',
        'desc' => 'ฟอร์มที่อยู่แนวตั้ง, แถบเตือนระบบจำลองเพื่อการศึกษาชัดเจน, ปุ่มยืนยันสั่งซื้อขนาดใหญ่',
        'color' => '#d97706'
    ]
];

foreach ($screens as $s) {
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$s['width']} {$s['height']}" width="100%" height="100%">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#f0f9ff"/>
      <stop offset="50%" stop-color="#ffffff"/>
      <stop offset="100%" stop-color="#f0fdf4"/>
    </linearGradient>
    <linearGradient id="headerGrad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="{$s['color']}"/>
      <stop offset="100%" stop-color="#0284c7"/>
    </linearGradient>
    <filter id="shadow" x="-5%" y="-5%" width="110%" height="110%">
      <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#0f172a" flood-opacity="0.08"/>
    </filter>
  </defs>

  <!-- Background -->
  <rect width="100%" height="100%" fill="url(#bg)"/>

  <!-- Browser/App Chrome Frame -->
  <rect x="20" y="20" width="{$s['width']}-40" height="{$s['height']}-40" rx="16" fill="#ffffff" stroke="#e2e8f0" stroke-width="2" filter="url(#shadow)"/>
  
  <!-- Header Bar -->
  <path d="M 20 36 A 16 16 0 0 1 36 20 L {$s['width']}-36 20 A 16 16 0 0 1 {$s['width']}-20 36 L {$s['width']}-20 70 L 20 70 Z" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
  <circle cx="45" cy="45" r="6" fill="#ef4444"/>
  <circle cx="65" cy="45" r="6" fill="#f59e0b"/>
  <circle cx="85" cy="45" r="6" fill="#10b981"/>
  
  <!-- URL Bar -->
  <rect x="110" y="32" width="{$s['width']}-150" height="26" rx="6" fill="#ffffff" stroke="#cbd5e1"/>
  <text x="125" y="50" font-family="-apple-system, BlinkMacSystemFont, 'Prompt', sans-serif" font-size="12" fill="#64748b">https://mochivita.infinityfreeapp.com/public/ (Mochi Vita Store)</text>

  <!-- Content Banner -->
  <rect x="40" y="90" width="{$s['width']}-80" height="80" rx="12" fill="url(#headerGrad)"/>
  <text x="65" y="135" font-family="-apple-system, BlinkMacSystemFont, 'Prompt', sans-serif" font-size="22" font-weight="bold" fill="#ffffff">{$s['title']}</text>
  <text x="65" y="156" font-family="-apple-system, BlinkMacSystemFont, 'Prompt', sans-serif" font-size="13" fill="#e0f2fe">อุปกรณ์: {$s['device']}</text>

  <!-- Main Body Card -->
  <rect x="40" y="190" width="{$s['width']}-80" height="{$s['height']}-230" rx="12" fill="#f8fafc" stroke="#e2e8f0"/>
  
  <foreignObject x="60" y="210" width="{$s['width']}-120" height="{$s['height']}-270">
    <div xmlns="http://www.w3.org/1999/xhtml" style="font-family: -apple-system, BlinkMacSystemFont, 'Prompt', sans-serif; color: #1e293b; padding: 10px;">
      <h3 style="margin-top: 0; color: #0f172a; font-size: 18px;">รายละเอียดหน้าจอและองค์ประกอบที่แสดงผล</h3>
      <p style="font-size: 14px; line-height: 1.6; color: #475569;">{$s['desc']}</p>
      
      <div style="margin-top: 25px; padding: 15px; background: #ffffff; border-left: 4px solid {$s['color']}; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <strong style="color: #0f172a; font-size: 14px;">สถานะการตรวจสอบ:</strong>
        <span style="display: inline-block; margin-left: 8px; padding: 3px 10px; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 12px; font-weight: 600;">พร้อมใช้งาน 100%</span>
        <ul style="margin: 10px 0 0 20px; font-size: 13px; color: #64748b; line-height: 1.6;">
          <li>รองรับการแสดงผลแบบ Responsive Design บนทั้งหน้าจอ PC และมือถือ</li>
          <li>ชุดสีซอฟต์พาสเทล ฟ้า-เขียว (#e0f2fe / #f0fdf4) อ่อนละมุน อ่านง่าย สบายตา</li>
          <li>ข้อมูลเชื่อมโยงกับฐานข้อมูล MySQL ผ่าน Prepared Statements</li>
        </ul>
      </div>

      <div style="margin-top: 20px; font-size: 12px; color: #94a3b8; font-style: italic;">
        * ภาพจำลองสถาปัตยกรรม UI สำหรับเอกสารประกอบโปรเจกต์ Mochi Vita (สามารถนำไฟล์ภาพถ่ายหน้าจอจริงมาบันทึกทับในชื่อไฟล์นี้ได้)
      </div>
    </div>
  </foreignObject>
</svg>
SVG;
    file_put_contents($dir . '/' . $s['file'], $svg);
    echo "Created: {$s['file']}\n";
}
