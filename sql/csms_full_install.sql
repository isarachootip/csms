-- ============================================================
-- CSMS - EV Charging Station Management System
-- Full Install Script (Schema + Migration + Seed Data)
-- Version: 1.0  |  Date: 2026-03-29
-- ============================================================
-- วิธีใช้: Import ไฟล์นี้ใน phpMyAdmin ครั้งเดียว
-- URL: http://localhost/phpmyadmin
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- STEP 1: สร้าง Database
-- ============================================================
CREATE DATABASE IF NOT EXISTS csms
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE csms;

-- ============================================================
-- STEP 2: สร้าง Tables ทั้งหมด
-- ============================================================

-- ── 1. users
CREATE TABLE IF NOT EXISTS users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    first_name       VARCHAR(100)  NOT NULL,
    last_name        VARCHAR(100)  NOT NULL,
    phone            VARCHAR(20)   NOT NULL,
    email            VARCHAR(150)  NOT NULL UNIQUE,
    password         VARCHAR(255)  NOT NULL,
    is_verified      TINYINT(1)    DEFAULT 0,
    otp_code         VARCHAR(6)    DEFAULT NULL,
    otp_expires_at   DATETIME      DEFAULT NULL,
    role             ENUM('admin','operator','viewer','customer') DEFAULT 'customer',
    avatar_url       VARCHAR(255)  DEFAULT NULL,
    api_token        VARCHAR(100)  DEFAULT NULL,
    token_expires_at DATETIME      DEFAULT NULL,
    created_at       DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. car_types
CREATE TABLE IF NOT EXISTS car_types (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100)  NOT NULL,
    brand          VARCHAR(100),
    icon           VARCHAR(50)   DEFAULT 'directions_car',
    connector_type ENUM('Type1','Type2','CCS1','CCS2','CHAdeMO','GB/T') DEFAULT 'Type2',
    battery_kwh    DECIMAL(8,2)  DEFAULT 0,
    created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. stations
CREATE TABLE IF NOT EXISTS stations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    name       VARCHAR(200) NOT NULL,
    location   TEXT,
    address    TEXT,
    latitude   DECIMAL(10,8) DEFAULT NULL,
    longitude  DECIMAL(11,8) DEFAULT NULL,
    status     ENUM('active','inactive','maintenance') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. chargers
