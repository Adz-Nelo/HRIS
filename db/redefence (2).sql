-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 03, 2026 at 06:12 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `redefence`
--

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `department_id` varchar(20) NOT NULL COMMENT 'Unique department identifier, e.g., BBH, BCC',
  `department_name` varchar(255) NOT NULL COMMENT 'Full department name, e.g., Bacolod Boys Home',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp when department was created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores all city departments';

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`department_id`, `department_name`, `created_at`) VALUES
('BBH', 'Bacolod Boys Home', '2025-01-09 18:52:03'),
('BCC', 'Bacolod City College', '2025-01-09 18:52:14'),
('BCVO', 'Bacolod City Veterinary Office', '2025-01-09 19:06:00'),
('BCYDO', 'Bacolod City Youth Development Office', '2025-01-09 18:52:27'),
('BENRO', 'Bacolod Environment and Natural Resources Office', '2025-01-09 18:52:46'),
('BHA', 'Bacolod Housing Authority', '2025-01-09 18:53:08'),
('BTTM', 'Bacolod Traffic and Transport Management', '2025-01-09 18:53:34'),
('CAO', 'City Administrator Office', '2025-01-09 18:49:50'),
('CBO', 'City Budget Office', '2025-01-09 18:54:03'),
('CCLDO', 'City Cooperative and Livelihood Development Office', '2025-01-09 18:54:32'),
('CEO', 'City Engineer Office', '2025-01-09 18:55:46'),
('CHO', 'City Health Office', '2025-01-09 18:55:58'),
('CHO - Infirmary', 'City Health Office - Infirmary', '2025-01-09 18:59:14'),
('CHO - Lying In', 'City Health Office - Lying In', '2025-01-09 19:00:07'),
('CHO-Mental Care', 'City Health Office Mental Care', '2025-01-09 19:00:50'),
('City Accountant Offi', 'City Accountant Office', '2025-01-09 19:09:04'),
('City Library', 'City Library', '2025-01-09 18:59:54'),
('City Superintendent', 'City Superintendent of Schools', '2025-01-09 18:57:22'),
('CLO', 'City Legal Office', '2025-01-09 18:59:46'),
('CMO', 'Office of the Mayor', '2025-01-09 19:00:34'),
('CPDO', 'City Planning and Development Office', '2025-01-09 18:56:12'),
('CPO', 'City Prosecutor Office', '2025-01-09 19:04:44'),
('CSWDO', 'City Social Welfare and Development Office', '2025-01-09 18:56:32'),
('CTO', 'City Treasurer’s Office', '2025-01-09 18:56:45'),
('DA', 'Department of Agriculture', '2025-01-09 18:57:00'),
('DILG', 'Development of the Interior and Local Government', '2025-01-09 18:57:35'),
('DRRMO', 'Disaster Risk Reduction and Management Office', '2025-01-09 18:57:50'),
('GSO', 'City General Services Office', '2025-01-09 18:58:08'),
('HRMS', 'Human Resource Management Services', '2025-01-09 18:58:27'),
('IAO', 'Internal Audit Office', '2025-01-09 18:58:41'),
('LEDIP', 'Department of Local Economic Development and Investment Promotions', '2025-01-09 18:59:30'),
('MASO', 'Management Audit Service Office', '2025-01-09 19:00:21'),
('MITCS', 'Management Information Technology and Computer Services', '2025-01-09 19:02:50'),
('OBO', 'Office of the City Building Official', '2025-01-09 19:03:22'),
('OCA', 'City Assessor Office', '2025-01-09 18:51:09'),
('OCCR', 'Office of the City Civil Registrar', '2025-01-09 18:55:32'),
('PAAD', 'Public Affairs and Assistance Division', '2025-01-09 19:03:38'),
('PESO', 'Public Employment Services', '2025-01-09 19:03:53'),
('POPCOM', 'City Population Office', '2025-01-09 19:04:06'),
('POSO', 'Public Order and Safety Office', '2025-01-09 19:04:31'),
('RD', 'City Registrar of Deeds', '2025-01-09 19:05:05'),
('SEEU', 'Socio-Economic Enterprise Unit', '2025-01-09 19:05:19'),
('SP', 'Sangguniang Panlungsod', '2025-01-09 19:05:37'),
('Tourism', 'Tourism', '2025-01-09 19:05:47');

-- --------------------------------------------------------

--
-- Table structure for table `detailed_department`
--

