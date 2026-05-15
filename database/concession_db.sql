-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2026 at 03:55 AM
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
-- Database: `concession_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `receiving`
--

CREATE TABLE `receiving` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `store_code` varchar(50) NOT NULL,
  `os_no` varchar(100) NOT NULL,
  `from_store` varchar(50) NOT NULL,
  `to_store` varchar(30) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receiving`
--

INSERT INTO `receiving` (`id`, `username`, `store_code`, `os_no`, `from_store`, `to_store`, `quantity`, `created_at`) VALUES
(2, 'admin', 'ADMIN-001', '123', '123', '', 13, '2026-04-27 07:35:27');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `store_code` varchar(20) NOT NULL,
  `return_item` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `return_amount` decimal(10,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `is_exchange` tinyint(1) DEFAULT 0,
  `exchange_name` varchar(100) DEFAULT NULL,
  `exchange_item` varchar(100) DEFAULT NULL,
  `exchange_quantity` int(11) DEFAULT 0,
  `exchange_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `returns`
--

INSERT INTO `returns` (`id`, `username`, `store_code`, `return_item`, `quantity`, `return_amount`, `reason`, `is_exchange`, `exchange_name`, `exchange_item`, `exchange_quantity`, `exchange_amount`, `created_at`) VALUES
(1, 'test', 'test', 'qweqeq', 1, 123.00, '123', 0, '', '', 0, 0.00, '2026-04-27 06:18:24'),
(3, 'test', 'test', NULL, 1, NULL, NULL, 1, 'qwe', 'qwe', 0, 123.00, '2026-04-27 06:23:04'),
(4, 'test', 'test', NULL, 1, NULL, NULL, 1, 'qweqe', '123123', 0, 123.00, '2026-04-27 06:25:14'),
(5, 'test', 'test', '123', 1, 123.00, 'qeqweq', 0, NULL, NULL, 0, NULL, '2026-04-27 10:36:20'),
(6, 'test', 'test', NULL, 1, NULL, NULL, 1, 'wqqeqe', '123', 0, 123.00, '2026-04-27 10:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `store_code` varchar(50) NOT NULL,
  `item_no` varchar(150) NOT NULL,
  `amount_sold` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `line_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `username`, `store_code`, `item_no`, `amount_sold`, `quantity`, `line_total`, `created_at`) VALUES
(2, 'janedoe', 'STR-999', '123', 123.00, 1, 123.00, '2026-04-27 03:45:36'),
(3, 'test', 'test', '123', 123.00, 123, 15129.00, '2026-04-27 03:53:22'),
(4, 'test', 'test', '123', 123.00, 213, 26199.00, '2026-04-27 04:37:06'),
(5, 'test', 'test', '123', 123.00, 123, 15129.00, '2026-04-27 04:46:11'),
(6, 'test', 'test', '123', 123.00, 123, 15129.00, '2026-04-27 04:48:47'),
(7, 'test', 'test', '123', 123.00, 123, 15129.00, '2026-04-27 04:52:10'),
(8, 'test', 'test', '123', 123.00, 123, 15129.00, '2026-04-27 04:52:16'),
(9, 'test', 'test', '123', 123.00, 123, 15129.00, '2026-04-27 04:52:22'),
(10, 'test', 'test', '123', 123.00, 123, 15129.00, '2026-04-27 04:52:30'),
(11, 'test', 'test', '1', 1.00, 1, 1.00, '2026-04-27 05:01:18'),
(12, 'test', 'test', '1', 1.00, 1, 1.00, '2026-04-27 05:01:18'),
(13, 'test', 'test', '1', 1.00, 1, 1.00, '2026-04-27 05:01:28'),
(14, 'test', 'test', '1', 1.00, 1, 1.00, '2026-04-27 05:02:24'),
(15, 'test', 'test', '1', 1.00, 1, 1.00, '2026-04-27 05:02:29'),
(16, 'test', 'test', 'qweqeqe', 123.00, 2, 246.00, '2026-04-27 05:04:02'),
(17, 'admin', 'ADMIN-001', '12323123', 123.00, 123, 15129.00, '2026-04-27 07:33:01'),
(19, 'test', '1BD', 'test', 123.00, 123, 15129.00, '2026-04-29 01:23:39'),
(20, 'test', '14Y', 'eqweqweqwe', 123123.00, 123123, 99999999.99, '2026-04-29 01:27:51'),
(21, 'test', '14Y', '123', 123.00, 123, 15129.00, '2026-04-29 01:35:00');

