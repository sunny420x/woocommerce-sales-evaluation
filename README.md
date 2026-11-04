# Sales Evaluation For WooCommerce

ปลั๊กอิน WordPress สำหรับวิเคราะห์ยอดขายและที่มาของลูกค้า (referral source) ใน WooCommerce พร้อมแสดงสัดส่วนคำสั่งซื้อจากแต่ละช่องทาง

## ความสามารถ

- **Dashboard** แสดงยอดปิดการขายจาก Google, LINE และ Facebook พร้อมสรุปภาพรวมยอดขายเดือนปัจจุบัน และคำสั่งซื้อล่าสุด
- **เมนูรายงานยอด (Sales Report)**
  - เลือกช่วงวันที่เริ่มต้นและสิ้นสุดได้
  - ดึงคำสั่งซื้อ WooCommerce ออกมาแสดงในตาราง: สั่งซื้อเมื่อ, คำสั่งซื้อ, ชื่อลูกค้า, รายการสินค้า, สถานะ, ยอดทั้งหมด
  - สรุปภาพรวมการขาย: Gross Sale (completed + processing + on-hold) และ Net Sale (Gross หักยอดคืนเงิน)
  - ออกรายงาน (พิมพ์หน้า) ด้วยปุ่มในหน้าเว็บ
  - Export CSV รองรับภาษาไทยใน Excel (พร้อมสรุป Gross Sale และ Net Sale ท้ายไฟล์)
- **หน้าแยกตามช่องทาง**: Google, Bing, ChatGPT, LINE, Facebook แสดงจำนวนคำสั่งซื้อ ยอดรวม ยอดที่ปิดการขายสำเร็จ และสัดส่วนเทียบกับคำสั่งซื้อทั้งหมด

## ความต้องการของระบบ

- WordPress
- WooCommerce (คำสั่งซื้อแบบ `shop_order` ใน `wp_posts`)
- สิทธิ์ผู้ดูแลระบบ (`manage_options`)

## การติดตั้ง

1. ดาวน์โหลดโค้ดจาก GitHub เป็นไฟล์ ZIP
2. อัปโหลดที่ `/wp-admin/plugin-install.php` แล้วกด Activate
3. เปิดเมนู **Sales Evaluation** ในแถบด้านข้างของ wp-admin

รองรับการอัปเดตผ่าน [Git Updater](https://git-updater.com/) (ระบุ `GitHub Plugin URI` และ `Primary Branch: master` ไว้ในไฟล์ปลั๊กอินแล้ว)

## วิธีนับที่มาของลูกค้า

ปลั๊กอินค้นหาคำสำคัญของแต่ละช่องทาง (เช่น `google`, `facebook`) ใน meta key และ meta value ของคำสั่งซื้อ ถ้าพบและค่าไม่ว่าง จะนับเป็นคำสั่งซื้อจากช่องทางนั้น

## ผู้พัฒนา

Jirakit Pawnsakunrungrot — [LinkedIn](https://www.linkedin.com/in/sunny-jirakit)
