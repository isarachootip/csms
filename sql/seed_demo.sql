USE csms;

-- Demo Stations
INSERT INTO stations (user_id, name, location, address, latitude, longitude, status) VALUES
(1, 'สถานี EV สาขาลาดพร้าว', 'ชั้น B1 โซน A', '1234 ถนนลาดพร้าว แขวงจตุจักร กรุงเทพฯ 10900', 13.8189, 100.5671, 'active'),
(1, 'สถานี EV สาขาสุขุมวิท', 'ลานจอดรถชั้น 2', '88 ถนนสุขุมวิท แขวงคลองเตย กรุงเทพฯ 10110', 13.7308, 100.5694, 'active'),
(1, 'สถานี EV สาขาเซ็นทรัลรัตนาธิเบศร์', 'ชั้น P3', '68/1234 ถนนรัตนาธิเบศร์ นนทบุรี 11000', 13.8581, 100.5192, 'maintenance');

-- Demo Chargers (Station 1)
INSERT INTO chargers (station_id, serial_number, model, brand, max_power_kw, controller_status, last_heartbeat) VALUES
(1, 'EVCS-LPR-001', 'Terra AC W22', 'ABB',       22.0, 'Online',  NOW() - INTERVAL 2 MINUTE),
(1, 'EVCS-LPR-002', 'Terra DC 60',  'ABB',       60.0, 'Online',  NOW() - INTERVAL 1 MINUTE),
(1, 'EVCS-LPR-003', 'Wallbox Pulsar', 'Wallbox', 7.4,  'Offline', NOW() - INTERVAL 3 HOUR),
(1, 'EVCS-LPR-004', 'Alfen Eve',    'Alfen',     22.0, 'Faulted', NOW() - INTERVAL 30 MINUTE);

-- Demo Chargers (Station 2)
INSERT INTO chargers (station_id, serial_number, model, brand, max_power_kw, controller_status, last_heartbeat) VALUES
(2, 'EVCS-SKW-001', 'Terra AC W22', 'ABB',      22.0, 'Online',  NOW() - INTERVAL 5 MINUTE),
(2, 'EVCS-SKW-002', 'Juice Charger','Juicebar', 11.0, 'Online',  NOW() - INTERVAL 3 MINUTE);

-- Connectors for each charger
INSERT INTO connectors (charger_id, connector_number, connector_type, status) VALUES
(1, 1, 'Type2',  'Plugged in'),
(2, 1, 'CCS2',   'Ready to use'),
(3, 1, 'Type2',  'Unavailable'),
(4, 1, 'Type2',  'Unavailable'),
(5, 1, 'Type2',  'Charging in progress'),
(6, 1, 'Type2',  'Ready to use');

-- Service Fee Settings
INSERT INTO service_fee_settings (station_id, fee_type, price_per_kwh, currency, effective_from, is_active) VALUES
(1, 'kWh-Based', 5.50, 'THB', CURDATE(), 1),
(2, 'kWh-Based', 6.00, 'THB', CURDATE(), 1),
(3, 'Free Charge', 0.00, 'THB', CURDATE(), 1);

-- Demo Transactions (historical)
INSERT INTO transactions (connector_id, charger_id, station_id, user_id, estimate_amount, actual_amount, energy_kwh, start_time, stop_time, duration_minutes, status, stop_reason, remark, fee_type, price_per_kwh) VALUES
(1, 1, 1, 1, 200.00, 192.50, 35.000, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 3 DAY + INTERVAL 2 HOUR,   120, 'Completed', 'Remote', 'ทะเบียน กข-1234', 'kWh-Based', 5.50),
(1, 1, 1, 1, 100.00,  99.00, 18.000, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY + INTERVAL 1 HOUR,    65, 'Completed', 'Local',  'ทะเบียน กค-5678', 'kWh-Based', 5.50),
(2, 2, 1, 1, 500.00, 495.00, 90.000, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY + INTERVAL 90 MINUTE, 90, 'Completed', 'Remote', 'ทะเบียน ขก-9999', 'kWh-Based', 5.50),
(5, 5, 2, 1, 300.00,   0.00,  0.000, NOW() - INTERVAL 1 HOUR, NULL, 0, 'Charging', NULL, 'ทะเบียน พม-4444', 'kWh-Based', 6.00),
(2, 2, 1, 1,  50.00,  44.00,  8.000, NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 4 HOUR, 58, 'Stopped', 'Local', 'ทะเบียน งง-1111', 'kWh-Based', 5.50),
(1, 1, 1, 1, 150.00,   0.00,  0.000, NOW() - INTERVAL 30 MINUTE, NULL, 0, 'Charging', NULL, 'ทะเบียน มก-7777', 'kWh-Based', 5.50);

-- Update connector 1 (currently charging)
UPDATE connectors SET status='Charging in progress' WHERE id=1;