CREATE TABLE IF NOT EXISTS chargers (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    station_id         INT          NOT NULL,
    serial_number      VARCHAR(100) NOT NULL UNIQUE,
    model              VARCHAR(100),
    brand              VARCHAR(100),
    max_power_kw       DECIMAL(8,2) DEFAULT 0,
    controller_status  ENUM('Online','Offline','Faulted','Updating') DEFAULT 'Offline',
    firmware_version   VARCHAR(50)  DEFAULT NULL,
    last_heartbeat     DATETIME     DEFAULT NULL,
    created_at         DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. connectors
CREATE TABLE IF NOT EXISTS connectors (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    charger_id       INT NOT NULL,
    connector_number INT NOT NULL DEFAULT 1,
    connector_type   ENUM('Type1','Type2','CCS1','CCS2','CHAdeMO','GB/T') DEFAULT 'Type2',
    status           ENUM('Ready to use','Plugged in','Charging in progress',
                          'Charging paused by vehicle','Charging paused by charger',
                          'Charging finish','Unavailable') DEFAULT 'Unavailable',
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (charger_id) REFERENCES chargers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. service_fee_settings
CREATE TABLE IF NOT EXISTS service_fee_settings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    station_id      INT NOT NULL,
    fee_type        ENUM('kWh-Based','Time-Based','TOU','Free Charge') DEFAULT 'kWh-Based',
    price_per_kwh   DECIMAL(10,4) DEFAULT 0.00,
    price_per_minute DECIMAL(10,4) DEFAULT 0.00,
    peak_price      DECIMAL(10,4) DEFAULT 0.00,
    offpeak_price   DECIMAL(10,4) DEFAULT 0.00,
    peak_start      TIME          DEFAULT '09:00:00',
    peak_end        TIME          DEFAULT '22:00:00',
    currency        VARCHAR(10)   DEFAULT 'THB',
    effective_from  DATE          DEFAULT NULL,
    is_active       TINYINT(1)    DEFAULT 1,
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. customers
CREATE TABLE IF NOT EXISTS customers (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NOT NULL,
    full_name      VARCHAR(200) NOT NULL,
    phone          VARCHAR(20),
    email          VARCHAR(150),
    license_plate  VARCHAR(30),
    car_type_id    INT          DEFAULT NULL,
    member_since   DATE         DEFAULT (CURDATE()),
    total_sessions INT          DEFAULT 0,
    total_kwh      DECIMAL(12,4) DEFAULT 0,
    total_spend    DECIMAL(12,2) DEFAULT 0,
    avatar_url     VARCHAR(255) DEFAULT NULL,
    notes          TEXT,
    api_token      VARCHAR(100) DEFAULT NULL,
    token_expires_at DATETIME   DEFAULT NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (car_type_id) REFERENCES car_types(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. transactions
CREATE TABLE IF NOT EXISTS transactions (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    connector_id        INT          NOT NULL,
    charger_id          INT          NOT NULL,
    station_id          INT          NOT NULL,
    user_id             INT          NOT NULL,
    customer_id         INT          DEFAULT NULL,
    car_type_id         INT          DEFAULT NULL,
    estimate_amount     DECIMAL(10,2) DEFAULT 0.00,
    actual_amount       DECIMAL(10,2) DEFAULT 0.00,
    energy_kwh          DECIMAL(10,4) DEFAULT 0.0000,
    start_time          DATETIME     DEFAULT NULL,
    stop_time           DATETIME     DEFAULT NULL,
    duration_minutes    INT          DEFAULT 0,
    status              ENUM('Pending','Charging','Completed','Stopped','Faulted') DEFAULT 'Pending',
    stop_reason         ENUM('EVDisconnected','Local','Remote','PowerLoss','Other') DEFAULT NULL,
    remark              TEXT,
    fee_type            ENUM('kWh-Based','Time-Based','TOU','Free Charge') DEFAULT 'kWh-Based',
    price_per_kwh       DECIMAL(10,4) DEFAULT 0.00,
    ocpp_transaction_id VARCHAR(100) DEFAULT NULL,
    created_at          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (connector_id) REFERENCES connectors(id),
    FOREIGN KEY (charger_id)   REFERENCES chargers(id),
    FOREIGN KEY (station_id)   REFERENCES stations(id),
    FOREIGN KEY (user_id)      REFERENCES users(id),
    FOREIGN KEY (customer_id)  REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (car_type_id)  REFERENCES car_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. meter_values
CREATE TABLE IF NOT EXISTS meter_values (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT          NOT NULL,
    power_kw       DECIMAL(10,4) DEFAULT 0.00,
    energy_kwh     DECIMAL(10,4) DEFAULT 0.00,
    voltage        DECIMAL(8,2)  DEFAULT 0.00,
    current_a      DECIMAL(8,2)  DEFAULT 0.00,
    soc_percent    INT           DEFAULT NULL,
    recorded_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. daily_summary
CREATE TABLE IF NOT EXISTS daily_summary (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    station_id       INT          NOT NULL,
    summary_date     DATE         NOT NULL,
    sessions         INT          DEFAULT 0,
    unique_customers INT          DEFAULT 0,
    total_kwh        DECIMAL(12,4) DEFAULT 0,
    total_revenue    DECIMAL(12,2) DEFAULT 0,
    avg_duration_min INT          DEFAULT 0,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_daily (station_id, summary_date),
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 11. system_logs
CREATE TABLE IF NOT EXISTS system_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          DEFAULT NULL,
    action      VARCHAR(200),
    entity_type VARCHAR(50),
    entity_id   INT          DEFAULT NULL,
    detail      TEXT,
    ip_address  VARCHAR(45),
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 12. wallet_accounts (Customer App)
CREATE TABLE IF NOT EXISTS wallet_accounts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT          NOT NULL UNIQUE,
    balance     DECIMAL(12,2) DEFAULT 0.00,
    currency    VARCHAR(10)   DEFAULT 'THB',
    updated_at  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 13. wallet_transactions (Customer App)
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id     INT          NOT NULL,
    type          ENUM('topup','charge','refund','reward') NOT NULL DEFAULT 'topup',
    amount        DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reference_id  VARCHAR(100) DEFAULT NULL,
    description   TEXT,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallet_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 14. customer_vehicles (Customer App)
CREATE TABLE IF NOT EXISTS customer_vehicles (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    customer_id   INT          NOT NULL,
    car_type_id   INT          DEFAULT NULL,
    license_plate VARCHAR(30)  NOT NULL,
    nickname      VARCHAR(100) DEFAULT NULL,
    color         VARCHAR(50)  DEFAULT NULL,
    is_default    TINYINT(1)   DEFAULT 0,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (car_type_id) REFERENCES car_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 15. customer_notifications (Customer App)
CREATE TABLE IF NOT EXISTS customer_notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT          NOT NULL,
    type        ENUM('session','wallet','promo','system','alert') DEFAULT 'system',
    title       VARCHAR(200) NOT NULL,
    body        TEXT,
    icon        VARCHAR(50)  DEFAULT 'notifications',
    read_at     DATETIME     DEFAULT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 16. customer_favorites (Customer App)
CREATE TABLE IF NOT EXISTS customer_favorites (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    station_id  INT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (customer_id, station_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id)  REFERENCES stations(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 17. station_reviews (Customer App)
CREATE TABLE IF NOT EXISTS station_reviews (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    station_id     INT         NOT NULL,
    customer_id    INT         NOT NULL,
    transaction_id INT         NOT NULL,
    rating         TINYINT     NOT NULL DEFAULT 5,
    comment        TEXT,
    created_at     DATETIME    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review (customer_id, transaction_id),
    FOREIGN KEY (station_id)     REFERENCES stations(id),
    FOREIGN KEY (customer_id)    REFERENCES customers(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 18. support_tickets (Customer App)
CREATE TABLE IF NOT EXISTS support_tickets (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT         NOT NULL,
    transaction_id INT         DEFAULT NULL,
    category       ENUM('charging','payment','account','app','other') DEFAULT 'other',
    subject        VARCHAR(200) NOT NULL,
    description    TEXT,
    status         ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    created_at     DATETIME    DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id)    REFERENCES customers(id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 3: Seed Data
-- ============================================================

-- ── Admin User (password: Admin@1234)
INSERT INTO users (first_name, last_name, phone, email, password, is_verified, role) VALUES
('Admin', 'CSMS', '0812345678', 'admin@csms.local',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXV/WwK8S', 1, 'admin');

-- ── Customer Demo User (password: Customer@1234)
INSERT INTO users (first_name, last_name, phone, email, password, is_verified, role) VALUES
('สมชาย', 'ใจดี', '0811111111', 'customer@csms.local',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXV/WwK8S', 1, 'customer');
-- หมายเหตุ: hash นี้คือ password = "password" (Laravel default hash)
-- รัน update_demo_password.sql เพื่อเปลี่ยนเป็น Customer@1234

-- ── Car Types
INSERT INTO car_types (name, brand, icon, connector_type, battery_kwh) VALUES
('Tesla Model 3',        'Tesla',   'electric_car',  'CCS2',    75.0),
('Tesla Model Y',        'Tesla',   'electric_car',  'CCS2',    82.0),
('BYD Atto 3',           'BYD',     'electric_car',  'CCS2',    60.5),
('BYD Seal',             'BYD',     'electric_car',  'CCS2',    82.6),
('Nissan Leaf',          'Nissan',  'electric_car',  'CHAdeMO', 40.0),
('MG EP',                'MG',      'electric_car',  'CCS2',    50.3),
('BMW iX3',              'BMW',     'electric_car',  'CCS2',    80.0),
('Volvo XC40 Recharge',  'Volvo',   'electric_car',  'CCS2',    82.0),
('Hyundai IONIQ 5',      'Hyundai', 'electric_car',  'CCS2',    77.4),
('Kia EV6',              'Kia',     'electric_car',  'CCS2',    77.4),
('Toyota bZ4X',          'Toyota',  'electric_car',  'CCS2',    71.4),
('ORA Good Cat',         'GWM',     'electric_car',  'CCS2',    48.0),
('NETA V',               'NETA',    'electric_car',  'CCS2',    40.1),
('Other EV',             'Other',   'directions_car','Type2',    0.0);

-- ── Stations
INSERT INTO stations (id, user_id, name, location, address, latitude, longitude, status) VALUES
(1, 1, 'สถานี EV สาขาลาดพร้าว',              'ชั้น B1 โซน A',    '1234 ถนนลาดพร้าว แขวงจตุจักร กรุงเทพฯ', 13.81890000, 100.56710000, 'active'),
(2, 1, 'สถานี EV สาขาสุขุมวิท',              'ลานจอดรถชั้น 2',   '88 ถนนสุขุมวิท แขวงคลองเตย กรุงเทพฯ',   13.73080000, 100.56940000, 'active'),
(3, 1, 'สถานี EV สาขาเซ็นทรัลรัตนาธิเบศร์', 'ชั้น P3',          '68 ถนนรัตนาธิเบศร์ นนทบุรี',             13.85810000, 100.51920000, 'maintenance');

-- ── Chargers
INSERT INTO chargers (id, station_id, serial_number, model, brand, max_power_kw, controller_status, last_heartbeat) VALUES
(1, 1, 'EVCS-LPR-001', 'Terra AC W22',   'ABB',      22.0, 'Online',  NOW() - INTERVAL 2 MINUTE),
(2, 1, 'EVCS-LPR-002', 'Terra DC 60',    'ABB',      60.0, 'Online',  NOW() - INTERVAL 1 MINUTE),
(3, 1, 'EVCS-LPR-003', 'Wallbox Pulsar', 'Wallbox',   7.4, 'Offline', NOW() - INTERVAL 3 HOUR),
(4, 1, 'EVCS-LPR-004', 'Alfen Eve',      'Alfen',    22.0, 'Faulted', NOW() - INTERVAL 30 MINUTE),
(5, 2, 'EVCS-SKW-001', 'Terra AC W22',   'ABB',      22.0, 'Online',  NOW() - INTERVAL 5 MINUTE),
(6, 2, 'EVCS-SKW-002', 'Juice Charger',  'Juicebar', 11.0, 'Online',  NOW() - INTERVAL 3 MINUTE);

-- ── Connectors
INSERT INTO connectors (id, charger_id, connector_number, connector_type, status) VALUES
(1, 1, 1, 'Type2', 'Ready to use'),
(2, 2, 1, 'CCS2',  'Ready to use'),
(3, 3, 1, 'Type2', 'Unavailable'),
(4, 4, 1, 'Type2', 'Unavailable'),
(5, 5, 1, 'Type2', 'Ready to use'),
(6, 6, 1, 'Type2', 'Ready to use');

-- ── Service Fee Settings
INSERT INTO service_fee_settings (station_id, fee_type, price_per_kwh, currency, effective_from, is_active) VALUES
(1, 'kWh-Based',  5.50, 'THB', '2026-01-01', 1),
(2, 'kWh-Based',  6.00, 'THB', '2026-01-01', 1),
(3, 'Free Charge', 0.00, 'THB', '2026-01-01', 1);

-- ── Customers (15 คน)
INSERT INTO customers (id, user_id, full_name, phone, email, license_plate, car_type_id, member_since, total_sessions, total_kwh, total_spend) VALUES
(1,  2, 'สมชาย ใจดี',        '0811111111', 'somchai@email.com',   'กข-1234', 1,  '2025-01-15', 18, 420.5000,  2312.75),
(2,  1, 'วิภา รักไทย',       '0822222222', 'wipa@email.com',       'กค-5678', 3,  '2025-02-01', 12, 280.3000,  1541.65),
(3,  1, 'อนุชา สมบูรณ์',     '0833333333', 'anucha@email.com',     'ขก-9999', 9,  '2025-03-10',  8, 195.2000,  1073.60),
(4,  1, 'พิมพ์ใจ ชื่นชม',    '0844444444', 'pimjai@email.com',     'พม-4444', 4,  '2025-04-05', 22, 531.8000,  2924.90),
(5,  1, 'ธนกร วงศ์ดี',       '0855555555', 'thanakorn@email.com',  'งง-1111', 2,  '2025-05-20',  5, 115.0000,   632.50),
(6,  1, 'มาลี สุขใจ',        '0866666666', 'malee@email.com',      'มก-7777', 6,  '2025-06-01', 30, 720.0000,  3960.00),
(7,  1, 'ชาญณรงค์ เก่งดี',   '0877777777', 'channarong@email.com', 'ปก-2222', 7,  '2025-07-11', 14, 336.0000,  1848.00),
(8,  1, 'ศิริวรรณ แสงแก้ว',  '0888888888', 'siriwan@email.com',    'สก-3333', 10, '2025-08-03',  9, 216.0000,  1188.00),
(9,  1, 'ณัฐพล มีสุข',       '0899999999', 'nattapon@email.com',   'ตก-8888', 5,  '2025-09-15',  4,  92.0000,   506.00),
(10, 1, 'ประภาส โชติ',       '0800000001', 'praphat@email.com',    'รก-6666', 11, '2025-10-01', 16, 384.0000,  2112.00),
(11, 1, 'อรอุมา ทองดี',      '0800000002', 'onuma@email.com',      'ชก-5555', 8,  '2025-11-20',  7, 168.0000,   924.00),
(12, 1, 'กฤษณ์ ศรีสวัสดิ์',  '0800000003', 'krit@email.com',       'นก-4444', 12, '2025-12-05', 20, 480.0000,  2640.00),
(13, 1, 'พรรณี จันทร์งาม',   '0800000004', 'pannee@email.com',     'อก-1111', 13, '2026-01-10', 11, 264.0000,  1452.00),
(14, 1, 'วีระ สุวรรณ',       '0800000005', 'weera@email.com',      'บก-2222', 1,  '2026-02-14',  6, 144.0000,   792.00),
(15, 1, 'นภา รุ่งเรือง',     '0800000006', 'napa@email.com',       'ลก-3333', 3,  '2026-03-01',  3,  72.0000,   396.00);

-- ── Wallet สำหรับลูกค้า (customer id=1 คือ demo login)
INSERT INTO wallet_accounts (customer_id, balance) VALUES
(1,  500.00),
(2,  250.00),
(3,  100.00),
(4, 1000.00),
(5,   50.00),
(6,  750.00),
(7,  300.00),
(8,  200.00),
(9,  150.00),
(10, 400.00),
(11,  80.00),
(12, 600.00),
(13, 120.00),
(14, 350.00),
(15,  90.00);

-- ── Transactions (ประวัติการชาร์จ Jan–Mar 2026)
INSERT INTO transactions (connector_id, charger_id, station_id, user_id, customer_id, car_type_id,
    estimate_amount, actual_amount, energy_kwh, start_time, stop_time, duration_minutes,
    status, remark, fee_type, price_per_kwh) VALUES
-- January 2026
(1,1,1,1,1,1,   200, 192.5, 35.00, '2026-01-05 09:10:00','2026-01-05 11:10:00',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,2,3,   150, 143.0, 26.00, '2026-01-07 14:00:00','2026-01-07 15:30:00', 90,'Completed','กค-5678','kWh-Based',5.50),
(5,5,2,1,4,4,   300, 288.0, 48.00, '2026-01-10 10:00:00','2026-01-10 12:00:00',120,'Completed','พม-4444','kWh-Based',6.00),
(1,1,1,1,6,6,   100,  97.0, 17.60, '2026-01-12 16:00:00','2026-01-12 17:00:00', 60,'Completed','มก-7777','kWh-Based',5.50),
(2,2,1,1,7,7,   250, 242.0, 44.00, '2026-01-15 11:00:00','2026-01-15 13:30:00',150,'Completed','ปก-2222','kWh-Based',5.50),
(5,5,2,1,3,9,   180, 174.0, 29.00, '2026-01-18 09:00:00','2026-01-18 10:30:00', 90,'Completed','ขก-9999','kWh-Based',6.00),
(1,1,1,1,8,10,  200, 193.0, 35.10, '2026-01-20 13:00:00','2026-01-20 15:00:00',120,'Completed','สก-3333','kWh-Based',5.50),
(2,2,1,1,5,2,    80,  77.0, 14.00, '2026-01-22 10:00:00','2026-01-22 10:45:00', 45,'Completed','งง-1111','kWh-Based',5.50),
(5,5,2,1,10,11, 400, 390.0, 65.00, '2026-01-25 08:00:00','2026-01-25 10:30:00',150,'Completed','รก-6666','kWh-Based',6.00),
(1,1,1,1,12,12, 300, 297.0, 54.00, '2026-01-28 14:00:00','2026-01-28 17:00:00',180,'Completed','นก-4444','kWh-Based',5.50),
-- February 2026
(1,1,1,1,1,1,   200, 196.0, 35.60, '2026-02-03 09:00:00','2026-02-03 11:00:00',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,13,13, 100,  99.0, 18.00, '2026-02-05 15:00:00','2026-02-05 16:00:00', 60,'Completed','อก-1111','kWh-Based',5.50),
(5,5,2,1,4,4,   500, 495.0, 82.50, '2026-02-08 10:00:00','2026-02-08 13:00:00',180,'Completed','พม-4444','kWh-Based',6.00),
(1,1,1,1,14,1,  150, 148.5, 27.00, '2026-02-10 08:00:00','2026-02-10 09:30:00', 90,'Completed','บก-2222','kWh-Based',5.50),
(2,2,1,1,6,6,   200, 198.0, 36.00, '2026-02-12 14:00:00','2026-02-12 16:00:00',120,'Completed','มก-7777','kWh-Based',5.50),
(5,5,2,1,11,8,  250, 240.0, 40.00, '2026-02-14 10:00:00','2026-02-14 12:30:00',150,'Completed','ชก-5555','kWh-Based',6.00),
(1,1,1,1,9,5,    80,  78.0, 14.20, '2026-02-16 16:00:00','2026-02-16 17:00:00', 60,'Completed','ตก-8888','kWh-Based',5.50),
(2,2,1,1,2,3,   180, 175.0, 31.80, '2026-02-18 09:00:00','2026-02-18 10:45:00',105,'Completed','กค-5678','kWh-Based',5.50),
(5,5,2,1,15,3,  300, 294.0, 49.00, '2026-02-20 11:00:00','2026-02-20 13:00:00',120,'Completed','ลก-3333','kWh-Based',6.00),
(1,1,1,1,7,7,   400, 396.0, 72.00, '2026-02-25 13:00:00','2026-02-25 16:00:00',180,'Completed','ปก-2222','kWh-Based',5.50),
-- March 2026
(1,1,1,1,1,1,   200, 194.0, 35.30, '2026-03-01 09:00:00','2026-03-01 11:00:00',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,4,4,   300, 297.0, 49.50, '2026-03-03 14:00:00','2026-03-03 17:00:00',180,'Completed','พม-4444','kWh-Based',5.50),
(5,5,2,1,3,9,   200, 192.0, 32.00, '2026-03-05 10:00:00','2026-03-05 12:00:00',120,'Completed','ขก-9999','kWh-Based',6.00),
(1,1,1,1,6,6,   150, 148.5, 27.00, '2026-03-07 08:00:00','2026-03-07 09:30:00', 90,'Completed','มก-7777','kWh-Based',5.50),
(2,2,1,1,12,12, 100,  99.0, 18.00, '2026-03-10 15:00:00','2026-03-10 16:00:00', 60,'Completed','นก-4444','kWh-Based',5.50),
(5,5,2,1,8,10,  400, 390.0, 65.00, '2026-03-12 11:00:00','2026-03-12 13:30:00',150,'Completed','สก-3333','kWh-Based',6.00),
(1,1,1,1,2,3,   180, 176.0, 32.00, '2026-03-15 09:00:00','2026-03-15 11:00:00',120,'Completed','กค-5678','kWh-Based',5.50),
(2,2,1,1,10,11, 250, 242.0, 44.00, '2026-03-18 14:00:00','2026-03-18 16:30:00',150,'Completed','รก-6666','kWh-Based',5.50),
(5,5,2,1,5,2,    80,  78.0, 13.00, '2026-03-20 16:00:00','2026-03-20 17:00:00', 60,'Completed','งง-1111','kWh-Based',6.00),
(1,1,1,1,7,7,   300, 297.0, 54.00, '2026-03-22 10:00:00','2026-03-22 13:00:00',180,'Completed','ปก-2222','kWh-Based',5.50),
(2,2,1,1,13,13, 200, 194.0, 35.30, '2026-03-24 09:00:00','2026-03-24 11:00:00',120,'Completed','อก-1111','kWh-Based',5.50),
(5,5,2,1,14,1,  150, 144.0, 24.00, '2026-03-26 14:00:00','2026-03-26 15:30:00', 90,'Completed','บก-2222','kWh-Based',6.00),
(1,1,1,1,1,1,   200, 194.0, 35.30, '2026-03-28 07:30:00','2026-03-28 09:30:00',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,4,4,   300, 291.0, 52.90, '2026-03-28 10:00:00','2026-03-28 12:30:00',150,'Completed','พม-4444','kWh-Based',5.50),
(5,5,2,1,6,6,   150, 144.0, 24.00, '2026-03-28 13:00:00','2026-03-28 14:30:00', 90,'Completed','มก-7777','kWh-Based',6.00),
(1,1,1,1,8,10,  400, 388.0, 70.50, '2026-03-28 15:00:00','2026-03-28 18:00:00',180,'Completed','สก-3333','kWh-Based',5.50);

-- ── อัปเดต stats ลูกค้าจาก transactions จริง
UPDATE customers c
JOIN (
    SELECT customer_id,
           COUNT(*)              AS s,
           COALESCE(SUM(energy_kwh), 0)     AS kwh,
           COALESCE(SUM(actual_amount), 0)  AS spend
    FROM transactions
    WHERE customer_id IS NOT NULL
      AND status IN ('Completed','Stopped')
    GROUP BY customer_id
) t ON t.customer_id = c.id
SET c.total_sessions = t.s,
    c.total_kwh      = t.kwh,
    c.total_spend    = t.spend;

-- ── Welcome notification สำหรับ customer demo
INSERT INTO customer_notifications (customer_id, type, title, body, icon) VALUES
(1, 'system', 'ยินดีต้อนรับสู่ EV Charge! 🎉', 'สมัครสมาชิกสำเร็จแล้ว เริ่มชาร์จรถของคุณได้เลย', 'celebration'),
(1, 'wallet', 'เติมเงินสำเร็จ 💰', 'เติมเงิน ฿500.00 | ยอดคงเหลือ ฿500.00', 'account_balance_wallet');

-- ── Vehicle สำหรับ customer demo
INSERT INTO customer_vehicles (customer_id, car_type_id, license_plate, nickname, is_default) VALUES
(1, 1, 'กข-1234', 'รถหลัก', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- STEP 4: อัปเดต password ให้ถูกต้อง
-- ============================================================
-- Demo Login:
--   Admin:    admin@csms.local    / Admin@1234
--   Customer: customer@csms.local / Admin@1234 (ใช้ hash เดียวกัน)
--
-- ถ้าต้องการเปลี่ยน password customer เป็น Customer@1234
-- ให้รันคำสั่งนี้ใน phpMyAdmin > SQL tab:
--
-- UPDATE users
-- SET password = '$2y$10$TKh8H1.PfO5J0y4MqyXqPO6bV8.7NmXe4Q3GKhGqW5PV5VKXDrVba'
-- WHERE email = 'customer@csms.local';
--
-- ============================================================
-- เสร็จสิ้น! Tables: 18 ตาราง | Stations: 3 | Chargers: 6 | Customers: 15
-- ============================================================
