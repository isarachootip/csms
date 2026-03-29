# CSMS – Charging Station Management System
**Version:** 1.0.0 | **PHP Pure** | **Tailwind CSS** | **MySQL**

---

## โครงสร้างไฟล์

```
csms/
├── index.php              → Redirect to login/stations
├── login.php              → หน้าเข้าสู่ระบบ
├── register.php           → ลงทะเบียน + ยืนยัน OTP
├── logout.php             → ออกจากระบบ
├── stations.php           → จัดการสถานีชาร์จ (CRUD)
├── chargers.php           → จัดการเครื่องชาร์จ + Start/Stop
├── transactions.php       → รายงาน Transaction + Export CSV
├── settings.php           → ตั้งค่าค่าบริการ (kWh-Based / Free)
├── install.php            → ตัวช่วยติดตั้งฐานข้อมูล (ลบหลังใช้!)
├── otp_log.txt            → Log OTP (Dev mode)
│
├── includes/
│   ├── config.php         → ค่าคอนฟิก DB, Session, App
│   ├── db.php             → PDO Database Helper Class
│   ├── auth.php           → Authentication (login/register/OTP/logout)
│   ├── helpers.php        → Utility functions + Badge generators
│   └── layout.php         → Shared HTML layout, sidebar, nav
│
├── api/
│   ├── status.php         → GET: Real-time charger status (JSON)
│   └── connector.php      → POST: Update connector status
│
└── sql/
    └── csms_schema.sql    → Database schema + sample data
```

---

## วิธีติดตั้ง

### 1. ติดตั้งด้วย Installer
```
http://localhost/project3/csms/install.php
```
กรอก DB credentials แล้วกดติดตั้ง จากนั้น **ลบไฟล์ install.php ทันที**

### 2. ติดตั้งด้วย phpMyAdmin
1. Import `sql/csms_schema.sql`
2. แก้ไข `includes/config.php` ตามค่า DB ของคุณ

---

## Default Login
| Email | Password |
|---|---|
| admin@csms.local | Admin@1234 |

---

## Features
- ✅ ลงทะเบียน + ยืนยัน OTP (Email)
- ✅ เข้าสู่ระบบ / ออกจากระบบ
- ✅ จัดการสถานีชาร์จ (CRUD)
- ✅ จัดการเครื่องชาร์จ (CRUD) + จำลองสถานะ
- ✅ เริ่ม/หยุดการชาร์จ พร้อมคำนวณ kWh
- ✅ แสดงสถานะ Controller และ Connector แบบ Real-time
- ✅ รายงาน Transaction + Filter + Export CSV
- ✅ ตั้งค่าค่าบริการ (kWh-Based / Free Charge)
- ✅ Responsive – รองรับมือถือ
- ✅ Dark Tech UI (Navy/Yellow – Tailwind CSS)
- ✅ REST API สำหรับ OCPP integration

---

## Tech Stack
- **Backend:** PHP 8.x (Pure, No Framework)
- **Database:** MySQL 5.7+ / MariaDB
- **CSS:** Tailwind CSS v3 (CDN)
- **Icons:** Google Material Icons
- **Font:** Inter (Google Fonts)
