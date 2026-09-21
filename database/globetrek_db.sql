-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 21, 2026 at 07:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `globetrek_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL,
  `booking_date` int(11) NOT NULL,
  `travel_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `persons` int(11) NOT NULL,
  `days` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`id`, `user_id`, `package_id`, `booking_date`, `travel_date`, `status`, `persons`, `days`, `total_price`) VALUES
(1, 11, 1, 2026, '2026-01-01', 'Pending', 1, NULL, NULL),
(2, 11, 1, 2026, '2026-01-01', 'Pending', 1, NULL, NULL),
(11, 7, 1, 2026, '2026-01-01', 'Paid', 1, 2, 4000.00),
(12, 12, 1, 2026, '2026-01-01', 'Pending', 1, 2, 4000.00),
(13, 12, 1, 2026, '2026-01-01', 'Pending', 1, 2, 4000.00),
(14, 12, 1, 2026, '2026-01-01', 'Pending', 1, 2, 4000.00),
(15, 12, 1, 2026, '2026-01-01', 'Pending', 1, 2, 4000.00),
(16, 12, 1, 2026, '2026-01-01', 'Pending', 1, 2, 4000.00),
(17, 12, 1, 2026, '2026-01-01', 'Paid', 1, 2, 4000.00),
(18, 15, 2, 2026, '2026-01-01', 'Paid', 1, 2, 8000.00),
(19, 12, 1, 2026, '2026-01-01', 'Pending', 1, 2, 4000.00),
(20, 12, 1, 2026, '2026-01-01', 'Paid', 1, 2, 4000.00),
(21, 16, 2, 2026, '2026-01-01', 'Paid', 1, -14, -56000.00),
(22, 17, 4, 2026, '2026-09-26', 'Pending', 1, 1, 2800.00),
(23, 17, 2, 2026, '2026-09-26', 'Pending', 1, 1, 4000.00);

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `duration` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `title`, `description`, `price`, `image`, `destination`, `duration`) VALUES
(1, 'Kandy', 'Beautiful hill capital with cultural heritage', 2000.00, 'kandy.jpg', 'Colombo -Kandy', 'per 1 day'),
(2, 'Sigiriya', 'Ancient rock fortress and world heritage site.', 4000.00, 'sigiriya.jpg', 'Colombo - Sigiriya', 'per 1 day'),
(3, 'Galle', 'Historic city with beautiful beaches and fort.', 3000.00, 'galle.jpg', 'Colombo - Galle', 'per 1 day'),
(4, 'Ella', 'Scenic mountain views and waterfalls.', 2800.00, 'ella.jpg', 'Colombo - Ella', 'per 1 day');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` varchar(50) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `card_name` varchar(100) NOT NULL,
  `card_number` varchar(50) NOT NULL,
  `exp_date` varchar(20) NOT NULL,
  `cvv` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `amount`, `payment_status`, `payment_method`, `user_id`, `card_name`, `card_number`, `exp_date`, `cvv`) VALUES
(2, 11, 4000.00, 'Paid', 'Card', 7, 'shehan sewmina', '12345656788', '24/4', '123'),
(3, 17, 4000.00, 'Paid', 'Card', 12, 'shehan sewmina', '1234566778989', '123', '234'),
(4, 18, 8000.00, 'Paid', 'Card', 15, 'sanju', '1234567890', '1203', '123'),
(5, 20, 4000.00, 'Paid', 'Card', 12, 'shehan', '1234567890', '0512', '123'),
(6, 21, -56000.00, 'Paid', 'Card', 16, 'shehan sewmina', '1234567890', '12/28', '234');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','staff','admin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `profile_image`, `email`, `phone`, `location`, `bio`, `password`, `role`) VALUES
(7, 'shehan sewmina', 'user_7_1789995249.jpg', 'shehansewmina1@gmail.com', '', '', 'HI I M SHEHAN SEWMINA', '$2y$10$uqQfdATxgA78rBAJ5TNaG.ZT5V4HJ5IujTbxdwuaG7.FFKJImXamy', 'customer'),
(10, 'admin', NULL, 'admin@gmail.com', '', '', '', '$2y$10$7Shln2v2m9abKElrmBQg7OIgJK05cwq3tVw7fgg1cjXleKF23pXOe', 'admin'),
(11, 'staff', NULL, 'staff@gmail.com', '', '', '', '$2y$10$a.I5RvryZD2Ypk1s.lBGtubrC53uB7PD5G/giliOrObeZsQESEbzm', 'staff'),
(12, 'shehan', NULL, 'shehan@gmail.com', NULL, NULL, NULL, '$2y$10$3EweJln.kZa6h8zNmNRoYORmZqF7oa6VvGMNSXxi2IdGarDqc/p2K', 'customer'),
(13, 'RS Sewmina', NULL, 'RS@gmail.com', NULL, NULL, NULL, '$2y$10$dT.eVLbmcMhaLhqlnrqsTefen23ts6Czsru0QQ/rIg7h.lncNu/m6', 'customer'),
(14, 'new', NULL, 'new@gmail.com', NULL, NULL, NULL, '123456', 'customer'),
(15, 'sanju', NULL, 'sanju@gmail.com', NULL, NULL, NULL, '$2y$10$w6j3h5EVp6InUM0h4eatP..Sfc1VpF5PJ2MKrKulz2aHNHJgxU4eG', 'customer'),
(16, 'nikan', NULL, 'nikan@gmail.com', NULL, NULL, NULL, '$2y$10$lBGWmrSE9KwM3vVGIUEGc.bkHqWC1fEk2o9m90KHYHNASZeWTooiO', 'admin'),
(17, 'nikan', 'user_17_1789653524.jpg', 'ai@gmail.com', '', '', '', '$2y$10$cpdAaab9sgoFcfi/jsG9rOJ6GPbxqXXs0uYOzhEXET5Keo/i9H96u', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `package_id` (`package_id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
