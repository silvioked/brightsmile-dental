-- ============================================================================
-- BrightSmile Dental Clinic - Shared Hosting / InfinityFree SQL Import Script
-- Instructions: Select your hosting database in phpMyAdmin, click "Import",
-- and upload this file. (No CREATE DATABASE / USE statements that fail on shared hosts)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `treatment_records`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `dentist_schedules`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- ----------------------------------------------------------------------------
-- 1. ROLES TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE `roles` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(32) NOT NULL UNIQUE COMMENT 'admin, dentist, receptionist, client',
    `name` VARCHAR(64) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `slug`, `name`, `description`) VALUES
(1, 'admin', 'Administrator', 'Full system access, staff and service management, reports'),
(2, 'dentist', 'Dentist', 'View personal schedule, patient history, and record treatments'),
(3, 'receptionist', 'Receptionist / Staff', 'Manage appointment requests, walk-ins, and rescheduling'),
(4, 'client', 'Client (Patient)', 'Book appointments and view personal dental history');

-- ----------------------------------------------------------------------------
-- 2. USERS TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` TINYINT UNSIGNED NOT NULL,
    `first_name` VARCHAR(64) NOT NULL,
    `last_name` VARCHAR(64) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `phone` VARCHAR(25) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `license_number` VARCHAR(64) DEFAULT NULL,
    `specialization` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_users_role_active` (`role_id`, `is_active`),
    INDEX `idx_users_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed demo accounts (Password for all demo accounts: password)
INSERT INTO `users` (`id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `license_number`, `specialization`) VALUES
(1, 1, 'System', 'Admin', 'admin@dentalclinic.local', '+639170000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL),
(2, 2, 'Elena', 'Reyes', 'dr.reyes@dentalclinic.local', '+639170000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PRC-DENT-44821', 'General Dentistry & Orthodontics'),
(3, 2, 'Marcus', 'Santos', 'dr.santos@dentalclinic.local', '+639170000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PRC-DENT-51902', 'Oral Surgery & Endodontics'),
(4, 3, 'Clara', 'Mendoza', 'frontdesk@dentalclinic.local', '+639170000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL),
(5, 4, 'Juan', 'Dela Cruz', 'patient@dentalclinic.local', '+639170000005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL);

-- ----------------------------------------------------------------------------
-- 3. SERVICES TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE `services` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_services_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `services` (`name`, `description`, `duration_minutes`, `price`) VALUES
('Oral Prophylaxis (Cleaning)', 'Routine dental cleaning and plaque removal.', 45, 1500.00),
('Simple Tooth Extraction', 'Removal of a visible, non-impacted tooth.', 45, 2000.00),
('Composite Dental Filling', 'Tooth-colored restoration for cavities.', 60, 1800.00),
('Orthodontic Consultation & Adjustment', 'Braces evaluation or monthly wire adjustment.', 30, 1200.00),
('Root Canal Therapy (Single Canal)', 'Endodontic treatment to save an infected tooth.', 90, 6500.00);

-- ----------------------------------------------------------------------------
-- 4. DENTIST SCHEDULES TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE `dentist_schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `dentist_id` INT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `break_start` TIME DEFAULT NULL,
    `break_end` TIME DEFAULT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_schedule_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `uq_dentist_day` UNIQUE (`dentist_id`, `day_of_week`),
    INDEX `idx_schedule_lookup` (`dentist_id`, `day_of_week`, `is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dentist_schedules` (`dentist_id`, `day_of_week`, `start_time`, `end_time`, `break_start`, `break_end`, `is_available`) VALUES
(2, 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00', 1),
(2, 2, '09:00:00', '17:00:00', '12:00:00', '13:00:00', 1),
(2, 3, '09:00:00', '17:00:00', '12:00:00', '13:00:00', 1),
(2, 4, '09:00:00', '17:00:00', '12:00:00', '13:00:00', 1),
(2, 5, '09:00:00', '17:00:00', '12:00:00', '13:00:00', 1),
(2, 6, '09:00:00', '15:00:00', '12:00:00', '12:30:00', 1),
(3, 1, '10:00:00', '18:00:00', '13:00:00', '14:00:00', 1),
(3, 2, '10:00:00', '18:00:00', '13:00:00', '14:00:00', 1),
(3, 3, '10:00:00', '18:00:00', '13:00:00', '14:00:00', 1),
(3, 4, '10:00:00', '18:00:00', '13:00:00', '14:00:00', 1),
(3, 5, '10:00:00', '18:00:00', '13:00:00', '14:00:00', 1);

-- ----------------------------------------------------------------------------
-- 5. APPOINTMENTS TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE `appointments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_code` VARCHAR(20) NOT NULL UNIQUE,
    `patient_id` INT UNSIGNED NOT NULL,
    `dentist_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `appointment_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `status` ENUM('Pending', 'Confirmed', 'Rescheduled', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `booking_type` ENUM('Online', 'Walk-In') NOT NULL DEFAULT 'Online',
    `patient_notes` VARCHAR(500) DEFAULT NULL,
    `cancellation_reason` VARCHAR(255) DEFAULT NULL,
    `managed_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_apt_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_apt_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_apt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_apt_manager` FOREIGN KEY (`managed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_conflict_check` (`dentist_id`, `appointment_date`, `status`, `start_time`, `end_time`),
    INDEX `idx_patient_history` (`patient_id`, `appointment_date` DESC),
    INDEX `idx_status_date` (`status`, `appointment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. TREATMENT RECORDS TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE `treatment_records` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `appointment_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `patient_id` INT UNSIGNED NOT NULL,
    `dentist_id` INT UNSIGNED NOT NULL,
    `tooth_numbers` VARCHAR(100) DEFAULT NULL,
    `diagnosis` TEXT NOT NULL,
    `procedure_performed` TEXT NOT NULL,
    `prescription` TEXT DEFAULT NULL,
    `dentist_notes` TEXT DEFAULT NULL,
    `follow_up_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tr_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_tr_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_tr_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_tr_patient` (`patient_id`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
