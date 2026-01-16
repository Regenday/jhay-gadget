-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 27, 2025 at 12:16 AM
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
-- Database: `jhay_gadget`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `activity_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `activity_date`) VALUES
(1, 1, 'admin', 'User Logout', 'User logged out from IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 01:38:50'),
(2, 1, 'admin', 'Product Added', 'Added new product: gGO (Category: phone, Price: ₱34443, Status: Available)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 01:39:31'),
(3, 1, 'admin', 'Stock Added', 'Added 1 stock items to WJE. Serials: 1231', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 01:39:58'),
(4, 1, 'admin', 'Stock Marked Defective', 'Marked 1 items as defective for WJE. Reason: Defective. Serials: 1231', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 01:40:13'),
(5, 1, 'admin', 'Purchase Completed', 'Processed purchase with receipt RCP20251119024052426. Sold 1 items. Total: ₱10000. Serials: 3443', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 01:40:52'),
(6, 1, NULL, 'product_updated', 'Updated product: AK45', NULL, NULL, '2025-11-19 05:20:53'),
(7, 1, NULL, 'product_updated', 'Updated product: AK45', NULL, NULL, '2025-11-19 05:21:14'),
(8, 1, 'admin', 'User Logout', 'User logged out from IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:18:27'),
(9, 1, NULL, 'product_updated', 'Updated product: AK47', NULL, NULL, '2025-11-19 06:20:54'),
(10, 1, NULL, 'product_updated', 'Updated product: AK48', NULL, NULL, '2025-11-19 06:21:06'),
(11, 1, NULL, 'product_updated', 'Updated product: AK48', NULL, NULL, '2025-11-19 06:21:17'),
(12, 1, NULL, 'product_updated', 'Updated product: AK48', NULL, NULL, '2025-11-19 06:22:36'),
(13, 1, 'admin', 'User Logout', 'User logged out from IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 01:55:02'),
(14, 1, 'admin', 'User Logout', 'User logged out from IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 02:03:01'),
(15, 1, 'admin', 'User Logout', 'User logged out from IP: ::1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 02:04:56'),
(16, 1, 'admin', 'Purchase Completed', 'Processed purchase with receipt RCP20251125155613281. Sold 1 items. Total: ₱10020. Serials: 33333', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 14:56:13'),
(17, 1, 'admin', 'Purchase Completed', 'Processed purchase with receipt RCP20251126011221100. Sold 1 items. Total: ₱10500. Serials: 133', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 00:12:21'),
(18, 1, 'admin', 'Stock Added', 'Added 1 stock items to AK48. Serials: 0099', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 00:21:44'),
(19, 1, 'admin', 'Purchase Completed', 'Processed purchase with receipt RCP20251126012206622. Sold 1 items. Total: ₱10000. Serials: 0099', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 00:22:06'),
(20, 1, 'admin', 'Stock Added', 'Added 1 stock items to AK48. Serials: 0098', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 01:30:18'),
(21, 1, 'admin', 'Purchase Completed', 'Processed purchase with receipt RCP20251126023041925. Sold 1 items. Total: ₱10500. Serials: 0098', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 01:30:41'),
(22, 1, 'admin', 'Stock Added', 'Added 1 stock items to AK48. Serials: 0097', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 01:32:30'),
(23, 1, 'admin', 'Purchase Completed', 'Processed purchase with receipt RCP20251126023248987. Sold 1 items. Total: ₱10000. Serials: 0097', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 01:32:48'),
(24, 1, 'admin', 'Stock Added', 'Added 1 stock items to AK48. Serials: 8980', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 02:51:29'),
(25, 1, 'admin', 'Purchase Completed', 'Processed purchase with receipt RCP20251126035314725. Sold 1 items. Total: ₱10050. Profit: ₱500. Serials: 8980', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 02:53:14'),
(26, 1, 'admin', 'Stock Added', 'Added 1 stock items to AK48. Serials: 0009', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 03:10:34'),
(27, 1, 'admin', 'Stock Added', 'Added 1 stock items to AK48. Serials: 0008', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 21:41:32');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_messages`
--

