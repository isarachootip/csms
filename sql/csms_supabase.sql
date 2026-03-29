-- ============================================================
-- CSMS - EV Charging Station Management System
-- Supabase (PostgreSQL) Install Script
-- Version: 1.0  |  Date: 2026-03-29
-- ============================================================
-- วิธีใช้:
--   1. เปิด Supabase Dashboard → SQL Editor
--   2. วาง SQL นี้ทั้งหมด → กด "Run"
-- ============================================================

-- ============================================================
-- STEP 1: สร้าง ENUM Types (PostgreSQL ใช้ TYPE แทน ENUM inline)
-- ============================================================

DO $$ BEGIN
    CREATE TYPE user_role         AS ENUM ('admin','operator','viewer','customer');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE station_status    AS ENUM ('active','inactive','maintenance');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE controller_status AS ENUM ('Online','Offline','Faulted','Updating');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE connector_type_enum AS ENUM ('Type1','Type2','CCS1','CCS2','CHAdeMO','GB/T');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE connector_status  AS ENUM (
        'Ready to use','Plugged in','Charging in progress',
        'Charging paused by vehicle','Charging paused by charger',
        'Charging finish','Unavailable'
    );
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE fee_type_enum     AS ENUM ('kWh-Based','Time-Based','TOU','Free Charge');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE transaction_status AS ENUM ('Pending','Charging','Completed','Stopped','Faulted');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE stop_reason_enum  AS ENUM ('EVDisconnected','Local','Remote','PowerLoss','Other');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE wallet_tx_type    AS ENUM ('topup','charge','refund','reward');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE notif_type        AS ENUM ('session','wallet','promo','system','alert');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE ticket_category   AS ENUM ('charging','payment','account','app','other');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE ticket_status     AS ENUM ('open','in_progress','resolved','closed');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- ============================================================
-- STEP 2: สร้าง Tables
-- ============================================================

-- ── 1. users
CREATE TABLE IF NOT EXISTS users (
    id               SERIAL PRIMARY KEY,
    first_name       VARCHAR(100)  NOT NULL,
    last_name        VARCHAR(100)  NOT NULL,
    phone            VARCHAR(20)   NOT NULL,
    email            VARCHAR(150)  NOT NULL UNIQUE,
    password         VARCHAR(255)  NOT NULL,
    is_verified      BOOLEAN       DEFAULT FALSE,
    otp_code         VARCHAR(6)    DEFAULT NULL,
    otp_expires_at   TIMESTAMPTZ   DEFAULT NULL,
    role             user_role     DEFAULT 'customer',
    avatar_url       VARCHAR(255)  DEFAULT NULL,
    api_token        VARCHAR(100)  DEFAULT NULL,
    token_expires_at TIMESTAMPTZ   DEFAULT NULL,
    created_at       TIMESTAMPTZ   DEFAULT NOW(),
    updated_at       TIMESTAMPTZ   DEFAULT NOW()
);

-- ── 2. car_types
CREATE TABLE IF NOT EXISTS car_types (
    id             SERIAL PRIMARY KEY,
    name           VARCHAR(100)        NOT NULL,
    brand          VARCHAR(100),
    icon           VARCHAR(50)         DEFAULT 'directions_car',
    connector_type connector_type_enum DEFAULT 'Type2',
    battery_kwh    DECIMAL(8,2)        DEFAULT 0,
    created_at     TIMESTAMPTZ         DEFAULT NOW()
);

-- ── 3. stations
CREATE TABLE IF NOT EXISTS stations (
    id         SERIAL PRIMARY KEY,
    user_id    INT             NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name       VARCHAR(200)    NOT NULL,
    location   TEXT,
    address    TEXT,
    latitude   DECIMAL(10,8)  DEFAULT NULL,
    longitude  DECIMAL(11,8)  DEFAULT NULL,
    status     station_status  DEFAULT 'active',
    created_at TIMESTAMPTZ    DEFAULT NOW(),
    updated_at TIMESTAMPTZ    DEFAULT NOW()
);

-- ── 4. chargers
CREATE TABLE IF NOT EXISTS chargers (
    id                SERIAL PRIMARY KEY,
    station_id        INT                NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    serial_number     VARCHAR(100)       NOT NULL UNIQUE,
    model             VARCHAR(100),
    brand             VARCHAR(100),
    max_power_kw      DECIMAL(8,2)       DEFAULT 0,
    controller_status controller_status  DEFAULT 'Offline',
    firmware_version  VARCHAR(50)        DEFAULT NULL,
    last_heartbeat    TIMESTAMPTZ        DEFAULT NULL,
    created_at        TIMESTAMPTZ        DEFAULT NOW(),
    updated_at        TIMESTAMPTZ        DEFAULT NOW()
);

