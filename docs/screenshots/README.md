# คำแนะนำการจัดเก็บภาพหน้าจอระบบ (System Screenshots Guide)

โฟลเดอร์นี้รวบรวมภาพหน้าจอตัวอย่างการทำงานของระบบ Mochi Vita ทั้งในมุมมองคอมพิวเตอร์ (Desktop) และมุมมองโทรศัพท์มือถือ (Mobile Viewport 390px)

---

## รายการไฟล์ภาพหน้าจอ (Screenshots Checklist)

| ชื่อไฟล์ | ส่วนการทำงาน | อุปกรณ์ / ขนาดหน้าจอ | วัตถุประสงค์ในการตรวจสอบ |
|---|---|---|---|
| `desktop-01-home.svg` | หน้าหลัก (Storefront Home) | Desktop (1200x675) | ตรวจสอบ Hero Banner โทนสีฟ้า-เขียว และหมวดหมู่ 8 หมวด |
| `desktop-02-products.svg` | แคตตาล็อกสินค้า (Catalog) | Desktop (1200x675) | ตรวจสอบการแสดงสินค้า 31 รายการ พร้อมรูปขวด 3D เวกเตอร์ |
| `desktop-03-cart.svg` | ตะกร้าสินค้า (Cart) | Desktop (1200x675) | ตรวจสอบการคำนวณยอดเงิน และยอดส่งฟรี ฿999 |
| `desktop-04-checkout.svg` | สั่งซื้อ (Checkout) | Desktop (1200x675) | ตรวจสอบแถบเตือนระบบจำลอง และฟอร์มที่อยู่จัดส่ง |
| `desktop-05-orders.svg` | ประวัติการสั่งซื้อ (Orders) | Desktop (1200x675) | ตรวจสอบการแสดงสถานะคำสั่งซื้อ และการป้องกัน IDOR |
| `desktop-06-admin-dashboard.svg` | แดชบอร์ดแอดมิน (Dashboard) | Desktop (1200x675) | ตรวจสอบการ์ด KPI ยอดขาย และกราฟแท่ง/โดนัท Chart.js |
| `desktop-07-admin-stock.svg` | จัดการสต็อก (Inventory) | Desktop (1200x675) | ตรวจสอบป้าย Low Stock และการปรับสต็อกด่วน |
| `mobile-01-home.svg` | หน้าแรกมือถือ (Mobile Home) | Mobile (390x844) | ตรวจสอบการแสดงผลบนจอเล็ก และเมนู Offcanvas |
| `mobile-02-products.svg` | แคตตาล็อกมือถือ (Mobile Catalog) | Mobile (390x844) | ตรวจสอบตัวกรองและปุ่มกดบนมือถือ |
| `mobile-03-cart.svg` | ตะกร้าสินค้ามือถือ (Mobile Cart) | Mobile (390x844) | ตรวจสอบการปรับจำนวนสินค้าบนมือถือ |
| `mobile-04-checkout.svg` | สั่งซื้อมือถือ (Mobile Checkout) | Mobile (390x844) | ตรวจสอบความง่ายในการกรอกและสั่งซื้อ |

---

### วิธีนำภาพจริงมาบันทึกทับ:
1. เปิดเบราว์เซอร์ไปที่หน้าเว็บจริง
2. กด `F12` > เปิดโหมด Responsive (`Ctrl + Shift + M`) แล้วเลือกขนาดหน้าจอ (Desktop: 1920x1080, Mobile: iPhone 12/14/15 390x844)
3. กด Capture screenshot แล้วนำไฟล์มาวางทับในโฟลเดอร์นี้ (รองรับนามสกุล `.png`, `.jpg`, `.svg`)