CREATE TABLE `chatbot_messages` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('general','question','complaint','feedback') DEFAULT 'general',
  `is_anonymous` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chatbot_messages`
--

INSERT INTO `chatbot_messages` (`id`, `email`, `message`, `type`, `is_anonymous`, `created_at`) VALUES
(1, 'niccoho1234@gmail.com', 'BAHU TAE', 'complaint', 1, '2025-11-17 21:42:53'),
(2, 'gemsu74@gmail.com', '7TUIIUYU', 'feedback', 0, '2025-11-17 22:32:23'),
(3, '', 'sino yung kasama mong babae', 'complaint', 1, '2025-11-18 01:17:29');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `issue_type` varchar(100) DEFAULT NULL,
  `complaint_details` text NOT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `status` enum('pending','in_progress','resolved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `name`, `email`, `contact`, `product_name`, `purchase_date`, `issue_type`, `complaint_details`, `is_anonymous`, `status`, `created_at`) VALUES
(1, 'Anonymous User', '', 'Anonymous', 'IPHONNE123', '2025-11-18', 'Defective Product', 'BAHU TAE', 1, 'pending', '2025-11-17 21:42:53'),
(2, 'Anonymous User', '', 'Anonymous', 'hotdog', '2025-11-18', 'Service Issue', 'sino yung kasama mong babae', 1, 'pending', '2025-11-18 01:17:29');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(100) DEFAULT NULL,
  `items` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `category` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'New',
  `photo` varchar(255) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `color`, `items`, `price`, `cost_price`, `category`, `date`, `status`, `photo`, `stock`, `created_at`) VALUES
(1, 'PHONE123', 'BLUE', 'DECRIPTION', 5000.00, 0.00, 'phone', '2025-11-04', 'New', '69099489b31b3.jpeg', 7, '2025-11-04 05:52:09'),
(2, 'RR', 'PINK', 'DECRIPTION', 60000.00, 0.00, 'phone', '2025-11-04', 'New', '6909b1715d6b7.jpeg', 32, '2025-11-04 07:55:29'),
(3, 'nicco', NULL, 'EJE\r\n', 50000.00, 0.00, 'phone', '2025-11-04', 'Available', 'uploads/6909ecf14b717_Screenshot_17-10-2025_12404_localhost.jpeg', 1, '2025-11-04 10:32:41'),
(4, 'SASA BOY', NULL, 'DWXEDE', 500.00, 0.00, 'tablet', '2025-11-14', 'Available', 'uploads/690c34e8b68df_Screenshot_17-10-2025_125532_localhost.jpeg', 0, '2025-11-06 05:40:56'),
(7, 'YY', NULL, 'S', 20000.00, 0.00, 'laptop', '2025-11-11', 'Available', '', 0, '2025-11-11 01:48:51'),
(8, 'egg', NULL, 'z', 11111.00, 0.00, 'phone', '2025-11-16', 'Available', '', 0, '2025-11-16 13:04:06'),
(9, 'Iphone 17', NULL, '8 Ram 256/512 internal storage ', 100000.00, 0.00, 'phone', '2025-11-17', 'Available', 'uploads/691bcbb9988af_iphone 17.jpg', 0, '2025-11-17 04:46:49'),
(10, 'PHONE', NULL, 'EW', 50000.00, 0.00, 'phone', '2025-11-18', 'Available', 'uploads/691bdf48b9f85.webp', 0, '2025-11-18 02:51:52'),
(11, 'AK48', NULL, 'WEEEQL;Q', 10000.00, 0.00, 'phone', '2025-11-18', 'Available', '', 0, '2025-11-18 02:54:59');

-- --------------------------------------------------------

--
-- Table structure for table `product_stock`
--

CREATE TABLE `product_stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `color` varchar(100) NOT NULL,
  `predetermined_profit` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `purchase_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_stock`
--

INSERT INTO `product_stock` (`id`, `product_id`, `serial_number`, `color`, `predetermined_profit`, `status`, `created_at`, `updated_at`, `purchase_price`) VALUES
(39, 3, '7777', 'blue', 0.00, 'Sold', '2025-11-10 07:41:54', '2025-11-16 11:53:33', NULL),
(42, 4, '1212', 'pink', 0.00, 'Sold', '2025-11-10 13:33:22', '2025-11-16 11:53:33', NULL),
(43, 7, '8888', 'YELLOW', 0.00, 'Sold', '2025-11-11 01:49:13', '2025-11-16 11:53:33', NULL),
(44, 7, '2222', 'blue', 0.00, 'Defective', '2025-11-16 08:04:44', '2025-11-16 11:53:33', NULL),
(45, 7, '1211', 'BLUE', 0.00, 'Sold', '2025-11-16 09:34:49', '2025-11-16 11:53:33', NULL),
(46, 7, '2323', 'BLUE', 0.00, 'Defective', '2025-11-16 11:49:42', '2025-11-16 12:00:12', NULL),
(47, 7, '0909', 'red', 0.00, 'Sold', '2025-11-16 12:02:40', '2025-11-16 12:04:58', NULL),
(48, 8, '1132', 'blue', 0.00, 'Sold', '2025-11-16 13:05:03', '2025-11-16 13:24:09', NULL),
(49, 8, '1122', 'blue', 0.00, 'Defective', '2025-11-16 13:07:58', '2025-11-16 13:08:26', NULL),
(50, 8, '1313', 'black', 0.00, 'Sold', '2025-11-16 22:31:05', '2025-11-16 23:21:41', NULL),
(51, 8, '3434', 'blue', 0.00, 'Sold', '2025-11-16 23:34:08', '2025-11-16 23:35:58', NULL),
(52, 8, '8484', 'black', 0.00, 'Sold', '2025-11-16 23:37:55', '2025-11-16 23:39:39', NULL),
(53, 8, '8585', 'black', 0.00, 'Sold', '2025-11-16 23:38:22', '2025-11-16 23:39:39', NULL),
(54, 8, '4545', 'blue', 0.00, 'Sold', '2025-11-17 00:10:36', '2025-11-17 00:11:51', NULL),
(55, 4, '4040', 'blue', 0.00, 'Sold', '2025-11-17 00:27:46', '2025-11-17 00:28:44', NULL),
(56, 4, '4141', 'blue', 0.00, 'Sold', '2025-11-17 00:27:46', '2025-11-17 00:55:27', NULL),
(57, 9, '8787', 'blue', 0.00, 'Sold', '2025-11-17 04:48:00', '2025-11-17 04:48:37', NULL),
(58, 9, '0101', 'pink', 0.00, 'Sold', '2025-11-17 06:17:36', '2025-11-17 06:18:03', NULL),
(59, 9, '5656', 'blue', 0.00, 'Sold', '2025-11-17 06:18:55', '2025-11-17 06:19:35', NULL),
(60, 9, '6767', 'pink', 0.00, 'Sold', '2025-11-17 06:21:14', '2025-11-17 06:21:26', NULL),
(61, 9, '1234', 'blue', 0.00, 'Defective', '2025-11-17 14:54:35', '2025-11-18 05:13:56', NULL),
(62, 9, '1112', 'blue', 0.00, 'Sold', '2025-11-18 01:23:06', '2025-11-18 05:14:28', NULL),
(63, 9, '1113', 'black', 0.00, 'Sold', '2025-11-18 01:23:06', '2025-11-18 05:33:50', NULL),
(64, 9, '1114', 'white', 0.00, 'Sold', '2025-11-18 01:23:06', '2025-11-18 06:41:50', NULL),
(65, 9, '1115', 'red', 0.00, 'Sold', '2025-11-18 01:23:06', '2025-11-18 06:45:21', NULL),
(66, 9, '1116', 'pink', 0.00, 'Defective', '2025-11-18 01:23:06', '2025-11-18 08:01:30', NULL),
(67, 9, '1117', 'green', 0.00, 'Sold', '2025-11-18 01:23:06', '2025-11-18 08:02:06', NULL),
(68, 10, '2345', 'BLUE', 0.00, 'Sold', '2025-11-18 02:53:14', '2025-11-18 02:53:30', NULL),
(69, 11, '2134', 'BLUE', 0.00, 'Sold', '2025-11-18 02:55:26', '2025-11-18 02:55:39', NULL),
(70, 11, '3443', 'BLUE', 0.00, 'Sold', '2025-11-18 05:31:20', '2025-11-19 01:40:52', NULL),
(71, 11, '133', 'BLUE', 0.00, 'Sold', '2025-11-18 08:00:13', '2025-11-26 00:12:21', NULL),
(72, 11, '33333', 'BLUE', 0.00, 'Sold', '2025-11-18 08:03:42', '2025-11-25 14:56:13', NULL),
(73, 11, '1920', 'BLUE', 0.00, 'Defective', '2025-11-19 00:12:17', '2025-11-19 00:12:33', NULL),
(74, 11, '1290', 'BLUE', 0.00, 'Sold', '2025-11-19 00:12:49', '2025-11-19 00:13:23', NULL),
(75, 11, '1291', 'BLUE', 0.00, 'Sold', '2025-11-19 00:13:41', '2025-11-19 00:13:58', NULL),
(76, 11, '4142', 'blue', 0.00, 'Sold', '2025-11-19 01:17:04', '2025-11-19 01:17:46', NULL),
(77, 11, '5655', 'blue', 0.00, 'Sold', '2025-11-19 01:18:44', '2025-11-19 01:19:03', NULL),
(78, 11, '1231', 'BLUE', 0.00, 'Defective', '2025-11-19 01:39:58', '2025-11-19 01:40:13', NULL),
(79, 11, '0099', 'BLUE', 0.00, 'Sold', '2025-11-26 00:21:44', '2025-11-26 00:22:06', NULL),
(80, 11, '0098', 'VIOLET', 0.00, 'Sold', '2025-11-26 01:30:18', '2025-11-26 01:30:41', NULL),
(81, 11, '0097', 'BLUE', 0.00, 'Sold', '2025-11-26 01:32:30', '2025-11-26 01:32:48', NULL),
(82, 11, '8980', 'BLUE', 0.00, 'Sold', '2025-11-26 02:51:29', '2025-11-26 02:53:14', NULL),
(83, 11, '0009', 'BLUE', 0.00, 'Available', '2025-11-26 03:10:34', '2025-11-26 03:10:34', NULL),
(84, 11, '0008', 'BLUE', 0.00, 'Available', '2025-11-26 21:41:32', '2025-11-26 21:41:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(255) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `items` text NOT NULL,
  `purchase_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cashier_id` int(11) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `total_profit` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `receipt_number`, `total_amount`, `items`, `purchase_date`, `created_at`, `cashier_id`, `cost_price`, `total_profit`) VALUES
