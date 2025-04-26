-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2025 at 05:52 PM
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
-- Database: `k&p`
--
CREATE DATABASE IF NOT EXISTS `k&p` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `k&p`;

-- --------------------------------------------------------

--
-- Table structure for table `address`
--

DROP TABLE IF EXISTS `address`;
CREATE TABLE `address` (
  `address_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `address_name` varchar(100) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `post_code` varchar(10) NOT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Malaysia',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `address`
--

INSERT INTO `address` (`address_id`, `user_id`, `address_name`, `recipient_name`, `phone`, `address_line1`, `address_line2`, `city`, `state`, `post_code`, `country`, `is_default`, `created_at`, `updated_at`) VALUES
('AD001', 'MB542', 'Home', 'Aiman Hakim', '60192837465', '12 Jalan Damai', 'Taman Sentosa', 'Kuala Lumpur', 'Wilayah Persekutuan', '50450', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:03:23'),
('AD002', 'MB247', 'Office', 'Nurul Aisyah', '60162384921', '18A Jalan Ampang', 'Menara Prisma', 'Kuala Lumpur', 'Wilayah Persekutuan', '50450', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-26 14:52:59'),
('AD003', 'MB542', 'Home', 'Ahmad Faizal', '60173458722', '27 Lorong Mawar', 'Taman Sri Andalas', 'Klang', 'Selangor', '41200', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:03:28'),
('AD004', 'MB542', 'Office', 'Siti Mariam', '60194587230', '9 Jalan Tanjung', 'Desa Sri Hartamas', 'Petaling Jaya', 'Selangor', '47800', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:04:37'),
('AD005', 'MB542', 'Home', 'Mohd Zulhilmi', '60125348971', '56 Jalan Seri', 'Taman Mutiara', 'Johor Bahru', 'Johor', '81100', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:03:39'),
('AD006', 'MB542', 'Home', 'Farah Nadia', '60184739215', '33 Jalan Lagenda', 'Taman Bukit Indah', 'Ipoh', 'Perak', '31400', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:04:49'),
('AD007', 'MB542', 'Home', 'Syafiq Ikhwan', '60162198374', '21 Jalan Semarak', 'Kawasan Perindustrian', 'Shah Alam', 'Selangor', '40100', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:03:55'),
('AD008', 'MB542', 'Office', 'Hafiz Rahman', '60193745268', '77 Jalan Putra', 'Residensi Putrajaya', 'Putrajaya', 'Wilayah Persekutuan', '62100', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:03:46'),
('AD009', 'MB542', 'Home', 'Nadia Izzati', '60182347651', '14 Jalan Bukit', 'Taman Kenari', 'Alor Setar', 'Kedah', '05050', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:03:42'),
('AD010', 'MB542', 'Office', 'Zulkifli Osman', '60173452689', '66 Jalan Pantai', 'Pantai Batu Ferringhi', 'George Town', 'Penang', '11100', 'Malaysia', 0, '2025-04-24 02:48:24', '2025-04-25 14:04:33'),
('AD011', 'MB247', 'Home', 'js', '60182250100', 'A-02-13, Mizumi Metro kepong', '', 'kepong', 'Kuala Lumpur', '52100', 'Malaysia', 1, '2025-04-23 14:28:46', '2025-04-26 14:52:59');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `cart_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `quantity_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `added_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `product_id`, `quantity_id`, `quantity`, `added_time`) VALUES
('CART_20250426222059_bcbe17ef', 'MB247', 'P018', 88, 2, '2025-04-26 14:20:59');

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `category_id` varchar(50) NOT NULL,
  `category_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`) VALUES
('CAT1001', 'Short T-shirt'),
('CAT1002', 'Long T-shirt'),
('CAT1003', 'Jeans');

-- --------------------------------------------------------

--
-- Table structure for table `delivery`
--