CREATE TABLE `detailed_department` (
  `detailed_department_id` varchar(20) NOT NULL COMMENT 'Unique identifier for detailed department',
  `detailed_department_name` varchar(255) NOT NULL COMMENT 'Full name of detailed department',
  `department_id` varchar(20) NOT NULL COMMENT 'Foreign key linking to main department',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp when detailed department was created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores detailed sub-units of each department';

--
-- Dumping data for table `detailed_department`
--

INSERT INTO `detailed_department` (`detailed_department_id`, `detailed_department_name`, `department_id`, `created_at`) VALUES
('BBH', 'Bacolod Boys Home', 'BBH', '2025-01-09 18:52:03'),
('BCC', 'Bacolod City College', 'BCC', '2025-01-09 18:52:14'),
('BCVO', 'Bacolod City Veterinary Office', 'BCVO', '2025-01-09 19:06:00'),
('BCYDO', 'Bacolod City Youth Development Office', 'BCYDO', '2025-01-09 18:52:27'),
('BENRO', 'Bacolod Environment and Natural Resources Office', 'BENRO', '2025-01-09 18:52:46'),
('BHA', 'Bacolod Housing Authority', 'BHA', '2025-01-09 18:53:08'),
('BTTM', 'Bacolod Traffic and Transport Management', 'BTTM', '2025-01-09 18:53:34'),
('CAO', 'City Administrator Office', 'CAO', '2025-01-09 18:49:50'),
('CBO', 'City Budget Office', 'CBO', '2025-01-09 18:54:03'),
('CCLDO', 'City Cooperative and Livelihood Development Office', 'CCLDO', '2025-01-09 18:54:32'),
('CEO', 'City Engineer Office', 'CEO', '2025-01-09 18:55:46'),
('CHO', 'City Health Office', 'CHO', '2025-01-09 18:55:58'),
('CHO - Infirmary', 'City Health Office - Infirmary', 'CHO - Infirmary', '2025-01-09 18:59:14'),
('CHO - Lying In', 'City Health Office - Lying In', 'CHO - Lying In', '2025-01-09 19:00:07'),
('CHO-Mental Care', 'City Health Office Mental Care', 'CHO-Mental Care', '2025-01-09 19:00:50'),
('City Accountant Offi', 'City Accountant Office', 'City Accountant Offi', '2025-01-09 19:09:04'),
('City Library', 'City Library', 'City Library', '2025-01-09 18:59:54'),
('City Superintendent', 'City Superintendent of Schools', 'City Superintendent ', '2025-01-09 18:57:22'),
('CLO', 'City Legal Office', 'CLO', '2025-01-09 18:59:46'),
('CMO', 'Office of the Mayor', 'CMO', '2025-01-09 19:00:34'),
('CPDO', 'City Planning and Development Office', 'CPDO', '2025-01-09 18:56:12'),
('CPO', 'City Prosecutor Office', 'CPO', '2025-01-09 19:04:44'),
('CSWDO', 'City Social Welfare and Development Office', 'CSWDO', '2025-01-09 18:56:32'),
('CTO', 'City Treasurer’s Office', 'CTO', '2025-01-09 18:56:45'),
('DA', 'Department of Agriculture', 'DA', '2025-01-09 18:57:00'),
('DILG', 'Development of the Interior and Local Government', 'DILG', '2025-01-09 18:57:35'),
('DRRMO', 'Disaster Risk Reduction and Management Office', 'DRRMO', '2025-01-09 18:57:50'),
('GSO', 'City General Services Office', 'GSO', '2025-01-09 18:58:08'),
('HRMS', 'Human Resource Management Services', 'HRMS', '2025-01-09 18:58:27'),
('IAO', 'Internal Audit Office', 'IAO', '2025-01-09 18:58:41'),
('LEDIP', 'Department of Local Economic Development and Investment Promotions', 'LEDIP', '2025-01-09 18:59:30'),
('MASO', 'Management Audit Service Office', 'MASO', '2025-01-09 19:00:21'),
('MITCS', 'Management Information Technology and Computer Services', 'MITCS', '2025-01-09 19:02:50'),
('OBO', 'Office of the City Building Official', 'OBO', '2025-01-09 19:03:22'),
('OCA', 'City Assessor Office', 'OCA', '2025-01-09 18:51:09'),
('OCCR', 'Office of the City Civil Registrar', 'OCCR', '2025-01-09 18:55:32'),
('PAAD', 'Public Affairs and Assistance Division', 'PAAD', '2025-01-09 19:03:38'),
('PESO', 'Public Employment Services', 'PESO', '2025-01-09 19:03:53'),
('POPCOM', 'City Population Office', 'POPCOM', '2025-01-09 19:04:06'),
('POSO', 'Public Order and Safety Office', 'POSO', '2025-01-09 19:04:31'),
('RD', 'City Registrar of Deeds', 'RD', '2025-01-09 19:05:05'),
('SEEU', 'Socio-Economic Enterprise Unit', 'SEEU', '2025-01-09 19:05:19'),
('SP', 'Sangguniang Panlungsod', 'SP', '2025-01-09 19:05:37'),
('Tourism', 'Tourism', 'Tourism', '2025-01-09 19:05:47');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `employee_id` int(11) NOT NULL COMMENT 'Unique ID for each employee, assigned manually',
  `first_name` varchar(100) DEFAULT NULL COMMENT 'Employee first name',
  `middle_name` varchar(100) DEFAULT NULL COMMENT 'Employee middle name',
  `last_name` varchar(100) DEFAULT NULL COMMENT 'Employee last name',
  `extension` varchar(50) DEFAULT NULL COMMENT 'Name extension (e.g., Jr., Sr.)',
  `suffix` varchar(50) DEFAULT NULL COMMENT 'Suffix for the name (if applicable)',
  `profile_pic` varchar(255) DEFAULT NULL COMMENT 'Path to profile picture',
  `birth_date` date DEFAULT NULL COMMENT 'Employee birth date',
  `gender` enum('Male','Female','Other') DEFAULT 'Male' COMMENT 'Gender of the employee',
  `department_id` varchar(20) NOT NULL COMMENT 'ID of the department the employee belongs to',
  `detailed_department_id` varchar(20) DEFAULT NULL COMMENT 'ID of the sub-department / unit',
  `position` varchar(150) DEFAULT NULL COMMENT 'Job position of the employee',
  `role` enum('Regular Employee','HR Officer','HR Staff','Department Head','Admin') DEFAULT 'Regular Employee' COMMENT 'Role of the employee in the system',
  `status` enum('Active','Inactive','Retired') NOT NULL DEFAULT 'Active' COMMENT 'Current employment status: Active, Inactive (resigned), or Retired',
  `salary` int(11) DEFAULT NULL COMMENT 'Employee salary in whole numbers',
  `email` varchar(150) DEFAULT NULL COMMENT 'Employee email address',
  `contact_number` varchar(20) DEFAULT NULL COMMENT 'Employee contact number',
  `password` varchar(255) NOT NULL DEFAULT '',
  `google2fa_secret` varchar(255) DEFAULT NULL COMMENT 'Stores Google Authenticator 2FA secret',
  `google2fa_expiry` datetime DEFAULT NULL COMMENT 'Expiration timestamp for 2FA verification',
  `reset_token` varchar(255) DEFAULT NULL COMMENT 'Token for password reset',
  `reset_token_expiry` datetime DEFAULT NULL COMMENT 'Expiration date/time for reset token',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp when the employee record was created',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Timestamp when the employee record was last updated',
  `last_login` timestamp NULL DEFAULT NULL COMMENT 'Last login timestamp',
  `last_active` timestamp NULL DEFAULT NULL COMMENT 'Last activity timestamp in the system',
  `authorized_officer_name` varchar(150) DEFAULT NULL COMMENT 'Name of the authorized officer approving the employee assignment',
  `authorized_officer_position` varchar(150) DEFAULT NULL COMMENT 'Position / title of the authorized officer',
  `authorized_officer_signature` varchar(255) DEFAULT NULL COMMENT 'Signature image / path of the authorized officer',
  `authorized_officer_sign_date` date DEFAULT NULL COMMENT 'Date when the authorized officer signed / approved',
  `authorized_official_name` varchar(150) DEFAULT NULL COMMENT 'Name of the higher-level official approving the employee assignment',
  `authorized_official_position` varchar(150) DEFAULT NULL COMMENT 'Position / title of the authorized official',
  `authorized_official_signature` varchar(255) DEFAULT NULL COMMENT 'Signature image / path of the authorized official',
  `authorized_official_sign_date` date DEFAULT NULL COMMENT 'Date when the authorized official signed / approved',
  `two_fa_enabled` tinyint(1) DEFAULT 0 COMMENT '1 if employee has completed 2FA setup, 0 otherwise'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Employee table: stores all employees including HR Staff, HR Officer, Department Head, Admin, and Regular Employees. Includes CSC Form 6 approvals, 2FA, and audit info.';

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`employee_id`, `first_name`, `middle_name`, `last_name`, `extension`, `suffix`, `profile_pic`, `birth_date`, `gender`, `department_id`, `detailed_department_id`, `position`, `role`, `status`, `salary`, `email`, `contact_number`, `password`, `google2fa_secret`, `google2fa_expiry`, `reset_token`, `reset_token_expiry`, `created_at`, `updated_at`, `last_login`, `last_active`, `authorized_officer_name`, `authorized_officer_position`, `authorized_officer_signature`, `authorized_officer_sign_date`, `authorized_official_name`, `authorized_official_position`, `authorized_official_signature`, `authorized_official_sign_date`, `two_fa_enabled`) VALUES
(1, 'Chals Remdelle', 'Dolendo', 'Castijon', '', '', NULL, '2002-04-26', 'Male', 'MITCS', '', 'SYSTEM ANALYST IV', 'Admin', 'Active', 30000, 'chalscastijon@gmail.com', '09514843052', '$2y$10$ObM2GJFiMzJwR2zFovQXNeJyX8pWRmMBsSsHzne1/u3YiJPURSI6K', NULL, NULL, '340916', '2025-12-22 06:39:53', '2025-12-18 06:53:46', '2026-01-02 17:03:00', '2026-01-02 17:03:00', '2026-01-02 17:03:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(2, 'Rovic', 'Dolendo', 'Castijon', '', NULL, NULL, '1982-10-07', 'Female', 'HRMS', NULL, 'UTILITY WORKER 1', 'Regular Employee', 'Active', 13780, 'roviccastijon7@gmail.com', '09514843052', '$2y$10$oQu0P.wYpTg.plOwYt.9a.LF4GaaI/Ja3fFEJuhv9Qb/Ha0NvcOJi', NULL, NULL, NULL, NULL, '2025-12-18 06:54:49', '2026-01-02 15:35:14', '2026-01-02 15:35:08', '2026-01-02 15:35:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(3, 'Crisyl', 'Esona', 'Urbanozo', '', '', NULL, '2003-06-24', 'Female', 'HRMS', '', 'OFFICER III', 'HR Staff', 'Active', 15000, 'kringzurbanozo@gmail.com', '09770663154', '$2y$10$BXMbgmvcT6GnQrOJWOqG9eY4tmmb16F6zmZ5GVSPe/MlZGRXMUKtq', NULL, NULL, '708341', '2025-12-20 13:43:49', '2025-12-18 06:56:58', '2026-01-02 15:55:32', '2026-01-02 15:55:02', '2026-01-02 15:55:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(4, 'Jose MA. Daniel', 'Magbanua', 'Evidente', '', '', NULL, '1972-11-07', 'Male', 'HRMS', '', 'OFFICER IV', 'HR Officer', 'Active', 48000, 'Danielevidente@gmail.com', '09514842758', '$2y$10$Gr8lNY1nhAppf2ocpwJK9.A/P.o8HJDValvdYjOiUkG5Rya4RWRWe', NULL, NULL, NULL, NULL, '2025-12-18 06:59:58', '2026-01-01 16:48:59', '2026-01-01 16:48:53', '2026-01-01 16:48:59', NULL, NULL, '/HRIS/assets/signatures/sig_4_1766298029.jpg', NULL, NULL, NULL, NULL, NULL, 0),
(5, 'Erman', 'A.', 'Aguire', '', '', NULL, '1978-11-15', 'Male', 'HRMS', 'BENRO', 'CHRMO ', 'Department Head', 'Active', 19000, 'Ermanaguire@gmail.com', '09770663154', '$2y$10$1Mz0a5tmT9/zCru4qW9xTenK71IPA45IRkabyXSGDGbGrA1V.NRWy', NULL, NULL, NULL, NULL, '2025-12-18 07:02:30', '2026-01-01 16:49:11', '2026-01-01 16:48:59', '2026-01-01 16:49:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(6, 'Shanna Guile', 'Guiron', 'Gonzaga', NULL, '', NULL, '2003-03-31', 'Female', 'HRMS', NULL, NULL, 'Regular Employee', 'Active', 35000, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-20 04:18:06', '2025-12-20 04:18:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(7, 'Jeevic', 'M', 'Magnabe', '', '', NULL, '1984-01-20', 'Female', 'HRMS', '', '', 'Regular Employee', 'Active', 19000, 'jeevicmagnabe@gmail.com', '09514843052', '', NULL, NULL, NULL, NULL, '2025-12-20 04:26:44', '2025-12-21 01:02:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(8, 'Arjulien ', '', 'Gulmatico', NULL, '', NULL, NULL, 'Male', 'City Accountant Offi', NULL, NULL, 'Regular Employee', 'Active', NULL, 'yhangi@gmail.com', NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 01:32:16', '2025-12-21 01:58:48', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(9, 'Eliza Kammie', '', 'Nang', NULL, '', NULL, NULL, 'Male', 'CEO', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 02:58:54', '2025-12-21 02:58:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(10, 'Sharon', 'D', 'Daliva', NULL, '', NULL, NULL, 'Male', 'CEO', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 02:59:25', '2025-12-21 02:59:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(11, 'Rose', '', 'Cubian', NULL, '', NULL, NULL, 'Male', 'CHO-Mental Care', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 02:59:53', '2025-12-21 02:59:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(12, 'Annie Rose', '', 'Yanson', NULL, '', NULL, NULL, 'Male', 'BENRO', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:08:10', '2025-12-21 03:08:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(13, 'Ella', '', 'Poledo', NULL, '', NULL, NULL, 'Male', 'CAO', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:09:08', '2025-12-21 03:09:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(14, 'Avegail', 'Cruz', 'Acosta', NULL, '', NULL, NULL, 'Male', 'GSO', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:09:28', '2025-12-21 03:09:28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(15, 'Raynalyn', '', 'Magtulis', NULL, '', NULL, '2004-11-11', 'Female', 'BCC', NULL, NULL, 'Regular Employee', 'Active', NULL, 'chalscastijon@gmail.com', '09514842758', '$2y$10$wWhRJm8x/O1yoCsmO4w9u.fHpiKlk6afBx6DbN6BB/yDHmrLPXJzu', 'G3VT2BLG3FUEL66TQZSA5QDBXR2FSMQL', NULL, '340916', '2025-12-22 06:39:53', '2025-12-21 03:11:24', '2025-12-21 21:24:53', '2025-12-21 08:20:49', '2025-12-21 08:22:27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(16, 'Ken Arvin', '', 'Catoto', NULL, '', NULL, NULL, 'Male', '1', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:31:13', '2025-12-21 03:31:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(17, 'Darwin', 'A', 'Corpuz', NULL, '', NULL, NULL, 'Male', '1', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:31:13', '2025-12-21 03:31:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(18, 'Luke', '', 'Delos Reyes', NULL, '', NULL, NULL, 'Male', '1', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:31:13', '2025-12-21 03:31:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(19, 'Mathew', '', 'Delos Reyes', NULL, '', NULL, NULL, 'Male', '1', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:31:13', '2025-12-21 03:31:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(20, 'Micheal', 'F', 'Farrinas', NULL, '', NULL, NULL, 'Male', '1', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:31:13', '2025-12-21 03:31:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(21, 'Nino', 'T', 'Villarna', NULL, '', NULL, NULL, 'Male', '1', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:31:13', '2025-12-21 03:31:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(22, 'Ma. Danica', 'D', 'Alfonso', NULL, '', NULL, NULL, 'Male', '1', NULL, NULL, 'Regular Employee', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 03:31:13', '2025-12-21 03:31:13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(25, 'Vladimir', '', 'Ledesma', NULL, '', NULL, NULL, 'Male', 'BCC', NULL, NULL, 'Department Head', 'Active', NULL, NULL, NULL, '$2y$10$V9joJkIqmvYTdiaid46v3eA9e9KZHzQlU4xR1D6x7BKCQBNrFLbXC', NULL, NULL, NULL, NULL, '2025-12-21 08:24:49', '2025-12-21 08:30:01', '2025-12-21 08:29:04', '2025-12-21 08:30:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(26, 'Shane', 'Maureen', 'Cantiller', NULL, '', NULL, NULL, 'Male', 'BCC', NULL, NULL, 'Admin', 'Active', NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, '2025-12-21 08:31:51', '2025-12-21 08:31:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0),
(1005, 'bea', '', 'sample', NULL, '', NULL, NULL, 'Male', 'POPCOM', NULL, NULL, 'Regular Employee', 'Active', NULL, 'chalscastijon@gmail.com', NULL, '$2y$10$ZYPwAcRbrVgLMIbGJWqlIenpfFnIBS9ukvapBIFJaC15CFtOS.pja', '6NU4GT5OSO7F54STTDFKLXNM7XFSYUGZ', NULL, NULL, NULL, '2025-12-21 21:27:21', '2025-12-21 21:29:56', '2025-12-21 21:29:56', '2025-12-21 21:29:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `employee_dependents`
--

CREATE TABLE `employee_dependents` (
  `dependent_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `dependent_name` varchar(255) NOT NULL,
  `relationship` varchar(100) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_dependents`
--

INSERT INTO `employee_dependents` (`dependent_id`, `employee_id`, `dependent_name`, `relationship`, `birth_date`, `created_at`) VALUES
(1, 1, 'Crisel Castijon', 'Daughter', '2020-07-22', '2025-12-20 05:08:56'),
(2, 2, 'Chals Castijon', 'Son', '2020-07-22', '2025-12-20 05:09:39');

-- --------------------------------------------------------

--
-- Table structure for table `leave_application`
--

CREATE TABLE `leave_application` (
  `application_id` int(11) NOT NULL,
  `reference_no` varchar(100) NOT NULL COMMENT 'Format: LV-YYYY-LASTNAME-INITIALS-GENDER-ID',
  `employee_id` int(11) NOT NULL COMMENT 'Refers to employee.employee_id',
  `leave_type_id` int(11) NOT NULL,
  `other_leave_description` varchar(255) DEFAULT NULL,
  `leave_detail_id` int(11) DEFAULT NULL,
  `details_description` varchar(255) DEFAULT NULL,
  `date_filing` date NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `working_days` decimal(5,3) DEFAULT 0.000,
  `commutation` enum('Requested','Not Requested') DEFAULT 'Not Requested',
  `applicant_signature` varchar(255) DEFAULT NULL COMMENT 'Path to the employee signature image',
  `applicant_sign_date` datetime DEFAULT NULL COMMENT 'Timestamp when the employee submitted',
  `status` enum('Pending','HR Staff Reviewed','Officer Recommended','Approved','Rejected','Cancelled','Cancellation Pending') DEFAULT 'Pending',
  `hr_staff_id` int(11) DEFAULT NULL,
  `hr_staff_reviewed_at` datetime DEFAULT NULL,
  `authorized_officer_id` int(11) DEFAULT NULL,
  `authorized_officer_name` varchar(150) DEFAULT NULL,
  `authorized_officer_position` varchar(150) DEFAULT NULL,
  `authorized_officer_signature` varchar(255) DEFAULT NULL,
  `authorized_officer_sign_date` datetime DEFAULT NULL,
  `authorized_official_id` int(11) DEFAULT NULL,
  `authorized_official_name` varchar(150) DEFAULT NULL,
  `authorized_official_position` varchar(150) DEFAULT NULL,
  `authorized_official_signature` varchar(255) DEFAULT NULL,
  `authorized_official_sign_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `cancel_proof_path` varchar(255) DEFAULT NULL,
  `cancellation_requested_at` datetime DEFAULT NULL,
  `cancel_hr_validated_by` int(11) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_application`
--

INSERT INTO `leave_application` (`application_id`, `reference_no`, `employee_id`, `leave_type_id`, `other_leave_description`, `leave_detail_id`, `details_description`, `date_filing`, `start_date`, `end_date`, `working_days`, `commutation`, `applicant_signature`, `applicant_sign_date`, `status`, `hr_staff_id`, `hr_staff_reviewed_at`, `authorized_officer_id`, `authorized_officer_name`, `authorized_officer_position`, `authorized_officer_signature`, `authorized_officer_sign_date`, `authorized_official_id`, `authorized_official_name`, `authorized_official_position`, `authorized_official_signature`, `authorized_official_sign_date`, `rejection_reason`, `cancel_reason`, `cancel_proof_path`, `cancellation_requested_at`, `cancel_hr_validated_by`, `rejected_by`, `created_at`, `updated_at`) VALUES
(1, 'LV-2025-B8425674', 2, 1, NULL, 2, NULL, '2025-12-19', '2025-12-18', '2025-12-20', '2.000', 'Requested', NULL, NULL, 'Cancelled', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2025-12-20 21:23:35', ' [BALANCE_RECALLED]', 'sda', '../../uploads/cancellation_proofs/CANCEL_LV-2025-B8425674_1766341955.png', '2025-12-22 02:32:35', 3, NULL, '2025-12-18 20:02:46', '2025-12-21 18:26:44'),
(2, 'LV-2025-E50A7816', 2, 8, NULL, 6, NULL, '2025-12-19', '2025-12-24', '2025-12-26', '3.000', 'Not Requested', NULL, NULL, 'Approved', 3, '2025-12-21 00:11:47', 4, 'Jose MA. Daniel Evidente', 'Authorized Officer', '', '2025-12-21 14:22:38', 5, 'Erman Aguire', 'CHRMO ', '', '2025-12-22 00:56:05', '', NULL, NULL, NULL, NULL, NULL, '2025-12-18 22:32:10', '2025-12-21 08:56:05'),
(3, 'LV-2025-9493D49E', 2, 12, NULL, 1, NULL, '2025-12-19', '2025-12-19', '2025-12-20', '1.000', 'Not Requested', NULL, NULL, 'Approved', NULL, NULL, 4, 'Jose MA. Daniel Evidente', 'HR Officer', NULL, '2025-12-21 00:54:55', 5, 'Erman Aguire', 'Department Head', NULL, '2025-12-21 23:17:30', '', NULL, NULL, NULL, NULL, NULL, '2025-12-18 22:36:41', '2025-12-21 07:17:30'),
(4, 'LV-2025-F96EBC0C', 2, 1, NULL, NULL, NULL, '2025-12-20', '2025-12-22', '2025-12-26', '5.000', 'Not Requested', NULL, NULL, 'Cancelled', 3, '2025-12-22 10:29:22', 4, 'Jose MA. Daniel Evidente', 'OFFICER IV', '', '2025-12-21 14:27:47', 5, 'Erman Aguire', 'CHRMO ', '', '2025-12-21 23:30:45', ' [BALANCE_RECALLED]', 'recall', '../../uploads/cancellation_proofs/CANCEL_LV-2025-F96EBC0C_1766341478.png', '2025-12-22 02:24:38', 3, NULL, '2025-12-20 08:13:51', '2025-12-21 18:29:46'),
(5, 'LV-2025-D01C9E67', 2, 12, NULL, 2, NULL, '2025-12-21', '2025-12-21', '2025-12-22', '1.000', 'Not Requested', NULL, NULL, 'HR Staff Reviewed', 3, '2025-12-21 14:31:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-20 22:28:27', '2025-12-20 22:31:09'),
(6, 'LV-2025-10D8984B', 2, 12, NULL, 2, NULL, '2025-12-21', '2025-12-29', '2025-12-30', '2.000', 'Requested', NULL, NULL, 'HR Staff Reviewed', 3, '2025-12-21 22:43:48', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-20 22:28:42', '2025-12-21 06:43:48'),
(7, 'LV-2025-0882BFAE', 15, 4, NULL, NULL, NULL, '2025-12-21', '2025-12-23', '2025-12-24', '2.000', 'Not Requested', NULL, NULL, 'Approved', 3, '2025-12-22 00:27:50', 4, 'Jose MA. Daniel Evidente', 'OFFICER IV', '', '2025-12-22 00:28:51', 25, 'Vladimir Ledesma', NULL, '', '2025-12-22 00:29:15', '', NULL, NULL, NULL, NULL, NULL, '2025-12-21 08:22:06', '2025-12-21 08:29:15'),
(8, 'LV-2025-39FE81AA', 2, 10, NULL, 8, NULL, '2025-12-21', '2025-12-23', '2025-12-23', '1.000', 'Not Requested', NULL, NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-21 09:08:55', '2025-12-21 09:08:55');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balance`
--

CREATE TABLE `leave_balance` (
  `leave_balance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL COMMENT 'Refers to employee.employee_id',
  `month_year` date NOT NULL COMMENT 'The month/year this credit record represents',
  `vacation_leave` decimal(6,3) DEFAULT 0.000,
  `sick_leave` decimal(6,3) DEFAULT 0.000,
  `earned_vacation` decimal(6,3) DEFAULT 1.250,
  `earned_sick` decimal(6,3) DEFAULT 1.250,
  `prev_month_vacation` decimal(6,3) DEFAULT 0.000,
  `prev_month_sick` decimal(6,3) DEFAULT 0.000,
  `maternity_leave` decimal(6,3) DEFAULT 0.000,
  `paternity_leave` decimal(6,3) DEFAULT 0.000,
  `special_leave` decimal(6,3) DEFAULT 0.000,
  `solo_parent_leave` decimal(6,3) DEFAULT 0.000,
  `calamity_leave` decimal(6,3) DEFAULT 0.000,
  `study_leave` decimal(6,3) DEFAULT 0.000,
  `is_latest` tinyint(1) DEFAULT 1 COMMENT '1 if this is the current active balance',
  `last_credit_month` date DEFAULT NULL,
  `forced_leave_deducted_year` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balance`
--

INSERT INTO `leave_balance` (`leave_balance_id`, `employee_id`, `month_year`, `vacation_leave`, `sick_leave`, `earned_vacation`, `earned_sick`, `prev_month_vacation`, `prev_month_sick`, `maternity_leave`, `paternity_leave`, `special_leave`, `solo_parent_leave`, `calamity_leave`, `study_leave`, `is_latest`, `last_credit_month`, `forced_leave_deducted_year`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 5, '2025-12-20', '100.000', '100.000', '1.250', '1.250', '0.000', '0.000', '0.000', '0.000', '0.000', '0.000', '0.000', '0.000', 1, NULL, NULL, 'for nothing', '2025-12-20 07:10:20', '2025-12-20 07:10:20'),
(2, 2, '2025-12-20', '107.000', '100.000', '1.250', '1.250', '0.000', '0.000', '0.000', '0.000', '0.000', '0.000', '0.000', '-3.000', 1, NULL, NULL, 'updated', '2025-12-20 07:10:40', '2025-12-21 18:29:46');

-- --------------------------------------------------------

--
-- Table structure for table `leave_details`
--

CREATE TABLE `leave_details` (
  `leave_details_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL COMMENT 'Links to leave_types.leave_type_id',
  `name` varchar(255) NOT NULL COMMENT 'Sub-option name',
  `description` text DEFAULT NULL,
  `requires_specification` tinyint(1) DEFAULT 0 COMMENT '1 if user must type additional info',
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_details`
--

INSERT INTO `leave_details` (`leave_details_id`, `leave_type_id`, `name`, `description`, `requires_specification`, `status`, `created_at`) VALUES
(1, 1, 'Within the Philippines', NULL, 0, 1, '2025-12-18 19:02:23'),
(2, 1, 'Abroad', NULL, 1, 1, '2025-12-18 19:02:23'),
(3, 3, 'In Hospital', NULL, 1, 1, '2025-12-18 19:02:23'),
(4, 3, 'Out Patient', NULL, 1, 1, '2025-12-18 19:02:23'),
(5, 11, 'Special Leave Benefits for Women', NULL, 1, 1, '2025-12-18 19:02:23'),
(6, 8, 'Completion of Master\'s Degree', NULL, 0, 1, '2025-12-18 19:02:23'),
(7, 8, 'BAR/Board Examination Review', NULL, 0, 1, '2025-12-18 19:02:23'),
(8, 1, 'Monetization of Leave Credits', NULL, 0, 1, '2025-12-18 19:02:23'),
(9, 1, 'Terminal Leave', NULL, 0, 1, '2025-12-18 19:02:23');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_types_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL COMMENT 'Legal Basis (RA / CSC MC Numbers)',
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`leave_types_id`, `name`, `description`, `status`, `created_at`) VALUES
(1, 'Vacation Leave', 'Vacation Leave (Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', 1, '2025-12-18 19:21:43'),
(2, 'Mandatory/Forced Leave', 'Mandatory/Forced Leave (Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', 1, '2025-12-18 19:21:43'),
(3, 'Sick Leave', 'Sick Leave (Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', 1, '2025-12-18 19:21:43'),
(4, 'Maternity Leave', 'Maternity Leave (RA 11210 / IRR issued by CSC, DOLE and SSS)', 1, '2025-12-18 19:21:43'),
(5, 'Paternity Leave', 'Paternity Leave (RA 8187 / CSC MC No. 71, s. 1998, as amended)', 1, '2025-12-18 19:21:43'),
(6, 'Special Privilege Leave', 'Special Privilege Leave (Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', 1, '2025-12-18 19:21:43'),
(7, 'Solo Parent Leave', 'Solo Parent Leave (RA 8972 / CSC MC No. 8, s. 2004)', 1, '2025-12-18 19:21:43'),
(8, 'Study Leave', 'Study Leave (Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', 1, '2025-12-18 19:21:43'),
(9, '10-Day VAWC Leave', '10-Day VAWC Leave (RA 9262 / CSC MC No. 15, s. 2005)', 1, '2025-12-18 19:21:43'),
(10, 'Rehabilitation Privilege', 'Rehabilitation Privilege (Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)', 1, '2025-12-18 19:21:43'),
(11, 'Special Leave Benefits for Women', 'Special Leave Benefits for Women (RA 9710 / CSC MC No. 25, s. 2010)', 1, '2025-12-18 19:21:43'),
(12, 'Special Emergency (Calamity) Leave', 'Special Emergency (Calamity) Leave (CSC MC No. 2, s. 2012, as amended)', 1, '2025-12-18 19:21:43'),
(13, 'Adoption Leave', 'Adoption Leave (RA 8552)', 1, '2025-12-18 19:21:43'),
(14, 'Others', 'Manual entry: user can type leave type not listed above.', 1, '2025-12-18 19:21:43');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `login_attempt_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL COMMENT 'Employee ID attempting to login',
  `ip_address` varchar(45) NOT NULL COMMENT 'IP address of login attempt',
  `attempts` int(11) DEFAULT 0 COMMENT 'Number of failed attempts',
  `last_login` datetime DEFAULT NULL COMMENT 'Last login attempt timestamp',
  `status` enum('Success','Failed') DEFAULT 'Failed' COMMENT 'Status of login attempt',
  `access_status` enum('Block','Unblock','N/A') DEFAULT 'N/A' COMMENT 'Account access status',
  `user_agent` text DEFAULT NULL COMMENT 'Browser user agent string',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks login attempts and blocks accounts after multiple failures';

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`login_attempt_id`, `employee_id`, `ip_address`, `attempts`, `last_login`, `status`, `access_status`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 5, '::1', 0, '2026-01-02 00:48:59', 'Success', 'Unblock', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 08:53:40', '2026-01-01 16:48:59'),
(2, 2, '::1', 0, '2026-01-02 23:35:08', 'Success', 'Unblock', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 08:56:53', '2026-01-02 15:35:08'),
(5, 3, '::1', 0, '2026-01-02 23:55:02', 'Success', 'Unblock', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 18:09:06', '2026-01-02 15:55:02'),
(9, 1, '::1', 0, '2026-01-03 01:03:00', 'Success', 'Unblock', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 21:26:47', '2026-01-02 17:03:00'),
(10, 1005, '::1', 0, '2025-12-22 13:29:56', 'Success', 'Unblock', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 21:29:56', '2025-12-21 21:29:56'),
(20, 4, '::1', 0, '2026-01-02 00:48:53', 'Success', 'Unblock', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 21:55:36', '2026-01-01 16:48:53');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('Login','Leave Application','Leave Approval','Balance Update') DEFAULT 'Login',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `employee_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 1, '2026-01-01 16:11:27'),
(2, 3, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 1, '2026-01-01 16:11:27'),
(3, 4, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 0, '2026-01-01 16:11:27'),
(4, 26, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 0, '2026-01-01 16:11:27'),
(5, 1, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 1, '2026-01-01 16:12:35'),
(6, 3, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 1, '2026-01-01 16:12:35'),
(7, 4, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 0, '2026-01-01 16:12:35'),
(8, 26, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 0, '2026-01-01 16:12:35'),
(9, 1, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 1, '2026-01-02 15:25:09'),
(10, 3, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 1, '2026-01-02 15:25:09'),
(11, 4, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 0, '2026-01-02 15:25:09'),
(12, 26, 'Service Record Request', 'A new Service Record request has been submitted for retirement processing.\n\nEmployee ID: 2\n\nRequested by: Rovic Castijon\nDepartment: HRMS', '', 0, '2026-01-02 15:25:09');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'hero_section', '{\"title\":\"Redefence Systems\",\"subtitle\":\"Professional Security Solutions and Management\"}', '2025-12-21 02:55:14'),
(2, 'company_logo', 'assets/images/HRMS.png', '2025-12-21 02:55:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `detailed_department`
--
ALTER TABLE `detailed_department`
  ADD PRIMARY KEY (`detailed_department_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`employee_id`);

--
-- Indexes for table `employee_dependents`
--
ALTER TABLE `employee_dependents`
  ADD PRIMARY KEY (`dependent_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `leave_application`
--
ALTER TABLE `leave_application`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `reference_no` (`reference_no`),
  ADD KEY `fk_applicant_id` (`employee_id`);

--
-- Indexes for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD PRIMARY KEY (`leave_balance_id`),
  ADD KEY `fk_leave_balance_employee` (`employee_id`);

--
-- Indexes for table `leave_details`
--
ALTER TABLE `leave_details`
  ADD PRIMARY KEY (`leave_details_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_types_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`login_attempt_id`),
  ADD UNIQUE KEY `unique_employee_ip` (`employee_id`,`ip_address`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employee_dependents`
--
ALTER TABLE `employee_dependents`
  MODIFY `dependent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_application`
--
ALTER TABLE `leave_application`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `leave_balance`
--
ALTER TABLE `leave_balance`
  MODIFY `leave_balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_details`
--
ALTER TABLE `leave_details`
  MODIFY `leave_details_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_types_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `login_attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detailed_department`
--
ALTER TABLE `detailed_department`
  ADD CONSTRAINT `detailed_department_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_dependents`
--
ALTER TABLE `employee_dependents`
  ADD CONSTRAINT `employee_dependents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_application`
--
ALTER TABLE `leave_application`
  ADD CONSTRAINT `fk_applicant_id` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`);

--
-- Constraints for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD CONSTRAINT `fk_leave_balance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
