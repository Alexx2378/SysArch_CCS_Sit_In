-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 25, 2026 at 04:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ccs_sit_in`
--

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `idNumber` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `idNumber`, `title`, `message`, `created_at`) VALUES
(1, '23788532', 'Welcome Back', 'You logged in successfully.', '2026-03-24 02:43:26'),
(2, '23788532', 'Reservation Submitted', 'Your reservation for 2026-03-25 at 03:36 is now pending review.', '2026-03-24 03:37:03'),
(3, '23788532', 'Profile Update Failed', 'ID Number or Email is already used by another account.', '2026-03-24 03:54:18'),
(4, '23788532', 'Welcome Back', 'You logged in successfully.', '2026-03-24 04:02:25'),
(5, '23788532', 'Welcome Back', 'You logged in successfully.', '2026-03-24 04:14:17'),
(6, '23788532', 'Profile Update Failed', 'Database error: Duplicate entry &#039;formenteralex@gmail.com&#039; for key &#039;email&#039;', '2026-03-24 04:25:10'),
(7, '23788532', 'Profile Update Failed', 'Unable to save profile right now. Please try again.', '2026-03-24 04:29:21'),
(8, '23788532', 'Profile Updated', 'Your profile information was updated successfully.', '2026-03-24 04:33:06'),
(9, '23788532', 'Profile Image Notice', 'ID Number and Email remained unchanged because they already exist.', '2026-03-24 04:33:06'),
(10, '23788532', 'Profile Updated', 'Your profile information was updated successfully.', '2026-03-24 04:33:32'),
(11, '23788532', 'Profile Image Notice', 'ID Number and Email remained unchanged because they already exist.', '2026-03-24 04:33:32'),
(12, '23788532', 'Profile Updated', 'Your profile information was updated successfully.', '2026-03-24 04:33:58'),
(13, '23788532', 'Profile Image Notice', 'ID Number and Email remained unchanged because they already exist.', '2026-03-24 04:33:58'),
(14, '23788532', 'Welcome Back', 'You logged in successfully.', '2026-03-24 05:05:30'),
(15, '23788532', 'Profile Updated', 'Your profile information was updated successfully.', '2026-03-24 05:19:56'),
(16, '23788532', 'Profile Image Notice', 'ID Number and Email remained unchanged because they already exist.', '2026-03-24 05:19:56'),
(17, '23788532', 'Profile Updated', 'Your profile information was updated successfully.', '2026-03-24 05:20:02'),
(18, '23788532', 'Profile Image Notice', 'ID Number and Email remained unchanged because they already exist.', '2026-03-24 05:20:02'),
(19, '23788532', 'Profile Updated', 'Your profile information was updated successfully.', '2026-03-24 08:34:25'),
(20, '23788532', 'Profile Image Notice', 'ID Number and Email remained unchanged because they already exist.', '2026-03-24 08:34:25'),
(21, '23788532', 'Welcome Back', 'You logged in successfully.', '2026-03-24 11:38:02');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(10) UNSIGNED NOT NULL,
  `idNumber` varchar(50) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `lab_room` varchar(50) NOT NULL,
  `reservation_date` date NOT NULL,
  `time_in` time NOT NULL,
  `remaining_sessions` int(11) DEFAULT 30,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `idNumber`, `student_name`, `purpose`, `lab_room`, `reservation_date`, `time_in`, `remaining_sessions`, `status`, `created_at`) VALUES
(1, '23788532', 'Alexandra Santillan Formentera', 'study java programming', '542', '2026-03-25', '03:36:00', 30, 'Pending', '2026-03-24 03:37:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `idnumber` varchar(50) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) NOT NULL,
  `course` varchar(100) NOT NULL,
  `courseLevel` varchar(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `profile_image_base64` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `idnumber`, `firstname`, `lastname`, `middlename`, `course`, `courseLevel`, `email`, `address`, `password`, `profile_image`, `profile_image_base64`) VALUES
(1, '23788532', 'Alexandra', 'Formentera', 'Santillan', 'Information Technology', '3', 'formenteralex@gmail.com', 'C. rodriguez, Capitol, Cebu City', '$2y$10$P.6ja1rbu9wYHADnAbpG6eT2pLWygSknlYI2Ojb0d/2HOFqbiEkk6', 'uploads/profile/23788532_1774297986.jpg', NULL),
(2, '23788532', 'Alexandra', 'Formentera', 'Santillan', 'Information Technology', '3', 'floppytoti@gmail.com', 'C. rodriguez, Capitol, Cebu City', '$2y$10$P.6ja1rbu9wYHADnAbpG6eT2pLWygSknlYI2Ojb0d/2HOFqbiEkk6', 'uploads/profile/23788532_1774297986.jpg', NULL),
(4, '2356789', 'amanda', 'maxwell', 'mae', 'Information Technology', '2', 'amandamaxwell@gmail.com', 'capitol, cebu city', '$2y$10$Rlxbi5l/6MMq58oPK2i0QuU1RCkQzSBSm1GTaotlRSiB71ke5wbi6', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_history`
--

CREATE TABLE `user_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `idNumber` varchar(50) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `activity_date` datetime DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Completed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_history`
--

INSERT INTO `user_history` (`id`, `idNumber`, `activity`, `details`, `activity_date`, `status`) VALUES
(1, '23788532', 'Login', 'User logged in successfully', '2026-03-24 02:43:26', 'Completed'),
(2, '23788532', 'Reservation Submitted', 'Purpose: study java programming, Lab: 542, Date: 2026-03-25 03:36', '2026-03-24 03:37:03', 'Pending'),
(3, '23788532', 'Login', 'User logged in successfully', '2026-03-24 04:02:25', 'Completed'),
(4, '23788532', 'Login', 'User logged in successfully', '2026-03-24 04:14:17', 'Completed'),
(5, '23788532', 'Login', 'User logged in successfully', '2026-03-24 05:05:30', 'Completed'),
(6, '23788532', 'Login', 'User logged in successfully', '2026-03-24 11:38:02', 'Completed');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_history`
--
ALTER TABLE `user_history`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_history`
--
ALTER TABLE `user_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