(4, 'RCP20251110083139438', 20000.00, '', '2025-11-10 15:31:39', '2025-11-10 07:31:39', 0, NULL, 0.00),
(5, 'RCP20251110084211668', 50000.00, '', '2025-11-10 15:42:11', '2025-11-10 07:42:11', 0, NULL, 0.00),
(6, 'RCP20251110084622319', 20000.00, '', '2025-11-10 15:46:22', '2025-11-10 07:46:22', 0, NULL, 0.00),
(7, 'RCP20251110091321540', 20000.00, '', '2025-11-10 16:13:21', '2025-11-10 08:13:21', 0, NULL, 0.00),
(8, 'RCP20251110143336787', 500.00, '', '2025-11-10 21:33:36', '2025-11-10 13:33:36', 0, NULL, 0.00),
(9, 'RCP20251111024927324', 20000.00, '', '2025-11-11 09:49:27', '2025-11-11 01:49:27', 0, NULL, 0.00),
(10, 'RCP20251116103527444', 20000.00, '', '2025-11-16 17:35:27', '2025-11-16 09:35:27', 0, NULL, 0.00),
(11, 'RCP20251116130458968', 20000.00, '', '2025-11-16 20:04:58', '2025-11-16 12:04:58', 0, NULL, 0.00),
(12, 'RCP20251116142409553', 11111.00, '[{\"id\":8,\"name\":\"egg\",\"price\":11111,\"sale_price\":11111,\"quantity\":1,\"serial_numbers\":[\"1132\"]}]', NULL, '2025-11-16 13:24:09', 1, NULL, 0.00),
(13, 'RCP20251117002141238', 11111.00, '[{\"id\":8,\"name\":\"egg\",\"price\":11111,\"sale_price\":11111,\"quantity\":1,\"serial_numbers\":[\"1313\"]}]', NULL, '2025-11-16 23:21:41', 1, NULL, 0.00),
(17, 'RCP20251117003558721', 11111.00, '', NULL, '2025-11-16 23:35:58', 1, NULL, 0.00),
(18, 'RCP20251117003939674', 2222200.00, '', NULL, '2025-11-16 23:39:39', 1, NULL, 2199978.00),
(19, 'RCP20251117011151960', 11115.00, '', NULL, '2025-11-17 00:11:51', 2, NULL, 0.00),
(20, 'RCP20251117012844773', 1000.00, '', NULL, '2025-11-17 00:28:44', 2, NULL, 500.00),
(21, 'RCP20251117015527528', 1000.00, '', NULL, '2025-11-17 00:55:27', 1, NULL, 500.00),
(22, 'RCP20251117054837289', 100050.00, '', NULL, '2025-11-17 04:48:37', 2, NULL, 50.00),
(23, 'RCP20251117071803885', 100000.00, '', NULL, '2025-11-17 06:18:03', 2, NULL, 0.00),
(24, 'RCP20251117071935783', 100000.00, '', NULL, '2025-11-17 06:19:35', 2, NULL, 0.00),
(25, 'RCP20251117072126555', 100000.00, '', NULL, '2025-11-17 06:21:26', 1, NULL, 0.00),
(26, 'RCP20251118035330414', 50000.00, '', NULL, '2025-11-18 02:53:30', 1, NULL, 0.00),
(27, 'RCP20251118035539488', 10.00, '', NULL, '2025-11-18 02:55:39', 1, NULL, 0.00),
(28, 'RCP20251118061428615', 100005.00, '', NULL, '2025-11-18 05:14:28', 1, NULL, 5.00),
(29, 'RCP20251118063350211', 100050.00, '', NULL, '2025-11-18 05:33:50', 1, NULL, 50.00),
(30, 'RCP20251118074150646', 100000.00, '', NULL, '2025-11-18 06:41:50', 1, NULL, 0.00),
(31, 'RCP20251118074521742', 100500.00, '', NULL, '2025-11-18 06:45:21', 2, NULL, 500.00),
(32, 'RCP20251118090206285', 100000.00, '', NULL, '2025-11-18 08:02:06', 1, NULL, 0.00),
(33, 'RCP20251119011323121', 10500.00, '', NULL, '2025-11-19 00:13:23', 1, NULL, 500.00),
(34, 'RCP20251119011358533', 10000.00, '', NULL, '2025-11-19 00:13:58', 1, NULL, 0.00),
(35, 'RCP20251119021746171', 10600.00, '', NULL, '2025-11-19 01:17:46', 1, NULL, 600.00),
(36, 'RCP20251119021903515', 10000.00, '', NULL, '2025-11-19 01:19:03', 1, NULL, 0.00),
(37, 'RCP20251119024052426', 10000.00, '', NULL, '2025-11-19 01:40:52', 1, NULL, 0.00),
(38, 'RCP20251125155613281', 10020.00, '', NULL, '2025-11-25 14:56:13', 1, NULL, 20.00),
(39, 'RCP20251126011221100', 10500.00, '', NULL, '2025-11-26 00:12:21', 1, NULL, 500.00),
(40, 'RCP20251126012206622', 10000.00, '', NULL, '2025-11-26 00:22:06', 1, NULL, 0.00),
(41, 'RCP20251126023041925', 10500.00, '', NULL, '2025-11-26 01:30:41', 1, NULL, 500.00),
(42, 'RCP20251126023248987', 10000.00, '', NULL, '2025-11-26 01:32:48', 1, NULL, 0.00),
(45, 'RCP20251126035314725', 10050.00, '', NULL, '2025-11-26 02:53:14', 1, NULL, 500.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `serial_numbers` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cost_price` decimal(10,2) DEFAULT NULL,
  `profit` decimal(10,2) DEFAULT 0.00,
  `base_price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `product_name`, `quantity`, `sale_price`, `serial_numbers`, `created_at`, `cost_price`, `profit`, `base_price`) VALUES
