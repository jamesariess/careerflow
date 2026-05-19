-- ============================================================
-- CareerFlow – Smart Job Application Tracker
-- Database Schema v1.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS careerflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE careerflow;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120)  NOT NULL,
    email       VARCHAR(180)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    avatar      VARCHAR(255)  DEFAULT NULL,
    theme       ENUM('light','dark') DEFAULT 'dark',
    timezone    VARCHAR(60)   DEFAULT 'UTC',
    remember_token VARCHAR(100) DEFAULT NULL,
    csrf_token  VARCHAR(64)   DEFAULT NULL,
    reset_token VARCHAR(64)   DEFAULT NULL,
    reset_expires DATETIME    DEFAULT NULL,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================================
-- RESUMES
-- ============================================================
CREATE TABLE resumes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    label       VARCHAR(120)  NOT NULL,
    filename    VARCHAR(255)  NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size   INT UNSIGNED  DEFAULT 0,
    version     TINYINT       DEFAULT 1,
    is_default  TINYINT(1)    DEFAULT 0,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- APPLICATIONS
-- ============================================================
CREATE TABLE applications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    resume_id       INT UNSIGNED  DEFAULT NULL,
    company         VARCHAR(160)  NOT NULL,
    job_title       VARCHAR(160)  NOT NULL,
    location        VARCHAR(160)  DEFAULT NULL,
    job_type        ENUM('Full-time','Part-time','Contract','Freelance','Internship','Remote') DEFAULT 'Full-time',
    salary_min      DECIMAL(12,2) DEFAULT NULL,
    salary_max      DECIMAL(12,2) DEFAULT NULL,
    salary_currency VARCHAR(10)   DEFAULT 'USD',
    status          ENUM('Wishlist','Applied','Screening','Assessment','Interview','Final Interview','Offer','Rejected','Hired') DEFAULT 'Wishlist',
    applied_date    DATE          DEFAULT NULL,
    job_url         VARCHAR(500)  DEFAULT NULL,
    job_description TEXT          DEFAULT NULL,
    recruiter_name  VARCHAR(120)  DEFAULT NULL,
    recruiter_email VARCHAR(180)  DEFAULT NULL,
    recruiter_phone VARCHAR(40)   DEFAULT NULL,
    notes           TEXT          DEFAULT NULL,
    is_starred      TINYINT(1)    DEFAULT 0,
    sort_order      INT           DEFAULT 0,
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resume_id) REFERENCES resumes(id) ON DELETE SET NULL,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    INDEX idx_company (company),
    INDEX idx_applied_date (applied_date)
) ENGINE=InnoDB;

-- ============================================================
-- INTERVIEWS
-- ============================================================
CREATE TABLE interviews (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id  INT UNSIGNED  NOT NULL,
    user_id         INT UNSIGNED  NOT NULL,
    title           VARCHAR(180)  NOT NULL,
    interview_type  ENUM('Phone','Video','On-site','Technical','HR','Panel','Final') DEFAULT 'Video',
    scheduled_at    DATETIME      NOT NULL,
    duration_min    SMALLINT      DEFAULT 60,
    location_or_link VARCHAR(400) DEFAULT NULL,
    interviewer     VARCHAR(120)  DEFAULT NULL,
    notes           TEXT          DEFAULT NULL,
    outcome         ENUM('Pending','Passed','Failed','Cancelled','Rescheduled') DEFAULT 'Pending',
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    INDEX idx_app  (application_id),
    INDEX idx_user (user_id),
    INDEX idx_scheduled (scheduled_at)
) ENGINE=InnoDB;

-- ============================================================
-- REMINDERS
-- ============================================================
CREATE TABLE reminders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    application_id  INT UNSIGNED  DEFAULT NULL,
    title           VARCHAR(200)  NOT NULL,
    remind_at       DATETIME      NOT NULL,
    is_sent         TINYINT(1)    DEFAULT 0,
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_user     (user_id),
    INDEX idx_remind   (remind_at)
) ENGINE=InnoDB;

-- ============================================================
-- NOTES
-- ============================================================
CREATE TABLE notes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id  INT UNSIGNED  NOT NULL,
    user_id         INT UNSIGNED  NOT NULL,
    content         TEXT          NOT NULL,
    note_type       ENUM('General','Follow-up','Recruiter','Feedback','Other') DEFAULT 'General',
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    INDEX idx_app (application_id)
) ENGINE=InnoDB;

-- ============================================================
-- ACTIVITY LOGS
-- ============================================================
CREATE TABLE activity_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    application_id  INT UNSIGNED  DEFAULT NULL,
    action          VARCHAR(100)  NOT NULL,
    description     TEXT          DEFAULT NULL,
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    title       VARCHAR(200)  NOT NULL,
    body        TEXT          DEFAULT NULL,
    type        VARCHAR(60)   DEFAULT 'info',
    is_read     TINYINT(1)    DEFAULT 0,
    link        VARCHAR(400)  DEFAULT NULL,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user   (user_id),
    INDEX idx_unread (is_read)
) ENGINE=InnoDB;
