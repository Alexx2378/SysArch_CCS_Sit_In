-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 24, 2026 at 05:03 AM
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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