(1, 4, 6, '33', 1, 20000.00, '1111', '2025-11-10 07:31:39', NULL, 0.00, 20000.00),
(2, 5, 3, 'nicco', 1, 50000.00, '7777', '2025-11-10 07:42:11', NULL, 0.00, 50000.00),
(3, 6, 6, '33', 1, 20000.00, '9999', '2025-11-10 07:46:22', NULL, 0.00, 20000.00),
(4, 7, 6, '33', 1, 20000.00, '4444', '2025-11-10 08:13:21', NULL, 0.00, 20000.00),
(5, 8, 4, 'SASA BOY', 1, 500.00, '1212', '2025-11-10 13:33:36', NULL, 0.00, 500.00),
(6, 9, 7, 'YY', 1, 20000.00, '8888', '2025-11-11 01:49:27', NULL, 0.00, 20000.00),
(7, 10, 7, 'YY', 1, 20000.00, '1211', '2025-11-16 09:35:27', NULL, 0.00, 20000.00),
(8, 11, 7, 'YY', 1, 20000.00, '0909', '2025-11-16 12:04:58', NULL, 0.00, 20000.00),
(9, 17, 8, NULL, 1, 11111.00, NULL, '2025-11-16 23:35:58', NULL, 0.00, 11111.00),
(10, 18, 8, NULL, 2, 1111100.00, NULL, '2025-11-16 23:39:39', NULL, 2199978.00, 1111100.00),
(11, 19, 8, NULL, 1, 11115.00, NULL, '2025-11-17 00:11:51', NULL, 0.00, 11111.00),
(12, 20, 4, NULL, 1, 1000.00, NULL, '2025-11-17 00:28:44', 500.00, 500.00, 0.00),
(13, 21, 4, NULL, 1, 1000.00, NULL, '2025-11-17 00:55:27', 500.00, 500.00, 0.00),
(14, 22, 9, NULL, 1, 100050.00, NULL, '2025-11-17 04:48:37', 100000.00, 50.00, 0.00),
(15, 23, 9, NULL, 1, 100000.00, NULL, '2025-11-17 06:18:03', 100000.00, 0.00, 0.00),
(16, 24, 9, NULL, 1, 100000.00, NULL, '2025-11-17 06:19:35', 100000.00, 0.00, 0.00),
(17, 25, 9, NULL, 1, 100000.00, NULL, '2025-11-17 06:21:26', 100000.00, 0.00, 0.00),
(18, 26, 10, NULL, 1, 50000.00, NULL, '2025-11-18 02:53:30', 50000.00, 0.00, 0.00),
(19, 27, 11, NULL, 1, 10.00, NULL, '2025-11-18 02:55:39', 10.00, 0.00, 0.00),
(20, 28, 9, NULL, 1, 100005.00, NULL, '2025-11-18 05:14:28', 100000.00, 5.00, 0.00),
(21, 29, 9, NULL, 1, 100050.00, NULL, '2025-11-18 05:33:50', 100000.00, 50.00, 0.00),
(22, 30, 9, NULL, 1, 100000.00, NULL, '2025-11-18 06:41:50', 100000.00, 0.00, 0.00),
(23, 31, 9, NULL, 1, 100500.00, NULL, '2025-11-18 06:45:21', 100000.00, 500.00, 0.00),
(24, 32, 9, NULL, 1, 100000.00, NULL, '2025-11-18 08:02:06', 100000.00, 0.00, 0.00),
(25, 33, 11, NULL, 1, 10500.00, NULL, '2025-11-19 00:13:23', 10000.00, 500.00, 0.00),
(26, 34, 11, NULL, 1, 10000.00, NULL, '2025-11-19 00:13:58', 10000.00, 0.00, 0.00),
(27, 35, 11, NULL, 1, 10600.00, NULL, '2025-11-19 01:17:46', 10000.00, 600.00, 0.00),
(28, 36, 11, NULL, 1, 10000.00, NULL, '2025-11-19 01:19:03', 10000.00, 0.00, 0.00),
(29, 37, 11, NULL, 1, 10000.00, NULL, '2025-11-19 01:40:52', 10000.00, 0.00, 0.00),
(30, 38, 11, NULL, 1, 10020.00, NULL, '2025-11-25 14:56:13', 10000.00, 20.00, 0.00),
(31, 39, 11, NULL, 1, 10500.00, NULL, '2025-11-26 00:12:21', 10000.00, 500.00, 0.00),
(32, 40, 11, NULL, 1, 10000.00, NULL, '2025-11-26 00:22:06', 10000.00, 0.00, 0.00),
(33, 41, 11, NULL, 1, 10500.00, NULL, '2025-11-26 01:30:41', 10000.00, 500.00, 0.00),
(34, 42, 11, NULL, 1, 10000.00, NULL, '2025-11-26 01:32:48', 10000.00, 0.00, 0.00),
(35, 45, 11, NULL, 1, 10050.00, NULL, '2025-11-26 02:53:14', 9550.00, 500.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `cost_price_at_sale` decimal(10,2) DEFAULT 0.00,
  `profit` decimal(10,2) NOT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  `receipt_number` varchar(100) DEFAULT NULL,
  `serial_numbers` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cost_price` decimal(10,2) DEFAULT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT 0.00,
  `base_price` decimal(10,2) DEFAULT 0.00,
  `product_stock_id` int(11) DEFAULT NULL,
  `original_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `product_id`, `quantity`, `total_amount`, `cost_price_at_sale`, `profit`, `sale_date`, `receipt_number`, `serial_numbers`, `created_at`, `cost_price`, `purchase_id`, `sale_price`, `base_price`, `product_stock_id`, `original_price`) VALUES