-- ── 5. connectors
CREATE TABLE IF NOT EXISTS connectors (
    id               SERIAL PRIMARY KEY,
    charger_id       INT                  NOT NULL REFERENCES chargers(id) ON DELETE CASCADE,
    connector_number INT                  NOT NULL DEFAULT 1,
    connector_type   connector_type_enum  DEFAULT 'Type2',
    status           connector_status     DEFAULT 'Unavailable',
    created_at       TIMESTAMPTZ          DEFAULT NOW(),
    updated_at       TIMESTAMPTZ          DEFAULT NOW()
);

-- ── 6. service_fee_settings
CREATE TABLE IF NOT EXISTS service_fee_settings (
    id               SERIAL PRIMARY KEY,
    station_id       INT           NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    fee_type         fee_type_enum DEFAULT 'kWh-Based',
    price_per_kwh    DECIMAL(10,4) DEFAULT 0.00,
    price_per_minute DECIMAL(10,4) DEFAULT 0.00,
    peak_price       DECIMAL(10,4) DEFAULT 0.00,
    offpeak_price    DECIMAL(10,4) DEFAULT 0.00,
    peak_start       TIME          DEFAULT '09:00:00',
    peak_end         TIME          DEFAULT '22:00:00',
    currency         VARCHAR(10)   DEFAULT 'THB',
    effective_from   DATE          DEFAULT NULL,
    is_active        BOOLEAN       DEFAULT TRUE,
    created_at       TIMESTAMPTZ   DEFAULT NOW(),
    updated_at       TIMESTAMPTZ   DEFAULT NOW()
);

-- ── 7. customers
CREATE TABLE IF NOT EXISTS customers (
    id               SERIAL PRIMARY KEY,
    user_id          INT           NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    full_name        VARCHAR(200)  NOT NULL,
    phone            VARCHAR(20),
    email            VARCHAR(150),
    license_plate    VARCHAR(30),
    car_type_id      INT           DEFAULT NULL REFERENCES car_types(id) ON DELETE SET NULL,
    member_since     DATE          DEFAULT CURRENT_DATE,
    total_sessions   INT           DEFAULT 0,
    total_kwh        DECIMAL(12,4) DEFAULT 0,
    total_spend      DECIMAL(12,2) DEFAULT 0,
    avatar_url       VARCHAR(255)  DEFAULT NULL,
    notes            TEXT,
    api_token        VARCHAR(100)  DEFAULT NULL,
    token_expires_at TIMESTAMPTZ   DEFAULT NULL,
    created_at       TIMESTAMPTZ   DEFAULT NOW(),
    updated_at       TIMESTAMPTZ   DEFAULT NOW()
);

-- ── 8. transactions
CREATE TABLE IF NOT EXISTS transactions (
    id                  SERIAL PRIMARY KEY,
    connector_id        INT                NOT NULL REFERENCES connectors(id),
    charger_id          INT                NOT NULL REFERENCES chargers(id),
    station_id          INT                NOT NULL REFERENCES stations(id),
    user_id             INT                NOT NULL REFERENCES users(id),
    customer_id         INT                DEFAULT NULL REFERENCES customers(id) ON DELETE SET NULL,
    car_type_id         INT                DEFAULT NULL REFERENCES car_types(id) ON DELETE SET NULL,
    estimate_amount     DECIMAL(10,2)      DEFAULT 0.00,
    actual_amount       DECIMAL(10,2)      DEFAULT 0.00,
    energy_kwh          DECIMAL(10,4)      DEFAULT 0.0000,
    start_time          TIMESTAMPTZ        DEFAULT NULL,
    stop_time           TIMESTAMPTZ        DEFAULT NULL,
    duration_minutes    INT                DEFAULT 0,
    status              transaction_status DEFAULT 'Pending',
    stop_reason         stop_reason_enum   DEFAULT NULL,
    remark              TEXT,
    fee_type            fee_type_enum      DEFAULT 'kWh-Based',
    price_per_kwh       DECIMAL(10,4)      DEFAULT 0.00,
    ocpp_transaction_id VARCHAR(100)       DEFAULT NULL,
    created_at          TIMESTAMPTZ        DEFAULT NOW(),
    updated_at          TIMESTAMPTZ        DEFAULT NOW()
);