-- --------------------------------------------------------

--
-- Table structure for table `storecode`
--

CREATE TABLE `storecode` (
  `scode` varchar(30) NOT NULL,
  `sname` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `storecode`
--

INSERT INTO `storecode` (`scode`, `sname`) VALUES
('14Y', 'CASH & CARRY'),
('1AY', 'CDM ANTIPOLO'),
('17C', 'FISHER MALL'),
('16A', 'GF CANLUBANG'),
('198', 'KCC GEN. SANTOS'),
('18T', 'KCC MALL ZAM'),
('138', 'KCC MARVEL'),
('1AB', 'LANDMARK ALABANG'),
('140', 'LANDMARK MAKATI'),
('1BD', 'LANDMARK MANILA BAY'),
('11X', 'LANDMARK TRINOMA'),
('147', 'RDS BACOLOD'),
('16W', 'RDS CENTRIO'),
('146', 'RDS ERMITA'),
('10D', 'RDS FESTIVAL'),
('19X', 'RDS ILIGAN'),
('152', 'RDS ILOILO'),
('17P', 'RDS MALOLOS'),
('1AN', 'RDS ORMOC'),
('1BC', 'SM ARANETA'),
('1BE', 'SM BACOLOD'),
('1BB', 'SM BACOOR'),
('183', 'SM BAGUIO'),
('168', 'SM CEBU'),
('11K', 'SM CLARK'),
('181', 'SM DASMA'),
('178', 'SM DAVAO'),
('172', 'SM FAIRVIEW'),
('11O', 'SM LIPA'),
('1BA', 'SM MAKATI'),
('11A', 'SM MALL OF ASIA'),
('174', 'SM MANDURRIAO'),
('175', 'SM MANILA'),
('164', 'SM MEGAMALL'),
('13J', 'SM NAGA'),
('166', 'SM NORTH EDSA'),
('14I', 'SM OLONGAPO'),
('176', 'SM PAMPANGA'),
('10P', 'SM SAN LAZARO'),
('1BF', 'SM SEASIDE CEBU'),
('11B', 'SM STA.ROSA'),
('12O', 'SM TAYTAY'),
('161', 'STA. LUCIA'),
('16S', 'TIONGSAN BAGUIO'),
('14W', 'GAI FIESTA MALL'),
('1AQ', 'GAI GRAND BUHANGIN'),
('17D', 'GAI GRAND MACTAN'),
('17E', 'GAI KIDAPAWAN'),
('14X', 'GAI  MALL OF DAVAO'),
('134', 'GAI SOUTH DAVAO'),
('15R', 'LCC IRIGA'),
('139', 'LCC LEGAZPI'),
('14V', 'LCC TABACO'),
('1AX', 'MET GAI AYALA CEBU'),
('131', 'MET GAI COLON'),
('12V', 'MET GAI MARKET MARKET'),
('13B', 'MET LEGAZPI');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `store_code` varchar(50) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `store_code`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$PSwdt.wuSEvzPIBlvCC2Qeqf/XgOzkpWyJg9O129yS4jOf2UIv.Ni', 'ADMIN-001', 'admin', '2026-04-27 03:43:36', '2026-04-29 01:54:04'),
(3, 'test', '$2y$10$get.pQxjWXtRiUOQ4SpZTe7GeAcyrCyGobvsBVM0bjuvvZZgxhdFC', 'test', 'user', '2026-04-27 03:52:25', '2026-04-29 01:03:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `receiving`
--
ALTER TABLE `receiving`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_store_code` (`store_code`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`),
  ADD KEY `store_code` (`store_code`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_store_code` (`store_code`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `receiving`
--
ALTER TABLE `receiving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
