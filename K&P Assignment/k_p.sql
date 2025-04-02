-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 02, 2025 at 06:41 PM
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

CREATE TABLE `address` (
  `address_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `street` decimal(10,0) NOT NULL,
  `city` decimal(10,0) NOT NULL,
  `state` varchar(255) NOT NULL,
  `post_code` date NOT NULL,
  `country` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `size` enum('S','M','L','XL','XXL') DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `added_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

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

CREATE TABLE `delivery` (
  `delivery_id` varchar(255) NOT NULL,
  `address_id` varchar(255) NOT NULL,
  `delivery_fee` decimal(10,0) NOT NULL,
  `delivery_status` enum('Processing','Out for Delivery','Delivered','Failed') NOT NULL DEFAULT 'Processing',
  `estimated_date` date DEFAULT NULL,
  `delivered_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discount`
--

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

CREATE TABLE `orders` (
  `order_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `delivery_id` varchar(255) NOT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `orders_status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL,
  `order_subtotal` decimal(10,2) DEFAULT NULL,
  `order_total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `order_id` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

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
(62, 'P013', 'M', 68, 6),
(63, 'P013', 'L', 51, 20),
(64, 'P013', 'XL', 43, 14),
(65, 'P013', 'XXL', 31, 7),
(66, 'P014', 'S', 45, 18),
(67, 'P014', 'M', 57, 9),
(68, 'P014', 'L', 63, 12),
(69, 'P014', 'XL', 38, 16),
(70, 'P014', 'XXL', 27, 11),
(71, 'P015', 'S', 52, 15),
(72, 'P015', 'M', 49, 22),
(73, 'P015', 'L', 44, 17),
(74, 'P015', 'XL', 36, 13),
(75, 'P015', 'XXL', 28, 8),
(76, 'P016', 'S', 61, 9),
(77, 'P016', 'M', 55, 14),
(78, 'P016', 'L', 47, 19),
(79, 'P016', 'XL', 39, 12),
(80, 'P016', 'XXL', 30, 7),
(81, 'P017', 'S', 54, 16),
(82, 'P017', 'M', 62, 8),
(83, 'P017', 'L', 58, 11),
(84, 'P017', 'XL', 42, 17),
(85, 'P017', 'XXL', 33, 5),
(86, 'P018', 'S', 49, 21),
(87, 'P018', 'M', 67, 7),
(88, 'P018', 'L', 53, 14),
(89, 'P018', 'XL', 45, 12),
(90, 'P018', 'XXL', 29, 9),
(91, 'P019', 'S', 57, 13),
(92, 'P019', 'M', 63, 9),
(93, 'P019', 'L', 48, 18),
(94, 'P019', 'XL', 37, 15),
(95, 'P019', 'XXL', 26, 11),
(96, 'P020', 'S', 52, 17),
(97, 'P020', 'M', 59, 11),
(98, 'P020', 'L', 64, 8),
(99, 'P020', 'XL', 41, 19),
(100, 'P020', 'XXL', 34, 6),
(101, 'P021', 'S', 46, 22),
(102, 'P021', 'M', 58, 13),
(103, 'P021', 'L', 51, 16),
(104, 'P021', 'XL', 39, 14),
(105, 'P021', 'XXL', 28, 9),
(106, 'P022', 'S', 63, 7),
(107, 'P022', 'M', 55, 15),
(108, 'P022', 'L', 47, 20),
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
(142, 'P029', 'M', 63, 11),
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
-- Table structure for table `user`
--

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
  `role` varchar(10) NOT NULL,
  `activation_token` varchar(64) DEFAULT NULL,
  `activation_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `user_name`, `user_Email`, `user_password`, `user_gender`, `user_phone`, `user_profile_pic`, `user_update_time`, `status`, `role`, `activation_token`, `activation_expiry`) VALUES
('AD001', 'Admin', 'admin@gmail.com', '$2a$10$N9qo8uLOickgx2ZMRZoMy.Mrq6PH.6U1JXzJYy7Dd7GFUj7z/s1G.', 'Male', '0159856479', 'admin.jpg', '2025-04-02 06:52:12', 'Active', 'admin', NULL, NULL),
('MB001', 'John Customer', 'kachun.customer@gmail.com', '$2a$10$3mXq7k.T9Uo5Z5J8r7vZUeW5v5X5J8r7vZUeW5v5X5J8r7vZUeW5v', 'Male', '0125946687', 'kachun.jpg', '2025-04-02 06:52:12', 'Active', 'member', NULL, NULL),
('MB825', 'js', 'js@gmail.com', '$2y$10$8XlUTcnh7uxMdr.vySWbDufzxVWGcM/njiuu9BE/ETpQGa193Erk2', 'Male', '60182259156', '67ed4c45ab7b2.jpg', '2025-04-02 16:38:15', 'Active', 'member', NULL, NULL),
('ST001', 'Staff', 'staff@gmail.com', '$2a$10$VE0tR5c5QlUgDZQZP1YrE.7ZJQ9Xz3JjZr3Jk6d1JvQmY9Jh5r1XO', 'Female', '0165897533', 'staff.jpg', '2025-04-02 06:52:12', 'Active', 'staff', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `voucher`
--

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
  ADD PRIMARY KEY (`order_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`);

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
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`);

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
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`),
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

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
-- Constraints for table `voucher`
--
ALTER TABLE `voucher`
  ADD CONSTRAINT `voucher_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