-- ── 9. meter_values
CREATE TABLE IF NOT EXISTS meter_values (
    id             SERIAL PRIMARY KEY,
    transaction_id INT           NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
    power_kw       DECIMAL(10,4) DEFAULT 0.00,
    energy_kwh     DECIMAL(10,4) DEFAULT 0.00,
    voltage        DECIMAL(8,2)  DEFAULT 0.00,
    current_a      DECIMAL(8,2)  DEFAULT 0.00,
    soc_percent    INT           DEFAULT NULL,
    recorded_at    TIMESTAMPTZ   DEFAULT NOW()
);

-- ── 10. daily_summary
CREATE TABLE IF NOT EXISTS daily_summary (
    id               SERIAL PRIMARY KEY,
    station_id       INT           NOT NULL REFERENCES stations(id) ON DELETE CASCADE,
    summary_date     DATE          NOT NULL,
    sessions         INT           DEFAULT 0,
    unique_customers INT           DEFAULT 0,
    total_kwh        DECIMAL(12,4) DEFAULT 0,
    total_revenue    DECIMAL(12,2) DEFAULT 0,
    avg_duration_min INT           DEFAULT 0,
    created_at       TIMESTAMPTZ   DEFAULT NOW(),
    updated_at       TIMESTAMPTZ   DEFAULT NOW(),
    UNIQUE (station_id, summary_date)
);

-- ── 11. system_logs
CREATE TABLE IF NOT EXISTS system_logs (
    id          SERIAL PRIMARY KEY,
    user_id     INT          DEFAULT NULL,
    action      VARCHAR(200),
    entity_type VARCHAR(50),
    entity_id   INT          DEFAULT NULL,
    detail      TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMPTZ  DEFAULT NOW()
);

-- ── 12. wallet_accounts
CREATE TABLE IF NOT EXISTS wallet_accounts (
    id          SERIAL PRIMARY KEY,
    customer_id INT           NOT NULL UNIQUE REFERENCES customers(id) ON DELETE CASCADE,
    balance     DECIMAL(12,2) DEFAULT 0.00,
    currency    VARCHAR(10)   DEFAULT 'THB',
    updated_at  TIMESTAMPTZ   DEFAULT NOW()
);

-- ── 13. wallet_transactions
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id            SERIAL PRIMARY KEY,
    wallet_id     INT             NOT NULL REFERENCES wallet_accounts(id),
    type          wallet_tx_type  NOT NULL DEFAULT 'topup',
    amount        DECIMAL(12,2)   NOT NULL,
    balance_after DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    reference_id  VARCHAR(100)    DEFAULT NULL,
    description   TEXT,
    created_at    TIMESTAMPTZ     DEFAULT NOW()
);

-- ── 14. customer_vehicles
CREATE TABLE IF NOT EXISTS customer_vehicles (
    id            SERIAL PRIMARY KEY,
    customer_id   INT          NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    car_type_id   INT          DEFAULT NULL REFERENCES car_types(id) ON DELETE SET NULL,
    license_plate VARCHAR(30)  NOT NULL,
    nickname      VARCHAR(100) DEFAULT NULL,
    color         VARCHAR(50)  DEFAULT NULL,
    is_default    BOOLEAN      DEFAULT FALSE,
    created_at    TIMESTAMPTZ  DEFAULT NOW()
);

-- ── 15. customer_notifications
CREATE TABLE IF NOT EXISTS customer_notifications (
    id          SERIAL PRIMARY KEY,
    customer_id INT          NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    type        notif_type   DEFAULT 'system',
    title       VARCHAR(200) NOT NULL,
    body        TEXT,
    icon        VARCHAR(50)  DEFAULT 'notifications',
    read_at     TIMESTAMPTZ  DEFAULT NULL,
    created_at  TIMESTAMPTZ  DEFAULT NOW()
);

-- ── 16. customer_favorites
CREATE TABLE IF NOT EXISTS customer_favorites (
    id          SERIAL PRIMARY KEY,
    customer_id INT         NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    station_id  INT         NOT NULL REFERENCES stations(id)  ON DELETE CASCADE,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (customer_id, station_id)
);

