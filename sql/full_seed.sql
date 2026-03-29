USE csms_db;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE daily_summary;
TRUNCATE TABLE meter_values;
TRUNCATE TABLE transactions;
TRUNCATE TABLE service_fee_settings;
TRUNCATE TABLE customers;
TRUNCATE TABLE connectors;
TRUNCATE TABLE chargers;
TRUNCATE TABLE stations;
TRUNCATE TABLE system_logs;
DELETE FROM users WHERE id > 1;
SET FOREIGN_KEY_CHECKS = 1;

-- ── Stations
INSERT INTO stations (id, user_id, name, location, address, latitude, longitude, status) VALUES
(1, 1, 'สถานี EV สาขาลาดพร้าว',                     'ชั้น B1 โซน A', '1234 ถนนลาดพร้าว แขวงจตุจักร กรุงเทพฯ', 13.8189, 100.5671, 'active'),
(2, 1, 'สถานี EV สาขาสุขุมวิท',                     'ลานจอดรถชั้น 2', '88 ถนนสุขุมวิท แขวงคลองเตย กรุงเทพฯ',  13.7308, 100.5694, 'active'),
(3, 1, 'สถานี EV สาขาเซ็นทรัลรัตนาธิเบศร์',        'ชั้น P3', '68 ถนนรัตนาธิเบศร์ นนทบุรี',               13.8581, 100.5192, 'maintenance');

-- ── Chargers
INSERT INTO chargers (id, station_id, serial_number, model, brand, max_power_kw, controller_status, last_heartbeat) VALUES
(1, 1, 'EVCS-LPR-001', 'Terra AC W22',  'ABB',     22.0, 'Online',  NOW() - INTERVAL 2 MINUTE),
(2, 1, 'EVCS-LPR-002', 'Terra DC 60',   'ABB',     60.0, 'Online',  NOW() - INTERVAL 1 MINUTE),
(3, 1, 'EVCS-LPR-003', 'Wallbox Pulsar','Wallbox',  7.4, 'Offline', NOW() - INTERVAL 3 HOUR),
(4, 1, 'EVCS-LPR-004', 'Alfen Eve',     'Alfen',   22.0, 'Faulted', NOW() - INTERVAL 30 MINUTE),
(5, 2, 'EVCS-SKW-001', 'Terra AC W22',  'ABB',     22.0, 'Online',  NOW() - INTERVAL 5 MINUTE),
(6, 2, 'EVCS-SKW-002', 'Juice Charger', 'Juicebar',11.0, 'Online',  NOW() - INTERVAL 3 MINUTE);

-- ── Connectors
INSERT INTO connectors (id, charger_id, connector_number, connector_type, status) VALUES
(1, 1, 1, 'Type2',  'Ready to use'),
(2, 2, 1, 'CCS2',   'Ready to use'),
(3, 3, 1, 'Type2',  'Unavailable'),
(4, 4, 1, 'Type2',  'Unavailable'),
(5, 5, 1, 'Type2',  'Ready to use'),
(6, 6, 1, 'Type2',  'Ready to use');

-- ── Service Fee Settings
INSERT INTO service_fee_settings (station_id, fee_type, price_per_kwh, currency, effective_from, is_active) VALUES
(1, 'kWh-Based', 5.50, 'THB', '2026-01-01', 1),
(2, 'kWh-Based', 6.00, 'THB', '2026-01-01', 1),
(3, 'Free Charge', 0.00, 'THB', '2026-01-01', 1);

