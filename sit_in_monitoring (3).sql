-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2026 at 04:15 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sit_in_monitoring`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT '',
  `message` text NOT NULL,
  `created_by` varchar(50) DEFAULT 'admin',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `created_by`, `created_at`) VALUES
(1, '', 'Hi Test', 'admin', '2026-04-16 09:53:38'),
(2, 'Hi (2)', 'Test', 'admin', '2026-04-16 09:53:55'),
(3, '', 'Ha', 'admin', '2026-04-16 10:04:04');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `sit_in_id` int(11) DEFAULT NULL,
  `id_number` varchar(20) NOT NULL,
  `rating` tinyint(4) DEFAULT 5,
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `sit_in_id`, `id_number`, `rating`, `message`, `created_at`) VALUES
(1, 1, '11-1111-1111', 5, 'Thanks', '2026-04-16 10:05:14');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id_number` varchar(20) NOT NULL,
  `type` varchar(20) DEFAULT 'info',
  `title` varchar(150) DEFAULT '',
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id_number`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, '11-1111-1111', 'success', 'Reservation Approved ✅', 'Your reservation for Lab 524 PC 2 on 2026-04-16 has been approved.', 1, '2026-04-16 09:53:16'),
(2, '20-1111-111', 'announcement', 'New Announcement', 'Hi Test', 0, '2026-04-16 09:53:38'),
(3, '20-2222-222', 'announcement', 'New Announcement', 'Hi Test', 0, '2026-04-16 09:53:38'),
(4, '20-3333-333', 'announcement', 'New Announcement', 'Hi Test', 0, '2026-04-16 09:53:38'),
(5, '11-1111-1111', 'announcement', 'New Announcement', 'Hi Test', 1, '2026-04-16 09:53:38'),
(6, '20-1111-111', 'announcement', 'New Announcement', 'Hi (2): Test', 0, '2026-04-16 09:53:55'),
(7, '20-2222-222', 'announcement', 'New Announcement', 'Hi (2): Test', 0, '2026-04-16 09:53:55'),
(8, '20-3333-333', 'announcement', 'New Announcement', 'Hi (2): Test', 0, '2026-04-16 09:53:55'),
(9, '11-1111-1111', 'announcement', 'New Announcement', 'Hi (2): Test', 1, '2026-04-16 09:53:55'),
(10, '20-1111-111', 'announcement', 'New Announcement', 'Ha', 0, '2026-04-16 10:04:04'),
(11, '20-2222-222', 'announcement', 'New Announcement', 'Ha', 0, '2026-04-16 10:04:04'),
(12, '20-3333-333', 'announcement', 'New Announcement', 'Ha', 0, '2026-04-16 10:04:04'),
(13, '11-1111-1111', 'announcement', 'New Announcement', 'Ha', 1, '2026-04-16 10:04:04'),
(14, '20-1111-111', 'success', 'Points Awarded 🌟', '+1 points awarded — Test', 0, '2026-04-16 10:08:50');

-- --------------------------------------------------------

--
-- Table structure for table `points_log`
--

CREATE TABLE `points_log` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `points` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `points_log`
--

INSERT INTO `points_log` (`id`, `id_number`, `points`, `reason`, `created_at`) VALUES
(1, '20-1111-111', 1, 'Test', '2026-04-16 10:08:50');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `lab` varchar(50) NOT NULL,
  `pc_number` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled','Done') DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `id_number`, `purpose`, `lab`, `pc_number`, `date`, `time_in`, `status`, `created_at`) VALUES
(1, '11-1111-1111', 'Thesis', '524', 2, '2026-04-16', '09:28:00', 'Approved', '2026-04-16 09:28:33');

-- --------------------------------------------------------

--
-- Table structure for table `sit_ins`
--

CREATE TABLE `sit_ins` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `lab` varchar(20) NOT NULL,
  `pc_number` int(11) DEFAULT NULL,
  `session_at_entry` int(11) DEFAULT 30,
  `status` enum('Active','Done') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp(),
  `timed_out_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sit_ins`
--

INSERT INTO `sit_ins` (`id`, `id_number`, `purpose`, `lab`, `pc_number`, `session_at_entry`, `status`, `created_at`, `timed_out_at`) VALUES
(1, '11-1111-1111', 'Research Paper', '524', NULL, 30, 'Done', '2026-04-16 10:03:30', '2026-04-16 10:04:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `course` varchar(20) NOT NULL,
  `year_level` int(11) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'student',
  `points` int(11) DEFAULT 0,
  `remaining_sessions` int(11) DEFAULT 30,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `id_number`, `email`, `first_name`, `last_name`, `middle_name`, `address`, `course`, `year_level`, `password`, `role`, `points`, `remaining_sessions`, `profile_photo`, `created_at`, `updated_at`) VALUES
(1, '20-1234-567', 'admin@uc.edu.ph', 'Admin', 'User', 'A', 'UC Cebu', 'CCS', 4, 'admin123', 'admin', 0, 30, NULL, '2026-04-16 00:45:51', '2026-04-16 00:58:53'),
(2, '20-1111-111', 'student1@uc.edu.ph', 'John', 'Doe', 'D', 'Cebu City', 'BSIT', 3, 'student123', 'student', 1, 30, NULL, '2026-04-16 00:45:51', '2026-04-16 02:08:50'),
(3, '20-2222-222', 'student2@uc.edu.ph', 'Jane', 'Smith', 'S', 'Lapu-Lapu City', 'BSCS', 2, 'student123', 'student', 0, 30, NULL, '2026-04-16 00:45:51', '2026-04-16 00:58:53'),
(4, '20-3333-333', 'student3@uc.edu.ph', 'Mark', 'Johnson', 'J', 'Mandaue City', 'BSIS', 1, 'student123', 'student', 0, 30, NULL, '2026-04-16 00:45:51', '2026-04-16 00:58:53'),
(5, '11-1111-1111', 'keancaballero143@gmail.com', 'Kean', 'Kun', 'M', 'cebu city', 'BSCS', 2, '$2y$10$p0m5JNCly.YT62MnF34yHuryvVzGbrmN4ZtM1JZGJthuXGsvYcHbW', 'student', 0, 29, NULL, '2026-04-16 01:21:45', '2026-04-16 02:07:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_number` (`id_number`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id_number`);

--
-- Indexes for table `points_log`
--
ALTER TABLE `points_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_number` (`id_number`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_number` (`id_number`),
  ADD KEY `lab` (`lab`,`date`,`status`);

--
-- Indexes for table `sit_ins`
--
ALTER TABLE `sit_ins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_number` (`id_number`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `points_log`
--
ALTER TABLE `points_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sit_ins`
--
ALTER TABLE `sit_ins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;

--
-- Constraints for table `points_log`
--
ALTER TABLE `points_log`
  ADD CONSTRAINT `fk_points_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_res_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;

--
-- Constraints for table `sit_ins`
--
ALTER TABLE `sit_ins`
  ADD CONSTRAINT `fk_sitin_user` FOREIGN KEY (`id_number`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
