-- ============================================================
-- CPTSA Driving School — MySQL Schema
-- Run this script once to initialise the database.
-- mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS cptsa_driving
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cptsa_driving;

-- ──────────────────────────────────────────────────────────
-- 1. VEHICLE CATEGORIES
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehicle_categories (
    id          VARCHAR(30)  NOT NULL PRIMARY KEY,   -- e.g. 'bike', 'van', '3wheel'
    label       VARCHAR(100) NOT NULL,               -- e.g. '🏍️ Bike (A)'
    is_default  TINYINT(1)   NOT NULL DEFAULT 0,     -- 1 = cannot be deleted
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO vehicle_categories (id, label, is_default) VALUES
    ('bike',    '🏍️ Bike (A)',              1),
    ('van',     '🚐 Van (B)',               1),
    ('3wheel',  '🛺 Three-Wheeler (B1)',    1);

-- ──────────────────────────────────────────────────────────
-- 2. ADMIN USERS
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(60)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,           -- bcrypt
    name         VARCHAR(100) NOT NULL DEFAULT 'Administrator',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: username=admin  password=admin123
-- Hash below is bcrypt of 'admin123'
INSERT IGNORE INTO admins (id, username, password_hash, name) VALUES
    (1, 'admin', '$2y$10$Azz0G0aYcdJupdN0IVavCeC2M39GIH00xbBS/d6NDdOHE1r0he3jG', 'Administrator');

-- ──────────────────────────────────────────────────────────
-- 3. STUDENTS
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS students (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nic             VARCHAR(20)  NOT NULL UNIQUE,
    id_type         ENUM('NIC','SL Passport','Foreign Passport','Diplomatic Passport') DEFAULT 'NIC',
    password        VARCHAR(20)  NOT NULL,
    last_name       VARCHAR(100),
    other_names     VARCHAR(100),
    name_init       VARCHAR(120),
    gender          ENUM('male','female','') DEFAULT '',
    dob             DATE         NULL,
    blood_group     VARCHAR(5),
    phone           VARCHAR(20)  NOT NULL,
    address         TEXT,
    div_secretariat VARCHAR(120),
    restrictions    VARCHAR(60)  DEFAULT 'None',
    organ_donor     TINYINT(1)   DEFAULT 0,
    photo_path      VARCHAR(255) NULL,              -- relative path under uploads/photos/
    reg_date        DATE         NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ──────────────────────────────────────────────────────────
-- 4. STUDENT ↔ VEHICLE (many-to-many)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS student_vehicles (
    student_id   INT UNSIGNED NOT NULL,
    vehicle_id   VARCHAR(30)  NOT NULL,
    manual_hours DECIMAL(5,1) NOT NULL DEFAULT 0.0,   -- admin-added hours
    deduction    DECIMAL(5,1) NOT NULL DEFAULT 0.0,   -- admin deduction
    extra_paid   TINYINT(1)   NOT NULL DEFAULT 0,     -- 1 = paid re-registration (10h limit)
    PRIMARY KEY (student_id, vehicle_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicle_categories(id)
);

-- ──────────────────────────────────────────────────────────
-- 5. EXAM RESULTS
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_results (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED NOT NULL,
    vehicle_id  VARCHAR(30)  NOT NULL,
    written     ENUM('pending','pass','fail') DEFAULT 'pending',
    practical   ENUM('pending','pass','fail') DEFAULT 'pending',
    exam_date   DATE NULL,
    UNIQUE KEY uq_student_vehicle (student_id, vehicle_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicle_categories(id)
);

-- ──────────────────────────────────────────────────────────
-- 6. TIME SLOTS (timetable)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS time_slots (
    id            VARCHAR(40)  NOT NULL PRIMARY KEY,
    slot_date     DATE         NOT NULL,
    time_start    TIME         NOT NULL,
    time_end      TIME         NOT NULL,
    display_time  VARCHAR(40)  NOT NULL,
    vehicle_id    VARCHAR(30)  NOT NULL,
    capacity      TINYINT UNSIGNED NOT NULL DEFAULT 4,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicle_categories(id)
);

-- ──────────────────────────────────────────────────────────
-- 7. SLOT BOOKINGS
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS slot_bookings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_id     VARCHAR(40)  NOT NULL,
    student_id  INT UNSIGNED NOT NULL,
    vehicle_id  VARCHAR(30)  NOT NULL,
    booked_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot_student (slot_id, student_id),
    FOREIGN KEY (slot_id)    REFERENCES time_slots(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ──────────────────────────────────────────────────────────
-- 8. ANNOUNCEMENTS
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS announcements (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    content     TEXT         NOT NULL,
    posted_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ──────────────────────────────────────────────────────────
-- 9. DOCUMENTS (fee PDFs / admin uploads)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    mime_type   VARCHAR(100),
    file_size   INT UNSIGNED,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ──────────────────────────────────────────────────────────
-- 10. FEEDBACK / REVIEWS
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reviewer_name VARCHAR(100) NOT NULL,
    category    VARCHAR(80)  NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    message     TEXT         NOT NULL,
    posted_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ──────────────────────────────────────────────────────────
-- 11. MESSAGES (admin → student)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    body        TEXT         NOT NULL,
    sent_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS message_recipients (
    message_id  INT UNSIGNED NOT NULL,
    student_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (message_id, student_id),
    FOREIGN KEY (message_id)  REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)  REFERENCES students(id) ON DELETE CASCADE
);

-- ──────────────────────────────────────────────────────────
-- 12. INSTRUCTORS
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS instructors (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    title       VARCHAR(200),
    badges      TEXT,           -- JSON array stored as text
    bio         TEXT,
    photo_path  VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ──────────────────────────────────────────────────────────
-- 13. FEES
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fees (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_type     VARCHAR(100) NOT NULL,
    total_amount     VARCHAR(60)  NOT NULL,
    installment_fee  VARCHAR(60),
    installments     VARCHAR(80),
    stamp_duty       VARCHAR(60),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ──────────────────────────────────────────────────────────
-- 14. REGISTRATION FORMS (student_access.html submissions)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS registration_forms (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_phone    VARCHAR(20),
    id_type          VARCHAR(40),
    id_no            VARCHAR(30),
    last_name        VARCHAR(100),
    other_names      VARCHAR(100),
    name_init        VARCHAR(120),
    gender           VARCHAR(10),
    dob              DATE NULL,
    age              TINYINT UNSIGNED,
    blood_group      VARCHAR(5),
    address          TEXT,
    phone            VARCHAR(20),
    div_secretariat  VARCHAR(120),
    restrictions     VARCHAR(60),
    organ_donor      TINYINT(1) DEFAULT 0,
    ntmi_date        DATE NULL,
    ntmi_no          VARCHAR(60),
    pol_date         DATE NULL,
    pol_station      VARCHAR(100),
    old_lic_no       VARCHAR(60),
    old_lic_issue    DATE NULL,
    old_lic_exp      DATE NULL,
    sign_text        VARCHAR(200),
    amount           DECIMAL(10,2),
    cashier          VARCHAR(100),
    signing_officer  VARCHAR(100),
    form_status      ENUM('Draft','Submitted') DEFAULT 'Draft',
    submitted_at     TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ──────────────────────────────────────────────────────────
-- 15. REG CODE (single-row settings table)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(60) NOT NULL PRIMARY KEY,
    setting_value TEXT        NOT NULL
);
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('reg_code', '5566');

-- ──────────────────────────────────────────────────────────
-- 16. SIGNIN LOGS (student_access sign-in log)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS signin_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    phone       VARCHAR(20)  NOT NULL,
    log_status  VARCHAR(30)  DEFAULT 'Signed In',
    signed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
