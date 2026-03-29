USE csms;

-- ── Car Types
CREATE TABLE IF NOT EXISTS car_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(100),
    icon VARCHAR(50) DEFAULT 'directions_car',
    connector_type ENUM('Type1','Type2','CCS1','CCS2','CHAdeMO','GB/T') DEFAULT 'Type2',
    battery_kwh DECIMAL(8,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── Customers
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(150),
    license_plate VARCHAR(30),
    car_type_id INT DEFAULT NULL,
    member_since DATE DEFAULT (CURDATE()),
    total_sessions INT DEFAULT 0,
    total_kwh DECIMAL(12,4) DEFAULT 0,
    total_spend DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (car_type_id) REFERENCES car_types(id) ON DELETE SET NULL
);

-- ── Add customer_id & car_type_id to transactions
ALTER TABLE transactions
    ADD COLUMN customer_id INT DEFAULT NULL AFTER user_id,
    ADD COLUMN car_type_id INT DEFAULT NULL AFTER customer_id,
    ADD FOREIGN KEY fk_tx_customer (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    ADD FOREIGN KEY fk_tx_cartype  (car_type_id)  REFERENCES car_types(id) ON DELETE SET NULL;

-- ── Daily Revenue Summary (materialized for performance)
CREATE TABLE IF NOT EXISTS daily_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    summary_date DATE NOT NULL,
    sessions INT DEFAULT 0,
    unique_customers INT DEFAULT 0,
    total_kwh DECIMAL(12,4) DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    avg_duration_min INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_daily (station_id, summary_date),
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
);

-- ── Car Type seed data
INSERT INTO car_types (name, brand, icon, connector_type, battery_kwh) VALUES
('Tesla Model 3',       'Tesla',      'electric_car',    'CCS2',   75.0),
('Tesla Model Y',       'Tesla',      'electric_car',    'CCS2',   82.0),
('BYD Atto 3',          'BYD',        'electric_car',    'CCS2',   60.5),
('BYD Seal',            'BYD',        'electric_car',    'CCS2',   82.6),
('Nissan Leaf',         'Nissan',     'electric_car',    'CHAdeMO',40.0),
('MG EP',               'MG',         'electric_car',    'CCS2',   50.3),
('BMW iX3',             'BMW',        'electric_car',    'CCS2',   80.0),
('Volvo XC40 Recharge', 'Volvo',      'electric_car',    'CCS2',   82.0),
('Hyundai IONIQ 5',     'Hyundai',    'electric_car',    'CCS2',   77.4),
('Kia EV6',             'Kia',        'electric_car',    'CCS2',   77.4),
('Toyota bZ4X',         'Toyota',     'electric_car',    'CCS2',   71.4),
('ORA Good Cat',        'GWM',        'electric_car',    'CCS2',   48.0),
('NETA V',              'NETA',       'electric_car',    'CCS2',   40.1),
('Other EV',            'Other',      'directions_car',  'Type2',   0.0);