-- ── 17. station_reviews
CREATE TABLE IF NOT EXISTS station_reviews (
    id             SERIAL PRIMARY KEY,
    station_id     INT         NOT NULL REFERENCES stations(id),
    customer_id    INT         NOT NULL REFERENCES customers(id),
    transaction_id INT         NOT NULL REFERENCES transactions(id),
    rating         SMALLINT    NOT NULL DEFAULT 5 CHECK (rating BETWEEN 1 AND 5),
    comment        TEXT,
    created_at     TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (customer_id, transaction_id)
);

-- ── 18. support_tickets
CREATE TABLE IF NOT EXISTS support_tickets (
    id             SERIAL PRIMARY KEY,
    customer_id    INT             NOT NULL REFERENCES customers(id),
    transaction_id INT             DEFAULT NULL REFERENCES transactions(id) ON DELETE SET NULL,
    category       ticket_category DEFAULT 'other',
    subject        VARCHAR(200)    NOT NULL,
    description    TEXT,
    status         ticket_status   DEFAULT 'open',
    created_at     TIMESTAMPTZ     DEFAULT NOW(),
    updated_at     TIMESTAMPTZ     DEFAULT NOW()
);

-- ============================================================
-- STEP 3: Auto-update updated_at (Trigger Function)
-- ============================================================
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- สร้าง trigger ให้แต่ละตาราง
DO $$ DECLARE
    t TEXT;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'users','stations','chargers','connectors',
        'service_fee_settings','customers','transactions',
        'daily_summary','wallet_accounts','support_tickets'
    ] LOOP
        EXECUTE format('
            DROP TRIGGER IF EXISTS trg_%s_updated_at ON %s;
            CREATE TRIGGER trg_%s_updated_at
            BEFORE UPDATE ON %s
            FOR EACH ROW EXECUTE FUNCTION update_updated_at();
        ', t, t, t, t);
    END LOOP;
END $$;

-- ============================================================
-- STEP 4: Seed Data
-- ============================================================

-- ── Admin User  (password hash = "Admin@1234" bcrypt)
INSERT INTO users (first_name, last_name, phone, email, password, is_verified, role)
VALUES ('Admin', 'CSMS', '0812345678', 'admin@csms.local',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXV/WwK8S', TRUE, 'admin')
ON CONFLICT (email) DO NOTHING;

-- ── Customer Demo User  (password hash = "Admin@1234" bcrypt)
INSERT INTO users (first_name, last_name, phone, email, password, is_verified, role)
VALUES ('สมชาย', 'ใจดี', '0811111111', 'customer@csms.local',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXV/WwK8S', TRUE, 'customer')
ON CONFLICT (email) DO NOTHING;

-- ── Car Types (14 รุ่น)
INSERT INTO car_types (name, brand, icon, connector_type, battery_kwh) VALUES
('Tesla Model 3',        'Tesla',   'electric_car',   'CCS2',    75.0),
('Tesla Model Y',        'Tesla',   'electric_car',   'CCS2',    82.0),
('BYD Atto 3',           'BYD',     'electric_car',   'CCS2',    60.5),
('BYD Seal',             'BYD',     'electric_car',   'CCS2',    82.6),
('Nissan Leaf',          'Nissan',  'electric_car',   'CHAdeMO', 40.0),
('MG EP',                'MG',      'electric_car',   'CCS2',    50.3),
('BMW iX3',              'BMW',     'electric_car',   'CCS2',    80.0),
('Volvo XC40 Recharge',  'Volvo',   'electric_car',   'CCS2',    82.0),
('Hyundai IONIQ 5',      'Hyundai', 'electric_car',   'CCS2',    77.4),
('Kia EV6',              'Kia',     'electric_car',   'CCS2',    77.4),
('Toyota bZ4X',          'Toyota',  'electric_car',   'CCS2',    71.4),
('ORA Good Cat',         'GWM',     'electric_car',   'CCS2',    48.0),
('NETA V',               'NETA',    'electric_car',   'CCS2',    40.1),
('Other EV',             'Other',   'directions_car', 'Type2',    0.0);

-- ── Stations (3 สาขา)
INSERT INTO stations (id, user_id, name, location, address, latitude, longitude, status) VALUES
(1, 1, 'สถานี EV สาขาลาดพร้าว',              'ชั้น B1 โซน A',  '1234 ถนนลาดพร้าว แขวงจตุจักร กรุงเทพฯ',  13.81890000, 100.56710000, 'active'),
(2, 1, 'สถานี EV สาขาสุขุมวิท',              'ลานจอดรถชั้น 2', '88 ถนนสุขุมวิท แขวงคลองเตย กรุงเทพฯ',    13.73080000, 100.56940000, 'active'),
(3, 1, 'สถานี EV สาขาเซ็นทรัลรัตนาธิเบศร์', 'ชั้น P3',        '68 ถนนรัตนาธิเบศร์ นนทบุรี',              13.85810000, 100.51920000, 'maintenance');

-- Reset sequence หลัง insert with explicit id
SELECT setval('stations_id_seq', (SELECT MAX(id) FROM stations));

-- ── Chargers (6 เครื่อง)
INSERT INTO chargers (id, station_id, serial_number, model, brand, max_power_kw, controller_status, last_heartbeat) VALUES
(1, 1, 'EVCS-LPR-001', 'Terra AC W22',   'ABB',      22.0, 'Online',  NOW() - INTERVAL '2 minutes'),
(2, 1, 'EVCS-LPR-002', 'Terra DC 60',    'ABB',      60.0, 'Online',  NOW() - INTERVAL '1 minute'),
(3, 1, 'EVCS-LPR-003', 'Wallbox Pulsar', 'Wallbox',   7.4, 'Offline', NOW() - INTERVAL '3 hours'),
(4, 1, 'EVCS-LPR-004', 'Alfen Eve',      'Alfen',    22.0, 'Faulted', NOW() - INTERVAL '30 minutes'),
(5, 2, 'EVCS-SKW-001', 'Terra AC W22',   'ABB',      22.0, 'Online',  NOW() - INTERVAL '5 minutes'),
(6, 2, 'EVCS-SKW-002', 'Juice Charger',  'Juicebar', 11.0, 'Online',  NOW() - INTERVAL '3 minutes');

SELECT setval('chargers_id_seq', (SELECT MAX(id) FROM chargers));

-- ── Connectors (6 หัว)
INSERT INTO connectors (id, charger_id, connector_number, connector_type, status) VALUES
(1, 1, 1, 'Type2', 'Ready to use'),
(2, 2, 1, 'CCS2',  'Ready to use'),
(3, 3, 1, 'Type2', 'Unavailable'),
(4, 4, 1, 'Type2', 'Unavailable'),
(5, 5, 1, 'Type2', 'Ready to use'),
(6, 6, 1, 'Type2', 'Ready to use');

SELECT setval('connectors_id_seq', (SELECT MAX(id) FROM connectors));

-- ── Service Fee Settings
INSERT INTO service_fee_settings (station_id, fee_type, price_per_kwh, currency, effective_from, is_active) VALUES
(1, 'kWh-Based',  5.50, 'THB', '2026-01-01', TRUE),
(2, 'kWh-Based',  6.00, 'THB', '2026-01-01', TRUE),
(3, 'Free Charge', 0.00, 'THB', '2026-01-01', TRUE);

-- ── Customers (15 คน — customer_id=1 ผูกกับ demo user)
INSERT INTO customers (id, user_id, full_name, phone, email, license_plate, car_type_id, member_since, total_sessions, total_kwh, total_spend) VALUES
(1,  2, 'สมชาย ใจดี',        '0811111111', 'somchai@email.com',   'กข-1234', 1,  '2025-01-15', 18, 420.5000, 2312.75),
(2,  1, 'วิภา รักไทย',       '0822222222', 'wipa@email.com',       'กค-5678', 3,  '2025-02-01', 12, 280.3000, 1541.65),
(3,  1, 'อนุชา สมบูรณ์',     '0833333333', 'anucha@email.com',     'ขก-9999', 9,  '2025-03-10',  8, 195.2000, 1073.60),
(4,  1, 'พิมพ์ใจ ชื่นชม',    '0844444444', 'pimjai@email.com',     'พม-4444', 4,  '2025-04-05', 22, 531.8000, 2924.90),
(5,  1, 'ธนกร วงศ์ดี',       '0855555555', 'thanakorn@email.com',  'งง-1111', 2,  '2025-05-20',  5, 115.0000,  632.50),
(6,  1, 'มาลี สุขใจ',        '0866666666', 'malee@email.com',      'มก-7777', 6,  '2025-06-01', 30, 720.0000, 3960.00),
(7,  1, 'ชาญณรงค์ เก่งดี',   '0877777777', 'channarong@email.com', 'ปก-2222', 7,  '2025-07-11', 14, 336.0000, 1848.00),
(8,  1, 'ศิริวรรณ แสงแก้ว',  '0888888888', 'siriwan@email.com',    'สก-3333', 10, '2025-08-03',  9, 216.0000, 1188.00),
(9,  1, 'ณัฐพล มีสุข',       '0899999999', 'nattapon@email.com',   'ตก-8888', 5,  '2025-09-15',  4,  92.0000,  506.00),
(10, 1, 'ประภาส โชติ',       '0800000001', 'praphat@email.com',    'รก-6666', 11, '2025-10-01', 16, 384.0000, 2112.00),
(11, 1, 'อรอุมา ทองดี',      '0800000002', 'onuma@email.com',      'ชก-5555', 8,  '2025-11-20',  7, 168.0000,  924.00),
(12, 1, 'กฤษณ์ ศรีสวัสดิ์',  '0800000003', 'krit@email.com',       'นก-4444', 12, '2025-12-05', 20, 480.0000, 2640.00),
(13, 1, 'พรรณี จันทร์งาม',   '0800000004', 'pannee@email.com',     'อก-1111', 13, '2026-01-10', 11, 264.0000, 1452.00),
(14, 1, 'วีระ สุวรรณ',       '0800000005', 'weera@email.com',      'บก-2222', 1,  '2026-02-14',  6, 144.0000,  792.00),
(15, 1, 'นภา รุ่งเรือง',     '0800000006', 'napa@email.com',       'ลก-3333', 3,  '2026-03-01',  3,  72.0000,  396.00);

SELECT setval('customers_id_seq', (SELECT MAX(id) FROM customers));

-- ── Wallet Accounts
INSERT INTO wallet_accounts (customer_id, balance) VALUES
(1,  500.00), (2,  250.00), (3,  100.00), (4, 1000.00), (5,   50.00),
(6,  750.00), (7,  300.00), (8,  200.00), (9,  150.00), (10, 400.00),
(11,  80.00), (12, 600.00), (13, 120.00), (14, 350.00), (15,  90.00);

-- ── Transactions (36 รายการ Jan–Mar 2026)
INSERT INTO transactions (connector_id, charger_id, station_id, user_id, customer_id, car_type_id,
    estimate_amount, actual_amount, energy_kwh, start_time, stop_time, duration_minutes,
    status, remark, fee_type, price_per_kwh) VALUES
-- January 2026
(1,1,1,1,1,1,   200, 192.5, 35.00, '2026-01-05 09:10:00+07','2026-01-05 11:10:00+07',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,2,3,   150, 143.0, 26.00, '2026-01-07 14:00:00+07','2026-01-07 15:30:00+07', 90,'Completed','กค-5678','kWh-Based',5.50),
(5,5,2,1,4,4,   300, 288.0, 48.00, '2026-01-10 10:00:00+07','2026-01-10 12:00:00+07',120,'Completed','พม-4444','kWh-Based',6.00),
(1,1,1,1,6,6,   100,  97.0, 17.60, '2026-01-12 16:00:00+07','2026-01-12 17:00:00+07', 60,'Completed','มก-7777','kWh-Based',5.50),
(2,2,1,1,7,7,   250, 242.0, 44.00, '2026-01-15 11:00:00+07','2026-01-15 13:30:00+07',150,'Completed','ปก-2222','kWh-Based',5.50),
(5,5,2,1,3,9,   180, 174.0, 29.00, '2026-01-18 09:00:00+07','2026-01-18 10:30:00+07', 90,'Completed','ขก-9999','kWh-Based',6.00),
(1,1,1,1,8,10,  200, 193.0, 35.10, '2026-01-20 13:00:00+07','2026-01-20 15:00:00+07',120,'Completed','สก-3333','kWh-Based',5.50),
(2,2,1,1,5,2,    80,  77.0, 14.00, '2026-01-22 10:00:00+07','2026-01-22 10:45:00+07', 45,'Completed','งง-1111','kWh-Based',5.50),
(5,5,2,1,10,11, 400, 390.0, 65.00, '2026-01-25 08:00:00+07','2026-01-25 10:30:00+07',150,'Completed','รก-6666','kWh-Based',6.00),
(1,1,1,1,12,12, 300, 297.0, 54.00, '2026-01-28 14:00:00+07','2026-01-28 17:00:00+07',180,'Completed','นก-4444','kWh-Based',5.50),
-- February 2026
(1,1,1,1,1,1,   200, 196.0, 35.60, '2026-02-03 09:00:00+07','2026-02-03 11:00:00+07',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,13,13, 100,  99.0, 18.00, '2026-02-05 15:00:00+07','2026-02-05 16:00:00+07', 60,'Completed','อก-1111','kWh-Based',5.50),
(5,5,2,1,4,4,   500, 495.0, 82.50, '2026-02-08 10:00:00+07','2026-02-08 13:00:00+07',180,'Completed','พม-4444','kWh-Based',6.00),
(1,1,1,1,14,1,  150, 148.5, 27.00, '2026-02-10 08:00:00+07','2026-02-10 09:30:00+07', 90,'Completed','บก-2222','kWh-Based',5.50),
(2,2,1,1,6,6,   200, 198.0, 36.00, '2026-02-12 14:00:00+07','2026-02-12 16:00:00+07',120,'Completed','มก-7777','kWh-Based',5.50),
(5,5,2,1,11,8,  250, 240.0, 40.00, '2026-02-14 10:00:00+07','2026-02-14 12:30:00+07',150,'Completed','ชก-5555','kWh-Based',6.00),
(1,1,1,1,9,5,    80,  78.0, 14.20, '2026-02-16 16:00:00+07','2026-02-16 17:00:00+07', 60,'Completed','ตก-8888','kWh-Based',5.50),
(2,2,1,1,2,3,   180, 175.0, 31.80, '2026-02-18 09:00:00+07','2026-02-18 10:45:00+07',105,'Completed','กค-5678','kWh-Based',5.50),
(5,5,2,1,15,3,  300, 294.0, 49.00, '2026-02-20 11:00:00+07','2026-02-20 13:00:00+07',120,'Completed','ลก-3333','kWh-Based',6.00),
(1,1,1,1,7,7,   400, 396.0, 72.00, '2026-02-25 13:00:00+07','2026-02-25 16:00:00+07',180,'Completed','ปก-2222','kWh-Based',5.50),
-- March 2026
(1,1,1,1,1,1,   200, 194.0, 35.30, '2026-03-01 09:00:00+07','2026-03-01 11:00:00+07',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,4,4,   300, 297.0, 49.50, '2026-03-03 14:00:00+07','2026-03-03 17:00:00+07',180,'Completed','พม-4444','kWh-Based',5.50),
(5,5,2,1,3,9,   200, 192.0, 32.00, '2026-03-05 10:00:00+07','2026-03-05 12:00:00+07',120,'Completed','ขก-9999','kWh-Based',6.00),
(1,1,1,1,6,6,   150, 148.5, 27.00, '2026-03-07 08:00:00+07','2026-03-07 09:30:00+07', 90,'Completed','มก-7777','kWh-Based',5.50),
(2,2,1,1,12,12, 100,  99.0, 18.00, '2026-03-10 15:00:00+07','2026-03-10 16:00:00+07', 60,'Completed','นก-4444','kWh-Based',5.50),
(5,5,2,1,8,10,  400, 390.0, 65.00, '2026-03-12 11:00:00+07','2026-03-12 13:30:00+07',150,'Completed','สก-3333','kWh-Based',6.00),
(1,1,1,1,2,3,   180, 176.0, 32.00, '2026-03-15 09:00:00+07','2026-03-15 11:00:00+07',120,'Completed','กค-5678','kWh-Based',5.50),
(2,2,1,1,10,11, 250, 242.0, 44.00, '2026-03-18 14:00:00+07','2026-03-18 16:30:00+07',150,'Completed','รก-6666','kWh-Based',5.50),
(5,5,2,1,5,2,    80,  78.0, 13.00, '2026-03-20 16:00:00+07','2026-03-20 17:00:00+07', 60,'Completed','งง-1111','kWh-Based',6.00),
(1,1,1,1,7,7,   300, 297.0, 54.00, '2026-03-22 10:00:00+07','2026-03-22 13:00:00+07',180,'Completed','ปก-2222','kWh-Based',5.50),
(2,2,1,1,13,13, 200, 194.0, 35.30, '2026-03-24 09:00:00+07','2026-03-24 11:00:00+07',120,'Completed','อก-1111','kWh-Based',5.50),
(5,5,2,1,14,1,  150, 144.0, 24.00, '2026-03-26 14:00:00+07','2026-03-26 15:30:00+07', 90,'Completed','บก-2222','kWh-Based',6.00),
(1,1,1,1,1,1,   200, 194.0, 35.30, '2026-03-28 07:30:00+07','2026-03-28 09:30:00+07',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,4,4,   300, 291.0, 52.90, '2026-03-28 10:00:00+07','2026-03-28 12:30:00+07',150,'Completed','พม-4444','kWh-Based',5.50),
(5,5,2,1,6,6,   150, 144.0, 24.00, '2026-03-28 13:00:00+07','2026-03-28 14:30:00+07', 90,'Completed','มก-7777','kWh-Based',6.00),
(1,1,1,1,8,10,  400, 388.0, 70.50, '2026-03-28 15:00:00+07','2026-03-28 18:00:00+07',180,'Completed','สก-3333','kWh-Based',5.50);

-- ── Notifications สำหรับ demo customer
INSERT INTO customer_notifications (customer_id, type, title, body, icon) VALUES
(1, 'system', 'ยินดีต้อนรับสู่ EV Charge! 🎉', 'สมัครสมาชิกสำเร็จแล้ว เริ่มชาร์จรถของคุณได้เลย', 'celebration'),
(1, 'wallet', 'เติมเงินสำเร็จ 💰', 'เติมเงิน ฿500.00 | ยอดคงเหลือ ฿500.00', 'account_balance_wallet'),
(1, 'session', 'ชาร์จเสร็จแล้ว ✅', 'ใช้พลังงาน 35.30 kWh | ค่าบริการ ฿194.00', 'electric_bolt');

-- ── Vehicle สำหรับ demo customer
INSERT INTO customer_vehicles (customer_id, car_type_id, license_plate, nickname, is_default) VALUES
(1, 1, 'กข-1234', 'รถหลัก', TRUE);

-- ── อัปเดต customer stats จาก transactions จริง
UPDATE customers c
SET total_sessions = t.s,
    total_kwh      = t.kwh,
    total_spend    = t.spend
FROM (
    SELECT customer_id,
           COUNT(*)                          AS s,
           COALESCE(SUM(energy_kwh), 0)      AS kwh,
           COALESCE(SUM(actual_amount), 0)   AS spend
    FROM transactions
    WHERE customer_id IS NOT NULL
      AND status IN ('Completed', 'Stopped')
    GROUP BY customer_id
) t
WHERE t.customer_id = c.id;

-- ============================================================
-- STEP 5: Row Level Security (RLS) — เปิดใช้สำหรับ public API
-- ============================================================
-- เปิด RLS ป้องกันข้อมูลรั่ว (Supabase แนะนำให้เปิดเสมอ)
ALTER TABLE users                  ENABLE ROW LEVEL SECURITY;
ALTER TABLE customers              ENABLE ROW LEVEL SECURITY;
ALTER TABLE stations               ENABLE ROW LEVEL SECURITY;
ALTER TABLE chargers               ENABLE ROW LEVEL SECURITY;
ALTER TABLE connectors             ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions           ENABLE ROW LEVEL SECURITY;
ALTER TABLE wallet_accounts        ENABLE ROW LEVEL SECURITY;
ALTER TABLE wallet_transactions    ENABLE ROW LEVEL SECURITY;
ALTER TABLE customer_notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE customer_favorites     ENABLE ROW LEVEL SECURITY;
ALTER TABLE service_fee_settings   ENABLE ROW LEVEL SECURITY;
ALTER TABLE car_types              ENABLE ROW LEVEL SECURITY;
ALTER TABLE customer_vehicles      ENABLE ROW LEVEL SECURITY;

-- Policy: อ่าน stations/chargers/connectors/car_types ได้ทุกคน (public)
CREATE POLICY "public_read_stations"    ON stations    FOR SELECT USING (true);
CREATE POLICY "public_read_chargers"    ON chargers    FOR SELECT USING (true);
CREATE POLICY "public_read_connectors"  ON connectors  FOR SELECT USING (true);
CREATE POLICY "public_read_car_types"   ON car_types   FOR SELECT USING (true);
CREATE POLICY "public_read_fee"         ON service_fee_settings FOR SELECT USING (true);

-- ============================================================
-- เสร็จสิ้น!
-- Tables: 18 | Stations: 3 | Chargers: 6 | Customers: 15 | Transactions: 36
-- Login: customer@csms.local / Admin@1234
-- ============================================================
