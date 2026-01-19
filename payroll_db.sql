-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 19, 2026 at 02:52 PM
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
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `department_code`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Office of the City Mayor', 'CMO', 'Executive', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(2, 'City Administrator Office', 'CAO', 'Administration', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(3, 'Business Permits and License Office', 'BPLO', 'Licensing', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(4, 'City Treasurer Office', 'CTO', 'Treasury', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(5, 'City Assessor Office', 'CASSO', 'Assessment', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(6, 'City Accountant Office', 'CACCO', 'Accounting', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(7, 'City Budget Office', 'CBO', 'Budget', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(8, 'City Planning and Development Office', 'CPDO', 'Planning', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(9, 'Department of Local Economic and Investment Promotions', 'DLEIP', 'Investment', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(10, 'City Engineer Office', 'CEO', 'Engineering', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(11, 'Office of the Building Official', 'OBO', 'Building', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(12, 'City Health Office', 'CHO', 'Health', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(13, 'Department of Social Services and Development', 'DSSD', 'Social', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(14, 'Population Office', 'POPO', 'Population', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(15, 'Bureau of Fire Protection - Bacolod', 'BFP', 'Fire', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(16, 'Disaster Risk Reduction and Management Office', 'DRRMO', 'Disaster', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(17, 'City Legal Office', 'CLO', 'Legal', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(18, 'Bids and Awards Committee', 'BAC', 'Procurement', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(19, 'Business Tax and Trade Management Division', 'BTTMD', 'Taxation', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(20, 'Management Information Technology and Computer Services', 'MITCS', 'Technology', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(21, 'City Public Information Office', 'CPIO', 'Information', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(22, 'Bacolod City Youth Development Office', 'BCYDO', 'Youth', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(23, 'Bacolod Housing Authority', 'BHA', 'Housing', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(24, 'Bacolod City College', 'BCC', 'Education', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(25, 'City Transport and Traffic Management Office', 'CTTMO', 'Traffic', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(26, 'City Environment and Natural Resources Office', 'CENRO', 'Environment', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(27, 'General Services Office', 'GSO', 'Services', '2026-01-18 17:02:50', '2026-01-18 17:02:50'),
(28, 'City Civil Registry Office', 'CCRO', 'Registry', '2026-01-18 17:02:50', '2026-01-18 17:02:50');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `employment_status` enum('Regular','Contractual','Job Order','Casual') DEFAULT 'Regular',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `first_name`, `middle_name`, `last_name`, `email`, `phone`, `address`, `date_hired`, `department_id`, `position_id`, `employment_status`, `is_active`, `created_at`, `updated_at`) VALUES
(23, '1010194', 'Godfred Angelo', 'Cabigo', 'Bendicio', 'godfredbendicio@gmail.com', '9293528298', 'Purok 5 Brgy Granada Bacolod City Neg Occ', '2002-02-10', 1, 36, '', 1, '2026-01-18 16:56:29', '2026-01-19 08:30:56'),
(24, '0900535', 'Ernest', 'Albayda', 'Alcala', 'edgyalcala123@gmail.com', '09171234567', 'Eroreco', '2026-01-16', 24, 37, '', 1, '2026-01-19 13:27:10', '2026-01-19 13:37:23');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
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
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `employee_id`, `payroll_period`, `payroll_month`, `payroll_year`, `period_type`, `basic_salary`, `pera`, `gross_pay`, `wtax`, `philhealth`, `gsis`, `pagibig`, `provident`, `bcgeu`, `nocgem`, `bacgem`, `other_deductions`, `total_deductions`, `net_pay`, `status`, `created_at`, `updated_at`) VALUES
(28, 23, 'January 1-15, 2026', 'January', 2026, '1-15', 219763.48, 2000.00, 221763.48, 42577.13, 5000.00, 19778.71, 200.00, 10.00, 10.00, 10.00, 10.00, 10.00, 67605.84, 154157.64, 'Approved', '2026-01-19 08:33:08', '2026-01-19 08:33:38'),
(29, 24, 'January 1-15, 2026', 'January', 2026, '1-15', 116444.27, 2000.00, 118444.27, 18088.34, 2911.11, 10479.98, 200.00, 0.00, 0.00, 0.00, 0.00, 0.00, 31679.44, 86764.83, 'Draft', '2026-01-19 13:40:49', '2026-01-19 13:40:49');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_title` varchar(100) NOT NULL,
  `salary_grade` int(11) NOT NULL,
  `salary_step` int(11) DEFAULT 1,
  `basic_salary` decimal(12,2) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_title`, `salary_grade`, `salary_step`, `basic_salary`, `department_id`, `created_at`, `updated_at`) VALUES
