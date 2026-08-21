-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 21, 2026 at 12:28 PM
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
-- Database: `tarin_morales_dental_clinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_admins`
--

CREATE TABLE `tbl_admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_appointments`
--

CREATE TABLE `tbl_appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `procedure_name` varchar(150) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','confirmed','for_dentist','in_progress','completed','cancelled','no_show') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dental_records`
--

CREATE TABLE `tbl_dental_records` (
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `treatment_id` int(11) NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_summary` text NOT NULL,
  `remarks` text DEFAULT NULL,
  `record_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dentists`
--

CREATE TABLE `tbl_dentists` (
  `dentist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `license_no` varchar(50) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patients`
--

CREATE TABLE `tbl_patients` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `birth_date` date NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `date_registered` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patient_teeth`
--

CREATE TABLE `tbl_patient_teeth` (
  `patient_tooth_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `tooth_number` varchar(10) NOT NULL,
  `tooth_condition` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_tooth_conditions`
--

CREATE TABLE `tbl_tooth_conditions` (
  `tooth_condition_id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `tooth_number` varchar(10) NOT NULL,
  `tooth_condition` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_treatments`
--

CREATE TABLE `tbl_treatments` (
  `treatment_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `procedure_name` varchar(150) NOT NULL,
  `treatment_notes` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('in_progress','completed') DEFAULT 'in_progress',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','dentist','patient') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_admins`
--
ALTER TABLE `tbl_admins`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appointments_patient` (`patient_id`),
  ADD KEY `fk_appointments_dentist` (`dentist_id`),
  ADD KEY `fk_appointments_admin` (`admin_id`);

--
-- Indexes for table `tbl_dental_records`
--
ALTER TABLE `tbl_dental_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `fk_dental_records_patient` (`patient_id`),
  ADD KEY `fk_dental_records_appointment` (`appointment_id`),
  ADD KEY `fk_dental_records_dentist` (`dentist_id`),
  ADD KEY `fk_dental_records_treatment` (`treatment_id`);

--
-- Indexes for table `tbl_dentists`
--
ALTER TABLE `tbl_dentists`
  ADD PRIMARY KEY (`dentist_id`),
  ADD UNIQUE KEY `license_no` (`license_no`),
  ADD KEY `fk_dentists_user` (`user_id`);

--
-- Indexes for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `fk_patients_user` (`user_id`);

--
-- Indexes for table `tbl_patient_teeth`
--
ALTER TABLE `tbl_patient_teeth`
  ADD PRIMARY KEY (`patient_tooth_id`),
  ADD UNIQUE KEY `uq_patient_tooth` (`patient_id`,`tooth_number`);

--
-- Indexes for table `tbl_tooth_conditions`
--
ALTER TABLE `tbl_tooth_conditions`
  ADD PRIMARY KEY (`tooth_condition_id`),
  ADD KEY `fk_tooth_conditions_record` (`record_id`);

--
-- Indexes for table `tbl_treatments`
--
ALTER TABLE `tbl_treatments`
  ADD PRIMARY KEY (`treatment_id`),
  ADD KEY `fk_treatments_appointment` (`appointment_id`),
  ADD KEY `fk_treatments_dentist` (`dentist_id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_admins`
--
ALTER TABLE `tbl_admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_dental_records`
--
ALTER TABLE `tbl_dental_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_dentists`
--
ALTER TABLE `tbl_dentists`
  MODIFY `dentist_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_patient_teeth`
--
ALTER TABLE `tbl_patient_teeth`
  MODIFY `patient_tooth_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_tooth_conditions`
--
ALTER TABLE `tbl_tooth_conditions`
  MODIFY `tooth_condition_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_treatments`
--
ALTER TABLE `tbl_treatments`
  MODIFY `treatment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  ADD CONSTRAINT `fk_appointments_admin` FOREIGN KEY (`admin_id`) REFERENCES `tbl_admins` (`admin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `tbl_dentists` (`dentist_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_dental_records`
--
ALTER TABLE `tbl_dental_records`
  ADD CONSTRAINT `fk_dental_records_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dental_records_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `tbl_dentists` (`dentist_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dental_records_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dental_records_treatment` FOREIGN KEY (`treatment_id`) REFERENCES `tbl_treatments` (`treatment_id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_dentists`
--
ALTER TABLE `tbl_dentists`
  ADD CONSTRAINT `fk_dentists_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  ADD CONSTRAINT `fk_patients_user` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_patient_teeth`
--
ALTER TABLE `tbl_patient_teeth`
  ADD CONSTRAINT `fk_patient_teeth_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_tooth_conditions`
--
ALTER TABLE `tbl_tooth_conditions`
  ADD CONSTRAINT `fk_tooth_conditions_record` FOREIGN KEY (`record_id`) REFERENCES `tbl_dental_records` (`record_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_treatments`
--
ALTER TABLE `tbl_treatments`
  ADD CONSTRAINT `fk_treatments_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_treatments_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `tbl_dentists` (`dentist_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
