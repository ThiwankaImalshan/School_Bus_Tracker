-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 26, 2025 at 01:35 PM
-- Server version: 8.3.0
-- PHP Version: 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_bus_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `full_name`, `email`, `password_hash`, `phone`, `role`, `is_active`, `created_at`, `last_login`) VALUES
(1, 'System Administrator', 'admin@schoolbus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'super_admin', 1, '2025-03-22 09:34:31', '2025-03-26 18:42:25');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id` int NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `bus_seat_id` int DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `pickup_time` time DEFAULT NULL,
  `drop_time` time DEFAULT NULL,
  `status` enum('present','absent','partial') NOT NULL DEFAULT 'absent',
  `notes` text,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `unique_child_attendance` (`child_id`,`attendance_date`),
  KEY `idx_attendance_seat` (`bus_seat_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bus`
--

DROP TABLE IF EXISTS `bus`;
CREATE TABLE IF NOT EXISTS `bus` (
  `bus_id` int NOT NULL AUTO_INCREMENT,
  `bus_number` varchar(20) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `capacity` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `starting_location` varchar(100) DEFAULT NULL,
  `covering_cities` text,
  `registration_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `city` varchar(100) DEFAULT NULL,
  `covering_regions` text,
  PRIMARY KEY (`bus_id`),
  UNIQUE KEY `bus_number` (`bus_number`),
  UNIQUE KEY `license_plate` (`license_plate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bus_school`
--

DROP TABLE IF EXISTS `bus_school`;
CREATE TABLE IF NOT EXISTS `bus_school` (
  `bus_school_id` int NOT NULL AUTO_INCREMENT,
  `bus_id` int NOT NULL,
  `school_id` int NOT NULL,
  PRIMARY KEY (`bus_school_id`),
  KEY `bus_id` (`bus_id`),
  KEY `school_id` (`school_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bus_seat`
--

DROP TABLE IF EXISTS `bus_seat`;
CREATE TABLE IF NOT EXISTS `bus_seat` (
  `seat_id` int NOT NULL AUTO_INCREMENT,
  `bus_id` int NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `seat_type` enum('window','aisle','middle') NOT NULL DEFAULT 'middle',
  `is_reserved` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`seat_id`),
  KEY `idx_bus_seat_bus` (`bus_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bus_tracking`
--

DROP TABLE IF EXISTS `bus_tracking`;
CREATE TABLE IF NOT EXISTS `bus_tracking` (
  `tracking_id` int NOT NULL AUTO_INCREMENT,
  `bus_id` int NOT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `speed` decimal(5,2) DEFAULT NULL,
  `distance_remaining` decimal(10,2) DEFAULT NULL,
  `estimated_arrival_minutes` int DEFAULT NULL,
  `route_id` int DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `current_location` varchar(255) DEFAULT NULL,
  `status` enum('ongoing','completed','delayed') DEFAULT 'ongoing',
  PRIMARY KEY (`tracking_id`),
  KEY `idx_tracking_bus_time` (`bus_id`,`timestamp`),
  KEY `idx_route_id` (`route_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `child`
--

DROP TABLE IF EXISTS `child`;
CREATE TABLE IF NOT EXISTS `child` (
  `child_id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL,
  `school_id` int DEFAULT NULL,
  `bus_id` int DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `grade` varchar(20) DEFAULT NULL,
  `pickup_location` text,
  `photo_url` varchar(255) DEFAULT NULL,
  `medical_notes` text,
  `emergency_contact` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`child_id`),
  KEY `idx_child_parent` (`parent_id`),
  KEY `idx_child_school` (`school_id`),
  KEY `idx_child_bus` (`bus_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `child`
--

INSERT INTO `child` (`child_id`, `parent_id`, `school_id`, `bus_id`, `first_name`, `last_name`, `grade`, `pickup_location`, `photo_url`, `medical_notes`, `emergency_contact`) VALUES
(6, 2, 15, NULL, 'Nethmi', 'Sathsarani', '12', 'home', NULL, '', '0112345678'),
(5, 2, 5, NULL, 'Kumara', 'Perera', '9', 'home', NULL, '', '0732145678'),
(4, 2, 6, NULL, 'Amal', 'Santha', '10', 'home', NULL, '', '0712345678'),
(7, 2, 1, NULL, 'Sandika', 'Dunith', '10', 'bus_stop_1', NULL, '', '0987643211'),
(8, 2, 6, NULL, 'Avishka', 'Fernando', '9', 'Grand Ma Home', NULL, '', '0119876432');

-- --------------------------------------------------------

--
-- Table structure for table `child_reservation`
--

DROP TABLE IF EXISTS `child_reservation`;
CREATE TABLE IF NOT EXISTS `child_reservation` (
  `reservation_id` int NOT NULL AUTO_INCREMENT,
  `seat_id` int NOT NULL,
  `child_id` int NOT NULL,
  `reservation_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reservation_id`),
  UNIQUE KEY `unique_seat_reservation` (`seat_id`,`reservation_date`),
  KEY `idx_reservation_child` (`child_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver`
--

DROP TABLE IF EXISTS `driver`;
CREATE TABLE IF NOT EXISTS `driver` (
  `driver_id` int NOT NULL AUTO_INCREMENT,
  `bus_id` int DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `license_expiry_date` date NOT NULL,
  `experience_years` int DEFAULT NULL,
  `age` int DEFAULT NULL,
  `joined_date` date DEFAULT NULL,
  PRIMARY KEY (`driver_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `bus_id` (`bus_id`),
  KEY `idx_driver_email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident`
--

DROP TABLE IF EXISTS `incident`;
CREATE TABLE IF NOT EXISTS `incident` (
  `incident_id` int NOT NULL AUTO_INCREMENT,
  `bus_id` int DEFAULT NULL,
  `driver_id` int DEFAULT NULL,
  `incident_date` datetime NOT NULL,
  `incident_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `action_taken` text,
  `status` enum('reported','investigating','resolved','closed') NOT NULL DEFAULT 'reported',
  PRIMARY KEY (`incident_id`),
  KEY `bus_id` (`bus_id`),
  KEY `driver_id` (`driver_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

DROP TABLE IF EXISTS `maintenance`;
CREATE TABLE IF NOT EXISTS `maintenance` (
  `maintenance_id` int NOT NULL AUTO_INCREMENT,
  `bus_id` int NOT NULL,
  `service_date` date NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `description` text,
  `cost` decimal(10,2) DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  PRIMARY KEY (`maintenance_id`),
  KEY `idx_maintenance_bus` (`bus_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

DROP TABLE IF EXISTS `message`;
CREATE TABLE IF NOT EXISTS `message` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `sender_type` enum('parent','driver','admin') NOT NULL,
  `recipient_id` int NOT NULL,
  `recipient_type` enum('parent','driver','admin') NOT NULL,
  `message_content` text NOT NULL,
  `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`message_id`),
  KEY `idx_message_sender` (`sender_type`,`sender_id`),
  KEY `idx_message_recipient` (`recipient_type`,`recipient_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

DROP TABLE IF EXISTS `notification`;
CREATE TABLE IF NOT EXISTS `notification` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('parent','driver','admin') NOT NULL,
  `recipient_id` int NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT '0',
  `notification_type` enum('alert','info','warning','success') NOT NULL DEFAULT 'info',
  PRIMARY KEY (`notification_id`),
  KEY `idx_notification_recipient` (`recipient_type`,`recipient_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parent`
--

DROP TABLE IF EXISTS `parent`;
CREATE TABLE IF NOT EXISTS `parent` (
  `parent_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `home_address` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`parent_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_parent_email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `parent`
--

INSERT INTO `parent` (`parent_id`, `full_name`, `email`, `password_hash`, `phone`, `home_address`, `created_at`, `last_login`) VALUES
(2, 'Thiwanka Imalshan', 'thiwankaimalshan2001@gmail.com', '$2y$10$wM7.MkoB76mvwUq4E2W6d.Qb6V52z3gcwKm0nHMhLnHtUroa/qmSS', '', '101/3, Waragoda Road, Kelaniya', '2025-03-22 15:49:45', '2025-03-24 19:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
CREATE TABLE IF NOT EXISTS `payment` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL,
  `description` text,
  `month_covered` date NOT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `idx_payment_child` (`child_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route`
--

DROP TABLE IF EXISTS `route`;
CREATE TABLE IF NOT EXISTS `route` (
  `route_id` int NOT NULL AUTO_INCREMENT,
  `bus_id` int DEFAULT NULL,
  `route_name` varchar(100) NOT NULL,
  `route_description` text,
  `roads` text,
  `morning_pickup_start` time DEFAULT NULL,
  `morning_dropoff_end` time DEFAULT NULL,
  `evening_pickup_start` time DEFAULT NULL,
  `evening_dropoff_end` time DEFAULT NULL,
  `primary_service_area` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`route_id`),
  KEY `idx_route_bus` (`bus_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route_school`
--

DROP TABLE IF EXISTS `route_school`;
CREATE TABLE IF NOT EXISTS `route_school` (
  `route_school_id` int NOT NULL AUTO_INCREMENT,
  `route_id` int NOT NULL,
  `school_id` int NOT NULL,
  `arrival_time` time NOT NULL,
  `departure_time` time NOT NULL,
  PRIMARY KEY (`route_school_id`),
  KEY `route_id` (`route_id`),
  KEY `school_id` (`school_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `route_stop`
--

DROP TABLE IF EXISTS `route_stop`;
CREATE TABLE IF NOT EXISTS `route_stop` (
  `stop_id` int NOT NULL AUTO_INCREMENT,
  `route_id` int NOT NULL,
  `location` varchar(100) NOT NULL,
  `estimated_time` time DEFAULT NULL,
  `sequence_number` int NOT NULL,
  PRIMARY KEY (`stop_id`),
  KEY `route_id` (`route_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school`
--

DROP TABLE IF EXISTS `school`;
CREATE TABLE IF NOT EXISTS `school` (
  `school_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `arrival_time` time DEFAULT NULL,
  `departure_time` time DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`school_id`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `school`
--

INSERT INTO `school` (`school_id`, `name`, `location`, `address`, `arrival_time`, `departure_time`, `contact_number`) VALUES
(1, 'Royal College', 'Colombo 07', 'Rajakeeya Mawatha, Colombo 07, Sri Lanka', '07:30:00', '14:00:00', '+94112691029'),
(2, 'Visakha Vidyalaya', 'Colombo 05', '133 Vajira Road, Colombo 05, Sri Lanka', '07:15:00', '13:45:00', '+94112588334'),
(3, 'Ananda College', 'Colombo 10', 'Maradana Road, Colombo 10, Sri Lanka', '07:30:00', '14:15:00', '+94112695162'),
(4, 'Colombo International School', 'Colombo 03', '57 Horton Place, Colombo 03, Sri Lanka', '08:00:00', '15:00:00', '+94112576012'),
(5, 'Devi Balika Vidyalaya', 'Colombo 08', 'Devi Balika Mawatha, Colombo 08, Sri Lanka', '07:20:00', '13:50:00', '+94112695256'),
(6, 'D.S. Senanayake College', 'Colombo 07', 'Nawala Road, Colombo 07, Sri Lanka', '07:45:00', '14:00:00', '+94112694781'),
(7, 'St. Bridget\'s Convent', 'Colombo 07', '21 Malalasekera Mawatha, Colombo 07, Sri Lanka', '07:30:00', '14:00:00', '+94112541500'),
(8, 'St. Joseph\'s College', 'Colombo 10', 'T.B. Jayah Mawatha, Colombo 10, Sri Lanka', '07:45:00', '14:15:00', '+94112322411'),
(9, 'Ladies College', 'Colombo 03', '66 Sir Ernest De Silva Mawatha, Colombo 03, Sri Lanka', '07:30:00', '14:30:00', '+94112575469'),
(10, 'Mahanama College', 'Colombo 03', 'Dharmarama Road, Colombo 03, Sri Lanka', '07:15:00', '13:45:00', '+94112435212'),
(11, 'Royal College', 'Colombo 07', 'Rajakeeya Mawatha, Colombo 07, Sri Lanka', '07:30:00', '14:00:00', '+94112691029'),
(12, 'Visakha Vidyalaya', 'Colombo 05', '133 Vajira Road, Colombo 05, Sri Lanka', '07:15:00', '13:45:00', '+94112588334'),
(13, 'Ananda College', 'Colombo 10', 'Maradana Road, Colombo 10, Sri Lanka', '07:30:00', '14:15:00', '+94112695162'),
(14, 'Colombo International School', 'Colombo 03', '57 Horton Place, Colombo 03, Sri Lanka', '08:00:00', '15:00:00', '+94112576012'),
(15, 'Devi Balika Vidyalaya', 'Colombo 08', 'Devi Balika Mawatha, Colombo 08, Sri Lanka', '07:20:00', '13:50:00', '+94112695256'),
(16, 'D.S. Senanayake College', 'Colombo 07', 'Nawala Road, Colombo 07, Sri Lanka', '07:45:00', '14:00:00', '+94112694781'),
(17, 'St. Bridget\'s Convent', 'Colombo 07', '21 Malalasekera Mawatha, Colombo 07, Sri Lanka', '07:30:00', '14:00:00', '+94112541500'),
(18, 'St. Joseph\'s College', 'Colombo 10', 'T.B. Jayah Mawatha, Colombo 10, Sri Lanka', '07:45:00', '14:15:00', '+94112322411'),
(19, 'Ladies College', 'Colombo 03', '66 Sir Ernest De Silva Mawatha, Colombo 03, Sri Lanka', '07:30:00', '14:30:00', '+94112575469'),
(20, 'Mahanama College', 'Colombo 03', 'Dharmarama Road, Colombo 03, Sri Lanka', '07:15:00', '13:45:00', '+94112435212');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
