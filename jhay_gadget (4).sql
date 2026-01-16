-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 26, 2025 at 02:55 AM
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
-- Table structure for table `chatbot_messages`
--

CREATE TABLE `chatbot_messages` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('complaint','feedback','question','general') DEFAULT 'general',
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chatbot_messages`
--

INSERT INTO `chatbot_messages` (`id`, `message`, `type`, `email`, `created_at`) VALUES
(4, 'I love the quality of your products. Keep up the good work!', 'feedback', 'loyalcustomer@hotmail.com', '2024-01-12 08:45:00'),
(5, 'The item I received has scratches on the screen.', 'complaint', 'disappointed@email.com', '2024-01-11 03:30:00'),
(6, 'Do you have this product in different colors?', 'complaint', 'shopper@gmail.com', '2024-01-10 05:20:00'),
(7, 'Just wanted to say your customer service team is amazing!', 'feedback', 'satisfied@email.com', '2024-01-09 00:45:00'),
(8, 'fvccbc', 'complaint', 'niccoho1234@gmail.com', '2025-10-22 14:15:22'),
(9, 'KJKKKK', 'complaint', 'niccoho1234@gmail.com', '2025-10-22 14:28:07'),
(10, 'KJKKKK', 'complaint', 'niccoho1234@gmail.com', '2025-10-22 14:34:40'),
(11, 'hotdog', 'complaint', 'gemsu74@gmail.com', '2025-10-23 02:23:54'),
(12, 'goods', 'feedback', 'niccoho1234@gmail.com', '2025-10-23 02:24:16');

-- --------------------------------------------------------

--
-- Table structure for table `installments`
--

CREATE TABLE `installments` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) NOT NULL,
  `remaining_amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Active','Completed','Overdue') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `installments`
--

INSERT INTO `installments` (`id`, `customer_name`, `product_name`, `total_amount`, `paid_amount`, `remaining_amount`, `due_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 'nicco', 'tae', 100.00, 60.00, 40.00, '2025-12-25', 'Active', '2025-10-02 01:28:01', '2025-10-02 01:42:34'),
(3, 'jistine', 'tae', 100.00, 90.00, 10.00, '2025-12-10', 'Active', '2025-10-02 01:41:49', '2025-10-10 02:21:56'),
(4, 'HOT', 'PP', 200.00, 165.00, 35.00, '2025-11-05', 'Active', '2025-10-02 13:42:13', '2025-10-10 02:21:10'),
(7, 'W', 'W', 10000.00, 8000.00, 2000.00, '2025-10-10', 'Active', '2025-10-10 04:58:12', '2025-10-22 14:23:13'),
(8, 'IINT', 'RED', 5000.00, 4500.00, 500.00, '2025-10-18', 'Active', '2025-10-10 23:50:24', '2025-10-10 23:51:22'),
(9, 'OP', 'PP', 10000.00, 9500.00, 500.00, '2025-10-22', 'Active', '2025-10-14 06:56:11', '2025-10-14 06:56:56');

-- --------------------------------------------------------

--
-- Table structure for table `install_transactions`
--

CREATE TABLE `install_transactions` (
  `id` int(11) NOT NULL,
  `installment_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `payment_amount` decimal(10,0) NOT NULL,
  `previous_balance` decimal(10,0) NOT NULL,
  `new_balance` decimal(10,0) NOT NULL,
  `payment_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `install_transactions`
--

INSERT INTO `install_transactions` (`id`, `installment_id`, `customer_name`, `product_name`, `payment_amount`, `previous_balance`, `new_balance`, `payment_date`) VALUES
(1, 4, 'HOT', 'PP', 5, 40, 35, '2025-10-10 10:21:10'),
(2, 3, 'jistine', 'tae', 10, 25, 15, '2025-10-10 10:21:28'),
(3, 3, 'jistine', 'tae', 5, 15, 10, '2025-10-10 10:21:56'),
(4, 6, 'power', 'iphone', 4000, 5000, 1000, '2025-10-10 12:45:04'),
(5, 6, 'power', 'iphone', 500, 1000, 500, '2025-10-10 12:55:18'),
(6, 7, 'W', 'W', 5000, 9500, 4500, '2025-10-10 13:16:29'),
(7, 7, 'W', 'W', 500, 4500, 4000, '2025-10-10 14:43:52'),
(8, 7, 'W', 'W', 1000, 4000, 3000, '2025-10-10 14:44:17'),
(9, 7, 'W', 'W', 500, 3000, 2500, '2025-10-10 14:44:53'),
(10, 6, 'power', 'iphone', 100, 500, 400, '2025-10-11 07:50:48'),
(11, 8, 'IINT', 'RED', 1000, 4000, 3000, '2025-10-11 07:51:04'),
(12, 8, 'IINT', 'RED', 2500, 3000, 500, '2025-10-11 07:51:22'),
(13, 9, 'OP', 'PP', 9000, 9500, 500, '2025-10-14 14:56:56'),
(14, 7, 'W', 'W', 500, 2500, 2000, '2025-10-22 22:23:13');

-- --------------------------------------------------------

--
-- Table structure for table `login`
--

CREATE TABLE `login` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login`
--

INSERT INTO `login` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'ADMIN', '1234', 'admin', '0000-00-00 00:00:00'),
(2, 'user', '1234', 'user', '0000-00-00 00:00:00'),
(3, 'gg', '$2y$10$Ou.w4ivO3HatfLVtiI1ccuEhXXB7pzca9bdrkdB0lLCzeTjr8UIje', 'user', '2025-10-19 08:14:03'),
(4, 'Goods', '$2y$10$BywwbBRyC14wZI1uvaZSzewVwsYI6IlvTwEemZ.P2LbVIKid9NF/W', 'user', '2025-10-22 05:01:00');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `status` enum('New','Pending','Available','Discontinued') NOT NULL,
  `link` varchar(255) NOT NULL,
  `items` text NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `stock` int(11) NOT NULL,
  `category` varchar(255) NOT NULL,
  `photo` varchar(255) NOT NULL,
  `price` decimal(10,3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `name`, `logo`, `date`, `status`, `link`, `items`, `supplier`, `stock`, `category`, `photo`, `price`) VALUES
