-- ============================================================================
-- BrightSmile Dental Clinic - Supabase (PostgreSQL) Schema & Seed Data
-- Instructions:
--   1. Open your Supabase Project Dashboard -> SQL Editor -> "+ New query"
--   2. Paste this entire script and click "Run"
-- ============================================================================

DROP TABLE IF EXISTS treatment_records CASCADE;
DROP TABLE IF EXISTS appointments CASCADE;
DROP TABLE IF EXISTS dentist_schedules CASCADE;
DROP TABLE IF EXISTS services CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS roles CASCADE;

-- ----------------------------------------------------------------------------
-- 1. ROLES TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE roles (
    id SMALLSERIAL PRIMARY KEY,
    slug VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (id, slug, name, description) VALUES
(1, 'admin', 'Administrator', 'Full system access, staff and service management, reports'),
(2, 'dentist', 'Dentist', 'View personal schedule, patient history, and record treatments'),
(3, 'receptionist', 'Receptionist / Staff', 'Manage appointment requests, walk-ins, and rescheduling'),
(4, 'client', 'Client (Patient)', 'Book appointments and view personal dental history');

SELECT setval(pg_get_serial_sequence('roles', 'id'), (SELECT MAX(id) FROM roles));

-- ----------------------------------------------------------------------------
-- 2. USERS TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    role_id SMALLINT NOT NULL REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    first_name VARCHAR(64) NOT NULL,
    last_name VARCHAR(64) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(25) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    date_of_birth DATE DEFAULT NULL,
    license_number VARCHAR(64) DEFAULT NULL,
    specialization VARCHAR(100) DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    failed_login_attempts SMALLINT NOT NULL DEFAULT 0,
    locked_until TIMESTAMPTZ DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_role_active ON users (role_id, is_active);
CREATE INDEX idx_users_name ON users (last_name, first_name);

-- Seed demo accounts (Password for all demo accounts: password)
INSERT INTO users (id, role_id, first_name, last_name, email, phone, password_hash, license_number, specialization) VALUES
(1, 1, 'System', 'Admin', 'admin@dentalclinic.local', '+639170000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL),
(2, 2, 'Elena', 'Reyes', 'dr.reyes@dentalclinic.local', '+639170000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PRC-DENT-44821', 'General Dentistry & Orthodontics'),
(3, 2, 'Marcus', 'Santos', 'dr.santos@dentalclinic.local', '+639170000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PRC-DENT-51902', 'Oral Surgery & Endodontics'),
(4, 3, 'Clara', 'Mendoza', 'frontdesk@dentalclinic.local', '+639170000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL),
(5, 4, 'Juan', 'Dela Cruz', 'patient@dentalclinic.local', '+639170000005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL);

SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT MAX(id) FROM users));

-- ----------------------------------------------------------------------------
-- 3. SERVICES TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE services (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    duration_minutes SMALLINT NOT NULL DEFAULT 30,
    price NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_services_active ON services (is_active);

INSERT INTO services (name, description, duration_minutes, price) VALUES
('Oral Prophylaxis (Cleaning)', 'Routine dental cleaning and plaque removal.', 45, 1500.00),
('Simple Tooth Extraction', 'Removal of a visible, non-impacted tooth.', 45, 2000.00),
('Composite Dental Filling', 'Tooth-colored restoration for cavities.', 60, 1800.00),
('Orthodontic Consultation & Adjustment', 'Braces evaluation or monthly wire adjustment.', 30, 1200.00),
('Root Canal Therapy (Single Canal)', 'Endodontic treatment to save an infected tooth.', 90, 6500.00);

-- ----------------------------------------------------------------------------
-- 4. DENTIST SCHEDULES TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE dentist_schedules (
    id SERIAL PRIMARY KEY,
    dentist_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    day_of_week SMALLINT NOT NULL CHECK (day_of_week BETWEEN 0 AND 6),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    break_start TIME DEFAULT NULL,
    break_end TIME DEFAULT NULL,
    is_available SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_dentist_day UNIQUE (dentist_id, day_of_week),
    CONSTRAINT chk_valid_shift CHECK (start_time < end_time)
);

CREATE INDEX idx_schedule_lookup ON dentist_schedules (dentist_id, day_of_week, is_available);

INSERT INTO dentist_schedules (dentist_id, day_of_week, start_time, end_time, break_start, break_end, is_available) VALUES
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
CREATE TABLE appointments (
    id BIGSERIAL PRIMARY KEY,
    reference_code VARCHAR(20) NOT NULL UNIQUE,
    patient_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    dentist_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    service_id INTEGER NOT NULL REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Confirmed', 'Rescheduled', 'Completed', 'Cancelled')),
    booking_type VARCHAR(20) NOT NULL DEFAULT 'Online' CHECK (booking_type IN ('Online', 'Walk-In')),
    patient_notes VARCHAR(500) DEFAULT NULL,
    cancellation_reason VARCHAR(255) DEFAULT NULL,
    managed_by INTEGER DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_apt_time CHECK (start_time < end_time)
);

CREATE INDEX idx_conflict_check ON appointments (dentist_id, appointment_date, status, start_time, end_time);
CREATE INDEX idx_patient_history ON appointments (patient_id, appointment_date DESC);
CREATE INDEX idx_status_date ON appointments (status, appointment_date);

-- ----------------------------------------------------------------------------
-- 6. TREATMENT RECORDS TABLE
-- ----------------------------------------------------------------------------
CREATE TABLE treatment_records (
    id BIGSERIAL PRIMARY KEY,
    appointment_id BIGINT NOT NULL UNIQUE REFERENCES appointments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    patient_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    dentist_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    tooth_numbers VARCHAR(100) DEFAULT NULL,
    diagnosis TEXT NOT NULL,
    procedure_performed TEXT NOT NULL,
    prescription TEXT DEFAULT NULL,
    dentist_notes TEXT DEFAULT NULL,
    follow_up_date DATE DEFAULT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_tr_patient ON treatment_records (patient_id, created_at DESC);
