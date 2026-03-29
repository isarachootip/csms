# CSMS Frontend Function Specification
**Charging Station Management System — Frontend UX/Function Design**
> เอกสารนี้ออกแบบโดยผู้เชี่ยวชาญด้านพัฒนาระบบ 20+ ปี เพื่อกำหนด function ฝั่ง frontend ที่ควรมีใน CSMS
> เพื่ออำนวยความสะดวกสูงสุดแก่ผู้ใช้งาน (Admin / Operator / Viewer)

---

## สารบัญ

1. [ภาพรวมระบบ (System Overview)](#1-ภาพรวมระบบ)
2. [ผู้ใช้งานและสิทธิ์ (User Roles)](#2-ผู้ใช้งานและสิทธิ์)
3. [Authentication & Profile](#3-authentication--profile)
4. [Dashboard & Analytics](#4-dashboard--analytics)
5. [Station Management](#5-station-management)
6. [Charger & Connector Management](#6-charger--connector-management)
7. [Charging Session Control](#7-charging-session-control)
8. [Customer Management](#8-customer-management)
9. [Transaction & Report](#9-transaction--report)
10. [Settings & Configuration](#10-settings--configuration)
11. [Notification & Alert System](#11-notification--alert-system)
12. [Real-time Monitoring](#12-real-time-monitoring)
13. [Mobile UX Functions](#13-mobile-ux-functions)
14. [Multi-language & Accessibility](#14-multi-language--accessibility)
15. [System Admin Functions](#15-system-admin-functions)
16. [API Integration Functions](#16-api-integration-functions)
17. [Future Roadmap Functions](#17-future-roadmap-functions)
18. [สรุปความสำคัญและ Priority](#18-สรุปความสำคัญและ-priority)

---

## 1. ภาพรวมระบบ

CSMS (Charging Station Management System) เป็นระบบบริหารจัดการสถานีชาร์จรถยนต์ไฟฟ้า (EV) แบบ Full-Stack Web Application ที่รองรับการทำงานแบบ Real-time ระบบ Frontend ต้องออกแบบให้:

- **Simple First**: ผู้ใช้ใหม่ทำงานได้ใน 3 Click
- **Data Driven**: ตัวเลขสำคัญแสดงชัดเจน ไม่ต้องค้นหา
- **Mobile Ready**: ใช้งานได้สมบูรณ์บนมือถือ (Operator ในสนาม)
- **Real-time**: สถานะเปลี่ยนทันทีโดยไม่ต้อง Refresh

---

## 2. ผู้ใช้งานและสิทธิ์

| Role | คำอธิบาย | สิทธิ์หลัก |
|------|----------|------------|
| **Admin** | เจ้าของระบบ / ผู้ดูแลระบบสูงสุด | ทุก function รวมถึงจัดการ User |
| **Operator** | พนักงานดูแลสถานีชาร์จ | ดูแล Charger, เริ่ม/หยุดชาร์จ, ดู Report |
| **Viewer** | ผู้บริหาร / ฝ่ายการเงิน | ดูข้อมูลและ Report อย่างเดียว ไม่แก้ไข |

---

## 3. Authentication & Profile

### 3.1 Login Page
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-AUTH-01` Email/Password Login | Form validation ทันทีที่พิมพ์ (Real-time) ไม่ต้อง Submit ก่อน | 🔴 Must |
| `F-AUTH-02` Remember Me | เก็บ session นาน 30 วัน ด้วย Secure Cookie | 🔴 Must |
| `F-AUTH-03` Forgot Password | กรอก Email → รับ OTP → Reset Password (3 ขั้นตอน) | 🔴 Must |
| `F-AUTH-04` OTP Timer | แสดง Countdown 5 นาที และปุ่ม "ส่งใหม่" เมื่อหมดเวลา | 🔴 Must |
| `F-AUTH-05` Login Attempt Limit | Block หลัง 5 ครั้งผิด แสดงเวลา Cooldown | 🟡 Should |
| `F-AUTH-06` Session Timeout Warning | แจ้งเตือน Popup 2 นาทีก่อน Session หมด + ปุ่ม Extend | 🟡 Should |
| `F-AUTH-07` Social / SSO Login | รองรับ Google OAuth หรือ LINE Login | 🟢 Nice |

### 3.2 User Profile
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-PROF-01` View Profile | ชื่อ, อีเมล, Role, วันที่สมัคร, รูปโปรไฟล์ | 🔴 Must |
| `F-PROF-02` Edit Profile | แก้ชื่อ-นามสกุล, เบอร์โทร, อัปโหลดรูป | 🔴 Must |
| `F-PROF-03` Change Password | Old → New → Confirm พร้อม Strength Meter | 🔴 Must |
| `F-PROF-04` Activity Log | ประวัติ Login/Logout ล่าสุด 30 รายการ | 🟡 Should |
| `F-PROF-05` Notification Preferences | เลือก Alert แบบไหนที่ต้องการรับ (Email/In-app) | 🟡 Should |

---

## 4. Dashboard & Analytics

### 4.1 KPI Summary Cards
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-DASH-01` Period Selector | ปุ่ม toggle: วันนี้ / MTD / YTD / Custom Range | 🔴 Must |
| `F-DASH-02` Revenue KPI Card | รายได้รวม + % เพิ่ม/ลดจากช่วงก่อน + เส้น Sparkline | 🔴 Must |
| `F-DASH-03` Sessions KPI Card | จำนวนครั้งชาร์จ + เฉลี่ยต่อวัน + เทรนด์ | 🔴 Must |
| `F-DASH-04` Energy KPI Card | kWh รวม + CO₂ ที่ประหยัดได้ (แปลงอัตโนมัติ) | 🔴 Must |
| `F-DASH-05` Customer KPI Card | ลูกค้าทั้งหมด / ลูกค้าใหม่ / Active ในช่วง | 🔴 Must |
| `F-DASH-06` Live Charger Status | สรุป Online/Offline/Charging/Faulted แบบ Real-time | 🔴 Must |
| `F-DASH-07` Quick Action Buttons | ปุ่มลัด: + สถานีใหม่, + ลูกค้าใหม่, ดู Report | 🟡 Should |

### 4.2 Charts & Graphs
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CHART-01` Daily Revenue Bar Chart | รายได้ 30 วันย้อนหลัง Interactive hover tooltip | 🔴 Must |
| `F-CHART-02` Monthly Revenue Line Chart | 12 เดือน YTD เปรียบเทียบปีก่อน | 🔴 Must |
| `F-CHART-03` Car Type Donut Chart | สัดส่วนรถแต่ละยี่ห้อ/รุ่น พร้อม Legend | 🔴 Must |
| `F-CHART-04` Revenue by Station Bar | เปรียบเทียบรายได้แต่ละสาขา | 🔴 Must |
| `F-CHART-05` Hourly Usage Heatmap | แสดง Peak Hour แต่ละวันในสัปดาห์ (7×24 grid) | 🟡 Should |
| `F-CHART-06` Customer Growth Line | จำนวนลูกค้าสะสมรายเดือน | 🟡 Should |
| `F-CHART-07` Energy vs Revenue Dual-Axis | kWh และ รายได้ บนกราฟเดียวกัน | 🟡 Should |
| `F-CHART-08` Export Chart as PNG | ปุ่ม Download กราฟเป็นรูปภาพ | 🟢 Nice |

### 4.3 Dashboard Widgets
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-WIDGET-01` Top Customers Table | Top 5 ลูกค้า รายได้, ครั้ง, kWh คลิกดู Detail ได้ | 🔴 Must |
| `F-WIDGET-02` Recent Sessions Feed | 10 รายการล่าสุด แบบ Live Feed | 🔴 Must |
| `F-WIDGET-03` Active Sessions Monitor | การชาร์จที่กำลังดำเนินอยู่ + เวลาที่ผ่านไป + % ที่จะได้รับ | 🔴 Must |
| `F-WIDGET-04` Station Health Map | แผนที่แสดงที่ตั้งสถานีพร้อมสีสถานะ (ต้องการ Google Maps API) | 🟡 Should |
| `F-WIDGET-05` Alert Summary Panel | แจ้งเตือนล่าสุดที่ยังไม่ได้อ่าน | 🟡 Should |
| `F-WIDGET-06` Drag & Drop Widget Order | ผู้ใช้จัดลำดับ Widget เองได้ (บันทึกใน localStorage) | 🟢 Nice |

---

## 5. Station Management

### 5.1 Station List
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-STA-01` Card/Table View Toggle | สลับมุมมองระหว่าง Card Grid และ Data Table | 🔴 Must |
| `F-STA-02` Status Filter Chips | กรองด้วย All / Active / Maintenance / Inactive | 🔴 Must |
| `F-STA-03` Search by Name/Address | Search Box ค้นหาทันทีแบบ Live (ไม่ต้อง Submit) | 🔴 Must |
| `F-STA-04` Station Summary Badge | แสดง: จำนวน Charger, Online, กำลังชาร์จ บน Card | 🔴 Must |
| `F-STA-05` Quick Status Toggle | เปลี่ยน Active ↔ Maintenance ได้จาก List โดยตรง | 🟡 Should |
| `F-STA-06` Sort Options | เรียงตาม: ชื่อ, สถานะ, จำนวน Charger, รายได้ | 🟡 Should |
| `F-STA-07` Map View | ดูที่ตั้งสถานีทั้งหมดบนแผนที่ | 🟡 Should |

### 5.2 Add/Edit Station Form
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-STA-08` Inline Form Validation | ตรวจสอบ Field ทุก Field ก่อน Submit | 🔴 Must |
| `F-STA-09` Address Autocomplete | พิมพ์ชื่อถนน/แขวง → แนะนำอัตโนมัติ (Google Places API) | 🟡 Should |
| `F-STA-10` Map Pin Selector | ลาก Marker บนแผนที่เพื่อเลือก Lat/Long | 🟡 Should |
| `F-STA-11` Upload Station Photo | อัปโหลดรูปสถานี รองรับ Drag & Drop + Preview | 🟡 Should |
| `F-STA-12` Duplicate Station | Copy ข้อมูลสถานีที่มีอยู่เป็นฐานสถานีใหม่ | 🟢 Nice |

### 5.3 Station Detail Page
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-STA-13` Station Overview Tab | รายได้, Sessions, kWh ของสถานีนั้นๆ | 🔴 Must |
| `F-STA-14` Charger List Tab | รายการ Charger พร้อมสถานะ Real-time | 🔴 Must |
| `F-STA-15` Transaction History Tab | ประวัติรายการของสถานีนั้น Filter/Export ได้ | 🔴 Must |
| `F-STA-16` Settings Tab | ค่าบริการ, ข้อมูลติดต่อ, Operating Hours | 🟡 Should |

---

## 6. Charger & Connector Management

### 6.1 Charger List
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CHG-01` Real-time Status Cards | สีบ่งบอกสถานะ: เขียว=Online, แดง=Faulted, เทา=Offline | 🔴 Must |
| `F-CHG-02` Status Filter | กรอง: All / Online / Charging / Offline / Faulted | 🔴 Must |
| `F-CHG-03` Charger Detail Expand | คลิกดูรายละเอียด: Serial, Brand, kW, Connectors, Last heartbeat | 🔴 Must |
| `F-CHG-04` Auto-refresh Status | อัปเดตสถานะทุก 30 วินาที โดยไม่ Reload หน้า | 🔴 Must |
| `F-CHG-05` Charger Health Indicator | แสดง Uptime %, จำนวน Error ในรอบ 30 วัน | 🟡 Should |
| `F-CHG-06` Last Heartbeat Alert | แจ้งเตือนถ้า Heartbeat หายไปเกิน 5 นาที | 🟡 Should |

### 6.2 Connector Management
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CON-01` Connector Status Badge | แสดง: Available / Charging / Unavailable / Faulted | 🔴 Must |
| `F-CON-02` Connector Type Icon | ไอคอนแยก Type 2 / CCS2 / CHAdeMO / GB/T | 🔴 Must |
| `F-CON-03` Active Session Info | ถ้ากำลังชาร์จ แสดง: ลูกค้า, เวลา, kWh, บาทที่ใช้ | 🔴 Must |
| `F-CON-04` Add Multiple Connectors | เพิ่ม Connector ได้มากกว่า 1 อัน ต่อ Charger | 🟡 Should |

---

## 7. Charging Session Control

### 7.1 Start Charging
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CHG-10` Start Charge Modal | เลือก: จำนวนเงิน หรือ kWh เป้าหมาย หรือ Full Charge | 🔴 Must |
| `F-CHG-11` Customer Lookup | พิมพ์ชื่อ/ทะเบียน → แนะนำลูกค้าที่มีอยู่ในระบบ | 🔴 Must |
| `F-CHG-12` Walk-in Guest Mode | ชาร์จได้โดยไม่ต้องเลือกลูกค้า (เป็น Guest) | 🔴 Must |
| `F-CHG-13` Estimated Preview | แสดง: ประมาณ kWh ที่จะได้ + เวลาที่ใช้ + ค่าบริการ | 🔴 Must |
| `F-CHG-14` QR Code Scan | Scan QR ของรถ/ลูกค้าเพื่อ Auto-fill ข้อมูล | 🟡 Should |
| `F-CHG-15` License Plate Camera | ถ่ายรูปป้ายทะเบียน → ระบบ OCR หา Customer อัตโนมัติ | 🟢 Nice |

### 7.2 Active Session Monitor
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CHG-20` Live Timer | แสดง เวลาที่ผ่านไป HH:MM:SS อัปเดตทุกวินาที | 🔴 Must |
| `F-CHG-21` Energy Progress Bar | แสดง kWh ที่ชาร์จแล้ว vs เป้าหมาย เป็น % | 🔴 Must |
| `F-CHG-22` Estimated Completion | คำนวณเวลาเสร็จโดยประมาณตาม kW ปัจจุบัน | 🟡 Should |
| `F-CHG-23` Current Cost Display | แสดงค่าใช้จ่ายสะสมแบบ Real-time | 🟡 Should |
| `F-CHG-24` Stop Charge Confirmation | Confirm 2 ขั้นก่อน Stop (ป้องกันกดผิด) | 🔴 Must |

### 7.3 Session Receipt
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CHG-30` Session Summary Modal | เมื่อ Stop แสดง: เวลา, kWh, ราคา, ลูกค้า, ยืนยัน | 🔴 Must |
| `F-CHG-31` Print Receipt | พิมพ์ใบเสร็จได้ทันที (ขนาด A5 หรือ thermal 80mm) | 🟡 Should |
| `F-CHG-32` Send Receipt by Email | ส่ง Receipt PDF ไปยังอีเมลลูกค้า | 🟡 Should |
| `F-CHG-33` Send Receipt by LINE | แชร์สรุปการชาร์จผ่าน LINE (ใช้ LINE Notify) | 🟢 Nice |

---

## 8. Customer Management

### 8.1 Customer List
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CUS-01` Search Bar (Live) | ค้นหาตาม: ชื่อ, เบอร์, ทะเบียน, อีเมล ทันที | 🔴 Must |
| `F-CUS-02` Filter by Car Type | Dropdown กรองตามยี่ห้อ/รุ่นรถ | 🔴 Must |
| `F-CUS-03` Sort Options | เรียงตาม: ชื่อ, สมาชิกใหม่, ยอดใช้, ครั้งชาร์จ | 🔴 Must |
| `F-CUS-04` Customer Card/Table | แสดง: ชื่อ, ทะเบียน, รถ, ยอดรวม, ครั้งล่าสุด | 🔴 Must |
| `F-CUS-05` Tier/Badge Display | แสดง Badge: Bronze/Silver/Gold/Platinum ตามยอดใช้ | 🟡 Should |
| `F-CUS-06` Export Customer List | Export CSV หรือ Excel | 🟡 Should |
| `F-CUS-07` Bulk Actions | เลือกหลายคน → ส่ง Email / Tag / Export | 🟢 Nice |

### 8.2 Customer Detail
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CUS-10` Profile Summary | รูป, ชื่อ, ข้อมูลติดต่อ, รถ, วันสมัคร | 🔴 Must |
| `F-CUS-11` Stat Cards | ยอดรวม, ครั้งทั้งหมด, kWh สะสม, เฉลี่ยต่อครั้ง | 🔴 Must |
| `F-CUS-12` Transaction History | รายการชาร์จทั้งหมด Filter/Paginate ได้ | 🔴 Must |
| `F-CUS-13` Favorite Stations | สถานีที่ใช้บ่อยที่สุด Top 3 | 🟡 Should |
| `F-CUS-14` Charging Pattern Chart | กราฟแสดง Pattern การชาร์จรายเดือน | 🟡 Should |
| `F-CUS-15` Notes / Remarks | บันทึกหมายเหตุสำหรับ Operator | 🟡 Should |
| `F-CUS-16` Customer Loyalty Points | ระบบแต้มสะสม แลกส่วนลด | 🟢 Nice |

### 8.3 Add/Edit Customer
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-CUS-20` Multi-vehicle Support | ลูกค้า 1 คนมีได้หลายทะเบียน | 🟡 Should |
| `F-CUS-21` Photo Upload | อัปโหลดรูปลูกค้า หรือรูปรถ | 🟢 Nice |
| `F-CUS-22` Duplicate Check | แจ้งเตือนหากเบอร์/ทะเบียนซ้ำในระบบ | 🔴 Must |
| `F-CUS-23` Import from CSV | นำเข้ารายชื่อลูกค้าจาก CSV ครั้งละหลายคน | 🟡 Should |

---

## 9. Transaction & Report

### 9.1 Transaction List
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-TXN-01` Advanced Filters | กรองตาม: สถานี, วันที่, สถานะ, ลูกค้า, ประเภทรถ | 🔴 Must |
| `F-TXN-02` Date Range Picker | ปฏิทิน 2 เดือน เลือก Range ได้ง่าย | 🔴 Must |
| `F-TXN-03` Summary Bar | แสดงผลรวม: รายได้, Sessions, kWh ของ Filter ปัจจุบัน | 🔴 Must |
| `F-TXN-04` Status Color Code | Completed=เขียว, Active=น้ำเงิน, Cancelled=เทา, Error=แดง | 🔴 Must |
| `F-TXN-05` Row Detail Expand | คลิก Row ดูรายละเอียดเพิ่มเติมโดยไม่ออกจากหน้า | 🟡 Should |
| `F-TXN-06` Pagination + Page Size | เลือกแสดง 10 / 25 / 50 / 100 rows | 🔴 Must |

### 9.2 Export & Reports
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-RPT-01` CSV Export | Export ข้อมูลที่ Filter อยู่เป็น CSV | 🔴 Must |
| `F-RPT-02` Excel Export (.xlsx) | Export พร้อม Formatting ที่ดีกว่า CSV | 🟡 Should |
| `F-RPT-03` PDF Report | สร้าง PDF รายงานสรุปประจำเดือน พร้อม Logo | 🟡 Should |
| `F-RPT-04` Scheduled Report | ตั้งให้ส่ง Report ทางอีเมลอัตโนมัติ (รายวัน/สัปดาห์/เดือน) | 🟡 Should |
| `F-RPT-05` Revenue Report by Station | รายงานรายได้แยกตามสถานี | 🔴 Must |
| `F-RPT-06` Energy Consumption Report | รายงาน kWh รวม แยกตาม Connector Type | 🟡 Should |
| `F-RPT-07` Customer Activity Report | ลูกค้า Active / Inactive ในช่วงเวลา | 🟡 Should |

---

## 10. Settings & Configuration

### 10.1 Service Fee Settings
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-SET-01` Fee Type Selection | kWh-Based / Time-Based / TOU / Free พร้อม UI อธิบาย | 🔴 Must |
| `F-SET-02` Live Calculation Preview | กรอกตัวเลข → แสดงตัวอย่างการคิดค่าบริการทันที | 🔴 Must |
| `F-SET-03` Fee History Log | ดูประวัติการเปลี่ยน Fee ย้อนหลัง | 🟡 Should |
| `F-SET-04` Effective Date Setting | ตั้งวันที่มีผล เพื่อเปลี่ยนราคาล่วงหน้า | 🟡 Should |
| `F-SET-05` Per-Connector Fee | ตั้งค่าแตกต่างกันตาม Connector Type (AC/DC) | 🟡 Should |
| `F-SET-06` TOU Rate Table | ตั้ง Tariff แยกตามช่วงเวลา (Peak/Off-Peak/Holiday) | 🟢 Nice |

### 10.2 System Settings (Admin Only)
| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-SET-10` User Management | เพิ่ม/แก้ไข/ลบ User, กำหนด Role | 🔴 Must |
| `F-SET-11` Email Configuration | ตั้งค่า SMTP สำหรับส่งอีเมล OTP/Receipt | 🔴 Must |
| `F-SET-12` System Logs Viewer | ดู Audit Log การกระทำในระบบ Filter ได้ | 🟡 Should |
| `F-SET-13` Backup Database | ดาวน์โหลด SQL Dump ผ่านหน้าเว็บ | 🟡 Should |
| `F-SET-14` App Settings | ชื่อระบบ, Logo, สี, Timezone, Default Language | 🟡 Should |
| `F-SET-15` Car Type Management | เพิ่ม/แก้ไขรายการประเภทรถ EV ในระบบ | 🟡 Should |

---

## 11. Notification & Alert System

| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-NOTIF-01` In-app Notification Bell | ไอคอนระฆัง + Badge จำนวนที่ยังไม่ได้อ่าน | 🔴 Must |
| `F-NOTIF-02` Charger Fault Alert | แจ้งทันทีเมื่อ Charger Status = Faulted | 🔴 Must |
| `F-NOTIF-03` Charger Offline Alert | แจ้งเมื่อ Heartbeat หายเกิน 5 นาที | 🔴 Must |
| `F-NOTIF-04` Session Complete Alert | แจ้งเมื่อการชาร์จเสร็จสิ้น | 🔴 Must |
| `F-NOTIF-05` Revenue Milestone Alert | แจ้งเมื่อถึงเป้ารายได้รายวัน | 🟡 Should |
| `F-NOTIF-06` Email Notification | ส่งอีเมลแจ้งเตือน Fault ให้ Admin | 🟡 Should |
| `F-NOTIF-07` Browser Push Notification | Web Push Notification แม้ไม่ได้เปิดหน้าเว็บ | 🟡 Should |
| `F-NOTIF-08` LINE Notify Integration | ส่งแจ้งเตือน Fault ผ่าน LINE Group | 🟢 Nice |
| `F-NOTIF-09` Notification Preferences | แต่ละ User เลือกประเภทแจ้งเตือนที่ต้องการ | 🟡 Should |
| `F-NOTIF-10` Mark All as Read | ปุ่ม "อ่านทั้งหมด" ใน Notification Panel | 🔴 Must |

---

## 12. Real-time Monitoring

| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-RT-01` Auto-refresh Dashboard | อัปเดต KPI ทุก 60 วินาที โดยไม่ Reload | 🔴 Must |
| `F-RT-02` Charger Status Polling | Polling API `/api/status.php` ทุก 30 วินาที | 🔴 Must |
| `F-RT-03` Active Session Timer | Live timer สำหรับทุก Session ที่กำลัง Active | 🔴 Must |
| `F-RT-04` WebSocket Real-time | อัปเดตสถานะ Charger แบบ Push (ไม่ต้อง Poll) | 🟡 Should |
| `F-RT-05` Connection Status Indicator | แสดง Online/Offline ของการเชื่อมต่อ Server | 🟡 Should |
| `F-RT-06` Last Updated Timestamp | แสดงเวลาที่ Data อัปเดตล่าสุดทุก Widget | 🟡 Should |

---

## 13. Mobile UX Functions

| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-MOB-01` Responsive Sidebar | Hamburger → Slide-in Drawer บน Mobile | 🔴 Must |
| `F-MOB-02` Bottom Navigation Bar | Nav ด้านล่างสำหรับ Mobile: Dashboard, Stations, Chargers, Customers | 🔴 Must |
| `F-MOB-03` Touch-friendly Buttons | ขนาดปุ่มอย่างน้อย 44×44px ตาม Apple HIG | 🔴 Must |
| `F-MOB-04` Swipe to Delete | Swipe ซ้ายบน List Item เพื่อ Delete | 🟡 Should |
| `F-MOB-05` Pull to Refresh | ดึงหน้าจอลงเพื่อ Refresh ข้อมูล | 🟡 Should |
| `F-MOB-06` Progressive Web App (PWA) | Install เป็น App บนมือถือได้ + Offline Cache | 🟡 Should |
| `F-MOB-07` Camera Access | ถ่ายรูปป้ายทะเบียน / QR Code ผ่าน Browser | 🟡 Should |
| `F-MOB-08` Landscape Mode Support | UI ปรับได้ดีทั้ง Portrait และ Landscape | 🔴 Must |
| `F-MOB-09` Large Font Mode | รองรับ System Font Size (Accessibility) | 🟡 Should |

---

## 14. Multi-language & Accessibility

| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-LANG-01` Language Switcher | สลับ ไทย / English / 中文 บน Sidebar + Login | 🔴 Must |
| `F-LANG-02` Persistent Language | จดจำภาษาที่เลือกไว้ตลอด Session + Cookie | 🔴 Must |
| `F-LANG-03` Number Format | แสดงตัวเลขตาม Locale: 1,234.56 (EN) / ๑,๒๓๔.๕๖ (TH) | 🟡 Should |
| `F-LANG-04` Date Format | แสดงวันที่ตาม Locale: วว/ดด/ปปปป (TH พ.ศ.) | 🟡 Should |
| `F-LANG-05` RTL Support | รองรับภาษาที่เขียน RTL (อนาคต: Arabic) | 🟢 Nice |
| `F-A11Y-01` ARIA Labels | ทุก Button/Input มี ARIA สำหรับ Screen Reader | 🟡 Should |
| `F-A11Y-02` Keyboard Navigation | ใช้งานได้ด้วย Keyboard ล้วน (Tab, Enter, Escape) | 🟡 Should |
| `F-A11Y-03` Color Contrast | ผ่านเกณฑ์ WCAG 2.1 AA Contrast Ratio | 🟡 Should |
| `F-A11Y-04` Focus Indicators | แสดง Focus Ring ชัดเจนเมื่อ Navigate ด้วย Keyboard | 🟡 Should |

---

## 15. System Admin Functions

| Function | รายละเอียด | Priority |
|----------|-----------|----------|
| `F-ADM-01` User List & Management | ตาราง User ทั้งหมด + เพิ่ม/แก้ไข/ปิดใช้งาน | 🔴 Must |
| `F-ADM-02` Role Assignment | กำหนด Admin / Operator / Viewer ต่อ User | 🔴 Must |
| `F-ADM-03` Station Ownership | กำหนดว่า Operator ดูแลสถานีไหนได้บ้าง | 🟡 Should |
| `F-ADM-04` System Log Viewer | ดู Audit Trail: Login, CRUD, Setting Changes | 🟡 Should |
| `F-ADM-05` OTP Log Viewer | ดู OTP ที่ส่งออกไป (Dev/Test mode) | 🟢 Nice |
| `F-ADM-06` Performance Metrics | ดู Server Load, DB Query Time, Response Time | 🟢 Nice |
| `F-ADM-07` Announcement Banner | ประกาศข้อความสำคัญแสดงบน Header ทุกหน้า | 🟡 Should |

---

## 16. API Integration Functions

### 16.1 Current APIs (มีอยู่แล้ว)
| Endpoint | Method | Function |
|----------|--------|----------|
| `/api/status.php?station_id=X` | GET | ดึงสถานะ Charger ทั้งหมดของสถานี |
| `/api/connector.php` | POST | อัปเดตสถานะ Connector (จาก OCPP) |

### 16.2 APIs ที่ควรเพิ่ม
| Endpoint ที่แนะนำ | Method | Function | Priority |
|-------------------|--------|----------|----------|
| `/api/dashboard/kpi` | GET | KPI Summary สำหรับ Dashboard Widget | 🔴 Must |
| `/api/transactions/summary` | GET | สรุปรายการตาม Filter | 🔴 Must |
| `/api/chargers/{id}/start` | POST | เริ่มชาร์จ (OCPP StartTransaction) | 🔴 Must |
| `/api/chargers/{id}/stop` | POST | หยุดชาร์จ (OCPP StopTransaction) | 🔴 Must |
| `/api/customers/search` | GET | ค้นหาลูกค้า สำหรับ Autocomplete | 🔴 Must |
| `/api/notifications` | GET | ดึง Notification ที่ยังไม่ได้อ่าน | 🟡 Should |
| `/api/reports/export` | POST | Generate และ Download Report | 🟡 Should |
| `/api/webhook/ocpp` | POST | รับ Event จาก OCPP Charger จริง | 🟡 Should |

---

## 17. Future Roadmap Functions

> ฟังก์ชันเหล่านี้เหมาะสำหรับ Phase ถัดไป เมื่อระบบ Stable และมี User ใช้งานจริง

| Function | รายละเอียด | Phase |
|----------|-----------|-------|
| `F-FUTURE-01` OCPP 1.6 / 2.0.1 Integration | เชื่อมต่อ Charger จริงผ่าน OCPP Protocol | Phase 2 |
| `F-FUTURE-02` Mobile App (React Native) | App มือถือ Native สำหรับ iOS/Android | Phase 2 |
| `F-FUTURE-03` Customer Self-Service Portal | ลูกค้าดูประวัติการชาร์จ/ใบเสร็จเองได้ | Phase 2 |
| `F-FUTURE-04` Payment Gateway | ชำระผ่าน QR PromptPay, Credit Card, บัตร EV | Phase 2 |
| `F-FUTURE-05` Dynamic Pricing (AI) | ปรับราคาอัตโนมัติตาม Demand และ Peak Hour | Phase 3 |
| `F-FUTURE-06` Energy Management | บริหารจัดการ Load Balancing ระหว่าง Charger | Phase 3 |
| `F-FUTURE-07` Multi-tenant SaaS | ให้บริการหลายองค์กรบน Platform เดียว | Phase 3 |
| `F-FUTURE-08` Fleet Management | บริหารรถยนต์ EV สำหรับองค์กร/บริษัทรถรับจ้าง | Phase 3 |
| `F-FUTURE-09` POS Integration | เชื่อมกับระบบ POS เพื่อออกใบกำกับภาษี | Phase 2 |
| `F-FUTURE-10` Carbon Credit Tracking | คำนวณ Carbon Credit จากพลังงานที่ชาร์จ | Phase 3 |

---

## 18. สรุปความสำคัญและ Priority

### Priority Matrix

| ระดับ | หมายความ | สัญลักษณ์ |
|-------|----------|-----------|
| **Must Have** | ระบบทำงานไม่ได้โดยปราศจาก Function นี้ | 🔴 |
| **Should Have** | ช่วยให้ระบบใช้งานได้ดีขึ้นอย่างมีนัยสำคัญ | 🟡 |
| **Nice to Have** | เพิ่มประสบการณ์ดีขึ้น แต่ไม่จำเป็นเร่งด่วน | 🟢 |

### สรุปจำนวน Function ทั้งหมด

| หมวด | Must 🔴 | Should 🟡 | Nice 🟢 | รวม |
|------|---------|-----------|---------|-----|
| Authentication & Profile | 5 | 5 | 1 | 11 |
| Dashboard & Analytics | 12 | 11 | 3 | 26 |
| Station Management | 8 | 8 | 2 | 18 |
| Charger & Connector | 7 | 4 | 0 | 11 |
| Charging Session | 8 | 4 | 2 | 14 |
| Customer Management | 8 | 9 | 3 | 20 |
| Transaction & Report | 9 | 8 | 0 | 17 |
| Settings | 4 | 10 | 2 | 16 |
| Notification & Alert | 5 | 5 | 2 | 12 |
| Real-time Monitoring | 3 | 3 | 0 | 6 |
| Mobile UX | 4 | 4 | 0 | 8 |
| Multi-language | 2 | 7 | 1 | 10 |
| Admin Functions | 2 | 3 | 2 | 7 |
| API Functions | 4 | 4 | 0 | 8 |
| **รวมทั้งหมด** | **81** | **85** | **18** | **184** |

### การแนะนำลำดับการพัฒนา

```
Phase 1 — Core MVP (ปัจจุบัน ✅)
├── Auth (Login/Register/OTP)
├── Station CRUD
├── Charger CRUD + Start/Stop Simulate
├── Transaction List + CSV Export
├── Basic Dashboard (KPI + Charts)
├── Customer CRUD
└── Multi-language (TH/EN/ZH)

Phase 1.5 — UX Enhancement (แนะนำทำต่อไป)
├── 🔴 In-app Notification Bell
├── 🔴 Auto-refresh Dashboard & Charger Status
├── 🔴 Customer Autocomplete ใน Start Charge
├── 🔴 Session Receipt + Print
├── 🔴 User Management (Admin)
├── 🟡 Live Calculation Preview (Fee Settings)
├── 🟡 Advanced Transaction Filters
└── 🟡 Mobile Bottom Navigation Bar

Phase 2 — Advanced Features
├── Email/PDF Report
├── Payment Gateway
├── Customer Self-Service Portal
├── OCPP Integration
└── PWA (Install as App)

Phase 3 — Scale & Intelligence
├── AI Dynamic Pricing
├── Energy Load Balancing
├── Multi-tenant SaaS
└── Carbon Credit Tracking
```

---

## หมายเหตุสำหรับนักพัฒนา

### Tech Stack ปัจจุบัน
- **Backend**: Pure PHP 8.x + PDO (MySQL/MariaDB)
- **Frontend**: Tailwind CSS v3 + Google Material Icons + Chart.js
- **Font**: Sarabun (TH) + Inter (EN) + Noto Sans SC (ZH)
- **API**: REST-like JSON endpoints (session-based auth)

### ข้อแนะนำด้าน Architecture สำหรับ Phase 1.5
1. **เพิ่ม `/api/notifications.php`** → In-app Bell
2. **เพิ่ม `?format=json` ใน dashboard.php** → AJAX Refresh
3. **เพิ่ม `/api/customers/search.php`** → Autocomplete
4. **เพิ่ม `receipt.php?tx_id=X`** → Print Receipt
5. **เพิ่ม `users.php` (Admin Only)** → User Management

### Security Considerations
- ทุก API endpoint ต้อง validate `session + role`
- ใช้ **CSRF Token** สำหรับทุก Form POST
- Input Sanitization ครบทุก User Input
- Rate Limiting สำหรับ Login และ API
- SQL Injection ป้องกันด้วย Prepared Statements (PDO) ✅ (ทำแล้ว)

---

*เอกสารนี้จัดทำโดย: Senior System Architect*
*วันที่: มีนาคม 2569 (2026)*
*Version: 1.0*
*สำหรับโครงการ: CSMS — Charging Station Management System*
