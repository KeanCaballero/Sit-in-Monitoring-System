-- Fixed Database for CCS Sit-in Monitoring System
-- Test Accounts:
-- ADMIN: ID: 20-1234-567, Password: admin123
-- STUDENT: ID: 20-1111-111, Password: student123

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ========================================
-- DROP OLD TABLES (to start fresh)
-- ========================================
DROP TABLE IF EXISTS `sit_ins`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `reservations`;
DROP TABLE IF EXISTS `points_log`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `users`;

-- ========================================
-- CREATE USERS TABLE (all users here, including admin)
-- ========================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_number` varchar(20) NOT NULL UNIQUE KEY,
  `email` varchar(100) NOT NULL UNIQUE KEY,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(100),
  `address` text,
  `course` varchar(20) NOT NULL,
  `year_level` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'student',
  `points` int(11) DEFAULT 0,
  `remaining_sessions` int(11) DEFAULT 30,
  `profile_photo` varchar(255),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- INSERT TEST DATA
-- ========================================
-- Passwords are hashed with password_hash()
-- Admin: 20-1234-567 / admin123
-- Student: 20-1111-111 / student123

INSERT INTO `users` (`id`, `id_number`, `email`, `first_name`, `last_name`, `middle_name`, `address`, `course`, `year_level`, `password`, `role`, `points`, `remaining_sessions`) VALUES
(1, '20-1234-567', 'admin@uc.edu.ph', 'Admin', 'User', 'A', 'UC Cebu', 'CCS', 4, '$2y$10$kSW3GhGDr6hRqbVH.vu/3O7.Lj4jWc7JvDz8PZc8H7QlAx9.P6fwm', 'admin', 0, 30),
(2, '20-1111-111', 'student1@uc.edu.ph', 'John', 'Doe', 'D', 'Cebu City', 'BSIT', 3, '$2y$10$P9Fvyt4rLZq6EJQ5x6Ej.uJZ8u6R4p4Kc5Z7Y9X2V.3W1S0Q8N6', 'student', 0, 30),
(3, '20-2222-222', 'student2@uc.edu.ph', 'Jane', 'Smith', 'S', 'Lapu-Lapu City', 'BSCS', 2, '$2y$10$P9Fvyt4rLZq6EJQ5x6Ej.uJZ8u6R4p4Kc5Z7Y9X2V.3W1S0Q8N6', 'student', 0, 30),
(4, '20-3333-333', 'student3@uc.edu.ph', 'Mark', 'Johnson', 'J', 'Mandaue City', 'BSIS', 1, '$2y$10$P9Fvyt4rLZq6EJQ5x6Ej.uJZ8u6R4p4Kc5Z7Y9X2V.3W1S0Q8N6', 'student', 0, 30);

-- ========================================
-- CREATE SIT_INS TABLE
-- ========================================
CREATE TABLE `sit_ins` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_number` varchar(20) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `lab` varchar(20) NOT NULL,
  `pc_number` int(11),
  `session_at_entry` int(11) DEFAULT 30,
  `status` enum('Active','Done') DEFAULT 'Active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `timed_out_at` datetime,
  KEY `idx_id_number` (`id_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_sitin_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- CREATE RESERVATIONS TABLE
-- ========================================
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_number` varchar(20) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `lab` varchar(50) NOT NULL,
  `pc_number` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled','Done') DEFAULT 'Pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `id_number` (`id_number`),
  KEY `lab` (`lab`,`date`,`status`),
  CONSTRAINT `fk_res_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- CREATE FEEDBACK TABLE
-- ========================================
CREATE TABLE `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `sit_in_id` int(11),
  `id_number` varchar(20) NOT NULL,
  `rating` tinyint(4) DEFAULT 5,
  `message` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `id_number` (`id_number`),
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- CREATE ANNOUNCEMENTS TABLE
-- ========================================
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) DEFAULT '',
  `message` text NOT NULL,
  `created_by` varchar(50) DEFAULT 'admin',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- CREATE NOTIFICATIONS TABLE
-- ========================================
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id_number` varchar(20) NOT NULL,
  `type` varchar(20) DEFAULT 'info',
  `title` varchar(150) DEFAULT '',
  `message` text,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user` (`user_id_number`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- CREATE POINTS_LOG TABLE
-- ========================================
CREATE TABLE `points_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_number` varchar(20) NOT NULL,
  `points` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `id_number` (`id_number`),
  CONSTRAINT `fk_points_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
