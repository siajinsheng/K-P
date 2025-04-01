-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 01, 2025 at 03:11 PM
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
  `cus_id` varchar(255) NOT NULL,
  `street` decimal(10,0) NOT NULL,
  `city` decimal(10,0) NOT NULL,
  `state` varchar(255) NOT NULL,
  `post_code` date NOT NULL,
  `country` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` varchar(255) NOT NULL,
  `admin_name` varchar(255) DEFAULT NULL,
  `admin_password` varchar(255) NOT NULL,
  `con_admin_password` varchar(255) NOT NULL,
  `admin_update_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_email` varchar(255) NOT NULL,
  `admin_status` varchar(255) NOT NULL,
  `admin_profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` varchar(255) NOT NULL,
  `cus_id` varchar(255) NOT NULL,
  `product_id` varchar(255) NOT NULL,
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
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `cus_id` varchar(255) NOT NULL,
  `cus_name` varchar(255) DEFAULT NULL,
  `cus_Email` varchar(255) NOT NULL,
  `cus_password` varchar(255) NOT NULL,
  `con_cus_password` varchar(255) NOT NULL,
  `cus_gender` enum('Male','Female','Other') NOT NULL,
  `cus_phone` varchar(20) DEFAULT NULL,
  `cus_profile_pic` varchar(255) DEFAULT NULL,
  `cus_update_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cus_status` enum('Active','Inactive','Banned') NOT NULL DEFAULT 'Active',
  `role` varchar(10) DEFAULT NULL,
  `activation_token` varchar(64) DEFAULT NULL,
  `activation_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `cus_id` varchar(255) NOT NULL,
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
  `product_price` decimal(10,2) DEFAULT NULL,
  `product_status` enum('Available','Out of Stock','Discontinued') NOT NULL DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `category_id`, `product_name`, `product_pic1`, `product_pic2`, `product_pic3`, `product_description`, `product_price`, `product_status`) VALUES