(36, 'City Mayor', 30, 8, 219763.48, 1, '2026-01-19 08:30:41', '2026-01-19 08:30:41'),
(37, 'Vice Mayor', 26, 4, 116444.27, 1, '2026-01-19 13:36:56', '2026-01-19 13:36:56');

-- --------------------------------------------------------

--
-- Table structure for table `salary`
--

CREATE TABLE `salary` (
  `salary_id` int(11) NOT NULL,
  `salary_rate` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `step_inc`
--

CREATE TABLE `step_inc` (
  `id` int(11) NOT NULL,
  `salary_grade` int(11) NOT NULL,
  `step_1` decimal(10,2) NOT NULL,
  `step_2` decimal(10,2) NOT NULL,
  `step_3` decimal(10,2) NOT NULL,
  `step_4` decimal(10,2) NOT NULL,
  `step_5` decimal(10,2) NOT NULL,
  `step_6` decimal(10,2) NOT NULL,
  `step_7` decimal(10,2) NOT NULL,
  `step_8` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `step_inc`
--

INSERT INTO `step_inc` (`id`, `salary_grade`, `step_1`, `step_2`, `step_3`, `step_4`, `step_5`, `step_6`, `step_7`, `step_8`, `created_at`) VALUES
(9, 9, 18763.00, 19325.89, 19905.67, 20502.84, 21117.93, 21751.47, 22404.01, 23076.13, '2026-01-17 12:20:55'),
(10, 10, 20219.00, 20825.57, 21450.34, 22093.85, 22756.67, 23439.37, 24142.55, 24866.83, '2026-01-17 12:20:55'),
(12, 12, 24465.00, 25198.95, 25954.92, 26733.57, 27535.58, 28361.64, 29212.49, 30088.87, '2026-01-17 12:20:55'),
(14, 14, 29277.00, 30155.31, 31060.97, 31994.80, 32957.64, 33950.37, 34973.88, 36029.10, '2026-01-17 12:20:55'),
(15, 15, 32063.00, 33024.89, 34015.64, 35036.11, 36087.19, 37169.81, 38284.90, 39433.45, '2026-01-17 12:20:55'),
(16, 16, 35106.00, 36159.18, 37243.96, 38361.28, 39512.12, 40696.48, 41915.38, 43169.84, '2026-01-17 12:20:55'),
(17, 17, 38484.00, 39638.52, 40827.68, 42052.51, 43314.09, 44613.51, 45951.92, 47330.48, '2026-01-17 12:20:55'),
(18, 18, 42156.00, 43420.68, 44723.30, 46065.00, 47446.95, 48870.36, 50336.47, 51846.56, '2026-01-17 12:20:55'),
(19, 19, 46701.00, 48102.03, 49545.09, 51031.44, 52562.38, 54139.25, 55763.43, 57436.33, '2026-01-17 12:20:55'),
(20, 20, 52703.00, 54284.09, 55912.61, 57589.99, 59317.69, 61097.22, 62930.14, 64818.04, '2026-01-17 12:20:55'),
(21, 21, 60353.00, 62163.59, 64028.50, 65949.36, 67927.84, 69965.68, 72064.65, 74226.59, '2026-01-17 12:20:55'),
(22, 22, 68887.00, 70953.61, 73082.22, 75274.69, 77532.93, 79858.92, 82254.69, 84722.33, '2026-01-17 12:20:55'),
(23, 23, 75859.00, 78134.77, 80478.81, 82893.18, 85379.97, 87941.37, 90579.61, 93297.00, '2026-01-17 12:20:55'),
(24, 24, 85074.00, 87626.22, 90255.01, 92962.66, 95751.54, 98624.09, 101582.81, 104630.30, '2026-01-17 12:20:55'),
(26, 26, 106563.00, 109759.89, 113052.69, 116444.27, 119937.60, 123535.73, 127241.80, 131059.05, '2026-01-17 12:20:55'),
(27, 27, 123859.00, 127574.77, 131402.01, 135344.07, 139404.39, 143586.52, 147894.12, 152330.94, '2026-01-17 12:20:55'),
(28, 28, 139839.00, 144034.17, 148355.20, 152805.85, 157390.03, 162111.73, 166975.08, 171984.33, '2026-01-17 12:20:55'),
(29, 29, 158131.00, 162874.93, 167761.18, 172794.02, 177977.84, 183317.18, 188816.69, 194481.19, '2026-01-17 12:20:55'),
(30, 30, 178688.00, 184048.64, 189570.10, 195257.00, 201114.71, 207148.15, 213362.60, 219763.48, '2026-01-17 12:20:55'),
(31, 31, 265695.00, 273665.85, 281875.83, 290332.30, 299042.27, 308013.54, 317253.95, 326771.57, '2026-01-17 12:20:55'),
(32, 32, 315512.00, 324977.36, 334726.68, 344769.48, 355115.57, 365775.04, 376758.29, 388077.04, '2026-01-17 12:20:55'),
(33, 33, 395858.00, 407733.74, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-01-17 12:20:55'),
(34, 1, 11251.00, 11588.53, 11936.19, 12294.28, 12663.11, 13042.80, 13433.09, 13834.88, '2026-01-17 13:01:26'),
(35, 2, 12267.00, 12635.01, 13014.06, 13404.48, 13806.62, 14220.82, 14647.44, 15086.87, '2026-01-17 13:01:26'),
(36, 3, 13019.00, 13409.57, 13811.86, 14226.21, 14653.00, 15092.59, 15545.37, 16011.73, '2026-01-17 13:01:26'),
(37, 4, 13807.00, 14221.21, 14647.85, 15087.29, 15539.91, 16006.11, 16486.29, 16980.88, '2026-01-17 13:01:26'),
(38, 5, 14641.00, 15080.23, 15532.64, 15998.62, 16478.58, 16972.94, 17482.13, 18006.59, '2026-01-17 13:01:26'),
(39, 6, 15524.00, 15989.72, 16469.41, 16963.49, 17472.40, 17996.57, 18536.47, 19092.56, '2026-01-17 13:01:26'),
(40, 7, 16458.00, 16951.74, 17460.29, 17984.10, 18523.62, 19079.33, 19651.71, 20241.26, '2026-01-17 13:01:26'),
(41, 8, 17505.00, 18030.15, 18571.05, 19128.18, 19702.03, 20292.09, 20898.85, 21522.82, '2026-01-17 13:01:26');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_employees_department` (`department_id`),
  ADD KEY `idx_employees_position` (`position_id`),
  ADD KEY `idx_employees_active` (`is_active`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_payroll` (`employee_id`,`payroll_month`,`payroll_year`,`period_type`),
  ADD KEY `idx_payroll_employee` (`employee_id`),
  ADD KEY `idx_payroll_period` (`payroll_month`,`payroll_year`,`period_type`),
  ADD KEY `idx_payroll_status` (`status`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `idx_salary_grade` (`salary_grade`);

--
-- Indexes for table `salary`
--
ALTER TABLE `salary`
  ADD PRIMARY KEY (`salary_id`);

--
-- Indexes for table `step_inc`
--
ALTER TABLE `step_inc`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `salary_grade` (`salary_grade`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `salary`
--
ALTER TABLE `salary`
  MODIFY `salary_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `step_inc`
--
ALTER TABLE `step_inc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