DROP TABLE IF EXISTS `delivery`;
CREATE TABLE `delivery` (
  `delivery_id` varchar(255) NOT NULL,
  `address_id` varchar(255) NOT NULL,
  `delivery_fee` decimal(10,0) NOT NULL,
  `delivery_status` enum('Processing','Out for Delivery','Delivered','Failed') NOT NULL DEFAULT 'Processing',
  `estimated_date` date DEFAULT NULL,
  `delivered_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery`
--

INSERT INTO `delivery` (`delivery_id`, `address_id`, `delivery_fee`, `delivery_status`, `estimated_date`, `delivered_date`) VALUES
('DV001', 'AD003', 40, 'Delivered', '2025-04-13', '2025-04-16'),
('DV002', 'AD009', 40, 'Delivered', '2025-03-23', '2025-03-26'),
('DV003', 'AD004', 40, 'Delivered', '2025-04-09', '2025-04-12'),
('DV004', 'AD001', 20, 'Delivered', '2025-02-28', '2025-03-03'),
('DV005', 'AD002', 20, 'Delivered', '2025-01-15', '2025-01-18'),
('DV006', 'AD008', 40, 'Delivered', '2025-04-23', '2025-04-26'),
('DV007', 'AD006', 40, 'Delivered', '2025-03-01', '2025-03-04'),
('DV008', 'AD002', 20, 'Delivered', '2025-02-26', '2025-03-01'),
('DV009', 'AD001', 20, 'Delivered', '2025-03-24', '2025-03-27'),
('DV010', 'AD004', 40, 'Delivered', '2025-01-08', '2025-01-11'),
('DV011', 'AD002', 20, 'Delivered', '2025-03-09', '2025-03-12'),
('DV012', 'AD001', 20, 'Delivered', '2025-01-03', '2025-01-06'),
('DV013', 'AD002', 20, 'Delivered', '2025-02-19', '2025-02-22'),
('DV014', 'AD007', 40, 'Delivered', '2025-03-10', '2025-03-13'),
('DV015', 'AD006', 40, 'Delivered', '2025-01-02', '2025-01-05'),
('DV016', 'AD010', 40, 'Delivered', '2025-04-20', '2025-04-23'),
('DV017', 'AD001', 20, 'Delivered', '2025-04-05', '2025-04-08'),
('DV018', 'AD003', 40, 'Delivered', '2025-03-07', '2025-03-10'),
('DV019', 'AD006', 40, 'Delivered', '2025-04-10', '2025-04-13'),
('DV020', 'AD009', 40, 'Delivered', '2025-02-25', '2025-02-28'),
('DV021', 'AD008', 40, 'Delivered', '2025-03-03', '2025-03-06'),
('DV022', 'AD010', 40, 'Delivered', '2025-02-11', '2025-02-14'),
('DV023', 'AD003', 40, 'Delivered', '2025-03-01', '2025-03-04'),
('DV024', 'AD009', 40, 'Delivered', '2025-02-12', '2025-02-15'),
('DV025', 'AD004', 40, 'Delivered', '2025-01-30', '2025-02-02'),
('DV026', 'AD005', 40, 'Delivered', '2025-04-07', '2025-04-10'),
('DV027', 'AD010', 40, 'Delivered', '2025-01-17', '2025-01-20'),
('DV028', 'AD004', 40, 'Delivered', '2025-02-20', '2025-02-23'),
('DV029', 'AD006', 40, 'Delivered', '2025-03-25', '2025-03-28'),
('DV030', 'AD003', 40, 'Delivered', '2025-01-27', '2025-01-30'),
('DV031', 'AD005', 40, 'Delivered', '2025-04-08', '2025-04-11'),
('DV032', 'AD009', 40, 'Delivered', '2025-02-22', '2025-02-25'),
('DV033', 'AD007', 40, 'Delivered', '2025-01-04', '2025-01-07'),
('DV034', 'AD005', 40, 'Delivered', '2025-03-11', '2025-03-14'),
('DV035', 'AD008', 40, 'Delivered', '2025-04-15', '2025-04-18'),
('DV036', 'AD003', 40, 'Delivered', '2025-02-09', '2025-02-12'),
('DV037', 'AD001', 20, 'Delivered', '2025-02-13', '2025-02-16'),
('DV038', 'AD002', 20, 'Delivered', '2025-04-04', '2025-04-07'),
('DV039', 'AD001', 20, 'Delivered', '2025-01-05', '2025-01-08'),
('DV040', 'AD002', 20, 'Delivered', '2025-03-13', '2025-03-16'),
('DV041', 'AD001', 20, 'Delivered', '2025-02-04', '2025-02-07'),
('DV042', 'AD002', 20, 'Delivered', '2025-02-06', '2025-02-09'),
('DV043', 'AD001', 20, 'Delivered', '2025-03-18', '2025-03-21'),
('DV044', 'AD002', 20, 'Delivered', '2025-01-25', '2025-01-28'),
('DV045', 'AD001', 20, 'Delivered', '2025-02-16', '2025-02-19'),
('DV046', 'AD002', 20, 'Delivered', '2025-01-14', '2025-01-17'),
('DV047', 'AD001', 20, 'Delivered', '2025-01-19', '2025-01-22'),
('DV048', 'AD002', 20, 'Delivered', '2025-02-14', '2025-02-17'),
('DV049', 'AD001', 20, 'Delivered', '2025-01-11', '2025-01-14'),
('DV050', 'AD002', 20, 'Delivered', '2025-03-20', '2025-03-23'),
('DV051', 'AD011', 20, '', '2025-04-29', NULL),
('DV052', 'AD011', 20, '', '2025-04-29', NULL),
('DV053', 'AD011', 20, 'Processing', '2025-04-29', NULL),
('DV054', 'AD011', 20, 'Processing', '2025-04-29', NULL),
('DV055', 'AD011', 20, 'Processing', '2025-04-29', NULL),
('DV056', 'AD011', 20, 'Processing', '2025-04-29', NULL),
('DV057', 'AD011', 20, 'Processing', '2025-04-29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `discount`
--

DROP TABLE IF EXISTS `discount`;
CREATE TABLE `discount` (
  `Discount_id` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `discount_rate` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `order_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `delivery_id` varchar(255) NOT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `orders_status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL,
  `order_subtotal` decimal(10,2) DEFAULT NULL,
  `order_total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `delivery_id`, `order_date`, `orders_status`, `order_subtotal`, `order_total`) VALUES
('OR001', 'MB971', 'DV025', '2025-02-08 00:00:00', 'Delivered', 343.86, 322.43),
('OR002', 'MB570', 'DV004', '2025-04-03 00:00:00', 'Processing', 457.32, 455.01),
('OR003', 'MB570', 'DV021', '2025-01-03 00:00:00', 'Shipped', 133.04, 136.81),
('OR004', 'MB570', 'DV002', '2025-04-24 00:00:00', 'Cancelled', 131.89, 107.64),
('OR005', 'MB570', 'DV007', '2025-02-02 00:00:00', 'Processing', 383.45, 358.83),
('OR006', 'MB570', 'DV022', '2025-02-19 00:00:00', 'Delivered', 217.14, 226.21),
('OR007', 'MB570', 'DV028', '2025-01-09 00:00:00', 'Processing', 174.61, 163.71),
('OR008', 'MB971', 'DV050', '2025-04-16 00:00:00', 'Delivered', 195.81, 179.63),
('OR009', 'MB570', 'DV020', '2025-02-25 00:00:00', 'Pending', 471.34, 451.14),
('OR010', 'MB222', 'DV021', '2025-02-16 00:00:00', 'Shipped', 199.46, 211.21),
('OR011', 'MB570', 'DV016', '2025-03-27 00:00:00', 'Cancelled', 383.74, 380.77),
('OR012', 'MB570', 'DV038', '2025-04-20 00:00:00', 'Pending', 164.68, 174.28),
('OR013', 'MB971', 'DV024', '2025-02-25 00:00:00', 'Delivered', 241.39, 233.04),
('OR014', 'MB971', 'DV022', '2025-03-28 00:00:00', 'Cancelled', 497.09, 489.36),
('OR015', 'MB971', 'DV014', '2025-02-08 00:00:00', 'Shipped', 411.16, 406.28),
('OR016', 'MB971', 'DV049', '2025-04-25 00:00:00', 'Delivered', 310.98, 312.76),
('OR017', 'MB971', 'DV008', '2025-03-23 00:00:00', 'Delivered', 195.27, 162.27),
('OR018', 'MB971', 'DV002', '2025-03-19 00:00:00', 'Shipped', 467.05, 469.62),
('OR019', 'MB971', 'DV029', '2025-02-28 00:00:00', 'Processing', 151.54, 112.16),
('OR020', 'MB971', 'DV031', '2025-04-10 00:00:00', 'Processing', 183.96, 171.20),
('OR021', 'MB542', 'DV044', '2025-01-03 00:00:00', 'Cancelled', 245.16, 232.91),
('OR022', 'MB542', 'DV046', '2025-01-24 00:00:00', 'Shipped', 434.36, 431.36),
('OR023', 'MB570', 'DV013', '2025-04-06 00:00:00', 'Shipped', 154.00, 153.72),
('OR024', 'MB570', 'DV027', '2025-04-15 00:00:00', 'Pending', 456.36, 437.68),
('OR025', 'MB570', 'DV049', '2025-04-25 00:00:00', 'Shipped', 297.53, 298.72),
('OR026', 'MB222', 'DV030', '2025-01-23 00:00:00', 'Delivered', 496.62, 517.14),
('OR027', 'MB222', 'DV015', '2025-03-26 00:00:00', 'Shipped', 431.36, 418.22),
('OR028', 'MB222', 'DV018', '2025-04-01 00:00:00', 'Shipped', 169.99, 138.16),
('OR029', 'MB570', 'DV047', '2025-01-04 00:00:00', 'Processing', 210.06, 211.01),
('OR030', 'MB971', 'DV034', '2025-04-05 00:00:00', 'Cancelled', 340.95, 359.27),
('OR031', 'MB971', 'DV009', '2025-01-19 00:00:00', 'Delivered', 331.90, 334.86),
('OR032', 'MB971', 'DV039', '2025-04-01 00:00:00', 'Cancelled', 302.69, 317.50),
('OR033', 'MB971', 'DV040', '2025-01-01 00:00:00', 'Shipped', 125.95, 97.58),
('OR034', 'MB971', 'DV036', '2025-04-02 00:00:00', 'Shipped', 246.97, 255.60),
('OR035', 'MB971', 'DV015', '2025-03-06 00:00:00', 'Processing', 454.11, 436.99),
('OR036', 'MB971', 'DV044', '2025-02-24 00:00:00', 'Delivered', 225.16, 201.56),
('OR037', 'MB971', 'DV031', '2025-03-21 00:00:00', 'Processing', 372.66, 361.59),
('OR038', 'MB971', 'DV033', '2025-01-11 00:00:00', 'Shipped', 384.16, 368.71),
('OR039', 'MB971', 'DV021', '2025-03-29 00:00:00', 'Delivered', 127.54, 133.61),
('OR040', 'MB971', 'DV011', '2025-01-19 00:00:00', 'Cancelled', 459.86, 462.32),
('OR041', 'MB570', 'DV001', '2025-04-10 00:00:00', 'Delivered', 101.09, 99.18),
('OR042', 'MB222', 'DV050', '2025-01-19 00:00:00', 'Pending', 113.20, 104.49),
('OR043', 'MB222', 'DV044', '2025-01-21 00:00:00', 'Delivered', 274.26, 271.64),
('OR044', 'MB971', 'DV006', '2025-02-15 00:00:00', 'Delivered', 285.30, 271.05),
('OR045', 'MB570', 'DV041', '2025-01-29 00:00:00', 'Shipped', 412.52, 392.66),
('OR046', 'MB971', 'DV012', '2025-01-08 00:00:00', 'Cancelled', 268.69, 282.66),
('OR047', 'MB570', 'DV031', '2025-02-27 00:00:00', 'Processing', 246.06, 226.78),
('OR048', 'MB542', 'DV039', '2025-04-02 00:00:00', 'Cancelled', 286.21, 287.33),
('OR049', 'MB222', 'DV026', '2025-01-03 00:00:00', 'Delivered', 337.19, 349.46),
('OR050', 'MB971', 'DV018', '2025-01-20 00:00:00', 'Processing', 215.76, 199.88),
('OR051', 'MB247', 'DV051', '2025-04-26 11:02:26', 'Pending', 469.30, 517.46),
('OR052', 'MB247', 'DV052', '2025-04-26 11:12:13', 'Pending', 199.70, 231.68),
('OR053', 'MB247', 'DV053', '2025-04-26 11:16:56', 'Pending', 249.60, 284.58),
('OR054', 'MB247', 'DV054', '2025-04-26 20:50:40', 'Pending', 159.80, 189.39),
('OR055', 'MB247', 'DV055', '2025-04-26 21:59:01', '', 159.80, 189.39),
('OR056', 'MB247', 'DV056', '2025-04-26 22:02:39', '', 119.70, 146.88),
('OR057', 'MB247', 'DV057', '2025-04-26 22:10:12', '', 119.80, 146.99);

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

DROP TABLE IF EXISTS `order_details`;
CREATE TABLE `order_details` (
  `order_id` varchar(255) DEFAULT NULL,
  `product_id` varchar(255) DEFAULT NULL,
  `quantity_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_details`
--

INSERT INTO `order_details` (`order_id`, `product_id`, `quantity_id`, `quantity`, `unit_price`) VALUES
('OR001', 'P013', 61, 3, 39.90),
('OR001', 'P010', 46, 1, 59.90),
('OR002', 'P003', 11, 1, 59.90),
('OR003', 'P009', 41, 5, 79.90),
('OR003', 'P014', 66, 5, 59.90),
('OR004', 'P012', 56, 4, 759.90),
('OR004', 'P010', 47, 2, 59.90),
('OR004', 'P018', 86, 2, 59.90),
('OR005', 'P017', 81, 1, 59.90),
('OR006', 'P005', 21, 4, 79.90),
('OR007', 'P013', 62, 2, 39.90),
('OR007', 'P013', 63, 3, 39.90),
('OR007', 'P016', 76, 3, 39.90),
('OR008', 'P002', 6, 3, 49.90),
('OR009', 'P007', 31, 3, 39.90),
('OR010', 'P019', 91, 2, 79.90),
('OR010', 'P003', 12, 1, 59.90),
('OR010', 'P007', 32, 2, 39.90),
('OR011', 'P018', 86, 1, 59.90),
('OR011', 'P019', 91, 1, 79.90),
('OR012', 'P018', 90, 1, 59.90),
('OR012', 'P011', 51, 1, 49.90),
('OR012', 'P003', 13, 1, 59.90),
('OR013', 'P014', 66, 1, 59.90),
('OR013', 'P009', 41, 1, 79.90),
('OR014', 'P005', 21, 1, 79.90),
('OR014', 'P014', 67, 1, 59.90),
('OR014', 'P019', 91, 1, 79.90),
('OR015', 'P020', 96, 1, 79.90),
('OR015', 'P020', 97, 1, 79.90),
('OR016', 'P013', 61, 1, 39.90),
('OR017', 'P010', 46, 1, 59.90),
('OR017', 'P012', 56, 1, 759.90),
('OR018', 'P018', 86, 1, 59.90),
('OR019', 'P011', 51, 1, 49.90),
('OR019', 'P020', 96, 1, 79.90),
('OR020', 'P002', 6, 1, 49.90),
('OR020', 'P008', 36, 1, 49.90),
('OR021', 'P006', 26, 1, 59.90),
('OR021', 'P006', 26, 1, 59.90),
('OR021', 'P004', 16, 1, 79.90),
('OR022', 'P002', 7, 1, 49.90),
('OR023', 'P007', 32, 1, 39.90),
('OR023', 'P018', 86, 1, 59.90),
('OR023', 'P002', 7, 1, 49.90),
('OR024', 'P009', 42, 1, 79.90),
('OR025', 'P012', 56, 1, 759.90),
('OR025', 'P001', 5, 1, 49.90),
('OR026', 'P004', 17, 1, 79.90),
('OR027', 'P005', 22, 1, 79.90),
('OR027', 'P006', 27, 1, 59.90),
('OR027', 'P017', 82, 1, 59.90),
('OR028', 'P013', 62, 1, 39.90),
('OR029', 'P003', 13, 1, 59.90),
('OR029', 'P014', 66, 1, 59.90),
('OR029', 'P002', 8, 1, 49.90),
('OR030', 'P003', 14, 1, 59.90),
('OR030', 'P019', 92, 1, 79.90),
('OR031', 'P010', 47, 1, 59.90),
('OR031', 'P014', 67, 1, 59.90),
('OR031', 'P017', 82, 1, 59.90),
('OR032', 'P016', 76, 1, 39.90),
('OR033', 'P008', 37, 1, 49.90),
('OR033', 'P006', 27, 1, 59.90),
('OR033', 'P009', 42, 1, 79.90),
('OR034', 'P006', 27, 1, 59.90),
('OR034', 'P003', 12, 1, 59.90),
('OR034', 'P017', 81, 1, 59.90),
('OR035', 'P013', 63, 1, 39.90),
('OR035', 'P004', 17, 1, 79.90),
('OR036', 'P020', 97, 1, 79.90),
('OR037', 'P017', 82, 1, 59.90),
('OR037', 'P011', 52, 1, 49.90),
('OR038', 'P003', 13, 1, 59.90),
('OR038', 'P007', 31, 1, 39.90),
('OR038', 'P008', 36, 1, 49.90),
('OR039', 'P010', 48, 1, 59.90),
('OR039', 'P007', 32, 1, 39.90),
('OR039', 'P008', 37, 1, 49.90),
('OR040', 'P008', 38, 1, 49.90),
('OR040', 'P019', 92, 1, 79.90),
('OR040', 'P002', 9, 1, 49.90),
('OR041', 'P003', 13, 1, 59.90),
('OR041', 'P002', 8, 1, 49.90),
('OR042', 'P009', 43, 1, 79.90),
('OR042', 'P012', 57, 1, 759.90),
('OR043', 'P019', 92, 1, 79.90),
('OR044', 'P005', 22, 1, 79.90),
('OR045', 'P012', 57, 1, 759.90),
('OR045', 'P020', 97, 1, 79.90),
('OR046', 'P009', 43, 1, 79.90),
('OR046', 'P016', 77, 1, 39.90),
('OR047', 'P002', 9, 1, 49.90),
('OR048', 'P017', 81, 1, 59.90),
('OR048', 'P003', 12, 1, 59.90),
('OR048', 'P008', 36, 1, 49.90),
('OR049', 'P014', 67, 1, 59.90),
('OR049', 'P016', 77, 1, 39.90),
('OR050', 'P005', 22, 1, 79.90),
('OR055', 'P022', 108, 2, 79.90),
('OR056', 'P013', 63, 3, 39.90),
('OR057', 'P018', 88, 2, 59.90);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
CREATE TABLE `payment` (
  `payment_id` varchar(255) NOT NULL,
  `order_id` varchar(255) NOT NULL,
  `tax` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Credit Card','PayPal','Bank Transfer','Cash on Delivery') NOT NULL,
  `payment_status` enum('Pending','Completed','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `payment_date` datetime DEFAULT current_timestamp(),
  `discount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`payment_id`, `order_id`, `tax`, `total_amount`, `payment_method`, `payment_status`, `payment_date`, `discount`) VALUES
('PM001', 'OR001', 20.63, 322.43, 'Bank Transfer', 'Failed', '2025-02-09 00:00:00', 42.06),
('PM002', 'OR002', 27.44, 455.01, 'Credit Card', 'Failed', '2025-04-03 00:00:00', 29.75),
('PM003', 'OR003', 7.98, 136.81, 'Credit Card', 'Refunded', '2025-01-04 00:00:00', 4.21),
('PM004', 'OR004', 7.91, 107.64, 'Cash on Delivery', 'Pending', '2025-04-24 00:00:00', 32.16),
('PM005', 'OR005', 23.01, 358.83, 'Cash on Delivery', 'Pending', '2025-02-02 00:00:00', 47.63),
('PM006', 'OR006', 13.03, 226.21, 'PayPal', 'Failed', '2025-02-22 00:00:00', 3.96),
('PM007', 'OR007', 10.48, 163.71, 'Cash on Delivery', 'Pending', '2025-01-11 00:00:00', 21.38),
('PM008', 'OR008', 11.75, 179.63, 'Cash on Delivery', 'Completed', '2025-04-17 00:00:00', 27.93),
('PM009', 'OR009', 28.28, 451.14, 'Cash on Delivery', 'Pending', '2025-02-28 00:00:00', 48.48),
('PM010', 'OR010', 11.97, 211.21, 'Cash on Delivery', 'Completed', '2025-02-17 00:00:00', 0.22),
('PM011', 'OR011', 23.02, 380.77, 'Bank Transfer', 'Completed', '2025-03-30 00:00:00', 25.99),
('PM012', 'OR012', 9.88, 174.28, 'Bank Transfer', 'Pending', '2025-04-21 00:00:00', 0.28),
('PM013', 'OR013', 14.48, 233.04, 'PayPal', 'Completed', '2025-02-26 00:00:00', 22.83),
('PM014', 'OR014', 29.83, 489.36, 'Credit Card', 'Failed', '2025-03-31 00:00:00', 37.56),
('PM015', 'OR015', 24.67, 406.28, 'PayPal', 'Pending', '2025-02-11 00:00:00', 29.55),
('PM016', 'OR016', 18.66, 312.76, 'Bank Transfer', 'Pending', '2025-04-28 00:00:00', 16.88),
('PM017', 'OR017', 11.72, 162.27, 'Credit Card', 'Failed', '2025-03-25 00:00:00', 44.72),
('PM018', 'OR018', 28.02, 469.62, 'Bank Transfer', 'Failed', '2025-03-19 00:00:00', 25.45),
('PM019', 'OR019', 9.09, 112.16, 'Cash on Delivery', 'Completed', '2025-03-01 00:00:00', 48.47),
('PM020', 'OR020', 11.04, 171.20, 'PayPal', 'Failed', '2025-04-12 00:00:00', 23.80),
('PM021', 'OR021', 14.71, 232.91, 'Cash on Delivery', 'Refunded', '2025-01-05 00:00:00', 26.96),
('PM022', 'OR022', 26.06, 431.36, 'Cash on Delivery', 'Failed', '2025-01-25 00:00:00', 29.06),
('PM023', 'OR023', 9.24, 153.72, 'Cash on Delivery', 'Completed', '2025-04-08 00:00:00', 9.52),
('PM024', 'OR024', 27.38, 437.68, 'Cash on Delivery', 'Pending', '2025-04-16 00:00:00', 46.06),
('PM025', 'OR025', 17.85, 298.72, 'PayPal', 'Failed', '2025-04-27 00:00:00', 16.66),
('PM026', 'OR026', 29.80, 517.14, 'Cash on Delivery', 'Failed', '2025-01-26 00:00:00', 9.28),
('PM027', 'OR027', 25.88, 418.22, 'PayPal', 'Pending', '2025-03-28 00:00:00', 39.02),
('PM028', 'OR028', 10.20, 138.16, 'Bank Transfer', 'Pending', '2025-04-02 00:00:00', 42.03),
('PM029', 'OR029', 12.60, 211.01, 'Credit Card', 'Completed', '2025-01-06 00:00:00', 11.65),
('PM030', 'OR030', 20.46, 359.27, 'Bank Transfer', 'Refunded', '2025-04-08 00:00:00', 2.14),
('PM031', 'OR031', 19.91, 334.86, 'Credit Card', 'Pending', '2025-01-19 00:00:00', 16.95),
('PM032', 'OR032', 18.16, 317.50, 'Credit Card', 'Completed', '2025-04-03 00:00:00', 3.35),
('PM033', 'OR033', 7.56, 97.58, 'Cash on Delivery', 'Failed', '2025-01-04 00:00:00', 35.93),
('PM034', 'OR034', 14.82, 255.60, 'PayPal', 'Completed', '2025-04-04 00:00:00', 6.19),
('PM035', 'OR035', 27.25, 436.99, 'Cash on Delivery', 'Refunded', '2025-03-09 00:00:00', 44.37),
('PM036', 'OR036', 13.51, 201.56, 'Cash on Delivery', 'Completed', '2025-02-25 00:00:00', 37.11),
('PM037', 'OR037', 22.36, 361.59, 'Bank Transfer', 'Completed', '2025-03-22 00:00:00', 33.43),
('PM038', 'OR038', 23.05, 368.71, 'Cash on Delivery', 'Pending', '2025-01-11 00:00:00', 38.50),
('PM039', 'OR039', 7.65, 133.61, 'Bank Transfer', 'Refunded', '2025-03-30 00:00:00', 1.58),
('PM040', 'OR040', 27.59, 462.32, 'Cash on Delivery', 'Refunded', '2025-01-19 00:00:00', 25.13),
('PM041', 'OR041', 6.07, 99.18, 'Cash on Delivery', 'Pending', '2025-04-13 00:00:00', 7.98),
('PM042', 'OR042', 6.79, 104.49, 'Cash on Delivery', 'Pending', '2025-01-21 00:00:00', 15.50),
('PM043', 'OR043', 16.46, 271.64, 'Cash on Delivery', 'Pending', '2025-01-21 00:00:00', 19.08),
('PM044', 'OR044', 17.12, 271.05, 'Bank Transfer', 'Pending', '2025-02-16 00:00:00', 31.37),
('PM045', 'OR045', 24.75, 392.66, 'PayPal', 'Refunded', '2025-01-30 00:00:00', 44.61),
('PM046', 'OR046', 16.12, 282.66, 'Credit Card', 'Refunded', '2025-01-11 00:00:00', 2.15),
('PM047', 'OR047', 14.76, 226.78, 'PayPal', 'Failed', '2025-02-28 00:00:00', 34.04),
('PM048', 'OR048', 17.17, 287.33, 'Cash on Delivery', 'Refunded', '2025-04-05 00:00:00', 16.05),
('PM049', 'OR049', 20.23, 349.46, 'PayPal', 'Completed', '2025-01-05 00:00:00', 7.96),
('PM050', 'OR050', 12.95, 199.88, 'Bank Transfer', 'Failed', '2025-01-22 00:00:00', 28.83),
('PM051', 'OR051', 28.16, 517.46, '', 'Pending', '2025-04-26 11:02:26', 0.00),
('PM052', 'OR052', 11.98, 231.68, '', 'Pending', '2025-04-26 11:12:13', 0.00),
('PM053', 'OR053', 14.98, 284.58, '', 'Pending', '2025-04-26 11:16:56', 0.00),
('PM054', 'OR054', 9.59, 189.39, '', 'Pending', '2025-04-26 20:50:40', 0.00),
('PM055', 'OR055', 9.59, 189.39, 'Credit Card', 'Completed', '2025-04-26 21:59:01', 0.00),
('PM056', 'OR056', 7.18, 146.88, 'Credit Card', 'Completed', '2025-04-26 22:02:39', 0.00),
('PM057', 'OR057', 7.19, 146.99, 'Credit Card', 'Completed', '2025-04-26 22:10:12', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment_method`
--

DROP TABLE IF EXISTS `payment_method`;
CREATE TABLE `payment_method` (
  `method_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `method_type` enum('Credit Card','PayPal') NOT NULL,
  `card_type` varchar(50) DEFAULT NULL,
  `last_four` varchar(4) DEFAULT NULL,
  `cardholder_name` varchar(255) DEFAULT NULL,
  `expiry_month` varchar(2) DEFAULT NULL,
  `expiry_year` varchar(4) DEFAULT NULL,
  `paypal_email` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_method`
--

INSERT INTO `payment_method` (`method_id`, `user_id`, `method_type`, `card_type`, `last_four`, `cardholder_name`, `expiry_month`, `expiry_year`, `paypal_email`, `is_default`, `created_at`, `updated_at`) VALUES
('PM_20250426232533_aed55bdd', 'MB247', 'Credit Card', 'Visa', '7800', 'sia jin sheng', '8', '2032', NULL, 1, '2025-04-26 15:25:33', '2025-04-26 15:25:33');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

DROP TABLE IF EXISTS `product`;
CREATE TABLE `product` (
  `product_id` varchar(255) NOT NULL,
  `category_id` varchar(255) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_pic1` varchar(255) DEFAULT NULL,
  `product_pic2` varchar(255) DEFAULT NULL,
  `product_pic3` varchar(255) DEFAULT NULL,
  `product_description` varchar(255) DEFAULT NULL,
  `product_type` enum('Unisex','Man','Women','') NOT NULL,
  `product_price` decimal(10,2) DEFAULT NULL,
  `product_status` enum('Available','Out of Stock','Discontinued') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `category_id`, `product_name`, `product_pic1`, `product_pic2`, `product_pic3`, `product_description`, `product_type`, `product_price`, `product_status`) VALUES
('P001', 'CAT1001', 'Cotton Crew Neck T-shirt', 'ST001.jpg', 'ST001a.jpg', 'ST001b.jpg', 'Slightly longer sleeve length suits all body types.', 'Women', 49.90, 'Available'),
('P002', 'CAT1001', 'Cropped Stripe T-shirt', 'ST002.jpg', 'ST002a.jpg', 'ST002b.jpg', 'Cropped length pairs well with any bottoms.', 'Women', 49.90, 'Available'),
('P003', 'CAT1001', 'Pointelle Square Neck T-shirt', 'ST003.jpg', 'ST003a.jpg', 'ST003b.jpg', 'Modern cropped silhouette.', 'Women', 59.90, 'Available'),
('P004', 'CAT1001', 'Seamless T-shirt', 'ST004.jpg', 'ST004a.jpg', 'ST004b.jpg', 'Comfortable relaxed cut.', 'Women', 79.90, 'Available'),
('P005', 'CAT1001', 'Cotton Oversized T-shirt', 'ST005.jpg', 'ST005a.jpg', 'ST005b.jpg', 'Flowing silhouette.', 'Women', 79.90, 'Available'),
('P006', 'CAT1001', 'Soft Cropped T-shirt', 'ST006.jpg', 'ST006a.jpg', 'ST006b.jpg', 'Cropped, compact T-shirt with a fitted silhouette.', 'Women', 59.90, 'Available'),
('P007', 'CAT1001', 'Ribbed Boat Neck T-shirt', 'ST007.jpg', 'ST007a.jpg', 'ST007b.jpg', 'Versatile striped pattern.', 'Women', 39.90, 'Available'),
('P008', 'CAT1001', 'Shirring Boat Neck T-shirt', 'ST008.jpg', 'ST008a.jpg', 'ST008b.jpg', 'Perfect for stylish outfits.', 'Women', 49.90, 'Available'),
('P009', 'CAT1002', 'Soft Cropped T-shirt', 'LT001.jpg', 'LT001a.jpg', 'LT001b.jpg', 'Versatile design for any occasion, from walking and running to lounging.', 'Women', 79.90, 'Available'),
('P010', 'CAT1002', 'Cotton Relaxed Long T-shirt', 'LT002.jpg', 'LT002a.jpg', 'LT002b.jpg', 'Mock-neck T-shirt in a comfortable, relaxed silhouette.', 'Women', 59.90, 'Available'),
('P011', 'CAT1002', 'Cotton Long Sleeve T-Shirt', 'LT003.jpg', 'LT003a.jpg', 'LT003b.jpg', 'Simple design and versatile length pair well with pants and skirts.', 'Women', 49.90, 'Available'),
('P012', 'CAT1002', 'Cotton Long T-shirt', 'LT004.jpg', 'LT004a.jpg', 'LT004b.jpg', 'Side slits make it easy to access your pants pockets.', 'Women', 759.90, 'Available'),
('P013', 'CAT1001', 'Cotton Crew Neck T-shirt', 'MST001.jpg', 'MST001a.jpg', 'MST001b.jpg', 'Perfect for layering.', 'Man', 39.90, 'Available'),
('P014', 'CAT1001', 'Oversized Crew Neck T-shirt', 'MST002.jpg', 'MST002a.jpg', 'MST002b.jpg', 'Made of high-quality material with a luxurious luster and coloration.', 'Man', 59.90, 'Available'),
('P015', 'CAT1001', 'Crew Neck T-shirt', 'MST003.jpg', 'MST003a.jpg', 'MST003b.jpg', 'Heavy-weight cotton jersey fabric with a smooth feel and a laid-back look.', 'Man', 49.90, 'Available'),
('P016', 'CAT1001', 'Dry Color Crew Neck T-shirt', 'MST004.jpg', 'MST004a.jpg', 'MST004b.jpg', 'Classic crew neck.', 'Man', 39.90, 'Available'),
('P017', 'CAT1001', 'DRY-EX T-Shirt', 'MST005.jpg', 'MST005a.jpg', 'MST005b.jpg', 'Made with finer yarn, making it lighter than our regular DRY-EX T-shirt.', 'Man', 59.90, 'Available'),
('P018', 'CAT1001', 'DRY-EX Crew Neck T-shirt', 'MST006.jpg', 'MST006a.jpg', 'MST006b.jpg', 'High-performance T-shirt suitable for active or everyday wear.', 'Man', 59.90, 'Available'),
('P019', 'CAT1001', 'DRY-EX Relaxed Fit T-shirt', 'MST007.jpg', 'MST007a.jpg', 'MST007b.jpg', 'The casual-looking fabric is easy to style, even when not playing sports.', 'Man', 79.90, 'Available'),
('P020', 'CAT1001', 'Cotton Oversized Stripe T-Shirt', 'MST008.jpg', 'MST008a.jpg', 'MST008b.jpg', 'Double-faced fabric for a sleek silhouette.', 'Man', 79.90, 'Available'),
('P021', 'CAT1002', 'Cotton T-Shirt', 'MLT001.jpg', 'MLT001a.jpg', 'MLT001b.jpg', 'Relaxed silhouette looks great alone or layered.', 'Man', 79.90, 'Available'),
('P022', 'CAT1002', 'Cotton Crew Neck T-shirt', 'MLT002.jpg', 'MLT002a.jpg', 'MLT002b.jpg', 'Oversized cut.', 'Man', 79.90, 'Available'),
('P023', 'CAT1002', 'Waffle Henley Neck T-shirt', 'MLT003.jpg', 'MLT003a.jpg', 'MLT003b.jpg', 'Makes a great accent to your outfit.', 'Man', 79.90, 'Available'),
('P024', 'CAT1002', 'DRY-EX UV Protection T-shirt', 'MLT004.jpg', 'MLT004a.jpg', 'MLT004b.jpg', 'Casual fabric that looks great even if you are not wearing it for sports.', 'Man', 79.90, 'Available'),
('P025', 'CAT1003', 'Straight Jeans', 'WJ001.jpg', 'WJ001a.jpg', 'WJ001b.jpg', 'Classic straight silhouette.', 'Women', 149.90, 'Available'),
('P026', 'CAT1003', 'Wide Straight Jeans', 'WJ002.jpg', 'WJ002a.jpg', 'WJ002b.jpg', 'Pairs well with cropped tops or styled with a top tucked in.', 'Women', 149.90, 'Available'),
('P027', 'CAT1003', 'Wide Trouser Jeans', 'WJ003.jpg', 'WJ003a.jpg', 'WJ003b.jpg', 'Refined color with a minimal wash, suitable for dressing up or down.', 'Women', 129.90, 'Available'),
('P028', 'CAT1003', 'Drapey Wide Flare Jeans', 'WJ004.jpg', 'WJ004a.jpg', 'WJ004b.jpg', 'The wide hems contrast with the sleek midsection.', 'Women', 149.90, 'Available'),
('P029', 'CAT1003', 'EZY Ultra Stretch Jeans', 'MJ001.jpg', 'MJ001a.jpg', 'MJ001b.jpg', 'Slim-fit jeans with a sleek look.', 'Man', 149.90, 'Available'),
('P030', 'CAT1003', 'Slim Fit Jeans', 'MJ002.jpg', 'MJ002a.jpg', 'MJ002b.jpg', 'Sleek slim straight cut.', 'Man', 149.90, 'Available'),
('P031', 'CAT1003', 'Wide Straight Jeans', 'MJ003.jpg', 'MJ003a.jpg', 'MJ003b.jpg', 'Lightweight 100% cotton denim.', 'Man', 149.90, 'Available'),
('P032', 'CAT1003', 'Ultra Stretch Jeans', 'MJ004.jpg', 'MJ004a.jpg', 'MJ004b.jpg', 'Ultra Stretch fabric for a comfortable fit.', 'Man', 129.90, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `quantity`
--

DROP TABLE IF EXISTS `quantity`;
CREATE TABLE `quantity` (
  `quantity_id` int(11) NOT NULL,
  `product_id` varchar(255) DEFAULT NULL,
  `size` enum('S','M','L','XL','XXL') NOT NULL,
  `product_stock` int(11) NOT NULL,
  `product_sold` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quantity`
--

INSERT INTO `quantity` (`quantity_id`, `product_id`, `size`, `product_stock`, `product_sold`) VALUES
(1, 'P001', 'S', 34, 12),
(2, 'P001', 'M', 67, 5),
(3, 'P001', 'L', 45, 18),
(4, 'P001', 'XL', 28, 22),
(5, 'P001', 'XXL', 19, 8),
(6, 'P002', 'S', 52, 15),
(7, 'P002', 'M', 78, 3),
(8, 'P002', 'L', 41, 27),
(9, 'P002', 'XL', 36, 14),
(10, 'P002', 'XXL', 23, 5),
(11, 'P003', 'S', 47, 8),
(12, 'P003', 'M', 63, 12),
(13, 'P003', 'L', 39, 21),
(14, 'P003', 'XL', 31, 19),
(15, 'P003', 'XXL', 27, 11),
(16, 'P004', 'S', 58, 7),
(17, 'P004', 'M', 42, 25),
(18, 'P004', 'L', 37, 13),
(19, 'P004', 'XL', 29, 18),
(20, 'P004', 'XXL', 21, 9),
(21, 'P005', 'S', 65, 10),
(22, 'P005', 'M', 53, 17),
(23, 'P005', 'L', 48, 22),
(24, 'P005', 'XL', 32, 15),
(25, 'P005', 'XXL', 26, 4),
(26, 'P006', 'S', 43, 19),
(27, 'P006', 'M', 57, 8),
(28, 'P006', 'L', 62, 11),
(29, 'P006', 'XL', 38, 16),
(30, 'P006', 'XXL', 24, 7),
(31, 'P007', 'S', 51, 14),
(32, 'P007', 'M', 49, 21),
(33, 'P007', 'L', 44, 9),
(34, 'P007', 'XL', 33, 17),
(35, 'P007', 'XXL', 22, 12),
(36, 'P008', 'S', 46, 16),
(37, 'P008', 'M', 64, 7),
(38, 'P008', 'L', 59, 13),
(39, 'P008', 'XL', 35, 20),
(40, 'P008', 'XXL', 28, 5),
(41, 'P009', 'S', 55, 9),
(42, 'P009', 'M', 71, 4),
(43, 'P009', 'L', 52, 18),
(44, 'P009', 'XL', 41, 12),
(45, 'P009', 'XXL', 30, 8),
(46, 'P010', 'S', 48, 17),
(47, 'P010', 'M', 66, 11),
(48, 'P010', 'L', 57, 14),
(49, 'P010', 'XL', 39, 21),
(50, 'P010', 'XXL', 25, 6),
(51, 'P011', 'S', 62, 8),
(52, 'P011', 'M', 54, 19),
(53, 'P011', 'L', 49, 15),
(54, 'P011', 'XL', 37, 13),
(55, 'P011', 'XXL', 29, 10),
(56, 'P012', 'S', 53, 12),
(57, 'P012', 'M', 47, 23),
(58, 'P012', 'L', 42, 17),
(59, 'P012', 'XL', 34, 15),
(60, 'P012', 'XXL', 26, 9),
(61, 'P013', 'S', 59, 11),
(62, 'P013', 'M', 66, 8),
(63, 'P013', 'L', 47, 21),
(64, 'P013', 'XL', 43, 14),
(65, 'P013', 'XXL', 31, 7),
(66, 'P014', 'S', 45, 18),
(67, 'P014', 'M', 56, 10),
(68, 'P014', 'L', 63, 12),
(69, 'P014', 'XL', 38, 16),
(70, 'P014', 'XXL', 27, 11),
(71, 'P015', 'S', 52, 15),
(72, 'P015', 'M', 47, 24),
(73, 'P015', 'L', 44, 17),
(74, 'P015', 'XL', 36, 13),
(75, 'P015', 'XXL', 28, 8),
(76, 'P016', 'S', 61, 9),
(77, 'P016', 'M', 53, 16),
(78, 'P016', 'L', 47, 19),
(79, 'P016', 'XL', 39, 12),
(80, 'P016', 'XXL', 30, 7),
(81, 'P017', 'S', 54, 16),
(82, 'P017', 'M', 61, 9),
(83, 'P017', 'L', 58, 11),
(84, 'P017', 'XL', 42, 17),
(85, 'P017', 'XXL', 33, 5),
(86, 'P018', 'S', 49, 21),
(87, 'P018', 'M', 67, 7),
(88, 'P018', 'L', 51, 14),
(89, 'P018', 'XL', 45, 12),
(90, 'P018', 'XXL', 29, 9),
(91, 'P019', 'S', 57, 13),
(92, 'P019', 'M', 62, 10),
(93, 'P019', 'L', 48, 18),
(94, 'P019', 'XL', 37, 15),
(95, 'P019', 'XXL', 26, 11),
(96, 'P020', 'S', 52, 17),
(97, 'P020', 'M', 57, 13),
(98, 'P020', 'L', 64, 8),
(99, 'P020', 'XL', 41, 19),
(100, 'P020', 'XXL', 34, 6),
(101, 'P021', 'S', 46, 22),
(102, 'P021', 'M', 55, 16),
(103, 'P021', 'L', 50, 17),
(104, 'P021', 'XL', 39, 14),
(105, 'P021', 'XXL', 28, 9),
(106, 'P022', 'S', 63, 7),
(107, 'P022', 'M', 52, 18),
(108, 'P022', 'L', 45, 20),
(109, 'P022', 'XL', 36, 13),
(110, 'P022', 'XXL', 29, 8),
(111, 'P023', 'S', 59, 11),
(112, 'P023', 'M', 48, 19),
(113, 'P023', 'L', 52, 14),
(114, 'P023', 'XL', 43, 12),
(115, 'P023', 'XXL', 31, 7),
(116, 'P024', 'S', 54, 16),
(117, 'P024', 'M', 62, 9),
(118, 'P024', 'L', 57, 13),
(119, 'P024', 'XL', 45, 15),
(120, 'P024', 'XXL', 33, 8),
(121, 'P025', 'S', 47, 21),
(122, 'P025', 'M', 59, 12),
(123, 'P025', 'L', 53, 17),
(124, 'P025', 'XL', 41, 14),
(125, 'P025', 'XXL', 30, 9),
(126, 'P026', 'S', 58, 13),
(127, 'P026', 'M', 64, 8),
(128, 'P026', 'L', 49, 19),
(129, 'P026', 'XL', 37, 15),
(130, 'P026', 'XXL', 28, 11),
(131, 'P027', 'S', 52, 17),
(132, 'P027', 'M', 47, 22),
(133, 'P027', 'L', 56, 14),
(134, 'P027', 'XL', 43, 13),
(135, 'P027', 'XXL', 34, 8),
(136, 'P028', 'S', 61, 9),
(137, 'P028', 'M', 53, 16),
(138, 'P028', 'L', 48, 18),
(139, 'P028', 'XL', 39, 12),
(140, 'P028', 'XXL', 27, 10),
(141, 'P029', 'S', 55, 15),
(142, 'P029', 'M', 62, 12),
(143, 'P029', 'L', 57, 13),
(144, 'P029', 'XL', 46, 14),
(145, 'P029', 'XXL', 32, 8),
(146, 'P030', 'S', 49, 19),
(147, 'P030', 'M', 58, 12),
(148, 'P030', 'L', 52, 16),
(149, 'P030', 'XL', 41, 13),
(150, 'P030', 'XXL', 29, 9),
(151, 'P031', 'S', 62, 8),
(152, 'P031', 'M', 54, 17),
(153, 'P031', 'L', 47, 20),
(154, 'P031', 'XL', 38, 15),
(155, 'P031', 'XXL', 30, 10),
(156, 'P032', 'S', 57, 13),
(157, 'P032', 'M', 49, 19),
(158, 'P032', 'L', 53, 16),
(159, 'P032', 'XL', 42, 14),
(160, 'P032', 'XXL', 33, 9);

-- --------------------------------------------------------

--
-- Table structure for table `tokens`
--

DROP TABLE IF EXISTS `tokens`;
CREATE TABLE `tokens` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `token` varchar(255) NOT NULL,
  `type` enum('email_verification','password_reset') NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tokens`
--

INSERT INTO `tokens` (`id`, `user_id`, `token`, `type`, `expires_at`, `created_at`) VALUES
(7, 'MB247', 'c65ebc866921054406c0d4a04b42dfff33ac74935c8b401005210c70a5cead5c', 'password_reset', '2025-04-23 23:35:52', '2025-04-23 14:35:52'),
(8, 'MB222', '81e5dc3ef3b1f302c3b6362218a66a811aa82fd6719c2bc096e4b99593bbacdf', 'email_verification', '2025-04-25 08:48:52', '2025-04-24 00:48:52'),
(9, 'MB570', '745531269f215984e938ade2158f7cc7e9f25845a61574b04724504edf672ec9', 'email_verification', '2025-04-25 13:23:14', '2025-04-24 05:23:14'),
(10, 'MB971', '5fabd6f68d0f30c8dedf2f851a6b2db02260bb0317ab465a61210b90f9d35f62', 'email_verification', '2025-04-25 13:25:11', '2025-04-24 05:25:11'),
(11, 'MB542', '9ebcc3bf44fd1106526dd06762b71b25eb505c5d8d87c96e5dd0f494e9506051', 'email_verification', '2025-04-26 17:28:17', '2025-04-25 09:28:17');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `user_id` varchar(255) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `user_Email` varchar(255) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_gender` enum('Male','Female','Other') DEFAULT NULL,
  `user_phone` varchar(20) DEFAULT NULL,
  `user_profile_pic` varchar(255) DEFAULT NULL,
  `user_update_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Active','Inactive','Banned') NOT NULL DEFAULT 'Active',
  `role` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `user_name`, `user_Email`, `user_password`, `user_gender`, `user_phone`, `user_profile_pic`, `user_update_time`, `status`, `role`) VALUES
('MB222', 'kok heng', 'mbsboleh123@gmail.com', '$2y$10$JBqUpxk0cUk.lWCouSynMunVgu.k.0NYhXjbteJM6QTofbwBSs3NO', 'Male', '60161234567', '68098a7497630.jpg', '2025-04-24 05:23:58', 'Inactive', 'member'),
('MB247', 'wei hong', 'siajinsheng@gmail.com', '$2y$10$fmeUYMCv.FsAx66IAmZo6eD7lRk/xE6tweRx68ieavj7cKEUfEcsO', 'Male', '60182259000', '66321976e3099.jpg', '2025-04-26 15:24:38', 'Active', 'member'),
('MB542', 'js', 'js@gmail.com', '$2y$10$G/Lg5qlPMy7bOWivlX.PeeSh9Rhmz60T53KqIeQ68PGZIjqt4ErR6', 'Male', '60182259156', '680b55b1005d6.jpg', '2025-04-25 09:35:45', 'Active', 'member'),
('MB570', 'admin', '1@gmail.com', '$2y$10$.555jOgS0gL3pUeGQBzq0uaYFbl6psE7yrHvrzGt6eFhfK.xdLp5.', 'Male', '60123333333', '6809cac2c296e.jpg', '2025-04-26 01:15:53', 'Active', 'admin'),
('MB971', 'staff', '2@gmail.com', '$2y$10$jJFfVoWsibiHcf44gVXHWOTPjkGdGOi/6/ltYA4Y6DdNS/a/1X.8.', 'Male', '60121234567', '6809cb3752977.jpg', '2025-04-24 05:25:34', 'Active', 'staff');

-- --------------------------------------------------------

--
-- Table structure for table `voucher`
--

DROP TABLE IF EXISTS `voucher`;
CREATE TABLE `voucher` (
  `voucher_code` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `discount_rate` decimal(10,2) NOT NULL,
  `point_need` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `address`
--
ALTER TABLE `address`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `quantity_id` (`quantity_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `delivery`
--
ALTER TABLE `delivery`
  ADD PRIMARY KEY (`delivery_id`),
  ADD KEY `address_id` (`address_id`);

--
-- Indexes for table `discount`
--
ALTER TABLE `discount`
  ADD PRIMARY KEY (`Discount_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `delivery_id` (`delivery_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD KEY `quantity_id` (`quantity_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `payment_method`
--
ALTER TABLE `payment_method`
  ADD PRIMARY KEY (`method_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `quantity`
--
ALTER TABLE `quantity`
  ADD PRIMARY KEY (`quantity_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `tokens`
--
ALTER TABLE `tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `voucher`
--
ALTER TABLE `voucher`
  ADD PRIMARY KEY (`voucher_code`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tokens`
--
ALTER TABLE `tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `address`
--
ALTER TABLE `address`
  ADD CONSTRAINT `address_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`quantity_id`) REFERENCES `quantity` (`quantity_id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `cart_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);

--
-- Constraints for table `delivery`
--
ALTER TABLE `delivery`
  ADD CONSTRAINT `delivery_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `address` (`address_id`);

--
-- Constraints for table `discount`
--
ALTER TABLE `discount`
  ADD CONSTRAINT `discount_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `delivery` (`delivery_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`quantity_id`) REFERENCES `quantity` (`quantity_id`),
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`),
  ADD CONSTRAINT `order_details_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `payment_method`
--
ALTER TABLE `payment_method`
  ADD CONSTRAINT `payment_method_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`);

--
-- Constraints for table `quantity`
--
ALTER TABLE `quantity`
  ADD CONSTRAINT `quantity_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);

--
-- Constraints for table `tokens`
--
ALTER TABLE `tokens`
  ADD CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `voucher`
--
ALTER TABLE `voucher`
  ADD CONSTRAINT `voucher_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