('LST001', 'CAT1002', 'Cotton T-Shirt', 'LST001.jpg', 'LST001a.jpg', 'LST001b.jpg', 'Relaxed silhouette looks great alone or layered.', 79.90, 'Available'),
('LST002', 'CAT1002', 'Cotton Crew Neck T-shirt', 'LST002.jpg', 'LST002a.jpg', 'LST002b.jpg', 'Oversized cut.', 79.90, 'Available'),
('LST003', 'CAT1002', 'Waffle Henley Neck T-shirt', 'LST003.jpg', 'LST003a.jpg', 'LST003b.jpg', 'Makes a great accent to your outfit.', 79.90, 'Available'),
('LST004', 'CAT1002', 'DRY-EX UV Protection T-shirt', 'LST004.jpg', 'LST004a.jpg', 'LST004b.jpg', 'Casual fabric that looks great even if you are not wearing it for sports.', 79.90, 'Available'),
('LT001', 'CAT1002', 'Soft Cropped T-shirt', 'LT001.jpg', 'LT001a.jpg', 'LT001b.jpg', 'Versatile design for any occasion, from walking and running to lounging.', 79.90, 'Available'),
('LT002', 'CAT1002', 'Cotton Relaxed Long T-shirt', 'LT002.jpg', 'LT002a.jpg', 'LT002b.jpg', 'Mock-neck T-shirt in a comfortable, relaxed silhouette.', 59.90, 'Available'),
('LT003', 'CAT1002', 'Cotton Long Sleeve T-Shirt', 'LT003.jpg', 'LT003a.jpg', 'LT003b.jpg', 'Simple design and versatile length pair well with pants and skirts.', 49.90, 'Available'),
('LT004', 'CAT1002', 'Cotton Long T-shirt', 'LT004.jpg', 'LT004a.jpg', 'LT004b.jpg', 'Side slits make it easy to access your pants pockets.', 759.90, 'Available'),
('MJ001', 'CAT1003', 'EZY Ultra Stretch Jeans', 'MJ001.jpg', 'MJ001a.jpg', 'MJ001b.jpg', 'Slim-fit jeans with a sleek look.', 149.90, 'Available'),
('MJ002', 'CAT1003', 'Slim Fit Jeans', 'MJ002.jpg', 'MJ002a.jpg', 'MJ002b.jpg', 'Sleek slim straight cut.', 149.90, 'Available'),
('MJ003', 'CAT1003', 'Wide Straight Jeans', 'MJ003.jpg', 'MJ003a.jpg', 'MJ003b.jpg', 'Lightweight 100% cotton denim.', 149.90, 'Available'),
('MJ004', 'CAT1003', 'Ultra Stretch Jeans', 'MJ004.jpg', 'MJ004a.jpg', 'MJ004b.jpg', 'Ultra Stretch fabric for a comfortable fit.', 129.90, 'Available'),
('MST001', 'CAT1001', 'Cotton Crew Neck T-shirt', 'MST001.jpg', 'MST001a.jpg', 'MST001b.jpg', 'Perfect for layering.', 39.90, 'Available'),
('MST002', 'CAT1001', 'Oversized Crew Neck T-shirt', 'MST002.jpg', 'MST002a.jpg', 'MST002b.jpg', 'Made of high-quality material with a luxurious luster and coloration.', 59.90, 'Available'),
('MST003', 'CAT1001', 'Crew Neck T-shirt', 'MST003.jpg', 'MST003a.jpg', 'MST003b.jpg', 'Heavy-weight cotton jersey fabric with a smooth feel and a laid-back look.', 49.90, 'Available'),
('MST004', 'CAT1001', 'Dry Color Crew Neck T-shirt', 'MST004.jpg', 'MST004a.jpg', 'MST004b.jpg', 'Classic crew neck.', 39.90, 'Available'),
('MST005', 'CAT1001', 'DRY-EX T-Shirt', 'MST005.jpg', 'MST005a.jpg', 'MST005b.jpg', 'Made with finer yarn, making it lighter than our regular DRY-EX T-shirt.', 59.90, 'Available'),
('MST006', 'CAT1001', 'DRY-EX Crew Neck T-shirt', 'MST006.jpg', 'MST006a.jpg', 'MST006b.jpg', 'High-performance T-shirt suitable for active or everyday wear.', 59.90, 'Available'),
('MST007', 'CAT1001', 'DRY-EX Relaxed Fit T-shirt', 'MST007.jpg', 'MST007a.jpg', 'MST007b.jpg', 'The casual-looking fabric is easy to style, even when not playing sports.', 79.90, 'Available'),
('MST008', 'CAT1001', 'Cotton Oversized Stripe T-Shirt', 'MST008.jpg', 'MST008a.jpg', 'MST008b.jpg', 'Double-faced fabric for a sleek silhouette.', 79.90, 'Available'),
('ST001', 'CAT1001', 'Cotton Crew Neck T-shirt', 'ST001.jpg', 'ST001a.jpg', 'ST001b.jpg', 'Slightly longer sleeve length suits all body types.', 49.90, 'Available'),
('ST002', 'CAT1001', 'Cropped Stripe T-shirt', 'ST002.jpg', 'ST002a.jpg', 'ST002b.jpg', 'Cropped length pairs well with any bottoms.', 49.90, 'Available'),
('ST003', 'CAT1001', 'Pointelle Square Neck T-shirt', 'ST003.jpg', 'ST003a.jpg', 'ST003b.jpg', 'Modern cropped silhouette.', 59.90, 'Available'),
('ST004', 'CAT1001', 'Seamless T-shirt', 'ST004.jpg', 'ST004a.jpg', 'ST004b.jpg', 'Comfortable relaxed cut.', 79.90, 'Available'),
('ST005', 'CAT1001', 'Cotton Oversized T-shirt', 'ST005.jpg', 'ST005a.jpg', 'ST005b.jpg', 'Flowing silhouette.', 79.90, 'Available'),
('ST006', 'CAT1001', 'Soft Cropped T-shirt', 'ST006.jpg', 'ST006a.jpg', 'ST006b.jpg', 'Cropped, compact T-shirt with a fitted silhouette.', 59.90, 'Available'),
('ST007', 'CAT1001', 'Ribbed Boat Neck T-shirt', 'ST007.jpg', 'ST007a.jpg', 'ST007b.jpg', 'Versatile striped pattern.', 39.90, 'Available'),
('ST008', 'CAT1001', 'Shirring Boat Neck T-shirt', 'ST008.jpg', 'ST008a.jpg', 'ST008b.jpg', 'Perfect for stylish outfits.', 49.90, 'Available'),
('WJ001', 'CAT1003', 'Straight Jeans', 'WJ001.jpg', 'WJ001a.jpg', 'WJ001b.jpg', 'Classic straight silhouette.', 149.90, 'Available'),
('WJ002', 'CAT1003', 'Wide Straight Jeans', 'WJ002.jpg', 'WJ002a.jpg', 'WJ002b.jpg', 'Pairs well with cropped tops or styled with a top tucked in.', 149.90, 'Available'),
('WJ003', 'CAT1003', 'Wide Trouser Jeans', 'WJ003.jpg', 'WJ003a.jpg', 'WJ003b.jpg', 'Refined color with a minimal wash, suitable for dressing up or down.', 129.90, 'Available'),
('WJ004', 'CAT1003', 'Drapey Wide Flare Jeans', 'WJ004.jpg', 'WJ004a.jpg', 'WJ004b.jpg', 'The wide hems contrast with the sleek midsection.', 149.90, 'Available');

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

-- --------------------------------------------------------

--
-- Table structure for table `voucher`
--

CREATE TABLE `voucher` (
  `voucher_code` varchar(255) NOT NULL,
  `cus_id` varchar(255) NOT NULL,
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
  ADD KEY `cus_id` (`cus_id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `cus_id` (`cus_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`cus_id`);

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
  ADD KEY `cus_id` (`cus_id`);

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
-- Indexes for table `voucher`
--
ALTER TABLE `voucher`
  ADD PRIMARY KEY (`voucher_code`),
  ADD KEY `cus_id` (`cus_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `address`
--
ALTER TABLE `address`
  ADD CONSTRAINT `address_ibfk_1` FOREIGN KEY (`cus_id`) REFERENCES `customer` (`cus_id`);

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`cus_id`) REFERENCES `customer` (`cus_id`),
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
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`cus_id`) REFERENCES `customer` (`cus_id`);

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
  ADD CONSTRAINT `voucher_ibfk_1` FOREIGN KEY (`cus_id`) REFERENCES `customer` (`cus_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