-- ── Customers
INSERT INTO customers (id, user_id, full_name, phone, email, license_plate, car_type_id, member_since) VALUES
(1,  1, 'สมชาย ใจดี',        '0811111111', 'somchai@email.com',    'กข-1234', 1,  '2025-01-15'),
(2,  1, 'วิภา รักไทย',       '0822222222', 'wipa@email.com',        'กค-5678', 3,  '2025-02-01'),
(3,  1, 'อนุชา สมบูรณ์',     '0833333333', 'anucha@email.com',      'ขก-9999', 9,  '2025-03-10'),
(4,  1, 'พิมพ์ใจ ชื่นชม',    '0844444444', 'pimjai@email.com',      'พม-4444', 4,  '2025-04-05'),
(5,  1, 'ธนกร วงศ์ดี',       '0855555555', 'thanakorn@email.com',   'งง-1111', 2,  '2025-05-20'),
(6,  1, 'มาลี สุขใจ',        '0866666666', 'malee@email.com',       'มก-7777', 6,  '2025-06-01'),
(7,  1, 'ชาญณรงค์ เก่งดี',   '0877777777', 'channarong@email.com',  'ปก-2222', 7,  '2025-07-11'),
(8,  1, 'ศิริวรรณ แสงแก้ว',  '0888888888', 'siriwan@email.com',     'สก-3333', 10, '2025-08-03'),
(9,  1, 'ณัฐพล มีสุข',       '0899999999', 'nattapon@email.com',    'ตก-8888', 5,  '2025-09-15'),
(10, 1, 'ประภาส โชติ',       '0800000001', 'praphat@email.com',     'รก-6666', 11, '2025-10-01'),
(11, 1, 'อรอุมา ทองดี',      '0800000002', 'onuma@email.com',       'ชก-5555', 8,  '2025-11-20'),
(12, 1, 'กฤษณ์ ศรีสวัสดิ์',  '0800000003', 'krit@email.com',        'นก-4444', 12, '2025-12-05'),
(13, 1, 'พรรณี จันทร์งาม',   '0800000004', 'pannee@email.com',      'อก-1111', 13, '2026-01-10'),
(14, 1, 'วีระ สุวรรณ',       '0800000005', 'weera@email.com',       'บก-2222', 1,  '2026-02-14'),
(15, 1, 'นภา รุ่งเรือง',     '0800000006', 'napa@email.com',        'ลก-3333', 3,  '2026-03-01');

-- ── Transactions (historical + today)
INSERT INTO transactions (connector_id,charger_id,station_id,user_id,customer_id,car_type_id,
    estimate_amount,actual_amount,energy_kwh,start_time,stop_time,duration_minutes,
    status,remark,fee_type,price_per_kwh) VALUES
