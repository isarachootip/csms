-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: csms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `car_types`
--

DROP TABLE IF EXISTS `car_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `car_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'directions_car',
  `connector_type` enum('Type1','Type2','CCS1','CCS2','CHAdeMO','GB/T') DEFAULT 'Type2',
  `battery_kwh` decimal(8,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `car_types`
--

LOCK TABLES `car_types` WRITE;
/*!40000 ALTER TABLE `car_types` DISABLE KEYS */;
INSERT INTO `car_types` VALUES (1,'Tesla Model 3','Tesla','directions_car','CCS2',75.00,'2026-03-28 23:29:51'),(2,'Tesla Model Y','Tesla','directions_car','CCS2',82.00,'2026-03-28 23:29:51'),(3,'BYD Atto 3','BYD','directions_car','CCS2',60.50,'2026-03-28 23:29:51'),(4,'BYD Seal','BYD','directions_car','CCS2',82.60,'2026-03-28 23:29:51'),(5,'Nissan Leaf','Nissan','directions_car','CHAdeMO',40.00,'2026-03-28 23:29:51'),(6,'MG EP','MG','directions_car','CCS2',50.30,'2026-03-28 23:29:51'),(7,'BMW iX3','BMW','directions_car','CCS2',80.00,'2026-03-28 23:29:51'),(8,'Volvo XC40 Recharge','Volvo','directions_car','CCS2',82.00,'2026-03-28 23:29:51'),(9,'Hyundai IONIQ 5','Hyundai','directions_car','CCS2',77.40,'2026-03-28 23:29:51'),(10,'Kia EV6','Kia','directions_car','CCS2',77.40,'2026-03-28 23:29:51'),(11,'Toyota bZ4X','Toyota','directions_car','CCS2',71.40,'2026-03-28 23:29:51'),(12,'ORA Good Cat','GWM','directions_car','CCS2',48.00,'2026-03-28 23:29:51'),(13,'NETA V','NETA','directions_car','CCS2',40.10,'2026-03-28 23:29:51'),(14,'Other EV','Other','directions_car','Type2',0.00,'2026-03-28 23:29:51');
/*!40000 ALTER TABLE `car_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chargers`
--

DROP TABLE IF EXISTS `chargers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chargers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `station_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `max_power_kw` decimal(8,2) DEFAULT 0.00,
  `controller_status` enum('Online','Offline','Faulted','Updating') DEFAULT 'Offline',
  `firmware_version` varchar(50) DEFAULT NULL,
  `last_heartbeat` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `station_id` (`station_id`),
  CONSTRAINT `chargers_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chargers`
--

LOCK TABLES `chargers` WRITE;
/*!40000 ALTER TABLE `chargers` DISABLE KEYS */;
INSERT INTO `chargers` VALUES (1,1,'EVCS-LPR-001','Terra AC W22','ABB',22.00,'Online',NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51','2026-03-28 23:29:51'),(2,1,'EVCS-LPR-002','Terra DC 60','ABB',60.00,'Online',NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51','2026-03-28 23:29:51'),(3,1,'EVCS-LPR-003','Wallbox Pulsar','Wallbox',7.40,'Offline',NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51','2026-03-28 23:29:51'),(4,1,'EVCS-LPR-004','Alfen Eve','Alfen',22.00,'Faulted',NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51','2026-03-28 23:29:51'),(5,2,'EVCS-SKW-001','Terra AC W22','ABB',22.00,'Online',NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51','2026-03-28 23:29:51'),(6,2,'EVCS-SKW-002','Juice Charger','Juicebar',11.00,'Online',NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51','2026-03-28 23:29:51');
/*!40000 ALTER TABLE `chargers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `connectors`
--

DROP TABLE IF EXISTS `connectors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `connectors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `charger_id` int(11) NOT NULL,
  `connector_number` int(11) NOT NULL DEFAULT 1,
  `connector_type` enum('Type1','Type2','CCS1','CCS2','CHAdeMO','GB/T') DEFAULT 'Type2',
  `status` enum('Ready to use','Plugged in','Charging in progress','Charging paused by vehicle','Charging paused by charger','Charging finish','Unavailable') DEFAULT 'Unavailable',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `charger_id` (`charger_id`),
  CONSTRAINT `connectors_ibfk_1` FOREIGN KEY (`charger_id`) REFERENCES `chargers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `connectors`
--

LOCK TABLES `connectors` WRITE;
/*!40000 ALTER TABLE `connectors` DISABLE KEYS */;
INSERT INTO `connectors` VALUES (1,1,1,'Type2','Ready to use','2026-03-28 23:29:51','2026-03-29 09:27:45'),(2,2,1,'CCS2','Ready to use','2026-03-28 23:29:51','2026-03-28 23:29:51'),(3,3,1,'Type2','Unavailable','2026-03-28 23:29:51','2026-03-28 23:29:51'),(4,4,1,'Type2','Unavailable','2026-03-28 23:29:51','2026-03-28 23:29:51'),(5,5,1,'Type2','Ready to use','2026-03-28 23:29:51','2026-03-28 23:29:51'),(6,6,1,'Type2','Ready to use','2026-03-28 23:29:51','2026-03-28 23:29:51');
/*!40000 ALTER TABLE `connectors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_favorites`
--

DROP TABLE IF EXISTS `customer_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fav` (`customer_id`,`station_id`),
  KEY `station_id` (`station_id`),
  CONSTRAINT `customer_favorites_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_favorites_ibfk_2` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_favorites`
--

LOCK TABLES `customer_favorites` WRITE;
/*!40000 ALTER TABLE `customer_favorites` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_notifications`
--

DROP TABLE IF EXISTS `customer_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `type` enum('session','wallet','promo','system','alert') DEFAULT 'system',
  `title` varchar(200) NOT NULL,
  `body` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'notifications',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `customer_notifications_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_notifications`
--

LOCK TABLES `customer_notifications` WRITE;
/*!40000 ALTER TABLE `customer_notifications` DISABLE KEYS */;
INSERT INTO `customer_notifications` VALUES (1,1,'system','α╕óα╕┤α╕Öα╕öα╕╡α╕òα╣ëα╕¡α╕Öα╕úα╕▒α╕Üα╕¬α╕╣α╣ê CSMS! ≡ƒÄë','α╕¬α╕íα╕▒α╕äα╕úα╕¬α╕íα╕▓α╕èα╕┤α╕üα╕¬α╕│α╣Çα╕úα╣çα╕êα╣üα╕Ñα╣ëα╕º α╣Çα╕úα╕┤α╣êα╕íα╕èα╕▓α╕úα╣îα╕êα╕úα╕ûα╕éα╕¡α╕çα╕äα╕╕α╕ôα╣äα╕öα╣ëα╣Çα╕Ñα╕ó','celebration',NULL,'2026-03-29 05:38:31'),(2,16,'session','α╣Çα╕úα╕┤α╣êα╕íα╕èα╕▓α╕úα╣îα╕êα╣üα╕Ñα╣ëα╕º ΓÜí','α╕èα╕▓α╕úα╣îα╕êα╕ùα╕╡α╣ê connector #1 α╕¬α╕│α╣Çα╕úα╣çα╕êα╣üα╕Ñα╣ëα╕º','bolt',NULL,'2026-03-29 05:48:22'),(3,16,'session','α╕èα╕▓α╕úα╣îα╕êα╣Çα╕¬α╕úα╣çα╕êα╣üα╕Ñα╣ëα╕º! Γ£à','α╕èα╕▓α╕úα╣îα╕ê 0.3667 kWh α╕äα╣êα╕▓α╕Üα╕úα╕┤α╕üα╕▓α╕ú α╕┐2.02 α╕ùα╕╡α╣ê α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╕¬α╕▓α╕éα╕▓α╕Ñα╕▓α╕öα╕₧α╕úα╣ëα╕▓α╕º','check_circle',NULL,'2026-03-29 05:48:48'),(4,16,'session','α╣Çα╕úα╕┤α╣êα╕íα╕èα╕▓α╕úα╣îα╕êα╣üα╕Ñα╣ëα╕º ΓÜí','α╕èα╕▓α╕úα╣îα╕êα╕ùα╕╡α╣ê connector #1 α╕¬α╕│α╣Çα╕úα╣çα╕êα╣üα╕Ñα╣ëα╕º','bolt',NULL,'2026-03-29 05:52:16'),(5,16,'session','α╕èα╕▓α╕úα╣îα╕êα╣Çα╕¬α╕úα╣çα╕êα╣üα╕Ñα╣ëα╕º! Γ£à','α╕èα╕▓α╕úα╣îα╕ê 0.3667 kWh α╕äα╣êα╕▓α╕Üα╕úα╕┤α╕üα╕▓α╕ú α╕┐2.02 α╕ùα╕╡α╣ê α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╕¬α╕▓α╕éα╕▓α╕Ñα╕▓α╕öα╕₧α╕úα╣ëα╕▓α╕º','check_circle',NULL,'2026-03-29 05:52:20'),(6,16,'session','α╣Çα╕úα╕┤α╣êα╕íα╕èα╕▓α╕úα╣îα╕êα╣üα╕Ñα╣ëα╕º ΓÜí','α╕èα╕▓α╕úα╣îα╕êα╕ùα╕╡α╣ê connector #1 α╕¬α╕│α╣Çα╕úα╣çα╕êα╣üα╕Ñα╣ëα╕º','bolt',NULL,'2026-03-29 05:53:25'),(7,16,'session','α╕èα╕▓α╕úα╣îα╕êα╣Çα╕¬α╕úα╣çα╕êα╣üα╕Ñα╣ëα╕º! Γ£à','α╕èα╕▓α╕úα╣îα╕ê 78.4667 kWh α╕äα╣êα╕▓α╕Üα╕úα╕┤α╕üα╕▓α╕ú α╕┐431.57 α╕ùα╕╡α╣ê α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╕¬α╕▓α╕éα╕▓α╕Ñα╕▓α╕öα╕₧α╕úα╣ëα╕▓α╕º','check_circle',NULL,'2026-03-29 09:27:45'),(8,17,'system','α╕óα╕┤α╕Öα╕öα╕╡α╕òα╣ëα╕¡α╕Öα╕úα╕▒α╕Üα╕¬α╕╣α╣ê EV Charge! ≡ƒÄë','α╕¬α╕íα╕▒α╕äα╕úα╕¬α╕íα╕▓α╕èα╕┤α╕üα╕¬α╕│α╣Çα╕úα╣çα╕êα╣üα╕Ñα╣ëα╕º α╣Çα╕úα╕┤α╣êα╕íα╕èα╕▓α╕úα╣îα╕êα╕úα╕ûα╕éα╕¡α╕çα╕äα╕╕α╕ôα╣äα╕öα╣ëα╣Çα╕Ñα╕ó','celebration',NULL,'2026-03-29 12:07:44');
/*!40000 ALTER TABLE `customer_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_vehicles`
--

DROP TABLE IF EXISTS `customer_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `car_type_id` int(11) DEFAULT NULL,
  `license_plate` varchar(30) NOT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `car_type_id` (`car_type_id`),
  CONSTRAINT `customer_vehicles_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_vehicles_ibfk_2` FOREIGN KEY (`car_type_id`) REFERENCES `car_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_vehicles`
--

LOCK TABLES `customer_vehicles` WRITE;
/*!40000 ALTER TABLE `customer_vehicles` DISABLE KEYS */;
INSERT INTO `customer_vehicles` VALUES (1,1,1,'α╕üα╕é-1234','α╕úα╕ûα╕½α╕Ñα╕▒α╕ü',NULL,1,'2026-03-29 05:38:31'),(2,1,3,'α╕üα╕ä-5678','α╕úα╕ûα╕ùα╕╡α╣êα╕Üα╣ëα╕▓α╕Ö',NULL,0,'2026-03-29 05:38:31');
/*!40000 ALTER TABLE `customer_vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `license_plate` varchar(30) DEFAULT NULL,
  `car_type_id` int(11) DEFAULT NULL,
  `member_since` date DEFAULT (curdate()),
  `total_sessions` int(11) DEFAULT 0,
  `total_kwh` decimal(12,4) DEFAULT 0.0000,
  `total_spend` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `api_token` varchar(100) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `car_type_id` (`car_type_id`),
  CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`car_type_id`) REFERENCES `car_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,1,'α╕¬α╕íα╕èα╕▓α╕ó α╣âα╕êα╕öα╕╡','0811111111','somchai@email.com','α╕üα╕é-1234',1,'2025-01-15',4,141.2000,776.50,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(2,1,'α╕ºα╕┤α╕áα╕▓ α╕úα╕▒α╕üα╣äα╕ùα╕ó','0822222222','wipa@email.com','α╕üα╕ä-5678',3,'2025-02-01',3,89.8000,494.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(3,1,'α╕¡α╕Öα╕╕α╕èα╕▓ α╕¬α╕íα╕Üα╕╣α╕úα╕ôα╣î','0833333333','anucha@email.com','α╕éα╕ü-9999',9,'2025-03-10',2,61.0000,366.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(4,1,'α╕₧α╕┤α╕íα╕₧α╣îα╣âα╕ê α╕èα╕╖α╣êα╕Öα╕èα╕í','0844444444','pimjai@email.com','α╕₧α╕í-4444',4,'2025-04-05',4,232.9000,1371.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(5,1,'α╕ÿα╕Öα╕üα╕ú α╕ºα╕çα╕¿α╣îα╕öα╕╡','0855555555','thanakorn@email.com','α╕çα╕ç-1111',2,'2025-05-20',2,27.0000,155.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(6,1,'α╕íα╕▓α╕Ñα╕╡ α╕¬α╕╕α╕éα╣âα╕ê','0866666666','malee@email.com','α╕íα╕ü-7777',6,'2025-06-01',4,104.6000,587.50,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(7,1,'α╕èα╕▓α╕ìα╕ôα╕úα╕çα╕äα╣î α╣Çα╕üα╣êα╕çα╕öα╕╡','0877777777','channarong@email.com','α╕¢α╕ü-2222',7,'2025-07-11',3,170.0000,935.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(8,1,'α╕¿α╕┤α╕úα╕┤α╕ºα╕úα╕úα╕ô α╣üα╕¬α╕çα╣üα╕üα╣ëα╕º','0888888888','siriwan@email.com','α╕¬α╕ü-3333',10,'2025-08-03',3,170.6000,971.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(9,1,'α╕ôα╕▒α╕Éα╕₧α╕Ñ α╕íα╕╡α╕¬α╕╕α╕é','0899999999','nattapon@email.com','α╕òα╕ü-8888',5,'2025-09-15',1,14.2000,78.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(10,1,'α╕¢α╕úα╕░α╕áα╕▓α╕¬ α╣éα╕èα╕òα╕┤','0800000001','praphat@email.com','α╕úα╕ü-6666',11,'2025-10-01',2,109.0000,632.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(11,1,'α╕¡α╕úα╕¡α╕╕α╕íα╕▓ α╕ùα╕¡α╕çα╕öα╕╡','0800000002','onuma@email.com','α╕èα╕ü-5555',8,'2025-11-20',1,40.0000,240.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(12,1,'α╕üα╕ñα╕⌐α╕ôα╣î α╕¿α╕úα╕╡α╕¬α╕ºα╕▒α╕¬α╕öα╕┤α╣î','0800000003','krit@email.com','α╕Öα╕ü-4444',12,'2025-12-05',2,72.0000,396.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(13,1,'α╕₧α╕úα╕úα╕ôα╕╡ α╕êα╕▒α╕Öα╕ùα╕úα╣îα╕çα╕▓α╕í','0800000004','pannee@email.com','α╕¡α╕ü-1111',13,'2026-01-10',2,53.3000,293.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(14,1,'α╕ºα╕╡α╕úα╕░ α╕¬α╕╕α╕ºα╕úα╕úα╕ô','0800000005','weera@email.com','α╕Üα╕ü-2222',1,'2026-02-14',2,51.0000,292.50,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(15,1,'α╕Öα╕áα╕▓ α╕úα╕╕α╣êα╕çα╣Çα╕úα╕╖α╕¡α╕ç','0800000006','napa@email.com','α╕Ñα╕ü-3333',3,'2026-03-01',1,49.0000,294.00,NULL,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51',NULL,NULL),(16,2,'α╕¬α╕íα╕èα╕▓α╕ó α╣âα╕êα╕öα╕╡','0811111111','customer@csms.local','α╕üα╕é-1234',1,'2025-01-15',3,79.2001,435.61,NULL,NULL,'2026-03-29 05:38:31','2026-03-29 09:27:45',NULL,NULL),(17,3,'????? ????','0899000001','test_new@csms.local','??-0001',NULL,'2026-03-29',0,0.0000,0.00,NULL,NULL,'2026-03-29 12:07:43','2026-03-29 12:07:50','25834aeb48fa0b8a9dd866eb3ab3fd8ec4bb72a58f4d44110203b43f358d9746','2026-04-28 12:07:50');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_summary`
--

DROP TABLE IF EXISTS `daily_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `station_id` int(11) NOT NULL,
  `summary_date` date NOT NULL,
  `sessions` int(11) DEFAULT 0,
  `unique_customers` int(11) DEFAULT 0,
  `total_kwh` decimal(12,4) DEFAULT 0.0000,
  `total_revenue` decimal(12,2) DEFAULT 0.00,
  `avg_duration_min` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_daily` (`station_id`,`summary_date`),
  CONSTRAINT `daily_summary_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_summary`
--

LOCK TABLES `daily_summary` WRITE;
/*!40000 ALTER TABLE `daily_summary` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meter_values`
--

DROP TABLE IF EXISTS `meter_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meter_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `power_kw` decimal(10,4) DEFAULT 0.0000,
  `energy_kwh` decimal(10,4) DEFAULT 0.0000,
  `voltage` decimal(8,2) DEFAULT 0.00,
  `current_a` decimal(8,2) DEFAULT 0.00,
  `soc_percent` int(11) DEFAULT NULL,
  `recorded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `meter_values_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meter_values`
--

LOCK TABLES `meter_values` WRITE;
/*!40000 ALTER TABLE `meter_values` DISABLE KEYS */;
/*!40000 ALTER TABLE `meter_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_fee_settings`
--

DROP TABLE IF EXISTS `service_fee_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_fee_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `station_id` int(11) NOT NULL,
  `fee_type` enum('kWh-Based','Time-Based','TOU','Free Charge') DEFAULT 'kWh-Based',
  `price_per_kwh` decimal(10,4) DEFAULT 0.0000,
  `price_per_minute` decimal(10,4) DEFAULT 0.0000,
  `peak_price` decimal(10,4) DEFAULT 0.0000,
  `offpeak_price` decimal(10,4) DEFAULT 0.0000,
  `peak_start` time DEFAULT '09:00:00',
  `peak_end` time DEFAULT '22:00:00',
  `currency` varchar(10) DEFAULT 'THB',
  `effective_from` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `station_id` (`station_id`),
  CONSTRAINT `service_fee_settings_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_fee_settings`
--

LOCK TABLES `service_fee_settings` WRITE;
/*!40000 ALTER TABLE `service_fee_settings` DISABLE KEYS */;
INSERT INTO `service_fee_settings` VALUES (1,1,'kWh-Based',5.5000,0.0000,0.0000,0.0000,'09:00:00','22:00:00','THB','2026-01-01',1,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(2,2,'kWh-Based',6.0000,0.0000,0.0000,0.0000,'09:00:00','22:00:00','THB','2026-01-01',1,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(3,3,'Free Charge',0.0000,0.0000,0.0000,0.0000,'09:00:00','22:00:00','THB','2026-01-01',1,'2026-03-28 23:29:51','2026-03-28 23:29:51');
/*!40000 ALTER TABLE `service_fee_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `station_reviews`
--

DROP TABLE IF EXISTS `station_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `station_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `station_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review` (`customer_id`,`transaction_id`),
  KEY `station_id` (`station_id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `station_reviews_ibfk_1` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`),
  CONSTRAINT `station_reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `station_reviews_ibfk_3` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `station_reviews`
--

LOCK TABLES `station_reviews` WRITE;
/*!40000 ALTER TABLE `station_reviews` DISABLE KEYS */;
INSERT INTO `station_reviews` VALUES (1,1,16,37,5,'','2026-03-29 05:48:57');
/*!40000 ALTER TABLE `station_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stations`
--

DROP TABLE IF EXISTS `stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `location` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stations`
--

LOCK TABLES `stations` WRITE;
/*!40000 ALTER TABLE `stations` DISABLE KEYS */;
INSERT INTO `stations` VALUES (1,1,'α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╕¬α╕▓α╕éα╕▓α╕Ñα╕▓α╕öα╕₧α╕úα╣ëα╕▓α╕º','α╕èα╕▒α╣ëα╕Ö B1 α╣éα╕ïα╕Ö A','1234 α╕ûα╕Öα╕Öα╕Ñα╕▓α╕öα╕₧α╕úα╣ëα╕▓α╕º α╣üα╕éα╕ºα╕çα╕êα╕òα╕╕α╕êα╕▒α╕üα╕ú α╕üα╕úα╕╕α╕çα╣Çα╕ùα╕₧α╕» 10900',13.81890000,100.56710000,'active','2026-03-28 23:29:51','2026-03-28 23:29:51'),(2,1,'α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╕¬α╕▓α╕éα╕▓α╕¬α╕╕α╕éα╕╕α╕íα╕ºα╕┤α╕ù','α╕Ñα╕▓α╕Öα╕êα╕¡α╕öα╕úα╕ûα╕èα╕▒α╣ëα╕Ö 2','88 α╕ûα╕Öα╕Öα╕¬α╕╕α╕éα╕╕α╕íα╕ºα╕┤α╕ù α╣üα╕éα╕ºα╕çα╕äα╕Ñα╕¡α╕çα╣Çα╕òα╕ó α╕üα╕úα╕╕α╕çα╣Çα╕ùα╕₧α╕» 10110',13.73080000,100.56940000,'active','2026-03-28 23:29:51','2026-03-28 23:29:51'),(3,1,'α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╣Çα╕ïα╣çα╕Öα╕ùα╕úα╕▒α╕Ñα╕úα╕▒α╕òα╕Öα╕▓α╕ÿα╕┤α╣Çα╕Üα╕¿α╕úα╣î','α╕èα╕▒α╣ëα╕Ö P3','68 α╕ûα╕Öα╕Öα╕úα╕▒α╕òα╕Öα╕▓α╕ÿα╕┤α╣Çα╕Üα╕¿α╕úα╣î α╕Öα╕Öα╕ùα╕Üα╕╕α╕úα╕╡ 11000',13.85810000,100.51920000,'maintenance','2026-03-28 23:29:51','2026-03-28 23:29:51');
/*!40000 ALTER TABLE `stations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `category` enum('charging','payment','account','app','other') DEFAULT 'other',
  `subject` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(200) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (1,1,'LOGIN','users',1,'User logged in','::1','2026-03-28 23:30:13');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connector_id` int(11) NOT NULL,
  `charger_id` int(11) NOT NULL,
  `station_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `car_type_id` int(11) DEFAULT NULL,
  `estimate_amount` decimal(10,2) DEFAULT 0.00,
  `actual_amount` decimal(10,2) DEFAULT 0.00,
  `energy_kwh` decimal(10,4) DEFAULT 0.0000,
  `start_time` datetime DEFAULT NULL,
  `stop_time` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `status` enum('Pending','Charging','Completed','Stopped','Faulted') DEFAULT 'Pending',
  `stop_reason` enum('EVDisconnected','Local','Remote','PowerLoss','Other') DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `fee_type` enum('kWh-Based','Time-Based','TOU','Free Charge') DEFAULT 'kWh-Based',
  `price_per_kwh` decimal(10,4) DEFAULT 0.0000,
  `ocpp_transaction_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `connector_id` (`connector_id`),
  KEY `charger_id` (`charger_id`),
  KEY `station_id` (`station_id`),
  KEY `user_id` (`user_id`),
  KEY `fk_tx_customer` (`customer_id`),
  KEY `fk_tx_cartype` (`car_type_id`),
  CONSTRAINT `fk_tx_cartype` FOREIGN KEY (`car_type_id`) REFERENCES `car_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tx_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`connector_id`) REFERENCES `connectors` (`id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`charger_id`) REFERENCES `chargers` (`id`),
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`station_id`) REFERENCES `stations` (`id`),
  CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,1,1,1,1,1,1,200.00,192.50,35.0000,'2026-01-05 09:10:00','2026-01-05 11:10:00',120,'Completed',NULL,'α╕üα╕é-1234','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(2,2,2,1,1,2,3,150.00,143.00,26.0000,'2026-01-07 14:00:00','2026-01-07 15:30:00',90,'Completed',NULL,'α╕üα╕ä-5678','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(3,5,5,2,1,4,4,300.00,288.00,48.0000,'2026-01-10 10:00:00','2026-01-10 12:00:00',120,'Completed',NULL,'α╕₧α╕í-4444','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(4,1,1,1,1,6,6,100.00,97.00,17.6000,'2026-01-12 16:00:00','2026-01-12 17:00:00',60,'Completed',NULL,'α╕íα╕ü-7777','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(5,2,2,1,1,7,7,250.00,242.00,44.0000,'2026-01-15 11:00:00','2026-01-15 13:30:00',150,'Completed',NULL,'α╕¢α╕ü-2222','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(6,5,5,2,1,3,9,180.00,174.00,29.0000,'2026-01-18 09:00:00','2026-01-18 10:30:00',90,'Completed',NULL,'α╕éα╕ü-9999','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(7,1,1,1,1,8,10,200.00,193.00,35.1000,'2026-01-20 13:00:00','2026-01-20 15:00:00',120,'Completed',NULL,'α╕¬α╕ü-3333','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(8,2,2,1,1,5,2,80.00,77.00,14.0000,'2026-01-22 10:00:00','2026-01-22 10:45:00',45,'Completed',NULL,'α╕çα╕ç-1111','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(9,5,5,2,1,10,11,400.00,390.00,65.0000,'2026-01-25 08:00:00','2026-01-25 10:30:00',150,'Completed',NULL,'α╕úα╕ü-6666','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(10,1,1,1,1,12,12,300.00,297.00,54.0000,'2026-01-28 14:00:00','2026-01-28 17:00:00',180,'Completed',NULL,'α╕Öα╕ü-4444','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(11,1,1,1,1,1,1,200.00,196.00,35.6000,'2026-02-03 09:00:00','2026-02-03 11:00:00',120,'Completed',NULL,'α╕üα╕é-1234','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(12,2,2,1,1,13,13,100.00,99.00,18.0000,'2026-02-05 15:00:00','2026-02-05 16:00:00',60,'Completed',NULL,'α╕¡α╕ü-1111','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(13,5,5,2,1,4,4,500.00,495.00,82.5000,'2026-02-08 10:00:00','2026-02-08 13:00:00',180,'Completed',NULL,'α╕₧α╕í-4444','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(14,1,1,1,1,14,1,150.00,148.50,27.0000,'2026-02-10 08:00:00','2026-02-10 09:30:00',90,'Completed',NULL,'α╕Üα╕ü-2222','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(15,2,2,1,1,6,6,200.00,198.00,36.0000,'2026-02-12 14:00:00','2026-02-12 16:00:00',120,'Completed',NULL,'α╕íα╕ü-7777','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(16,5,5,2,1,11,8,250.00,240.00,40.0000,'2026-02-14 10:00:00','2026-02-14 12:30:00',150,'Completed',NULL,'α╕èα╕ü-5555','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(17,1,1,1,1,9,5,80.00,78.00,14.2000,'2026-02-16 16:00:00','2026-02-16 17:00:00',60,'Completed',NULL,'α╕òα╕ü-8888','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(18,2,2,1,1,2,3,180.00,175.00,31.8000,'2026-02-18 09:00:00','2026-02-18 10:45:00',105,'Completed',NULL,'α╕üα╕ä-5678','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(19,5,5,2,1,15,3,300.00,294.00,49.0000,'2026-02-20 11:00:00','2026-02-20 13:00:00',120,'Completed',NULL,'α╕Ñα╕ü-3333','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(20,1,1,1,1,7,7,400.00,396.00,72.0000,'2026-02-25 13:00:00','2026-02-25 16:00:00',180,'Completed',NULL,'α╕¢α╕ü-2222','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(21,1,1,1,1,1,1,200.00,194.00,35.3000,'2026-03-01 09:00:00','2026-03-01 11:00:00',120,'Completed',NULL,'α╕üα╕é-1234','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(22,2,2,1,1,4,4,300.00,297.00,49.5000,'2026-03-03 14:00:00','2026-03-03 17:00:00',180,'Completed',NULL,'α╕₧α╕í-4444','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(23,5,5,2,1,3,9,200.00,192.00,32.0000,'2026-03-05 10:00:00','2026-03-05 12:00:00',120,'Completed',NULL,'α╕éα╕ü-9999','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(24,1,1,1,1,6,6,150.00,148.50,27.0000,'2026-03-07 08:00:00','2026-03-07 09:30:00',90,'Completed',NULL,'α╕íα╕ü-7777','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(25,2,2,1,1,12,12,100.00,99.00,18.0000,'2026-03-10 15:00:00','2026-03-10 16:00:00',60,'Completed',NULL,'α╕Öα╕ü-4444','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(26,5,5,2,1,8,10,400.00,390.00,65.0000,'2026-03-12 11:00:00','2026-03-12 13:30:00',150,'Completed',NULL,'α╕¬α╕ü-3333','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(27,1,1,1,1,2,3,180.00,176.00,32.0000,'2026-03-15 09:00:00','2026-03-15 11:00:00',120,'Completed',NULL,'α╕üα╕ä-5678','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(28,2,2,1,1,10,11,250.00,242.00,44.0000,'2026-03-18 14:00:00','2026-03-18 16:30:00',150,'Completed',NULL,'α╕úα╕ü-6666','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(29,5,5,2,1,5,2,80.00,78.00,13.0000,'2026-03-20 16:00:00','2026-03-20 17:00:00',60,'Completed',NULL,'α╕çα╕ç-1111','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(30,1,1,1,1,7,7,300.00,297.00,54.0000,'2026-03-22 10:00:00','2026-03-22 13:00:00',180,'Completed',NULL,'α╕¢α╕ü-2222','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(31,2,2,1,1,13,13,200.00,194.00,35.3000,'2026-03-24 09:00:00','2026-03-24 11:00:00',120,'Completed',NULL,'α╕¡α╕ü-1111','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(32,5,5,2,1,14,1,150.00,144.00,24.0000,'2026-03-26 14:00:00','2026-03-26 15:30:00',90,'Completed',NULL,'α╕Üα╕ü-2222','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(33,1,1,1,1,1,1,200.00,194.00,35.3000,'2026-03-28 07:30:00','2026-03-28 09:30:00',120,'Completed',NULL,'α╕üα╕é-1234','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(34,2,2,1,1,4,4,300.00,291.00,52.9000,'2026-03-28 10:00:00','2026-03-28 12:30:00',150,'Completed',NULL,'α╕₧α╕í-4444','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(35,5,5,2,1,6,6,150.00,144.00,24.0000,'2026-03-28 13:00:00','2026-03-28 14:30:00',90,'Completed',NULL,'α╕íα╕ü-7777','kWh-Based',6.0000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(36,1,1,1,1,8,10,400.00,388.00,70.5000,'2026-03-28 15:00:00','2026-03-28 18:00:00',180,'Completed',NULL,'α╕¬α╕ü-3333','kWh-Based',5.5000,NULL,'2026-03-28 23:29:51','2026-03-28 23:29:51'),(37,1,1,1,1,16,NULL,100.00,2.02,0.3667,'2026-03-29 05:48:22','2026-03-29 05:48:48',1,'Completed',NULL,'','kWh-Based',5.5000,NULL,'2026-03-29 05:48:22','2026-03-29 05:48:48'),(38,1,1,1,1,16,NULL,100.00,2.02,0.3667,'2026-03-29 05:52:16','2026-03-29 05:52:20',1,'Completed',NULL,'','kWh-Based',5.5000,NULL,'2026-03-29 05:52:16','2026-03-29 05:52:20'),(39,1,1,1,1,16,NULL,200.00,431.57,78.4667,'2026-03-29 05:53:25','2026-03-29 09:27:45',214,'Completed',NULL,'','kWh-Based',5.5000,NULL,'2026-03-29 05:53:25','2026-03-29 09:27:45');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `role` enum('admin','operator','viewer','customer') DEFAULT 'customer',
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','CSMS','0812345678','admin@csms.local','$2y$10$lJJCGXZNDFxJfwTwg0nJZO65Vn4Udo14ff0eBxDPzjVzmSScQPv8O',1,NULL,NULL,'admin',NULL,'2026-03-28 23:00:58','2026-03-28 23:29:51'),(2,'α╕¬α╕íα╕èα╕▓α╕ó','α╣âα╕êα╕öα╕╡','0811111111','customer@csms.local','$2y$10$EvEM8kYxAdtq.BPMwKDOeOzV4tOU59I/IYv/KiS0z3hACLWk78ToO',1,NULL,NULL,'operator',NULL,'2026-03-29 05:38:31','2026-03-29 05:38:31'),(3,'?????','????','0899000001','test_new@csms.local','$2y$10$gYmkiwxO6GHG3kg5MXSIOuclAfKQVeaso1NRdv1kNme3a1KfEVAZ6',1,NULL,NULL,'customer',NULL,'2026-03-29 12:07:43','2026-03-29 12:07:43');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_accounts`
--

DROP TABLE IF EXISTS `wallet_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `balance` decimal(12,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'THB',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_id` (`customer_id`),
  CONSTRAINT `wallet_accounts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet_accounts`
--

LOCK TABLES `wallet_accounts` WRITE;
/*!40000 ALTER TABLE `wallet_accounts` DISABLE KEYS */;
INSERT INTO `wallet_accounts` VALUES (1,1,250.00,'THB','2026-03-29 05:38:31'),(2,2,100.00,'THB','2026-03-29 05:38:31'),(3,3,100.00,'THB','2026-03-29 05:38:31'),(4,4,100.00,'THB','2026-03-29 05:38:31'),(5,5,100.00,'THB','2026-03-29 05:38:31'),(6,6,100.00,'THB','2026-03-29 05:38:31'),(7,7,100.00,'THB','2026-03-29 05:38:31'),(8,8,100.00,'THB','2026-03-29 05:38:31'),(9,9,100.00,'THB','2026-03-29 05:38:31'),(10,10,100.00,'THB','2026-03-29 05:38:31'),(11,11,100.00,'THB','2026-03-29 05:38:31'),(12,12,100.00,'THB','2026-03-29 05:38:31'),(13,13,100.00,'THB','2026-03-29 05:38:31'),(14,14,100.00,'THB','2026-03-29 05:38:31'),(15,15,100.00,'THB','2026-03-29 05:38:31'),(16,16,0.00,'THB','2026-03-29 09:27:45'),(17,17,0.00,'THB','2026-03-29 12:07:43');
/*!40000 ALTER TABLE `wallet_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_transactions`
--

DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` int(11) NOT NULL,
  `type` enum('topup','charge','refund','reward') NOT NULL DEFAULT 'topup',
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_id` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `wallet_id` (`wallet_id`),
  CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`wallet_id`) REFERENCES `wallet_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet_transactions`
--

LOCK TABLES `wallet_transactions` WRITE;
/*!40000 ALTER TABLE `wallet_transactions` DISABLE KEYS */;
INSERT INTO `wallet_transactions` VALUES (1,16,'charge',2.02,97.98,NULL,'α╕èα╕▓α╕úα╣îα╕ê Tx#37 α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╕¬α╕▓α╕éα╕▓α╕Ñα╕▓α╕öα╕₧α╕úα╣ëα╕▓α╕º','2026-03-29 05:48:48'),(2,16,'charge',2.02,95.96,NULL,'α╕èα╕▓α╕úα╣îα╕ê Tx#38 α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╕¬α╕▓α╕éα╕▓α╕Ñα╕▓α╕öα╕₧α╕úα╣ëα╕▓α╕º','2026-03-29 05:52:20'),(3,16,'charge',431.57,0.00,NULL,'α╕èα╕▓α╕úα╣îα╕ê Tx#39 α╕¬α╕ûα╕▓α╕Öα╕╡ EV α╕¬α╕▓α╕éα╕▓α╕Ñα╕▓α╕öα╕₧α╕úα╣ëα╕▓α╕º','2026-03-29 09:27:45');
/*!40000 ALTER TABLE `wallet_transactions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-29 13:41:58
