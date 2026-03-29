-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 23, 2026 at 11:28 AM
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
-- Database: `student_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `attempt_time`, `success`) VALUES
(1, 'admin', '::1', '2026-03-22 13:00:25', 0),
(2, 'admin', '::1', '2026-03-22 13:02:29', 0),
(3, 'admin', '::1', '2026-03-22 13:05:32', 1),
(4, 'admin', '::1', '2026-03-22 13:13:01', 1),
(5, 'admin', '::1', '2026-03-22 13:22:59', 1),
(6, 'admin', '::1', '2026-03-22 13:48:39', 1),
(7, 'ej.romero', '::1', '2026-03-22 13:50:11', 1),
(8, 'admin', '::1', '2026-03-22 13:54:30', 1),
(9, 'admin', '::1', '2026-03-22 14:18:19', 1),
(10, 'admin', '::1', '2026-03-22 14:20:06', 1),
(11, 'admin', '::1', '2026-03-23 07:53:50', 1),
(12, 'admin', '::1', '2026-03-23 08:03:57', 1),
(13, 'ej.romero', '::1', '2026-03-23 08:04:45', 1),
(19, 'ej.romero', '::1', '2026-03-23 08:28:15', 0),
(20, 'ej.romero', '::1', '2026-03-23 08:32:15', 0),
(21, 'ej.romero', '::1', '2026-03-23 08:43:41', 1),
(22, 'ej.romero', '::1', '2026-03-23 08:49:22', 1),
(23, 'ej.romero', '::1', '2026-03-23 08:53:42', 1),
(24, 'ejr.romero', '::1', '2026-03-23 08:57:53', 0),
(25, 'ej.romero', '::1', '2026-03-23 08:58:11', 1),
(26, 'ej.romero', '::1', '2026-03-23 09:04:19', 1),
(27, 'ej.romero', '::1', '2026-03-23 09:10:03', 1),
(28, 'ej.romero', '::1', '2026-03-23 09:11:42', 1),
(29, 'ejr.romero', '::1', '2026-03-23 09:15:32', 0),
(30, 'ej.romero', '::1', '2026-03-23 09:15:50', 1),
(31, 'ej.romero', '::1', '2026-03-23 09:18:32', 1),
(32, 'ej.romero', '::1', '2026-03-23 09:19:11', 1),
(33, 'ej.romero', '::1', '2026-03-23 09:20:02', 1),
(34, 'admin', '::1', '2026-03-23 10:26:09', 1);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `course` enum('BSIT','BSBA','BSCRIM','BSCE','BSSW','BSFi','BSA') DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `status` enum('active','inactive','graduated','pending','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `course`, `year_level`, `gpa`, `status`, `created_at`, `updated_at`) VALUES
(2, 'STU20260001', 3, 'ej', 'romero', 'ejromero294@gmail.com', NULL, 'BSIT', 1, 1.00, 'active', '2026-03-22 13:49:21', '2026-03-22 13:49:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','student') DEFAULT 'student',
  `full_name` varchar(100) NOT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `last_login_attempt` timestamp NULL DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `full_name`, `login_attempts`, `last_login_attempt`, `is_locked`, `last_login`, `created_at`) VALUES
(2, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@studentsystem.com', 'admin', 'System Administrator', 0, NULL, 0, '2026-03-23 10:26:09', '2026-03-22 13:01:43'),
(3, 'ej.romero', '$2y$10$PPWvutcohFvM8dh.tIk06uzI25w2wtBax1dYdaxGd5Mac2zI29Utq', 'ejromero294@gmail.com', 'student', 'ej romero', 0, NULL, 0, '2026-03-23 09:20:02', '2026-03-22 13:49:21');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `last_activity`, `created_at`) VALUES
(1, 2, '860b7d7fbc3ec3254306bbe788ab7bd6df2f15ee649ae9bf28f2b770b1b91c2a', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-22 13:05:32', '2026-03-22 13:05:32'),
(6, 2, 'aa0f9aa2e7f53da6909d6762ca43f0ac6c999eeb1c1c874b34cb04f3094138fa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-22 14:17:56', '2026-03-22 13:54:30'),
(7, 2, '4c4b0e2c734f40b6d04187354cc3a69d082027b2f31d287d771ba209853f8408', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-22 14:19:13', '2026-03-22 14:18:19'),
(8, 2, 'd2da0892c9603f0388d4c4996aa0519e2f522729b681d5511043ff72d1fe6943', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-22 14:49:39', '2026-03-22 14:20:06'),
(10, 2, '4bbed55f63bcfaaf9d566e6432867555435ccc3886b176b04e9581b8691342ce', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 08:04:18', '2026-03-23 08:03:57'),
(12, 3, 'ebf08921fd7eea9a5ed8e75d6e2b51bbd3e260deea2853b047ff16946fe1fcda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 08:44:11', '2026-03-23 08:43:41'),
(13, 3, 'bd52df24b957b3c15a1f1a0cb14790b99ed7543e77afac5a6bb14aa66ce8e099', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 08:52:37', '2026-03-23 08:49:22'),
(14, 3, '373c421154bb783ead224702575c905d13785d00a10825b5c15a9bc7be26f99d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 08:57:35', '2026-03-23 08:53:42'),
(15, 3, '04d32f50af03ec25783abfd8ad98436d0ee310aa509464bde986974fc5bca253', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/146.0.0.0', '2026-03-23 08:58:11', '2026-03-23 08:58:11'),
(16, 3, '150eee93575cbb051063bc84e63b129144e2d0250ba3818d18abbf5de7a3d2ac', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 09:09:49', '2026-03-23 09:04:19'),
(17, 3, '2d1cdba1f26b1f4492a8758306d76303a5a6c420578cea7f2fe0c8acbb11d78b', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/146.0.0.0', '2026-03-23 09:11:07', '2026-03-23 09:10:03'),
(18, 3, 'd95dac7feef16206c155e565907a907f9ec0640199e7c43835d938f9791c1060', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/146.0.0.0', '2026-03-23 09:15:15', '2026-03-23 09:11:42'),
(19, 3, 'e46426a7d5cd986b3daaec70fe1abad12a3f2e77d8033acde4dc65250920a003', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 09:18:16', '2026-03-23 09:15:50'),
(20, 3, 'e729bd18fd346f21ebf44a92e452e4cd809545ea09963a13a25bd572b2af206b', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1 Edg/146.0.0.0', '2026-03-23 09:18:32', '2026-03-23 09:18:32'),
(21, 3, '82f7bdaa8806b41b63e7461cd29770a8c50e557bcd998a2cdb92c54acbd8f3e1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 09:19:11', '2026-03-23 09:19:11'),
(22, 3, '81c762c45bbe2f33b4895f0b20bf2d67ccc7e72b4b4dbbf6a1276c851e7fc6e0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 09:20:03', '2026-03-23 09:20:02'),
(23, 2, '612deb8e40a8a123cc2063135e00b82f6bc5b4168fef152003691260969b0054', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-23 10:27:21', '2026-03-23 10:26:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
