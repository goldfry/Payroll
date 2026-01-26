-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 26, 2026 at 04:39 AM
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
-- Database: `payroll_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `payroll_period` varchar(50) NOT NULL,
  `payroll_month` varchar(20) NOT NULL,
  `payroll_year` int(11) NOT NULL,
  `period_type` enum('1-15','16-31') NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pera` decimal(12,2) NOT NULL DEFAULT 2000.00,
  `gross_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wtax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `philhealth` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gsis` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pagibig` decimal(12,2) NOT NULL DEFAULT 0.00,
  `provident` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bcgeu` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nocgem` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bacgem` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Draft','Approved','Paid') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_payroll` (`employee_id`,`payroll_month`,`payroll_year`,`period_type`),
  ADD KEY `idx_payroll_employee` (`employee_id`),
  ADD KEY `idx_payroll_period` (`payroll_month`,`payroll_year`,`period_type`),
  ADD KEY `idx_payroll_status` (`status`),
  ADD KEY `idx_department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
