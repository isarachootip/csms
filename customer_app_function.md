# CSMS Customer Mobile App — Function Specification
**ระบบแอปพลิเคชันมือถือสำหรับลูกค้า EV Charging**
> ออกแบบโดย: Senior System Architect | 20+ ปีประสบการณ์
> วันที่: มีนาคม 2569 (2026) | Version: 1.0
> เชื่อมต่อกับ Backend: CSMS PHP API (MySQL + PDO)

---

## สารบัญ

1. [ภาพรวม Customer App](#1-ภาพรวม-customer-app)
2. [Architecture & Backend Integration](#2-architecture--backend-integration)
3. [Authentication & Onboarding](#3-authentication--onboarding)
4. [Home Screen & Dashboard](#4-home-screen--dashboard)
5. [Station Finder (ค้นหาสถานีชาร์จ)](#5-station-finder)
6. [Charger & Connector Status](#6-charger--connector-status)
7. [Charging Session (เริ่ม/ระหว่าง/จบการชาร์จ)](#7-charging-session)
8. [Payment & Wallet](#8-payment--wallet)
9. [History & Receipt](#9-history--receipt)
10. [Profile & Vehicle Management](#10-profile--vehicle-management)
11. [Loyalty & Rewards](#11-loyalty--rewards)
12. [Notifications & Alerts](#12-notifications--alerts)
13. [Settings & Preferences](#13-settings--preferences)
14. [Customer Support](#14-customer-support)
15. [API Endpoints (New) ที่ต้องเพิ่มใน Backend](#15-api-endpoints-ที่ต้องเพิ่มใน-backend)
16. [Database Extensions ที่ต้องเพิ่ม](#16-database-extensions-ที่ต้องเพิ่ม)
17. [UI/UX Screen Flow](#17-uiux-screen-flow)
18. [Priority Matrix & Roadmap](#18-priority-matrix--roadmap)

---

## 1. ภาพรวม Customer App

### วัตถุประสงค์
แอปมือถือสำหรับ **ลูกค้าผู้ใช้รถยนต์ไฟฟ้า (EV Driver)** เพื่อ:
- ค้นหาสถานีชาร์จที่ใกล้ที่สุด
- ดูสถานะ Charger แบบ Real-time
- เริ่ม / ติดตาม / จบการชาร์จ
- ชำระเงิน และดูใบเสร็จ
- ดูประวัติการชาร์จและสถิติส่วนตัว

### Platform
| Platform | Technology | หมายเหตุ |
|----------|-----------|----------|
| **iOS** | React Native / Flutter | รองรับ iPhone iOS 14+ |
| **Android** | React Native / Flutter | รองรับ Android 8.0+ |
| **Web (PWA)** | Tailwind CSS + Vanilla JS | Fallback สำหรับ Browser |

### Backend Connection
เชื่อมต่อกับ **CSMS PHP Backend** ที่มีอยู่ผ่าน:
- REST API (JSON over HTTPS)
- Session Token / JWT Authentication
- Real-time Polling (30s interval) → อนาคต: WebSocket

---

## 2. Architecture & Backend Integration

### ตาราง Database ที่ใช้งาน (Existing)

```
users           → สมาชิกลูกค้า (ใช้ร่วม role='customer')
stations        → สถานีชาร์จ (lat/long สำหรับแผนที่)
chargers        → เครื่องชาร์จ (status, brand, max_power_kw)
connectors      → หัวชาร์จ (type, status real-time)
transactions    → ประวัติการชาร์จ (customer_id FK)
customers       → โปรไฟล์ลูกค้า (full_name, license_plate, car_type_id)
car_types       → ข้อมูลรถ EV (brand, connector_type, battery_kwh)
service_fee_settings → ค่าบริการต่อสถานี (price_per_kwh)
meter_values    → ข้อมูล kWh Real-time ระหว่างชาร์จ
```

### API Flow ปัจจุบัน (Existing)
```
GET  /api/status.php?station_id=X   → สถานะ Charger ทั้งหมดของสถานี
POST /api/connector.php             → อัปเดต Connector Status
```

### API ที่ต้องเพิ่มสำหรับ Customer App (ดูหมวด 15)
```
POST /api/customer/auth/register    → ลงทะเบียนลูกค้า
POST /api/customer/auth/login       → เข้าสู่ระบบ
GET  /api/customer/stations/nearby  → สถานีใกล้เคียง (lat/long)
GET  /api/customer/stations/{id}    → รายละเอียดสถานี + ราคา
GET  /api/customer/chargers/{id}    → สถานะ Charger + Connector
POST /api/customer/sessions/start   → เริ่มชาร์จ
GET  /api/customer/sessions/{id}    → ติดตาม Session Live
POST /api/customer/sessions/stop    → หยุดชาร์จ
GET  /api/customer/history          → ประวัติการชาร์จ
GET  /api/customer/profile          → ข้อมูลโปรไฟล์
PUT  /api/customer/profile          → แก้ไขโปรไฟล์
GET  /api/customer/wallet           → ยอดเงิน Wallet
POST /api/customer/wallet/topup     → เติมเงิน
POST /api/customer/payment          → ชำระเงิน
GET  /api/customer/receipts/{id}    → ใบเสร็จ
GET  /api/customer/notifications    → การแจ้งเตือน
```

---

## 3. Authentication & Onboarding

### 3.1 Splash Screen & Onboarding
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-AUTH-01` | Splash Screen | โลโก้ CSMS + Animation 2 วินาที | - | 🔴 Must |
| `CA-AUTH-02` | Onboarding Slides | 3 หน้าแนะนำ: ค้นหา / ชาร์จ / จ่ายเงิน | - | 🟡 Should |
| `CA-AUTH-03` | Skip Onboarding | ข้ามได้ บันทึกใน Local Storage | - | 🟡 Should |

### 3.2 Register (ลงทะเบียน)
| ID | Function | รายละเอียด | Backend Table/API | Priority |
|----|----------|-----------|-------------------|----------|
| `CA-AUTH-10` | Register Form | ชื่อ-นามสกุล, เบอร์โทร, อีเมล, รหัสผ่าน | `POST /api/customer/auth/register` → `users` | 🔴 Must |
| `CA-AUTH-11` | OTP Verification | รับ OTP 6 หลักทาง SMS/Email | `users.otp_code`, `otp_expires_at` | 🔴 Must |
| `CA-AUTH-12` | OTP Timer 5 นาที | Countdown + ปุ่มส่งใหม่ | `users.otp_expires_at` | 🔴 Must |
| `CA-AUTH-13` | Password Strength | Indicator: อ่อน/กลาง/แข็ง แบบ Real-time | Client-side | 🔴 Must |
| `CA-AUTH-14` | Terms & Privacy | ต้อง Checkbox ยอมรับก่อน Register | - | 🔴 Must |
| `CA-AUTH-15` | Register with LINE | ใช้ LINE Login เพื่อความสะดวก | LINE OAuth | 🟢 Nice |
| `CA-AUTH-16` | Register with Google | Google OAuth | Google OAuth | 🟢 Nice |

### 3.3 Login (เข้าสู่ระบบ)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-AUTH-20` | Email/Password Login | Validate ทันที | `POST /api/customer/auth/login` → `users` | 🔴 Must |
| `CA-AUTH-21` | Biometric Login | Face ID / Fingerprint (ครั้งแรกต้อง Password) | Device OS API | 🔴 Must |
| `CA-AUTH-22` | Remember Me | Token บันทึกใน Secure Storage 30 วัน | JWT / Refresh Token | 🔴 Must |
| `CA-AUTH-23` | Forgot Password | กรอกอีเมล → OTP → Reset | `users.otp_code` | 🔴 Must |
| `CA-AUTH-24` | Login Attempt Limit | Lock 15 นาที หลัง 5 ครั้งผิด | Server-side Rate Limit | 🟡 Should |
| `CA-AUTH-25` | Auto-login | เปิด App แล้ว Login อัตโนมัติ (Token ยัง Valid) | JWT Check | 🔴 Must |

### 3.4 Vehicle Setup (ขั้นตอนหลัง Register)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-AUTH-30` | Add First Vehicle | เลือกยี่ห้อ/รุ่น จาก Dropdown + กรอกทะเบียน | `car_types` → `customers` | 🔴 Must |
| `CA-AUTH-31` | Car Type Search | พิมพ์ค้นหารุ่นรถ (Tesla, BYD, MG...) | `GET /api/customer/car-types` | 🔴 Must |
| `CA-AUTH-32` | Connector Type Auto | แสดง Connector ที่รถใช้ อัตโนมัติ | `car_types.connector_type` | 🔴 Must |

---

## 4. Home Screen & Dashboard

### 4.1 Home Screen Layout (Mobile First)
```
┌─────────────────────────────────┐
│  👋 สวัสดี, สมชาย            🔔 │  ← Header: ชื่อ + Notification Bell
│  🔋 รถ: BYD Atto 3 | กข-1234  │  ← Active Vehicle
├─────────────────────────────────┤
│  📍 ค้นหาสถานีชาร์จใกล้ฉัน   🔍 │  ← Search Bar (เด่นที่สุด)
├─────────────────────────────────┤
│  ⚡ กำลังชาร์จอยู่...          │  ← Active Session Card (ถ้ามี)
│  35 นาที | 18.5 kWh | ฿92.50  │
├─────────────────────────────────┤
│  [🗺️ แผนที่] [📋 สถานีใกล้]   │  ← Quick Access
├─────────────────────────────────┤
│  💰 Wallet: ฿250.00    เติมเงิน │  ← Wallet Balance
├─────────────────────────────────┤
│  📊 สถิติของฉัน (เดือนนี้)      │
│  12 ครั้ง | 145 kWh | ฿812.50  │
├─────────────────────────────────┤
│  🕐 การชาร์จล่าสุด             │  ← Recent Sessions
│  [สถานีลาดพร้าว - เมื่อวาน]    │
└─────────────────────────────────┘
```

### 4.2 Home Screen Functions
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-HOME-01` | Greeting Header | ชื่อลูกค้า + เวลา (เช้า/บ่าย/เย็น) | `customers.full_name` | 🔴 Must |
| `CA-HOME-02` | Active Vehicle Badge | แสดงรถที่ใช้งานอยู่ + ทะเบียน | `customers.license_plate`, `car_types.name` | 🔴 Must |
| `CA-HOME-03` | Active Session Banner | ถ้ากำลังชาร์จอยู่ แสดง Banner ใหญ่ทันที | `transactions` WHERE status='Charging' | 🔴 Must |
| `CA-HOME-04` | Quick Search Bar | กรอกชื่อสถานี หรือ Location แล้วค้นหา | `GET /api/customer/stations/search` | 🔴 Must |
| `CA-HOME-05` | Nearby Button | กด 1 ครั้ง → เปิดแผนที่สถานีใกล้ๆ | GPS + `stations.lat/long` | 🔴 Must |
| `CA-HOME-06` | Wallet Balance Card | ยอดคงเหลือ + ปุ่ม "เติมเงิน" | `wallet_accounts.balance` | 🟡 Should |
| `CA-HOME-07` | Monthly Stats | ครั้ง, kWh, ยอดเงินเดือนนี้ | `transactions` GROUP BY customer | 🔴 Must |
| `CA-HOME-08` | Recent Sessions List | 3 รายการล่าสุด คลิกดู Detail ได้ | `transactions` ORDER BY start_time DESC | 🔴 Must |
| `CA-HOME-09` | Pull to Refresh | ดึงลงเพื่อโหลดข้อมูลใหม่ | API Re-fetch | 🔴 Must |

---

## 5. Station Finder

### 5.1 แผนที่ (Map View)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-MAP-01` | Interactive Map | Google Maps / Mapbox แสดง Pin สถานีทั้งหมด | `stations.latitude`, `stations.longitude` | 🔴 Must |
| `CA-MAP-02` | My Location Pin | แสดงตำแหน่งปัจจุบันของผู้ใช้ | Device GPS | 🔴 Must |
| `CA-MAP-03` | Station Pin Color | 🟢 Available, 🔵 Busy, 🔴 Offline / Maintenance | `stations.status` + `connectors.status` | 🔴 Must |
| `CA-MAP-04` | Pin Tap → Summary | กด Pin → Popup: ชื่อ, ระยะ, จำนวน Charger ว่าง | `stations` + `connectors` | 🔴 Must |
| `CA-MAP-05` | Navigate Button | กด "นำทาง" → เปิด Google Maps / Waze | Deep Link | 🔴 Must |
| `CA-MAP-06` | Cluster Pins | เมื่อ Zoom ออก รวม Pin เป็น Cluster พร้อม Count | Client-side | 🟡 Should |
| `CA-MAP-07` | Radius Filter | กรองสถานีในระยะ: 1km / 5km / 10km / 20km | `HAVERSINE()` SQL | 🟡 Should |
| `CA-MAP-08` | Filter by Connector | CCS2 / CHAdeMO / Type2 ตามรถของฉัน | `connectors.connector_type` | 🔴 Must |
| `CA-MAP-09` | Filter by Power | กรองตาม kW: AC (≤22kW) / DC (>22kW) | `chargers.max_power_kw` | 🟡 Should |
| `CA-MAP-10` | Offline Map Cache | Cache แผนที่ Area ที่ใช้บ่อย | PWA Cache | 🟢 Nice |

### 5.2 รายการสถานี (List View)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-LIST-01` | Station List Card | ชื่อ, ระยะทาง, ราคา/kWh, จำนวนว่าง | `stations` + `service_fee_settings` | 🔴 Must |
| `CA-LIST-02` | Sort Options | เรียงตาม: ใกล้ที่สุด / ราคาถูกสุด / ว่างมากสุด | SQL ORDER BY | 🔴 Must |
| `CA-LIST-03` | Availability Badge | "3/6 ว่าง" หรือ "เต็ม" | COUNT connectors status | 🔴 Must |
| `CA-LIST-04` | Real-time Update | อัปเดตสถานะทุก 60 วินาที | `GET /api/customer/stations/nearby` | 🟡 Should |
| `CA-LIST-05` | Favorite Stations | ❤️ bookmark สถานีที่ชอบ บันทึกใน DB | `customer_favorites` (table ใหม่) | 🟡 Should |
| `CA-LIST-06` | Recently Visited | แสดง 3 สถานีที่เคยใช้ล่าสุด | `transactions` GROUP BY station | 🟡 Should |

### 5.3 รายละเอียดสถานี (Station Detail)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-STA-01` | Station Info | ชื่อ, ที่อยู่, เวลาเปิด-ปิด, รูปภาพ | `stations` | 🔴 Must |
| `CA-STA-02` | Live Charger Grid | กริดแสดง Charger ทุกตัว + สี Status | `GET /api/status.php?station_id=X` | 🔴 Must |
| `CA-STA-03` | Price Info Card | ค่าบริการ/kWh, ประเภท Fee, ค่าที่จอดรถ | `service_fee_settings` | 🔴 Must |
| `CA-STA-04` | Available Count | "เครื่องว่าง X/Y เครื่อง" | `connectors` STATUS COUNT | 🔴 Must |
| `CA-STA-05` | Queue Estimate | ประมาณการรอคิว (ถ้าเต็ม) | Avg session duration | 🟡 Should |
| `CA-STA-06` | Station Photos | รูปภาพสถานี (ลาก Swipe) | `station_photos` (table ใหม่) | 🟡 Should |
| `CA-STA-07` | User Reviews | คะแนนและรีวิวจากผู้ใช้คนอื่น | `station_reviews` (table ใหม่) | 🟢 Nice |
| `CA-STA-08` | Share Station | แชร์ Location ผ่าน LINE/WhatsApp | Native Share API | 🟡 Should |
| `CA-STA-09` | Navigate CTA | ปุ่ม "นำทาง" ขนาดใหญ่ | Google Maps Intent | 🔴 Must |
| `CA-STA-10` | Remote Queue | จอง Charger ล่วงหน้า (อนาคต) | `reservations` (table ใหม่) | 🟢 Nice |

---

## 6. Charger & Connector Status

| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-CHG-01` | Charger Card | แสดง: หมายเลข, ยี่ห้อ, kW, Connector Type | `chargers` + `connectors` | 🔴 Must |
| `CA-CHG-02` | Status Color Badge | 🟢 ว่าง / 🔵 กำลังชาร์จ / 🟡 Plugged in / 🔴 Fault | `connectors.status` | 🔴 Must |
| `CA-CHG-03` | Connector Type Icon | ไอคอนแยกแต่ละประเภท CCS2, CHAdeMO, Type2 | `connectors.connector_type` | 🔴 Must |
| `CA-CHG-04` | Max Power Display | "60 kW DC" หรือ "22 kW AC" | `chargers.max_power_kw` | 🔴 Must |
| `CA-CHG-05` | Compatible Check | ✅ / ❌ รองรับรถของฉันหรือไม่ | Match `car_types.connector_type` | 🔴 Must |
| `CA-CHG-06` | Auto-refresh 30s | อัปเดตสถานะทุก 30 วินาที | `/api/status.php` Polling | 🔴 Must |
| `CA-CHG-07` | In-use Timer | ถ้า Charging อยู่ แสดงเวลาที่ใช้ไป | `transactions.start_time` | 🟡 Should |
| `CA-CHG-08` | Start Charge Button | ปุ่ม "เริ่มชาร์จ" (เฉพาะ Connector ที่ว่าง) | POST /api/customer/sessions/start | 🔴 Must |

---

## 7. Charging Session

### 7.1 เริ่มชาร์จ (Start Charging)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-SES-01` | Scan QR Code | สแกน QR บน Charger เพื่อเลือกอัตโนมัติ | QR = `connector_id` | 🔴 Must |
| `CA-SES-02` | Manual Select | เลือก Charger จากรายการในแอป | `connectors.id` | 🔴 Must |
| `CA-SES-03` | Charge Amount Input | เลือกยอดเงิน: ฿50 / ฿100 / ฿200 / กำหนดเอง | Client-side | 🔴 Must |
| `CA-SES-04` | Charge kWh Input | หรือกรอก kWh เป้าหมาย (เช่น 30 kWh) | Client-side | 🟡 Should |
| `CA-SES-05` | Full Charge Option | ชาร์จเต็ม (คำนวณจาก battery_kwh ของรถ) | `car_types.battery_kwh` | 🟡 Should |
| `CA-SES-06` | Estimated Preview | ก่อน Confirm แสดง: ประมาณ kWh, ประมาณ % เพิ่ม, เวลาโดยประมาณ | `service_fee_settings.price_per_kwh` | 🔴 Must |
| `CA-SES-07` | Payment Select | เลือก: Wallet / PromptPay / บัตรเครดิต | `wallet_accounts` / Payment API | 🔴 Must |
| `CA-SES-08` | Confirm & Start | ปุ่มยืนยัน → เรียก API → Session เริ่ม | `POST /api/customer/sessions/start` → `transactions` INSERT | 🔴 Must |
| `CA-SES-09` | Vehicle Profile Auto | Auto-fill ข้อมูลรถจาก Profile | `customers.car_type_id` | 🔴 Must |

### 7.2 ระหว่างชาร์จ (Active Session Screen)
```
┌─────────────────────────────────┐
│  ⚡ กำลังชาร์จ...              │
│                                 │
│     [  🔋 Animation  ]          │
│                                 │
│  ⏱️  00:35:12                   │  ← Live Timer (ทุก 1 วินาที)
│  ⚡  18.52 kWh                  │  ← Energy (ทุก 30 วินาที)
│  💰  ฿ 101.86                   │  ← Cost Real-time
│                                 │
│  ████████░░░░░░ 42%             │  ← Progress Bar (kWh/เป้าหมาย)
│  เป้าหมาย: 44 kWh              │
│                                 │
│  🏪 สถานีลาดพร้าว | Charger 2  │
│  🔌 CCS2 | 60 kW DC            │
│                                 │
│  ┌──────────────────────────┐   │
│  │  ⏹️ หยุดชาร์จ             │   │  ← Stop Button
│  └──────────────────────────┘   │
└─────────────────────────────────┘
```

| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-SES-10` | Live Session Screen | หน้าจอระหว่างชาร์จ Full-screen | `GET /api/customer/sessions/{id}` | 🔴 Must |
| `CA-SES-11` | Real-time Timer | HH:MM:SS นับขึ้นทุกวินาที | `transactions.start_time` (client timer) | 🔴 Must |
| `CA-SES-12` | Energy Progress | kWh ที่ชาร์จแล้ว / เป้าหมาย เป็น % | `meter_values.energy_kwh` Polling | 🔴 Must |
| `CA-SES-13` | Live Cost | ยอดเงินสะสม Real-time | kWh × `price_per_kwh` (client calc) | 🔴 Must |
| `CA-SES-14` | Battery Animation | Animation แบตเตอรี่กำลังชาร์จ | Client-side CSS/Lottie | 🔴 Must |
| `CA-SES-15` | Estimated Finish | "คาดว่าเสร็จ 14:35 น." | Remaining kWh ÷ current kW | 🟡 Should |
| `CA-SES-16` | Keep Awake Screen | ป้องกันหน้าจอดับขณะชาร์จ | Wake Lock API | 🟡 Should |
| `CA-SES-17` | Background Mode | แสดง Notification บน Status Bar ขณะชาร์จ | Push Notification | 🔴 Must |
| `CA-SES-18` | Stop Confirmation | ยืนยัน 2 ขั้นก่อน Stop | Client Modal | 🔴 Must |
| `CA-SES-19` | Emergency Stop | กด Stop ได้ตลอดเวลา | `POST /api/customer/sessions/stop` → UPDATE transactions | 🔴 Must |
| `CA-SES-20` | Completion Alert | แจ้งเตือน Push เมื่อชาร์จครบตามที่ตั้ง | Push Notification | 🔴 Must |

### 7.3 สรุปหลังชาร์จ (Session Complete)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-SES-30` | Summary Screen | หน้าสรุป: เวลา, kWh, ราคา, สถานี, วันที่ | `transactions` Final Record | 🔴 Must |
| `CA-SES-31` | Save to History | บันทึกอัตโนมัติในประวัติ | `transactions` STATUS='Completed' | 🔴 Must |
| `CA-SES-32` | Share Summary | แชร์สรุปการชาร์จผ่าน Social | Native Share | 🟢 Nice |
| `CA-SES-33` | Rate & Review | ให้คะแนน ⭐ สถานีหลังชาร์จ | `station_reviews` | 🟡 Should |
| `CA-SES-34` | Back to Home | ปุ่ม "กลับหน้าแรก" | Navigation | 🔴 Must |

---

## 8. Payment & Wallet

### 8.1 Wallet System
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-PAY-01` | Wallet Balance Display | ยอดคงเหลือ (฿XXX.XX) บน Home + ก่อนชาร์จ | `wallet_accounts.balance` | 🔴 Must |
| `CA-PAY-02` | Top-up Wallet | เลือกยอด: ฿100 / ฿200 / ฿500 / ฿1000 / กำหนดเอง | `POST /api/customer/wallet/topup` | 🔴 Must |
| `CA-PAY-03` | Top-up via QR | PromptPay QR Code เติมเงิน | PromptPay API / Payment Gateway | 🔴 Must |
| `CA-PAY-04` | Top-up via Credit Card | กรอก Card Number / Scan Card | Payment Gateway (Omise/2C2P) | 🟡 Should |
| `CA-PAY-05` | Wallet Transaction History | ประวัติการเติม-ใช้ Wallet ทั้งหมด | `wallet_transactions` | 🟡 Should |
| `CA-PAY-06` | Low Balance Warning | แจ้งเตือนเมื่อยอดต่ำกว่า ฿50 | `wallet_accounts.balance` Check | 🟡 Should |
| `CA-PAY-07` | Auto Top-up | ตั้งให้เติมอัตโนมัติเมื่อยอดต่ำกว่าที่กำหนด | Scheduled Task | 🟢 Nice |

### 8.2 Payment Methods
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-PAY-10` | Pay via Wallet | หักจาก Wallet Balance | `wallet_accounts` UPDATE | 🔴 Must |
| `CA-PAY-11` | Pay via PromptPay | แสดง QR PromptPay → รอยืนยัน | Payment API Webhook | 🟡 Should |
| `CA-PAY-12` | Pay via Credit Card | บันทึก Card (Token) เพื่อใช้ครั้งต่อไป | Payment Gateway Token | 🟡 Should |
| `CA-PAY-13` | Pay via TrueMoney | TrueMoney Wallet | TrueMoney API | 🟢 Nice |
| `CA-PAY-14` | Invoice / Tax Receipt | ขอใบกำกับภาษีออนไลน์ | PDF Generation | 🟡 Should |

### 8.3 Pricing Display
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-PAY-20` | Price per kWh | แสดงราคา/kWh ของแต่ละสถานี | `service_fee_settings.price_per_kwh` | 🔴 Must |
| `CA-PAY-21` | Free Charge Badge | แสดง "ฟรี!" สำหรับสถานีที่ fee_type='Free Charge' | `service_fee_settings.fee_type` | 🔴 Must |
| `CA-PAY-22` | TOU Pricing Display | แสดงราคา Peak/Off-Peak ถ้ามี | `service_fee_settings.peak_price` | 🟡 Should |
| `CA-PAY-23` | Cost Calculator | กรอก kWh → แสดงค่าใช้จ่ายทันที | Client-side Calc | 🔴 Must |

---

## 9. History & Receipt

### 9.1 ประวัติการชาร์จ
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-HIST-01` | Transaction List | รายการทั้งหมด เรียงล่าสุดก่อน | `GET /api/customer/history` → `transactions` WHERE customer_id | 🔴 Must |
| `CA-HIST-02` | Filter by Month | กรองตาม เดือน/ปี | SQL WHERE DATE_FORMAT | 🔴 Must |
| `CA-HIST-03` | Filter by Station | กรองตามสถานี | `transactions.station_id` | 🟡 Should |
| `CA-HIST-04` | Filter by Status | Completed / Stopped / Faulted | `transactions.status` | 🟡 Should |
| `CA-HIST-05` | Search by Date | ค้นหาตาม วันที่ที่ระบุ | Date Picker | 🟡 Should |
| `CA-HIST-06` | Monthly Summary Bar | สรุปยอด: ครั้ง, kWh, บาท รายเดือน | GROUP BY Month | 🔴 Must |
| `CA-HIST-07` | Infinite Scroll | โหลดเพิ่มเมื่อ Scroll ถึงล่าง | Pagination API (offset/limit) | 🔴 Must |

### 9.2 รายละเอียด Transaction
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-HIST-10` | Transaction Detail | ข้อมูลครบ: สถานี, Charger, เวลา, kWh, ราคา | `transactions` JOIN `stations`, `chargers` | 🔴 Must |
| `CA-HIST-11` | Energy Chart | กราฟ kWh vs เวลาของ Session | `meter_values` (ถ้ามี) | 🟡 Should |
| `CA-HIST-12` | Download Receipt | PDF ใบเสร็จ ดาวน์โหลด | `GET /api/customer/receipts/{id}` | 🔴 Must |
| `CA-HIST-13` | Share Receipt | แชร์ใบเสร็จผ่าน LINE/Email | Native Share / Email API | 🟡 Should |
| `CA-HIST-14` | Report Problem | แจ้งปัญหาเกี่ยวกับรายการนี้ | `support_tickets` | 🟡 Should |

### 9.3 สถิติส่วนตัว (My Stats)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-STAT-01` | Total Sessions | ครั้งชาร์จทั้งหมดตลอดเวลา | `customers.total_sessions` | 🔴 Must |
| `CA-STAT-02` | Total kWh | พลังงานสะสม | `customers.total_kwh` | 🔴 Must |
| `CA-STAT-03` | Total Spend | ยอดใช้จ่ายสะสม | `customers.total_spend` | 🔴 Must |
| `CA-STAT-04` | CO₂ Saved | คำนวณ CO₂ ที่ไม่ปล่อยออกมา (kWh × 0.5 kg) | Client Calc | 🟡 Should |
| `CA-STAT-05` | Fuel Cost Saved | เปรียบเทียบกับรถน้ำมัน (฿/100km) | Client Calc | 🟡 Should |
| `CA-STAT-06` | Favorite Station | สถานีที่ใช้บ่อยที่สุด | COUNT transactions GROUP BY station | 🟡 Should |
| `CA-STAT-07` | Monthly Chart | กราฟ Bar รายเดือน 12 เดือนย้อนหลัง | `transactions` GROUP BY Month | 🟡 Should |
| `CA-STAT-08` | Charging Streak | กี่วันติดต่อกันที่มีการชาร์จ | Gamification Logic | 🟢 Nice |

---

## 10. Profile & Vehicle Management

### 10.1 โปรไฟล์ลูกค้า
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-PROF-01` | View Profile | ชื่อ, เบอร์, อีเมล, วันสมัคร, Level/Tier | `customers` + `users` | 🔴 Must |
| `CA-PROF-02` | Profile Photo | อัปโหลด/เปลี่ยนรูปโปรไฟล์ (Camera/Gallery) | `users.avatar` (field ใหม่) | 🟡 Should |
| `CA-PROF-03` | Edit Name/Phone | แก้ไขข้อมูลส่วนตัว | `PUT /api/customer/profile` → `customers` UPDATE | 🔴 Must |
| `CA-PROF-04` | Change Password | Old → New → Confirm | `users.password` bcrypt | 🔴 Must |
| `CA-PROF-05` | Verified Badge | ✅ อีเมลยืนยันแล้ว | `users.is_verified` | 🔴 Must |
| `CA-PROF-06` | Member Since | วันที่เป็นสมาชิก | `customers.member_since` | 🔴 Must |
| `CA-PROF-07` | Delete Account | ลบบัญชีและข้อมูลทั้งหมด (PDPA) | Soft Delete `users.deleted_at` | 🟡 Should |

### 10.2 Vehicle Management (จัดการรถ)
| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-VEH-01` | My Vehicles List | รายการรถทั้งหมดที่ลงทะเบียน | `customer_vehicles` (table ใหม่) | 🔴 Must |
| `CA-VEH-02` | Add Vehicle | เพิ่มรถใหม่: ยี่ห้อ/รุ่น + ทะเบียน | `car_types` SELECT + `customer_vehicles` INSERT | 🔴 Must |
| `CA-VEH-03` | Set Default Vehicle | เลือกรถหลักที่ใช้งาน | `customer_vehicles.is_default` | 🔴 Must |
| `CA-VEH-04` | Edit Vehicle | แก้ไขทะเบียน หรือเปลี่ยนรุ่น | `customer_vehicles` UPDATE | 🔴 Must |
| `CA-VEH-05` | Delete Vehicle | ลบรถออกจากรายการ | `customer_vehicles` DELETE | 🔴 Must |
| `CA-VEH-06` | Connector Type Display | แสดง Connector ที่รถใช้ ใต้ชื่อรุ่น | `car_types.connector_type` | 🔴 Must |
| `CA-VEH-07` | Battery Size Display | แสดง Battery ขนาด kWh ของรถ | `car_types.battery_kwh` | 🟡 Should |
| `CA-VEH-08` | Compatible Filter | เมื่อเลือกรถ → กรอง Charger ที่รองรับอัตโนมัติ | Match `connector_type` | 🔴 Must |
| `CA-VEH-09` | Vehicle Photo | ถ่าย/อัปโหลดรูปรถ | Image Upload | 🟢 Nice |

---

## 11. Loyalty & Rewards

| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-LOY-01` | Member Tier Display | Bronze / Silver / Gold / Platinum ตามยอดใช้ | `customers.total_spend` Threshold | 🟡 Should |
| `CA-LOY-02` | Points Balance | แต้มสะสม (10 แต้ม = ฿1) | `loyalty_points` (table ใหม่) | 🟡 Should |
| `CA-LOY-03` | Points History | ประวัติการได้/ใช้แต้ม | `loyalty_transactions` | 🟡 Should |
| `CA-LOY-04` | Redeem Points | แลกแต้มเป็นส่วนลด | `loyalty_points` Deduct | 🟡 Should |
| `CA-LOY-05` | Tier Progress Bar | Progress bar ไปถึง Tier ถัดไป | Threshold Calculation | 🟡 Should |
| `CA-LOY-06` | Referral Code | โค้ดแนะนำเพื่อน รับโบนัส | `referral_codes` (table ใหม่) | 🟢 Nice |
| `CA-LOY-07` | Coupon Codes | กรอก Coupon ส่วนลด | `coupons` (table ใหม่) | 🟢 Nice |
| `CA-LOY-08` | Tier Benefit Display | สิทธิประโยชน์แต่ละระดับ | Static Config | 🟡 Should |

**Tier Definition (แนะนำ):**
| Tier | ยอดสะสม | สิทธิ์พิเศษ |
|------|---------|-------------|
| 🥉 Bronze | ฿0 - ฿999 | - |
| 🥈 Silver | ฿1,000 - ฿4,999 | ส่วนลด 2% |
| 🥇 Gold | ฿5,000 - ฿19,999 | ส่วนลด 5% + Priority Queue |
| 💎 Platinum | ฿20,000+ | ส่วนลด 10% + ฟรีที่จอดรถ |

---

## 12. Notifications & Alerts

| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-NOTIF-01` | Push: ชาร์จเสร็จ | แจ้งเมื่อชาร์จครบ Target | Push Notification Service | 🔴 Must |
| `CA-NOTIF-02` | Push: ครบเวลา | แจ้งเมื่อ Session ครบตามเวลาที่ตั้ง | Push Service | 🔴 Must |
| `CA-NOTIF-03` | Push: Wallet ต่ำ | แจ้งเมื่อยอด Wallet < ฿50 | `wallet_accounts.balance` Check | 🟡 Should |
| `CA-NOTIF-04` | Push: Charger ขัดข้อง | แจ้งถ้า Charger ที่กำลังใช้ Fault | `transactions` + Charger Status | 🔴 Must |
| `CA-NOTIF-05` | Push: โปรโมชัน | แจ้งโปรโมชันและส่วนลด | `announcements` (table ใหม่) | 🟢 Nice |
| `CA-NOTIF-06` | In-app Bell | ระฆังแจ้งเตือนใน App | `notifications` (table ใหม่) | 🔴 Must |
| `CA-NOTIF-07` | Notification History | ดูแจ้งเตือนทั้งหมดย้อนหลัง | `notifications` | 🟡 Should |
| `CA-NOTIF-08` | Notification Settings | เลือก ON/OFF แต่ละประเภท | `notification_settings` | 🟡 Should |
| `CA-NOTIF-09` | Mark as Read | อ่านแล้ว / อ่านทั้งหมด | `notifications.read_at` | 🔴 Must |
| `CA-NOTIF-10` | Status Bar Indicator | แสดง Progress ระหว่างชาร์จบน Status Bar | Foreground Service (Android) | 🔴 Must |

---

## 13. Settings & Preferences

| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-SET-01` | Language | เลือกภาษา: ไทย / English / 中文 | `i18n` System | 🔴 Must |
| `CA-SET-02` | Default Payment | เลือก Payment Method หลัก | `user_settings.default_payment` | 🟡 Should |
| `CA-SET-03` | Dark Mode | สลับ Light / Dark Theme | Device Setting + localStorage | 🟡 Should |
| `CA-SET-04` | Unit Preference | kWh / kJ (สำหรับนักเทคนิค) | localStorage | 🟢 Nice |
| `CA-SET-05` | Currency | THB / USD (Multi-currency อนาคต) | `user_settings` | 🟢 Nice |
| `CA-SET-06` | Notification Settings | เปิด/ปิดแต่ละประเภทแจ้งเตือน | `notification_settings` | 🟡 Should |
| `CA-SET-07` | Privacy Settings | จัดการ Location Permission | Device OS | 🟡 Should |
| `CA-SET-08` | Biometric Login Toggle | เปิด/ปิด Face ID / Fingerprint | Device Keychain | 🔴 Must |
| `CA-SET-09` | Clear Cache | ล้าง Cache ข้อมูล App | localStorage.clear() | 🟢 Nice |
| `CA-SET-10` | App Version | แสดง Version + Check Update | - | 🟡 Should |

---

## 14. Customer Support

| ID | Function | รายละเอียด | Backend | Priority |
|----|----------|-----------|---------|----------|
| `CA-SUP-01` | FAQ | คำถามที่พบบ่อย แบบ Accordion | Static or CMS | 🟡 Should |
| `CA-SUP-02` | Report Problem | แจ้งปัญหา + เลือกหมวด + อธิบาย + รูป | `support_tickets` INSERT | 🔴 Must |
| `CA-SUP-03` | Chat Support | Live Chat กับ Support Team | Chat API / LINE OA | 🟡 Should |
| `CA-SUP-04` | Call Center | กดโทรหา Call Center | Tel: Intent | 🟡 Should |
| `CA-SUP-05` | Ticket Status | ติดตามสถานะ Ticket ที่แจ้ง | `support_tickets.status` | 🟡 Should |
| `CA-SUP-06` | Rate App | ให้คะแนน App ใน Store | Deep Link to App Store | 🟢 Nice |
| `CA-SUP-07` | Submit Feedback | ส่ง Feedback ทั่วไป | `feedbacks` (table ใหม่) | 🟢 Nice |

---

## 15. API Endpoints ที่ต้องเพิ่มใน Backend

### 15.1 Authentication APIs
```php
// ลงทะเบียนลูกค้าใหม่ (เพิ่ม role='customer')
POST /api/customer/auth/register
Body: { first_name, last_name, phone, email, password }
→ INSERT users (role='customer') + INSERT customers
→ ส่ง OTP ทาง Email/SMS
→ Return: { success, user_id, message }

// เข้าสู่ระบบ + ได้รับ Token
POST /api/customer/auth/login
Body: { email, password }
→ Auth::login() + Generate JWT Token
→ Return: { token, expires_at, customer: { id, name, email } }

// Refresh Token
POST /api/customer/auth/refresh
Header: Authorization: Bearer {refresh_token}
→ Return: { token, expires_at }

// ลืมรหัสผ่าน
POST /api/customer/auth/forgot-password
Body: { email }
→ Send OTP → users.otp_code

// Reset รหัสผ่าน
POST /api/customer/auth/reset-password
Body: { email, otp, new_password }
```

### 15.2 Station APIs
```php
// สถานีใกล้เคียง (Geolocation)
GET /api/customer/stations/nearby
Query: ?lat=13.xxx&lng=100.xxx&radius=5&connector_type=CCS2
→ SELECT stations + HAVERSINE distance + available connector count
→ Return: [ { id, name, distance_km, available, total, price_per_kwh, lat, lng } ]

// รายละเอียดสถานี + ราคา + Chargers
GET /api/customer/stations/{id}
→ station info + service_fee_settings + chargers + connectors status
→ Return: { station, fee, chargers: [ { id, connectors: [...] } ] }

// ค้นหาสถานี
GET /api/customer/stations/search
Query: ?q=ลาดพร้าว&lat=&lng=
→ Return: matching stations list
```

### 15.3 Session APIs
```php
// เริ่มชาร์จ
POST /api/customer/sessions/start
Header: Authorization: Bearer {token}
Body: { connector_id, target_amount OR target_kwh, payment_method, vehicle_id }
→ Check connector available → INSERT transactions (status='Charging')
→ UPDATE connectors status='Charging in progress'
→ Return: { transaction_id, start_time, estimated_kwh, estimated_duration }

// สถานะ Session แบบ Live
GET /api/customer/sessions/{id}
→ transactions JOIN meter_values
→ Return: { id, status, elapsed_min, energy_kwh, current_cost, connector_status }

// หยุดชาร์จ
POST /api/customer/sessions/stop
Header: Authorization: Bearer {token}
Body: { transaction_id }
→ UPDATE transactions status='Completed', stop_time=NOW()
→ UPDATE connectors status='Ready to use'
→ Calc final amount → Deduct wallet
→ Return: { final_kwh, final_amount, duration_minutes }

// ประวัติ Session ของลูกค้า
GET /api/customer/sessions/history
Query: ?page=1&limit=20&month=2026-03
→ Return: [ transaction records ]
```

### 15.4 Profile APIs
```php
// ดูโปรไฟล์
GET /api/customer/profile
→ customers JOIN users JOIN car_types
→ Return: { full_name, phone, email, vehicles, tier, stats }

// แก้ไขโปรไฟล์
PUT /api/customer/profile
Body: { full_name, phone }
→ UPDATE customers

// รายการรถของลูกค้า
GET /api/customer/vehicles
→ customer_vehicles JOIN car_types
→ Return: [ { id, license_plate, car_type, connector_type, battery_kwh, is_default } ]

// เพิ่มรถ
POST /api/customer/vehicles
Body: { car_type_id, license_plate }

// ลบรถ
DELETE /api/customer/vehicles/{id}
```

### 15.5 Payment APIs
```php
// ยอด Wallet
GET /api/customer/wallet
→ wallet_accounts WHERE customer_id
→ Return: { balance, currency }

// เติม Wallet (สร้าง QR PromptPay)
POST /api/customer/wallet/topup
Body: { amount, method: 'promptpay'|'credit_card' }
→ Create Payment Intent → Return QR/Payment URL

// Payment Webhook (จาก Gateway)
POST /api/customer/wallet/webhook
→ Verify Signature → UPDATE wallet_accounts.balance

// ดาวน์โหลดใบเสร็จ
GET /api/customer/receipts/{transaction_id}
→ Generate PDF → Return Base64 or File URL
```

### 15.6 Car Types API
```php
// รายการประเภทรถทั้งหมด
GET /api/customer/car-types
Query: ?brand=Tesla&q=Model
→ SELECT car_types
→ Return: [ { id, name, brand, connector_type, battery_kwh } ]
```

---

## 16. Database Extensions ที่ต้องเพิ่ม

```sql
-- ── รถของลูกค้า (Multi-vehicle Support)
CREATE TABLE customer_vehicles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    car_type_id     INT DEFAULT NULL,
    license_plate   VARCHAR(30) NOT NULL,
    nickname        VARCHAR(100),            -- ชื่อเล่น เช่น "รถที่บ้าน"
    color           VARCHAR(50),
    is_default      TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (car_type_id) REFERENCES car_types(id) ON DELETE SET NULL
);

-- ── กระเป๋าเงิน (Wallet)
CREATE TABLE wallet_accounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL UNIQUE,
    balance         DECIMAL(12,2) DEFAULT 0.00,
    currency        VARCHAR(10) DEFAULT 'THB',
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ── ประวัติ Wallet
CREATE TABLE wallet_transactions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id       INT NOT NULL,
    type            ENUM('topup','charge','refund','reward') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    balance_after   DECIMAL(12,2) NOT NULL,
    reference_id    VARCHAR(100),            -- Transaction ID หรือ Payment Ref
    description     TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallet_accounts(id)
);

-- ── แจ้งเตือน
CREATE TABLE notifications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    type            ENUM('session','wallet','promo','system','alert') NOT NULL,
    title           VARCHAR(200) NOT NULL,
    body            TEXT,
    data            JSON,                    -- Extra payload (transaction_id, etc.)
    read_at         DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ── สถานีโปรด
CREATE TABLE customer_favorites (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    station_id      INT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (customer_id, station_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
);

-- ── แต้มสะสม
CREATE TABLE loyalty_points (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL UNIQUE,
    points          INT DEFAULT 0,
    lifetime_points INT DEFAULT 0,          -- แต้มสะสมตลอดชีพ (สำหรับ Tier)
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- ── รีวิวสถานี
CREATE TABLE station_reviews (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    station_id      INT NOT NULL,
    customer_id     INT NOT NULL,
    transaction_id  INT NOT NULL,           -- ต้องเคยชาร์จก่อนถึง Review ได้
    rating          TINYINT NOT NULL,       -- 1-5
    comment         TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review (customer_id, transaction_id),
    FOREIGN KEY (station_id) REFERENCES stations(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
);

-- ── Support Tickets
CREATE TABLE support_tickets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    transaction_id  INT DEFAULT NULL,
    category        ENUM('charging','payment','account','app','other') DEFAULT 'other',
    subject         VARCHAR(200) NOT NULL,
    description     TEXT,
    status          ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
);
```

---

## 17. UI/UX Screen Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    CUSTOMER APP FLOW                        │
└─────────────────────────────────────────────────────────────┘

[Splash] ──→ [Onboarding x3] ──→ [Login/Register]
                                        │
                            ┌───────────┴───────────┐
                       [Register]               [Login]
                            │                       │
                       [OTP Verify]          [Biometric/Token]
                            │                       │
                       [Add Vehicle]                │
                            └───────────┬───────────┘
                                        ↓
                                 ┌──────────────┐
                                 │  HOME SCREEN │
                                 └──────┬───────┘
                    ┌────────────┬──────┴──────┬────────────┐
                    ↓            ↓             ↓            ↓
              [MAP/Finder]  [History]     [Wallet]    [Profile]
                    │
                    ↓
             [Station Detail]
                    │
                    ↓
             [Charger Select]
                    │
                    ↓
             [Start Charge Form]
             • Target Amount/kWh
             • Select Vehicle
             • Select Payment
                    │
                    ↓
             [Confirm & Pay]
                    │
                    ↓
         ┌──[ACTIVE SESSION]──┐
         │  Live Timer        │
         │  kWh Progress      │
         │  Cost Real-time    │
         │  Battery Animation │
         └────────┬───────────┘
                  │
             [Stop / Auto Complete]
                  │
                  ↓
            [Session Summary]
            • kWh, ฿, Duration
            • Rate Station ⭐
            • Download Receipt
            • Share
                  │
                  ↓
             [HOME SCREEN]

Bottom Navigation:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🏠 หน้าแรก | 📍 ค้นหา | ⚡ ชาร์จ | 📋 ประวัติ | 👤 โปรไฟล์
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## 18. Priority Matrix & Roadmap

### สรุปจำนวน Function

| หมวด | Must 🔴 | Should 🟡 | Nice 🟢 | รวม |
|------|---------|-----------|---------|-----|
| Authentication & Onboarding | 10 | 5 | 3 | 18 |
| Home Screen | 8 | 1 | 0 | 9 |
| Station Finder (Map + List + Detail) | 16 | 10 | 3 | 29 |
| Charger Status | 6 | 2 | 0 | 8 |
| Charging Session | 15 | 5 | 2 | 22 |
| Payment & Wallet | 8 | 8 | 2 | 18 |
| History & Stats | 9 | 9 | 1 | 19 |
| Profile & Vehicle | 11 | 5 | 2 | 18 |
| Loyalty & Rewards | 0 | 7 | 3 | 10 |
| Notifications | 5 | 4 | 1 | 10 |
| Settings | 3 | 6 | 3 | 12 |
| Customer Support | 1 | 5 | 2 | 8 |
| **รวมทั้งหมด** | **92** | **67** | **22** | **181** |

### Roadmap การพัฒนา

```
┌─────────────────────────────────────────────────┐
│  Phase 1 — MVP (3 เดือน)                        │
│  92 Must-Have Functions                         │
├─────────────────────────────────────────────────┤
│  ✅ Auth: Register, OTP, Login, Biometric       │
│  ✅ Home: Dashboard, Active Session Banner      │
│  ✅ Map: Nearby Stations, Pin Status            │
│  ✅ Station Detail: Chargers, Price             │
│  ✅ Charging: Scan QR, Start, Live Monitor      │
│  ✅ Session: Stop, Summary                      │
│  ✅ Wallet: Balance, Top-up QR PromptPay        │
│  ✅ History: Transaction List + Detail          │
│  ✅ Profile: View/Edit + Vehicle CRUD           │
│  ✅ Notifications: Push (Charge complete/Fault) │
│  ✅ Language: TH/EN/ZH                          │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│  Phase 2 — Enhanced (3 เดือน)                   │
│  67 Should-Have Functions                       │
├─────────────────────────────────────────────────┤
│  🔄 Loyalty Tier + Points System                │
│  🔄 PDF Receipt + Share                         │
│  🔄 Station Reviews & Ratings                   │
│  🔄 Credit Card Payment                         │
│  🔄 CO₂ & Fuel Cost Saved Stats               │
│  🔄 Support Tickets System                      │
│  🔄 Notification Preferences                    │
│  🔄 Station Favorites                           │
│  🔄 Monthly Charts / My Stats                   │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│  Phase 3 — Premium (6 เดือน)                    │
│  22 Nice-To-Have Functions                      │
├─────────────────────────────────────────────────┤
│  🔮 Social Login (LINE / Google)                │
│  🔮 Referral & Coupon System                    │
│  🔮 License Plate OCR Camera                   │
│  🔮 Charger Queue / Reservation                 │
│  🔮 Auto Top-up Wallet                          │
│  🔮 Charging Streak Gamification               │
│  🔮 TrueMoney Wallet Payment                    │
│  🔮 Offline Map Cache (PWA)                     │
└─────────────────────────────────────────────────┘
```

### Backend Work Required (สรุปงานที่ต้องทำ)

| งาน | ไฟล์/Table ที่แก้ | ประมาณเวลา |
|-----|-----------------|-----------|
| เพิ่ม `role='customer'` ใน users | `users` table + `auth.php` | 1 วัน |
| สร้าง Customer Auth API | `/api/customer/auth/*.php` | 3 วัน |
| สร้าง JWT Token System | `/includes/jwt.php` | 2 วัน |
| Station Nearby (Geolocation) | `/api/customer/stations/nearby.php` | 2 วัน |
| Session Start/Stop API | `/api/customer/sessions/*.php` | 3 วัน |
| Wallet System | Table + `/api/customer/wallet/*.php` | 4 วัน |
| Push Notification | FCM Integration + `/api/customer/notifications.php` | 3 วัน |
| PDF Receipt Generator | TCPDF/DomPDF | 2 วัน |
| เพิ่ม Tables ใหม่ 7 ตาราง | SQL Migration | 1 วัน |
| **รวม Backend** | | **~21 วันทำงาน** |

---

## หมายเหตุสำคัญสำหรับนักพัฒนา

### Security ที่ต้องระวัง
```
1. JWT Token ต้อง Sign ด้วย Secret Key (HS256 อย่างน้อย)
2. ทุก API ต้อง Verify Token ก่อน ไม่ใช้ Session Cookie
3. Rate Limiting สำหรับ Login / OTP Endpoints
4. Connector ID ต้อง Validate ว่า Status='Ready to use' ก่อน Start
5. Payment Amount ต้อง Verify ทั้ง Client และ Server
6. HTTPS บังคับสำหรับทุก API Call
7. Customer ต้องเห็นเฉพาะ Transaction ของตัวเอง (customer_id Filter)
```

### Performance Tips
```
1. ใช้ Redis Cache สำหรับ Station Status (TTL 30 วินาที)
2. Index: transactions(customer_id, start_time), stations(lat, lng)
3. Haversine Distance ให้ Limit ด้วย Bounding Box ก่อน (เร็วกว่า)
4. Pagination ทุก List API (ไม่ return ทั้งหมด)
5. Image Resize ก่อน Upload (max 800px)
```

### ข้อมูลที่เชื่อมจาก Backend ปัจจุบัน
```
stations        ✅ พร้อมใช้ (ต้องเพิ่ม photo_url, opening_hours)
chargers        ✅ พร้อมใช้
connectors      ✅ พร้อมใช้ (status real-time)
transactions    ✅ พร้อมใช้ (มี customer_id FK แล้ว)
customers       ✅ พร้อมใช้ (ต้องเพิ่ม avatar_url)
car_types       ✅ พร้อมใช้
service_fee_settings ✅ พร้อมใช้
meter_values    ⚠️  ยังไม่ได้ใช้ (ต้องเพิ่ม PHP logic เขียน)
users           ⚠️  ต้องเพิ่ม role='customer' + JWT support
wallet_accounts ❌  ต้องสร้างใหม่
notifications   ❌  ต้องสร้างใหม่
customer_vehicles ❌ ต้องสร้างใหม่
```

---

*เอกสารนี้จัดทำโดย: Senior System Architect & Mobile App Designer*
*วันที่: มีนาคม 2569 (2026)*
*Version: 1.0*
*สำหรับโครงการ: CSMS Customer Mobile App*
*เชื่อมต่อกับ: CSMS PHP Backend (MySQL + PDO + REST API)*