-- Jan 2026
(1,1,1,1,1,1,  200,192.5,35.0, '2026-01-05 09:10:00','2026-01-05 11:10:00',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,2,3,  150,143.0,26.0, '2026-01-07 14:00:00','2026-01-07 15:30:00', 90,'Completed','กค-5678','kWh-Based',5.50),
(5,5,2,1,4,4,  300,288.0,48.0, '2026-01-10 10:00:00','2026-01-10 12:00:00',120,'Completed','พม-4444','kWh-Based',6.00),
(1,1,1,1,6,6,  100, 97.0,17.6, '2026-01-12 16:00:00','2026-01-12 17:00:00', 60,'Completed','มก-7777','kWh-Based',5.50),
(2,2,1,1,7,7,  250,242.0,44.0, '2026-01-15 11:00:00','2026-01-15 13:30:00',150,'Completed','ปก-2222','kWh-Based',5.50),
(5,5,2,1,3,9,  180,174.0,29.0, '2026-01-18 09:00:00','2026-01-18 10:30:00', 90,'Completed','ขก-9999','kWh-Based',6.00),
(1,1,1,1,8,10, 200,193.0,35.1, '2026-01-20 13:00:00','2026-01-20 15:00:00',120,'Completed','สก-3333','kWh-Based',5.50),
(2,2,1,1,5,2,   80, 77.0,14.0, '2026-01-22 10:00:00','2026-01-22 10:45:00', 45,'Completed','งง-1111','kWh-Based',5.50),
(5,5,2,1,10,11,400,390.0,65.0, '2026-01-25 08:00:00','2026-01-25 10:30:00',150,'Completed','รก-6666','kWh-Based',6.00),
(1,1,1,1,12,12,300,297.0,54.0, '2026-01-28 14:00:00','2026-01-28 17:00:00',180,'Completed','นก-4444','kWh-Based',5.50),
-- Feb 2026
(1,1,1,1,1,1,  200,196.0,35.6, '2026-02-03 09:00:00','2026-02-03 11:00:00',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,13,13,100, 99.0,18.0, '2026-02-05 15:00:00','2026-02-05 16:00:00', 60,'Completed','อก-1111','kWh-Based',5.50),
(5,5,2,1,4,4,  500,495.0,82.5, '2026-02-08 10:00:00','2026-02-08 13:00:00',180,'Completed','พม-4444','kWh-Based',6.00),
(1,1,1,1,14,1, 150,148.5,27.0, '2026-02-10 08:00:00','2026-02-10 09:30:00', 90,'Completed','บก-2222','kWh-Based',5.50),
(2,2,1,1,6,6,  200,198.0,36.0, '2026-02-12 14:00:00','2026-02-12 16:00:00',120,'Completed','มก-7777','kWh-Based',5.50),
(5,5,2,1,11,8, 250,240.0,40.0, '2026-02-14 10:00:00','2026-02-14 12:30:00',150,'Completed','ชก-5555','kWh-Based',6.00),
(1,1,1,1,9,5,   80, 78.0,14.2, '2026-02-16 16:00:00','2026-02-16 17:00:00', 60,'Completed','ตก-8888','kWh-Based',5.50),
(2,2,1,1,2,3,  180,175.0,31.8, '2026-02-18 09:00:00','2026-02-18 10:45:00',105,'Completed','กค-5678','kWh-Based',5.50),
(5,5,2,1,15,3, 300,294.0,49.0, '2026-02-20 11:00:00','2026-02-20 13:00:00',120,'Completed','ลก-3333','kWh-Based',6.00),
(1,1,1,1,7,7,  400,396.0,72.0, '2026-02-25 13:00:00','2026-02-25 16:00:00',180,'Completed','ปก-2222','kWh-Based',5.50),
-- Mar 2026 (current month)
(1,1,1,1,1,1,  200,194.0,35.3, '2026-03-01 09:00:00','2026-03-01 11:00:00',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,4,4,  300,297.0,49.5, '2026-03-03 14:00:00','2026-03-03 17:00:00',180,'Completed','พม-4444','kWh-Based',5.50),
(5,5,2,1,3,9,  200,192.0,32.0, '2026-03-05 10:00:00','2026-03-05 12:00:00',120,'Completed','ขก-9999','kWh-Based',6.00),
(1,1,1,1,6,6,  150,148.5,27.0, '2026-03-07 08:00:00','2026-03-07 09:30:00', 90,'Completed','มก-7777','kWh-Based',5.50),
(2,2,1,1,12,12,100, 99.0,18.0, '2026-03-10 15:00:00','2026-03-10 16:00:00', 60,'Completed','นก-4444','kWh-Based',5.50),
(5,5,2,1,8,10, 400,390.0,65.0, '2026-03-12 11:00:00','2026-03-12 13:30:00',150,'Completed','สก-3333','kWh-Based',6.00),
(1,1,1,1,2,3,  180,176.0,32.0, '2026-03-15 09:00:00','2026-03-15 11:00:00',120,'Completed','กค-5678','kWh-Based',5.50),
(2,2,1,1,10,11,250,242.0,44.0, '2026-03-18 14:00:00','2026-03-18 16:30:00',150,'Completed','รก-6666','kWh-Based',5.50),
(5,5,2,1,5,2,   80, 78.0,13.0, '2026-03-20 16:00:00','2026-03-20 17:00:00', 60,'Completed','งง-1111','kWh-Based',6.00),
(1,1,1,1,7,7,  300,297.0,54.0, '2026-03-22 10:00:00','2026-03-22 13:00:00',180,'Completed','ปก-2222','kWh-Based',5.50),
(2,2,1,1,13,13,200,194.0,35.3, '2026-03-24 09:00:00','2026-03-24 11:00:00',120,'Completed','อก-1111','kWh-Based',5.50),
(5,5,2,1,14,1, 150,144.0,24.0, '2026-03-26 14:00:00','2026-03-26 15:30:00', 90,'Completed','บก-2222','kWh-Based',6.00),
-- Today (Mar 28 2026)
(1,1,1,1,1,1,  200,194.0,35.3, '2026-03-28 07:30:00','2026-03-28 09:30:00',120,'Completed','กข-1234','kWh-Based',5.50),
(2,2,1,1,4,4,  300,291.0,52.9, '2026-03-28 10:00:00','2026-03-28 12:30:00',150,'Completed','พม-4444','kWh-Based',5.50),
(5,5,2,1,6,6,  150,144.0,24.0, '2026-03-28 13:00:00','2026-03-28 14:30:00', 90,'Completed','มก-7777','kWh-Based',6.00),
(1,1,1,1,8,10, 400,388.0,70.5, '2026-03-28 15:00:00','2026-03-28 18:00:00',180,'Completed','สก-3333','kWh-Based',5.50);

-- ── Refresh customer stats
UPDATE customers c
JOIN (
    SELECT customer_id,
           COUNT(*) AS s,
           COALESCE(SUM(energy_kwh),0) AS kwh,
           COALESCE(SUM(actual_amount),0) AS spend
    FROM transactions
    WHERE customer_id IS NOT NULL AND status IN ('Completed','Stopped')
    GROUP BY customer_id
) t ON t.customer_id = c.id
SET c.total_sessions = t.s,
    c.total_kwh      = t.kwh,
    c.total_spend    = t.spend;
