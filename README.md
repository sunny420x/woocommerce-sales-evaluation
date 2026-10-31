# Sales Evaluation For WooCommerce

ปลั๊กอิน WordPress สำหรับวิเคราะห์ยอดขายและที่มาของลูกค้า (referral source) ใน WooCommerce พร้อมแสดงสัดส่วนคำสั่งซื้อจากแต่ละช่องทาง

## ความสามารถ

- **Dashboard** แสดงยอดปิดการขายจาก Google, LINE และ Facebook ของเดือนปัจจุบัน พร้อมเปอร์เซ็นต์
- **ตารางคำสั่งซื้อทั้งหมด** พร้อมชื่อลูกค้า รายการสินค้า สถานะ และยอดรวม กรองตามช่วงวันที่ได้
- **สรุปท้ายตาราง**
  - รวม: ผลรวมทุกคำสั่งซื้อในตาราง
  - Gross Sale: ผลรวมของคำสั่งซื้อสถานะ `wc-completed`, `wc-processing`, `wc-on-hold`
  - Net Sale: Gross Sale หักยอดคืนเงิน (refund) ของคำสั่งซื้อกลุ่มเดียวกัน
- **Export CSV** ของตารางคำสั่งซื้อ (รองรับภาษาไทยใน Excel)
- **ออกรายงาน** (พิมพ์หน้า) ด้วยปุ่มในหน้าเว็บ
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
