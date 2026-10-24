-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 24, 2026 at 02:36 PM
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
-- Database: `mau_hrms`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 6, 'LOGIN', '', '127.0.0.1', '2026-10-24 11:49:29'),
(2, 6, 'LOGOUT', '', '127.0.0.1', '2026-10-24 11:50:08'),
(3, NULL, 'LOGIN', '', '127.0.0.1', '2026-10-24 11:59:19'),
(4, NULL, 'LOGIN', '', '127.0.0.1', '2026-10-24 11:59:19'),
(5, 7, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:00:02'),
(6, 7, 'LOGOUT', '', '127.0.0.1', '2026-10-24 12:00:41'),
(7, 6, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:02:06'),
(8, 6, 'LOGOUT', '', '127.0.0.1', '2026-10-24 12:06:58'),
(9, NULL, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:06:59'),
(10, NULL, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:07:00'),
(11, NULL, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:12:03'),
(12, NULL, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:23:10'),
(13, NULL, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:23:58'),
(14, NULL, 'CREATE_EMPLOYEE', 'Managed Cascading Employee', '127.0.0.1', '2026-10-24 12:23:58'),
(15, 6, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:32:59'),
(16, 6, 'LOGOUT', '', '127.0.0.1', '2026-10-24 12:33:42'),
(17, 7, 'LOGIN', '', '127.0.0.1', '2026-10-24 12:33:52');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `app_id` int(11) NOT NULL,
  `vacancy_id` int(11) NOT NULL,
  `applicant_name` varchar(160) NOT NULL,
  `email` varchar(160) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `status` enum('Submitted','Shortlisted','Rejected','Hired') NOT NULL DEFAULT 'Submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `att_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late','On Leave') NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `college_id` int(11) NOT NULL,
  `name` varchar(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`college_id`, `name`) VALUES
(4, 'Administrative & Support Offices'),
(1, 'College of Agriculture and Natural Resources'),
(3, 'College of Natural and Computational Sciences'),
(2, 'College of Social Sciences and Humanities');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `college` varchar(120) DEFAULT NULL,
  `head_id` int(11) DEFAULT NULL,
  `college_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `name`, `college`, `head_id`, `college_id`) VALUES
(2, 'Computer Science', 'College of Natural and Computational Sciences', NULL, 3),
(3, 'Information Technology (ICT / IT Services)', 'College of Natural and Computational Sciences', NULL, 4),
(4, 'Human Resource Management (HR)', 'Administration', NULL, 4),
(6, 'Animal Science', 'College of Agriculture', NULL, 1),
(7, 'Plant Science', 'College of Agriculture', NULL, 1),
(8, 'English Language and Literature', 'College of Social Sciences and Humanities', NULL, 2),
(9, 'Biology', 'College of Natural and Computational Sciences', NULL, 3),
(10, 'Chemistry', 'College of Natural and Computational Sciences', NULL, 3),
(11, 'Mathematics', 'College of Natural and Computational Sciences', NULL, 3),
(12, 'Natural Resource Management', NULL, NULL, 1),
(13, 'Agricultural Economics', NULL, NULL, 1),
(14, 'Horticulture', NULL, NULL, 1),
(15, 'Rural Development and Agricultural Extension', NULL, NULL, 1),
(16, 'Forestry', NULL, NULL, 1),
(17, 'Agribusiness and Value Chain Management', NULL, NULL, 1),
(18, 'History and Heritage Management', NULL, NULL, 2),
(19, 'Ethiopian Languages and Literature ??? Amharic', NULL, NULL, 2),
(20, 'Geography and Environmental Studies', NULL, NULL, 2),
(21, 'Political Science and International Relations', NULL, NULL, 2),
(22, 'Physics', NULL, NULL, 3),
(23, 'Statistics', NULL, NULL, 3),
(24, 'Geology', NULL, NULL, 3),
(25, 'Sport Science', NULL, NULL, 3),
(26, 'Finance and Budget Directorate', NULL, NULL, 4),
(27, 'Procurement and Property Administration', NULL, NULL, 4),
(28, 'Registrar and Alumni Directorate', NULL, NULL, 4),
(29, 'President Office & Executive Organs', NULL, NULL, 4),
(44, 'Ethiopian Languages and Literature – Amharic', NULL, NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `emp_id` int(11) NOT NULL,
  `staff_no` varchar(40) DEFAULT NULL,
  `full_name` varchar(160) NOT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(160) DEFAULT NULL,
  `position` varchar(120) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `annual_leave_balance` decimal(6,2) NOT NULL DEFAULT 20.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `college_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`emp_id`, `staff_no`, `full_name`, `sex`, `birth_date`, `phone`, `email`, `position`, `dept_id`, `hire_date`, `salary`, `annual_leave_balance`, `created_at`, `college_id`) VALUES
(9, '1', 'lamesgin getaneh', NULL, NULL, '0962587904', 'lamesgingetaneh16@gmail.com', 'office', NULL, NULL, 0.00, 20.00, '2026-10-24 11:51:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `eval_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `evaluator_id` int(11) NOT NULL,
  `period` varchar(60) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `type` enum('Annual','Sick','Maternity','Paternity','Emergency','Unpaid','Other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` decimal(6,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending Head','Pending HR','Approved','Rejected') NOT NULL DEFAULT 'Pending Head',
  `head_comment` text DEFAULT NULL,
  `hr_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `not_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `pay_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `pay_month` char(7) NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pension` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_requests`
--

CREATE TABLE `role_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requested_role` enum('Administrator','HR Officer','Department Head','Employee','Management') NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile_requests`
--

CREATE TABLE `profile_requests` (
  `request_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `changes_json` longtext NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Administrator','HR Officer','Department Head','Employee','Management') NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `emp_id` int(11) DEFAULT NULL,
  `failed_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `role`, `status`, `emp_id`, `failed_attempts`, `locked_until`, `created_at`) VALUES
(6, 'superadmin', '$2y$10$83hu4XQ9Ib2FaHq4QS.T8eJt57WkI/VfVxSqqo9LXPn7e7NqVWQXq', 'Administrator', 'Active', NULL, 0, NULL, '2026-10-24 11:48:55'),
(7, 'lamesgingetanehasaye', '$2y$10$mZleAPT6o/MyFB48rizxt.tPmuLkqQD/l0QEbICnenJw51RpQ98R2', 'Employee', 'Active', 9, 0, NULL, '2026-10-24 11:51:15');

-- --------------------------------------------------------

--
-- Table structure for table `vacancies`
--

CREATE TABLE `vacancies` (
  `vacancy_id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `requirements` text NOT NULL,
  `deadline` date NOT NULL,
  `status` enum('Draft','Published','Closed') NOT NULL DEFAULT 'Draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`app_id`),
  ADD KEY `vacancy_id` (`vacancy_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`att_id`),
  ADD UNIQUE KEY `uq_attendance_emp_date` (`emp_id`,`attendance_date`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`college_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `uq_department_college` (`dept_id`,`college_id`),
  ADD KEY `fk_department_college` (`college_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`emp_id`),
  ADD UNIQUE KEY `staff_no` (`staff_no`),
  ADD KEY `fk_employee_college` (`college_id`),
  ADD KEY `fk_employee_department_college` (`dept_id`,`college_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`eval_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `evaluator_id` (`evaluator_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`not_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`pay_id`),
  ADD UNIQUE KEY `uq_payroll_emp_month` (`emp_id`,`pay_month`);

--
-- Indexes for table `role_requests`
--
ALTER TABLE `role_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_role_request_user` (`user_id`),
  ADD KEY `fk_role_request_reviewer` (`reviewed_by`);

--
-- Indexes for table `profile_requests`
--
ALTER TABLE `profile_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_profile_request_employee` (`emp_id`),
  ADD KEY `fk_profile_request_user` (`user_id`),
  ADD KEY `fk_profile_request_reviewer` (`reviewed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `emp_id` (`emp_id`);

--
-- Indexes for table `vacancies`
--
ALTER TABLE `vacancies`
  ADD PRIMARY KEY (`vacancy_id`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `app_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `att_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `college_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `emp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `not_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `pay_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_requests`
--
ALTER TABLE `role_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `profile_requests`
--
ALTER TABLE `profile_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `vacancies`
--
ALTER TABLE `vacancies`
  MODIFY `vacancy_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`vacancy_id`) REFERENCES `vacancies` (`vacancy_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_department_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employee_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_employee_department` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_employee_department_college` FOREIGN KEY (`dept_id`,`college_id`) REFERENCES `departments` (`dept_id`, `college_id`) ON DELETE SET NULL;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `role_requests`
--
ALTER TABLE `role_requests`
  ADD CONSTRAINT `fk_role_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_role_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `profile_requests`
--
ALTER TABLE `profile_requests`
  ADD CONSTRAINT `fk_profile_request_employee` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_profile_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_profile_request_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_employee` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL;

--
-- Constraints for table `vacancies`
--
ALTER TABLE `vacancies`
  ADD CONSTRAINT `vacancies_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