(1, '3272836', 'MacBook Air M4', '', '2025-09-29', 'New', '', '13-inch MacBook Air: Apple M4 chip with 10-core CPU and 8-core GPU (2025)', 'phili', 3, 'laptop', '68e2735f40b67.webp', 64990.000),
(3, '3272836', 'AirPods Pro', '', '2025-09-29', 'New', '', '4 to 5 hours Battery Life', 'phili', 6, 'tech accessories', '68e2752795c29.webp', 12890.000),
(4, '3272836', 'A50', '', '2025-09-29', 'New', '', '64ram, 5T storage', 'phili', 4, 'tech accessories', '68e27812a93bd.jpg', 4000.000),
(5, '3272836', 'Iphone 13', '', '2025-09-29', 'Available', '', '8GB/256GB/512GB storage, no card slot Ram 4GB', 'phili', 2, 'phone', '68e27486ce8d9.webp', 27990.000),
(15, '3942848', 'POP', '', '2025-10-06', 'New', '', 'ANO', 'ANO', 12, 'tech accessories', '', 1000.000),
(71, 'h', 'fGHtMGG', '', '2025-10-17', 'New', '', 'fd', 'fd', 15, 'phone', '68f49650e5070.jpeg', 22.000),
(77, 'MK', 'SA', '', '2025-10-25', 'New', '', 'A', 'S', 23, 'tech accessories', '68f85c199906c.jpeg', 12.000),
(78, 'RR', 'RR', '', '2025-10-31', 'New', '', 'RR', 'RRR', 30, 'tech accessories', '68f85c4251a94.jpeg', 33.000),
(79, 'BB', 'IPHONE', '', '2025-10-22', 'New', '', 'NN', 'NN', 15, 'phone', '68f8e84ddca5d.jpeg', 20.000);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `selling_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `other_fees` decimal(10,2) NOT NULL,
  `revenue` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `product_id`, `qty`, `sale_date`, `selling_price`, `cost_price`, `other_fees`, `revenue`, `profit`, `price`, `total`, `created_at`) VALUES
(1, 1, 1, '2025-09-29 02:25:29', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2000.00, '2025-09-29 08:25:29'),
(2, 5, 1, '2025-10-02 07:41:00', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-02 13:41:00'),
(3, 5, 1, '2025-10-02 15:03:57', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-02 21:03:57'),
(4, 4, 1, '2025-10-02 15:05:18', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-02 21:05:18'),
(5, 3, 1, '2025-10-02 15:05:39', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-02 21:05:39'),
(6, 4, 1, '2025-10-02 23:08:40', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-03 05:08:40'),
(7, 4, 1, '2025-10-02 23:30:08', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-03 05:30:08'),
(8, 3, 1, '2025-10-02 23:33:52', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-03 05:33:52'),
(9, 1, 1, '2025-10-02 23:34:05', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2000.00, '2025-10-03 05:34:05'),
(10, 4, 1, '2025-10-02 23:34:58', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-03 05:34:58'),
(11, 3, 1, '2025-10-02 23:35:29', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-03 05:35:29'),
(12, 3, 1, '2025-10-02 23:35:59', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-03 05:35:59'),
(13, 3, 1, '2025-10-02 23:36:18', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-03 05:36:18'),
(14, 3, 1, '2025-10-02 23:36:27', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-03 05:36:27'),
(15, 5, 1, '2025-10-05 07:08:00', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-05 13:08:00'),
(16, 5, 1, '2025-10-05 07:09:09', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-05 13:09:09'),
(17, 5, 1, '2025-10-05 20:52:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27990.00, '2025-10-06 02:52:41'),
(18, 4, 1, '2025-10-05 20:54:30', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-06 02:54:30'),
(19, 4, 1, '2025-10-05 20:54:45', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4000.00, '2025-10-06 02:54:45'),
(20, 5, 1, '2025-10-05 21:26:11', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27990.00, '2025-10-06 03:26:11'),
(21, 5, 1, '2025-10-05 21:26:20', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27990.00, '2025-10-06 03:26:20'),
(22, 5, 1, '2025-10-05 21:38:14', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27990.00, '2025-10-06 03:38:14'),
(23, 5, 1, '2025-10-09 19:09:52', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27990.00, '2025-10-10 01:09:52'),
(24, 5, 1, '2025-10-10 01:12:05', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 27990.00, '2025-10-10 01:12:05'),
(25, 4, 1, '2025-10-14 01:29:59', 0.00, 0.00, 0.00, 0.00, 800.00, 0.00, 4000.00, '2025-10-14 01:29:59'),
(26, 15, 2, '2025-10-14 03:41:41', 0.00, 0.00, 0.00, 0.00, 400.00, 0.00, 2000.00, '2025-10-14 03:41:41'),
(27, 5, 5, '2025-10-14 03:42:27', 0.00, 0.00, 0.00, 0.00, 27990.00, 0.00, 139950.00, '2025-10-14 03:42:27'),
(28, 15, 1, '2025-10-14 06:41:58', 0.00, 0.00, 0.00, 0.00, 200.00, 0.00, 1000.00, '2025-10-14 06:41:58'),
(29, 15, 2, '2025-10-14 06:43:27', 0.00, 0.00, 0.00, 0.00, 400.00, 0.00, 2000.00, '2025-10-14 06:43:27'),
(30, 5, 1, '2025-10-14 06:54:42', 0.00, 0.00, 0.00, 0.00, 5598.00, 0.00, 27990.00, '2025-10-14 06:54:42'),
(31, 5, 1, '2025-10-14 12:59:44', 0.00, 0.00, 0.00, 0.00, 5598.00, 0.00, 27990.00, '2025-10-14 12:59:44'),
(32, 4, 1, '2025-10-14 13:00:02', 0.00, 0.00, 0.00, 0.00, 800.00, 0.00, 4000.00, '2025-10-14 13:00:02'),
(65, 22, 1, '2025-10-15 01:41:01', 0.00, 0.00, 0.00, 0.00, 2.20, 0.00, 11.00, '2025-10-15 01:41:01'),
(66, 19, 1, '2025-10-15 01:43:11', 0.00, 0.00, 0.00, 0.00, 2.20, 0.00, 11.00, '2025-10-15 01:43:11'),
(67, 15, 1, '2025-10-15 01:45:19', 0.00, 0.00, 0.00, 0.00, 200.00, 0.00, 1000.00, '2025-10-15 01:45:19'),
(68, 19, 1, '2025-10-15 03:12:33', 0.00, 0.00, 0.00, 0.00, 2.20, 0.00, 11.00, '2025-10-15 03:12:33'),
(69, 19, 1, '2025-10-15 03:12:56', 0.00, 0.00, 0.00, 0.00, 2.20, 0.00, 11.00, '2025-10-15 03:12:56'),
(70, 72, 1, '2025-10-19 07:53:02', 0.00, 0.00, 0.00, 0.00, 4.40, 0.00, 22.00, '2025-10-19 07:53:02'),
(71, 76, 1, '2025-10-19 08:11:19', 0.00, 0.00, 0.00, 0.00, 4.40, 0.00, 22.00, '2025-10-19 08:11:19'),
(72, 71, 1, '2025-10-22 04:21:00', 0.00, 0.00, 0.00, 0.00, 4.40, 0.00, 22.00, '2025-10-22 04:21:00'),
(97, 71, 1, '2025-10-22 04:27:35', 0.00, 0.00, 0.00, 0.00, 4.40, 0.00, 22.00, '2025-10-22 04:27:35'),
(98, 78, 1, '2025-10-22 04:28:49', 0.00, 0.00, 0.00, 0.00, 6.60, 0.00, 33.00, '2025-10-22 04:28:49'),
(99, 78, 2, '2025-10-22 04:29:12', 0.00, 0.00, 0.00, 0.00, 13.20, 0.00, 66.00, '2025-10-22 04:29:12'),
(100, 78, 6, '2025-10-22 04:30:08', 0.00, 0.00, 0.00, 0.00, 39.60, 0.00, 198.00, '2025-10-22 04:30:08'),
(101, 78, 1, '2025-10-22 04:31:54', 0.00, 0.00, 0.00, 0.00, 6.60, 0.00, 33.00, '2025-10-22 04:31:54'),
(102, 78, 1, '2025-10-22 04:32:00', 0.00, 0.00, 0.00, 0.00, 6.60, 0.00, 33.00, '2025-10-22 04:32:00'),
(103, 5, 1, '2025-10-22 05:31:00', 0.00, 0.00, 0.00, 0.00, 5598.00, 0.00, 27990.00, '2025-10-22 05:31:00'),
(104, 15, 1, '2025-10-22 05:32:56', 0.00, 0.00, 0.00, 0.00, 200.00, 0.00, 1000.00, '2025-10-22 05:32:56'),
(105, 79, 1, '2025-10-22 14:21:13', 0.00, 0.00, 0.00, 0.00, 4.00, 0.00, 20.00, '2025-10-22 14:21:13'),
(106, 78, 1, '2025-10-22 16:16:05', 0.00, 0.00, 0.00, 0.00, 6.60, 0.00, 33.00, '2025-10-22 16:16:05'),
(107, 78, 1, '2025-10-22 16:16:22', 0.00, 0.00, 0.00, 0.00, 6.60, 0.00, 33.00, '2025-10-22 16:16:22'),
(108, 78, 1, '2025-10-22 16:16:32', 0.00, 0.00, 0.00, 0.00, 6.60, 0.00, 33.00, '2025-10-22 16:16:32'),
(109, 5, 1, '2025-10-23 00:23:21', 0.00, 0.00, 0.00, 0.00, 5598.00, 0.00, 27990.00, '2025-10-23 00:23:21'),
(110, 79, 11, '2025-10-23 02:26:37', 0.00, 0.00, 0.00, 0.00, 44.00, 0.00, 220.00, '2025-10-23 02:26:37');

-- --------------------------------------------------------

--
-- Table structure for table `stock_history`
--

CREATE TABLE `stock_history` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `old_stock` int(11) DEFAULT 0,
  `new_stock` int(11) NOT NULL,
  `change_amount` int(11) NOT NULL,
  `change_type` enum('input','edit','purchase','manual') NOT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `change_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_history`
--

INSERT INTO `stock_history` (`id`, `product_id`, `product_name`, `old_stock`, `new_stock`, `change_amount`, `change_type`, `changed_by`, `change_date`, `notes`) VALUES
(5, 15, 'POP', 14, 13, -1, 'edit', 'user', '2025-10-15 03:12:41', 'Stock updated from 14 to 13'),
(6, 5, 'Iphone 13', 3, 4, 1, 'input', 'user', '2025-10-15 03:15:26', 'Manual stock addition'),
(9, 71, 'fGHtMGG', 15, 16, 1, 'input', 'ADMIN', '2025-10-19 08:12:00', 'Stock updated from 15 to 16'),
(10, 71, '', 16, 17, 1, '', 'ADMIN', '2025-10-19 08:12:15', 'Manual stock addition'),
(11, 77, 'SA', 0, 23, 23, 'input', 'ADMIN', '2025-10-22 04:22:49', 'Initial product creation'),
(12, 78, 'RR', 0, 33, 33, 'input', 'ADMIN', '2025-10-22 04:23:30', 'Initial product creation'),
(13, 78, '', 33, 34, 1, '', 'ADMIN', '2025-10-22 04:25:38', 'Manual stock addition'),
(14, 78, '', 34, 44, 10, '', 'ADMIN', '2025-10-22 04:25:48', 'Manual stock addition'),
(15, 79, 'NN', 0, 6, 6, 'input', 'ADMIN', '2025-10-22 14:21:01', 'Initial product creation'),
(16, 79, '', 5, 25, 20, '', 'ADMIN', '2025-10-22 14:21:44', 'Manual stock addition'),
(17, 79, '', 25, 26, 1, '', 'ADMIN', '2025-10-23 02:26:27', 'Manual stock addition');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_price` decimal(10,0) NOT NULL,
  `transaction_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `product_id`, `product_name`, `qty`, `unit_price`, `transaction_date`) VALUES
(1, 5, 'Iphone 13', 1, 27990, '2025-10-10 09:25:08'),
(2, 4, 'A50', 1, 4000, '2025-10-10 09:25:21'),
(3, 4, 'A50', 1, 4000, '2025-10-10 09:25:28'),
(4, 5, 'Iphone 13', 1, 27990, '2025-10-10 10:31:30'),
(5, 5, 'Iphone 13', 1, 27990, '2025-10-10 10:37:27'),
(6, 4, 'A50', 1, 4000, '2025-10-10 10:37:46'),
(7, 5, 'Iphone 13', 1, 27990, '2025-10-10 10:38:57'),
(8, 7, 'HHww', 1, 12, '2025-10-10 12:41:25'),
(9, 7, 'HHww', 1, 12, '2025-10-10 12:48:25'),
(10, 7, 'HHww', 1, 12, '2025-10-10 13:06:49'),
(11, 6, 'ANO', 1, 1000, '2025-10-10 13:54:11'),
(12, 9, 'POQWE', 1, 1000, '2025-10-11 07:47:45'),
(13, 9, 'POQWE', 1, 1000, '2025-10-13 20:42:25'),
(14, 5, 'Iphone 13', 1, 27990, '2025-10-13 21:24:34'),
(15, 14, 'POP', 2, 1000, '2025-10-13 21:25:49'),
(16, 14, 'POP', 1, 1000, '2025-10-13 21:26:34'),
(17, 14, 'POP', 1, 100000, '2025-10-13 21:27:20'),
(18, 4, 'A50', 1, 4000, '2025-10-13 21:28:08'),
(19, 4, 'A50', 1, 4000, '2025-10-13 21:29:44'),
(20, 5, 'Iphone 13', 1, 27990, '2025-10-13 21:31:37'),
(21, 14, 'POP', 1, 100000, '2025-10-13 21:47:41'),
(22, 5, 'Iphone 13', 1, 27990, '2025-10-13 21:48:16'),
(23, 5, 'Iphone 13', 1, 27990, '2025-10-13 21:48:57'),
(24, 5, 'Iphone 13', 1, 27990, '2025-10-14 04:51:38'),
(25, 5, 'Iphone 13', 1, 27990, '2025-10-14 04:53:12'),
(26, 5, 'Iphone 13', 1, 27990, '2025-10-14 04:55:39'),
(27, 4, 'A50', 1, 4000, '2025-10-14 05:03:02'),
(28, 5, 'Iphone 13', 1, 27990, '2025-10-14 05:09:39'),
(29, 4, 'A50', 1, 4000, '2025-10-14 06:22:37'),
(30, 4, 'A50', 1, 4000, '2025-10-14 06:23:02'),
(31, 1, 'MacBook Air M4', 1, 64990, '2025-10-14 06:23:21'),
(32, 4, 'A50', 1, 4000, '2025-10-14 06:25:16'),
(65, 4, 'A50', 1, 4000, '2025-10-14 09:29:59'),
(66, 15, 'POP', 2, 1000, '2025-10-14 11:41:41'),
(67, 5, 'Iphone 13', 5, 27990, '2025-10-14 11:42:27'),
(68, 15, 'POP', 1, 1000, '2025-10-14 14:41:58'),
(69, 15, 'POP', 2, 1000, '2025-10-14 14:43:27'),
(70, 5, 'Iphone 13', 1, 27990, '2025-10-14 14:54:42'),
(71, 5, 'Iphone 13', 1, 27990, '2025-10-14 20:59:44'),
(72, 4, 'A50', 1, 4000, '2025-10-14 21:00:02'),
(105, 22, 're', 1, 11, '2025-10-15 09:41:01'),
(106, 19, 're', 1, 11, '2025-10-15 09:43:11'),
(107, 15, 'POP', 1, 1000, '2025-10-15 09:45:19'),
(108, 19, 're', 1, 11, '2025-10-15 11:12:33'),
(109, 19, 're', 1, 11, '2025-10-15 11:12:55'),
(110, 72, 'fGHtMGG', 1, 22, '2025-10-19 15:53:02'),
(111, 76, 'fGHtMGGNN', 1, 22, '2025-10-19 16:11:19'),
(112, 71, 'fGHtMGG', 1, 22, '2025-10-22 12:21:00'),
(137, 71, 'fGHtMGG', 1, 22, '2025-10-22 12:27:35'),
(138, 78, 'RR', 1, 33, '2025-10-22 12:28:49'),
(139, 78, 'RR', 2, 33, '2025-10-22 12:29:12'),
(140, 78, 'RR', 6, 33, '2025-10-22 12:30:08'),
(141, 78, 'RR', 1, 33, '2025-10-22 12:31:54'),
(142, 78, 'RR', 1, 33, '2025-10-22 12:32:00'),
(143, 5, 'Iphone 13', 1, 27990, '2025-10-22 13:31:00'),
(144, 15, 'POP', 1, 1000, '2025-10-22 13:32:56'),
(145, 79, 'NN', 1, 20, '2025-10-22 22:21:13'),
(146, 78, 'RR', 1, 33, '2025-10-23 00:16:04'),
(147, 78, 'RR', 1, 33, '2025-10-23 00:16:22'),
(148, 78, 'RR', 1, 33, '2025-10-23 00:16:32'),
(149, 5, 'Iphone 13', 1, 27990, '2025-10-23 08:23:21'),
(150, 79, 'NN', 11, 20, '2025-10-23 10:26:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `chatbot_messages`
--
ALTER TABLE `chatbot_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `installments`
--
ALTER TABLE `installments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `install_transactions`
--
ALTER TABLE `install_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sales_product` (`product_id`);

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
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `chatbot_messages`
--
ALTER TABLE `chatbot_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `installments`
--
ALTER TABLE `installments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `install_transactions`
--
ALTER TABLE `install_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `login`
--
ALTER TABLE `login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `stock_history`
--
ALTER TABLE `stock_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `installments`
--
ALTER TABLE `installments`
  ADD CONSTRAINT `installments_ibfk_1` FOREIGN KEY (`id`) REFERENCES `install_transactions` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