(1, 1, 2, 50000.00, 0.00, 10000.00, '2025-11-06 15:08:14', 'REC-001', '[\"SN123\", \"SN124\"]', '2025-11-06 07:08:14', NULL, NULL, 50000.00, 50000.00, NULL, NULL),
(2, 2, 1, 25000.00, 0.00, 5000.00, '2025-11-06 15:08:14', 'REC-002', '[\"SN125\"]', '2025-11-06 07:08:14', NULL, NULL, 25000.00, 25000.00, NULL, NULL),
(3, 3, 3, 45000.00, 0.00, 9000.00, '2025-11-06 15:08:14', 'REC-003', '[\"SN126\", \"SN127\", \"SN128\"]', '2025-11-06 07:08:14', NULL, NULL, 45000.00, 45000.00, NULL, NULL),
(8, 3, 1, 50000.00, 0.00, 15000.00, '2025-11-10 15:42:11', 'RCP20251110084211668', '7777', '2025-11-10 07:42:11', NULL, NULL, 50000.00, 50000.00, NULL, NULL),
(11, 4, 1, 500.00, 0.00, 150.00, '2025-11-10 21:33:36', 'RCP20251110143336787', '1212', '2025-11-10 13:33:36', NULL, NULL, 500.00, 500.00, NULL, NULL),
(12, 7, 1, 20000.00, 0.00, 6000.00, '2025-11-11 09:49:27', 'RCP20251111024927324', '8888', '2025-11-11 01:49:27', NULL, NULL, 20000.00, 20000.00, NULL, NULL),
(13, 7, 1, 20000.00, 0.00, 20000.00, '2025-11-16 17:35:27', 'RCP20251116103527444', '1211', '2025-11-16 09:35:27', NULL, NULL, 20000.00, 20000.00, NULL, NULL),
(14, 7, 1, 20000.00, 0.00, 20000.00, '2025-11-16 20:04:58', 'RCP20251116130458968', '0909', '2025-11-16 12:04:58', NULL, NULL, 20000.00, 20000.00, NULL, NULL),
(15, 8, 1, 11111.00, 0.00, 11111.00, '2025-11-17 07:35:58', NULL, NULL, '2025-11-16 23:35:58', NULL, 17, 11111.00, 11111.00, NULL, NULL),
(16, 8, 1, 1111100.00, 0.00, 2199978.00, '2025-11-17 07:39:39', NULL, NULL, '2025-11-16 23:39:39', NULL, 18, 1111100.00, 1111100.00, NULL, NULL),
(17, 8, 1, 1111100.00, 0.00, 2199978.00, '2025-11-17 07:39:39', NULL, NULL, '2025-11-16 23:39:39', NULL, 18, 1111100.00, 1111100.00, NULL, NULL),
(18, 8, 1, 11115.00, 0.00, 11115.00, '2025-11-17 08:11:51', NULL, NULL, '2025-11-17 00:11:51', NULL, 19, 11115.00, 11111.00, NULL, NULL),
(19, 4, 1, 1000.00, 0.00, 500.00, '2025-11-17 08:28:44', NULL, NULL, '2025-11-17 00:28:44', 500.00, 20, 1000.00, 0.00, NULL, NULL),
(20, 4, 1, 1000.00, 0.00, 500.00, '2025-11-17 08:55:27', NULL, NULL, '2025-11-17 00:55:27', 500.00, 21, 1000.00, 0.00, NULL, NULL),
(21, 9, 1, 100050.00, 0.00, 50.00, '2025-11-17 12:48:37', NULL, NULL, '2025-11-17 04:48:37', 100000.00, 22, 100050.00, 0.00, NULL, NULL),
(22, 9, 1, 100000.00, 0.00, 100000.00, '2025-11-17 14:18:03', NULL, NULL, '2025-11-17 06:18:03', 100000.00, 23, 100000.00, 0.00, NULL, NULL),
(23, 9, 1, 100000.00, 0.00, 100000.00, '2025-11-17 14:19:35', NULL, NULL, '2025-11-17 06:19:35', 100000.00, 24, 100000.00, 0.00, NULL, NULL),
(24, 9, 1, 100000.00, 0.00, 100000.00, '2025-11-17 14:21:26', NULL, NULL, '2025-11-17 06:21:26', 100000.00, 25, 100000.00, 0.00, NULL, NULL),
(25, 10, 1, 50000.00, 0.00, 50000.00, '2025-11-18 10:53:30', NULL, NULL, '2025-11-18 02:53:30', 50000.00, 26, 50000.00, 0.00, NULL, NULL),
(26, 11, 1, 10.00, 0.00, 10.00, '2025-11-18 10:55:39', NULL, NULL, '2025-11-18 02:55:39', 10.00, 27, 10.00, 0.00, NULL, NULL),
(27, 9, 1, 100005.00, 0.00, 5.00, '2025-11-18 13:14:28', NULL, NULL, '2025-11-18 05:14:28', 100000.00, 28, 100005.00, 0.00, NULL, NULL),
(28, 9, 1, 100050.00, 0.00, 50.00, '2025-11-18 13:33:50', NULL, NULL, '2025-11-18 05:33:50', 100000.00, 29, 100050.00, 0.00, NULL, NULL),
(29, 9, 1, 100000.00, 0.00, 100000.00, '2025-11-18 14:41:50', NULL, NULL, '2025-11-18 06:41:50', 100000.00, 30, 100000.00, 0.00, NULL, NULL),
(30, 9, 1, 100500.00, 0.00, 500.00, '2025-11-18 14:45:21', NULL, NULL, '2025-11-18 06:45:21', 100000.00, 31, 100500.00, 0.00, NULL, NULL),
(31, 9, 1, 100000.00, 0.00, 100000.00, '2025-11-18 16:02:06', NULL, NULL, '2025-11-18 08:02:06', 100000.00, 32, 100000.00, 0.00, NULL, NULL),
(32, 11, 1, 10500.00, 0.00, 500.00, '2025-11-19 08:13:23', NULL, NULL, '2025-11-19 00:13:23', 10000.00, 33, 10500.00, 0.00, NULL, NULL),
(33, 11, 1, 10000.00, 0.00, 10000.00, '2025-11-19 08:13:58', NULL, NULL, '2025-11-19 00:13:58', 10000.00, 34, 10000.00, 0.00, NULL, NULL),
(34, 11, 1, 10600.00, 0.00, 600.00, '2025-11-19 09:17:46', NULL, NULL, '2025-11-19 01:17:46', 10000.00, 35, 10600.00, 0.00, NULL, NULL),
(35, 11, 1, 10000.00, 0.00, 10000.00, '2025-11-19 09:19:03', NULL, NULL, '2025-11-19 01:19:03', 10000.00, 36, 10000.00, 0.00, NULL, NULL),
(36, 11, 1, 10000.00, 0.00, 10000.00, '2025-11-19 09:40:52', NULL, NULL, '2025-11-19 01:40:52', 10000.00, 37, 10000.00, 0.00, NULL, NULL),
(37, 11, 1, 10020.00, 0.00, 20.00, '2025-11-25 22:56:13', NULL, NULL, '2025-11-25 14:56:13', 10000.00, 38, 10020.00, 0.00, NULL, NULL),
(38, 11, 1, 10500.00, 0.00, 500.00, '2025-11-26 08:12:21', NULL, NULL, '2025-11-26 00:12:21', 10000.00, 39, 10500.00, 0.00, NULL, NULL),
(39, 11, 1, 10000.00, 0.00, 10000.00, '2025-11-26 08:22:06', NULL, NULL, '2025-11-26 00:22:06', 10000.00, 40, 10000.00, 0.00, NULL, NULL),
(40, 11, 1, 10500.00, 0.00, 500.00, '2025-11-26 09:30:41', NULL, NULL, '2025-11-26 01:30:41', 10000.00, 41, 10500.00, 0.00, NULL, NULL),
(41, 11, 1, 10000.00, 0.00, 0.00, '2025-11-26 09:32:48', NULL, NULL, '2025-11-26 01:32:48', 10000.00, 42, 10000.00, 0.00, NULL, NULL),
(42, 11, 1, 10050.00, 0.00, 500.00, '2025-11-26 10:53:14', NULL, NULL, '2025-11-26 02:53:14', 9550.00, 45, 10050.00, 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_history`
--

CREATE TABLE `stock_history` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `stock_id` int(11) DEFAULT NULL,
  `change_type` varchar(50) DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_date` datetime DEFAULT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quantity_change` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_history`
--

INSERT INTO `stock_history` (`id`, `product_id`, `stock_id`, `change_type`, `previous_status`, `new_status`, `changed_by`, `change_date`, `notes`, `created_at`, `quantity_change`) VALUES
(2, 3, 39, 'added', NULL, 'Available', 1, '2025-11-10 15:41:54', '', '2025-11-10 07:41:54', 1),
(5, 4, 42, 'added', NULL, 'Available', 1, '2025-11-10 21:33:22', '', '2025-11-10 13:33:22', 1),
(6, 7, 43, 'added', NULL, 'Available', 1, '2025-11-11 09:49:13', '', '2025-11-11 01:49:13', 1),
(7, 7, 44, 'added', NULL, 'Available', 1, '2025-11-16 16:04:44', '', '2025-11-16 08:04:44', 1),
(8, 7, 45, 'added', NULL, 'Available', 1, '2025-11-16 17:34:49', '', '2025-11-16 09:34:49', 1),
(9, 7, 45, 'sold', 'Available', 'Sold', 1, NULL, '', '2025-11-16 09:35:27', 0),
(10, 7, 46, 'added', NULL, 'Available', 1, '2025-11-16 19:49:42', '', '2025-11-16 11:49:42', 1),
(11, 7, 46, 'defective', 'Available', 'Defective', 1, NULL, 'Defective', '2025-11-16 12:00:12', -1),
(12, 7, 47, 'added', NULL, 'Available', 1, '2025-11-16 20:02:40', '', '2025-11-16 12:02:40', 1),
(13, 7, 47, 'sold', 'Available', 'Sold', 1, NULL, '', '2025-11-16 12:04:58', 0),
(14, 8, 48, 'added', NULL, 'Available', 1, '2025-11-16 21:05:03', '', '2025-11-16 13:05:03', 1),
(15, 8, 49, 'added', NULL, 'Available', 1, '2025-11-16 21:07:58', '', '2025-11-16 13:07:58', 1),
(16, 8, 49, 'defective', 'Available', 'Defective', 1, NULL, '', '2025-11-16 13:08:26', -1),
(17, 8, 48, 'sold', 'Available', 'Sold', 1, NULL, '', '2025-11-16 13:24:09', -1),
(18, 8, 50, 'added', NULL, 'Available', 1, '2025-11-17 06:31:05', '', '2025-11-16 22:31:05', 1),
(19, 8, 50, 'sold', 'Available', 'Sold', 1, NULL, '', '2025-11-16 23:21:41', -1),
(20, 8, 51, 'added', NULL, 'Available', 1, '2025-11-17 07:34:08', '', '2025-11-16 23:34:08', 1),
(21, 8, 52, 'added', NULL, 'Available', 1, '2025-11-17 07:37:55', '', '2025-11-16 23:37:55', 1),
(22, 8, 53, 'added', NULL, 'Available', 1, '2025-11-17 07:38:22', '', '2025-11-16 23:38:22', 1),
(23, 8, 54, 'added', NULL, 'Available', 1, '2025-11-17 08:10:36', '', '2025-11-17 00:10:36', 1),
(24, 4, 55, 'added', NULL, 'Available', 1, '2025-11-17 08:27:46', '', '2025-11-17 00:27:46', 1),
(25, 4, 56, 'added', NULL, 'Available', 1, '2025-11-17 08:27:46', '', '2025-11-17 00:27:46', 1),
(26, 9, 57, 'added', NULL, 'Available', 1, '2025-11-17 12:48:00', '', '2025-11-17 04:48:00', 1),
(27, 9, 58, 'added', NULL, 'Available', 1, '2025-11-17 14:17:36', '', '2025-11-17 06:17:36', 1),
(28, 9, 59, 'added', NULL, 'Available', 1, '2025-11-17 14:18:55', '', '2025-11-17 06:18:55', 1),
(29, 9, 60, 'added', NULL, 'Available', 1, '2025-11-17 14:21:14', '', '2025-11-17 06:21:14', 1),
(30, 9, 61, 'added', NULL, 'Available', 1, '2025-11-17 22:54:35', '', '2025-11-17 14:54:35', 1),
(31, 9, 62, 'added', NULL, 'Available', 1, '2025-11-18 09:23:06', '', '2025-11-18 01:23:06', 1),
(32, 9, 63, 'added', NULL, 'Available', 1, '2025-11-18 09:23:06', '', '2025-11-18 01:23:06', 1),
(33, 9, 64, 'added', NULL, 'Available', 1, '2025-11-18 09:23:06', '', '2025-11-18 01:23:06', 1),
(34, 9, 65, 'added', NULL, 'Available', 1, '2025-11-18 09:23:06', '', '2025-11-18 01:23:06', 1),
(35, 9, 66, 'added', NULL, 'Available', 1, '2025-11-18 09:23:06', '', '2025-11-18 01:23:06', 1),
(36, 9, 67, 'added', NULL, 'Available', 1, '2025-11-18 09:23:06', '', '2025-11-18 01:23:06', 1),
(37, 10, 68, 'added', NULL, 'Available', 1, '2025-11-18 10:53:14', '', '2025-11-18 02:53:14', 1),
(38, 11, 69, 'added', NULL, 'Available', 1, '2025-11-18 10:55:26', '', '2025-11-18 02:55:26', 1),
(39, 9, 61, 'defective', 'Available', 'Defective', 1, NULL, '', '2025-11-18 05:13:56', -1),
(40, 11, 70, 'added', NULL, 'Available', 1, '2025-11-18 13:31:20', '', '2025-11-18 05:31:20', 1),
(41, 11, 71, 'added', NULL, 'Available', 1, '2025-11-18 16:00:13', '', '2025-11-18 08:00:13', 1),
(42, 9, 66, 'defective', 'Available', 'Defective', 1, NULL, '', '2025-11-18 08:01:30', -1),
(43, 11, 72, 'added', NULL, 'Available', 1, '2025-11-18 16:03:42', '', '2025-11-18 08:03:42', 1),
(44, 11, 73, 'added', NULL, 'Available', 1, '2025-11-19 08:12:17', '', '2025-11-19 00:12:17', 1),
(45, 11, 73, 'defective', 'Available', 'Defective', 1, NULL, '', '2025-11-19 00:12:33', -1),
(46, 11, 74, 'added', NULL, 'Available', 1, '2025-11-19 08:12:49', '', '2025-11-19 00:12:49', 1),
(47, 11, 75, 'added', NULL, 'Available', 1, '2025-11-19 08:13:41', '', '2025-11-19 00:13:41', 1),
(48, 11, 76, 'added', NULL, 'Available', 1, '2025-11-19 09:17:04', '', '2025-11-19 01:17:04', 1),
(49, 11, 77, 'added', NULL, 'Available', 1, '2025-11-19 09:18:44', '', '2025-11-19 01:18:44', 1),
(50, 11, 78, 'added', NULL, 'Available', 1, '2025-11-19 09:39:58', '', '2025-11-19 01:39:58', 1),
(51, 11, 78, 'defective', 'Available', 'Defective', 1, NULL, '', '2025-11-19 01:40:13', -1),
(52, 11, 79, 'added', NULL, 'Available', 1, '2025-11-26 08:21:44', '', '2025-11-26 00:21:44', 1),
(53, 11, 80, 'added', NULL, 'Available', 1, '2025-11-26 09:30:18', '', '2025-11-26 01:30:18', 1),
(54, 11, 81, 'added', NULL, 'Available', 1, '2025-11-26 09:32:30', '', '2025-11-26 01:32:30', 1),
(55, 11, 82, 'added', NULL, 'Available', 1, '2025-11-26 10:51:29', '', '2025-11-26 02:51:29', 1),
(56, 11, 83, 'added', NULL, 'Available', 1, '2025-11-26 11:10:34', '', '2025-11-26 03:10:34', 1),
(60, 11, 84, 'added', NULL, 'Available', 1, '2025-11-27 05:41:32', '', '2025-11-26 21:41:32', 1);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_items`
--

CREATE TABLE `transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `sale_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','employee','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `email`, `role`, `created_at`, `last_login`, `is_active`) VALUES
(1, 'admin', '1111', '', 'admin@jhaygadget.com', 'admin', '2025-11-04 04:49:25', '2025-11-26 13:43:38', 1),
(2, 'yu', '$2y$10$qFbFfJPmbc/QpWE.HPqiTe2x0XxFymHgc5IA/Z5Sv3wBqOOTN3OTO', '', NULL, 'employee', '2025-11-16 20:11:28', '2025-11-20 02:05:03', 1),
(3, 'BAYUT', '$2y$10$35hz.QWMOWXksp3JGKHmaO9G0BYCM9B/fJJlmbjuW/Zw2K1/bBQ42', '', NULL, 'user', '2025-11-17 02:15:54', NULL, 1),
(4, 'OP', '$2y$10$M9DWOHIyOxPfjE7CJN6vvubKZ314KoGJPFgnuDKKVvshbhvTX11ma', '', NULL, 'user', '2025-11-17 07:23:59', '2025-11-17 07:24:14', 1),
(5, 'TEK', '$2y$10$ud9eGk3hvb17phY6uAdQke/2F.OuvJGWpxOBvgUkXtFEdpfFbnEpK', '', NULL, 'user', '2025-11-17 14:51:05', '2025-11-17 14:51:16', 1),
(6, 'BABYLYN', '$2y$10$rsfa05DKedmB8v4ff10ASeYQipft8Ldqna2TKx96lAs4sVD0Nhs1i', '', NULL, 'user', '2025-11-18 06:35:21', '2025-11-18 06:35:47', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chatbot_messages`
--
ALTER TABLE `chatbot_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_stock`
--
ALTER TABLE `product_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `idx_product_stock_product_id` (`product_id`),
  ADD KEY `idx_product_stock_serial` (`serial_number`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_stock_id` (`product_stock_id`);

--
-- Indexes for table `stock_history`
--
ALTER TABLE `stock_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `product_id` (`product_id`);

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
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `chatbot_messages`
--
ALTER TABLE `chatbot_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `product_stock`
--
ALTER TABLE `product_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `stock_history`
--
ALTER TABLE `stock_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_stock`
--
ALTER TABLE `product_stock`
  ADD CONSTRAINT `product_stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`product_stock_id`) REFERENCES `product_stock` (`id`);

--
-- Constraints for table `stock_history`
--
ALTER TABLE `stock_history`
  ADD CONSTRAINT `stock_history_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD CONSTRAINT `transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
