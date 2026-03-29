-- ============================================================
-- CSMS - Charging Station Management System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS csms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE csms;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    otp_code VARCHAR(6) DEFAULT NULL,
    otp_expires_at DATETIME DEFAULT NULL,
    role ENUM('admin','operator','viewer') DEFAULT 'operator',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Charging Stations table
CREATE TABLE IF NOT EXISTS stations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    location TEXT,
    address TEXT,
    latitude DECIMAL(10,8) DEFAULT NULL,
    longitude DECIMAL(11,8) DEFAULT NULL,
    status ENUM('active','inactive','maintenance') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chargers (Controllers) table
CREATE TABLE IF NOT EXISTS chargers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    serial_number VARCHAR(100) NOT NULL UNIQUE,
    model VARCHAR(100),
    brand VARCHAR(100),
    max_power_kw DECIMAL(8,2) DEFAULT 0,
    controller_status ENUM('Online','Offline','Faulted','Updating') DEFAULT 'Offline',
    firmware_version VARCHAR(50) DEFAULT NULL,
    last_heartbeat DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
);

-- Connectors (Charger Heads) table
CREATE TABLE IF NOT EXISTS connectors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    charger_id INT NOT NULL,
    connector_number INT NOT NULL DEFAULT 1,
    connector_type ENUM('Type1','Type2','CCS1','CCS2','CHAdeMO','GB/T') DEFAULT 'Type2',
    status ENUM('Ready to use','Plugged in','Charging in progress','Charging paused by vehicle','Charging paused by charger','Charging finish','Unavailable') DEFAULT 'Unavailable',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (charger_id) REFERENCES chargers(id) ON DELETE CASCADE
);

-- Service Fee Settings table
CREATE TABLE IF NOT EXISTS service_fee_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    station_id INT NOT NULL,
    fee_type ENUM('kWh-Based','Time-Based','TOU','Free Charge') DEFAULT 'kWh-Based',
    price_per_kwh DECIMAL(10,4) DEFAULT 0.00,
    price_per_minute DECIMAL(10,4) DEFAULT 0.00,
    peak_price DECIMAL(10,4) DEFAULT 0.00,
    offpeak_price DECIMAL(10,4) DEFAULT 0.00,
    peak_start TIME DEFAULT '09:00:00',
    peak_end TIME DEFAULT '22:00:00',
    currency VARCHAR(10) DEFAULT 'THB',
    effective_from DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    connector_id INT NOT NULL,
    charger_id INT NOT NULL,
    station_id INT NOT NULL,
    user_id INT NOT NULL,
    estimate_amount DECIMAL(10,2) DEFAULT 0.00,
    actual_amount DECIMAL(10,2) DEFAULT 0.00,
    energy_kwh DECIMAL(10,4) DEFAULT 0.0000,
    start_time DATETIME DEFAULT NULL,
    stop_time DATETIME DEFAULT NULL,
    duration_minutes INT DEFAULT 0,
    status ENUM('Pending','Charging','Completed','Stopped','Faulted') DEFAULT 'Pending',
    stop_reason ENUM('EVDisconnected','Local','Remote','PowerLoss','Other') DEFAULT NULL,
    remark TEXT,
    fee_type ENUM('kWh-Based','Time-Based','TOU','Free Charge') DEFAULT 'kWh-Based',
    price_per_kwh DECIMAL(10,4) DEFAULT 0.00,
    ocpp_transaction_id VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (connector_id) REFERENCES connectors(id),
    FOREIGN KEY (charger_id) REFERENCES chargers(id),
    FOREIGN KEY (station_id) REFERENCES stations(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Real-time meter values (optional logging)
CREATE TABLE IF NOT EXISTS meter_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    power_kw DECIMAL(10,4) DEFAULT 0.00,
    energy_kwh DECIMAL(10,4) DEFAULT 0.00,
    voltage DECIMAL(8,2) DEFAULT 0.00,
    current_a DECIMAL(8,2) DEFAULT 0.00,
    soc_percent INT DEFAULT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);

-- System Logs
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(200),
    entity_type VARCHAR(50),
    entity_id INT DEFAULT NULL,
    detail TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Sample Data
-- ============================================================

-- Admin user (password: Admin@1234)
INSERT INTO users (first_name, last_name, phone, email, password, is_verified, role)
VALUES ('Admin', 'CSMS', '0812345678', 'admin@csms.local',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXV/WwK8S', 1, 'admin');
