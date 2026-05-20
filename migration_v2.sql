-- ============================================================
-- CareerFlow v2.0 – Migration: Gmail + AI + Cover Letters
-- Run this AFTER the original database.sql
-- ============================================================

USE careerflow;

-- ── User settings (stores AI key, Gmail IMAP creds, profile extras) ──
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS ai_provider    VARCHAR(40)   DEFAULT 'openrouter'   AFTER theme,
    ADD COLUMN IF NOT EXISTS ai_api_key     VARCHAR(255)  DEFAULT NULL           AFTER ai_provider,
    ADD COLUMN IF NOT EXISTS ai_model       VARCHAR(100)  DEFAULT 'mistralai/mistral-7b-instruct:free' AFTER ai_api_key,
    ADD COLUMN IF NOT EXISTS gmail_address  VARCHAR(180)  DEFAULT NULL           AFTER ai_model,
    ADD COLUMN IF NOT EXISTS gmail_app_password VARCHAR(255) DEFAULT NULL        AFTER gmail_address,
    ADD COLUMN IF NOT EXISTS gmail_sync_at  DATETIME      DEFAULT NULL           AFTER gmail_app_password,
    ADD COLUMN IF NOT EXISTS full_name      VARCHAR(160)  DEFAULT NULL           AFTER name,
    ADD COLUMN IF NOT EXISTS job_title_pref VARCHAR(160)  DEFAULT NULL           AFTER full_name,
    ADD COLUMN IF NOT EXISTS skills_summary TEXT          DEFAULT NULL           AFTER job_title_pref,
    ADD COLUMN IF NOT EXISTS years_exp      TINYINT       DEFAULT 0              AFTER skills_summary,
    ADD COLUMN IF NOT EXISTS linkedin_url   VARCHAR(300)  DEFAULT NULL           AFTER years_exp,
    ADD COLUMN IF NOT EXISTS phone          VARCHAR(40)   DEFAULT NULL           AFTER linkedin_url;

-- ── Synced Gmail emails ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS gmail_emails (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    application_id  INT UNSIGNED  DEFAULT NULL,
    gmail_msg_id    VARCHAR(255)  NOT NULL,
    thread_id       VARCHAR(255)  DEFAULT NULL,
    subject         VARCHAR(500)  NOT NULL,
    sender_name     VARCHAR(200)  DEFAULT NULL,
    sender_email    VARCHAR(200)  NOT NULL,
    body_plain      LONGTEXT      DEFAULT NULL,
    body_html       LONGTEXT      DEFAULT NULL,
    received_at     DATETIME      NOT NULL,
    is_job_related  TINYINT(1)    DEFAULT 0,
    ai_category     VARCHAR(60)   DEFAULT NULL,
    ai_sentiment    VARCHAR(30)   DEFAULT NULL,
    ai_summary      TEXT          DEFAULT NULL,
    reply_sent      TINYINT(1)    DEFAULT 0,
    reply_body      TEXT          DEFAULT NULL,
    reply_sent_at   DATETIME      DEFAULT NULL,
    is_read         TINYINT(1)    DEFAULT 0,
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_msg (user_id, gmail_msg_id),
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
    INDEX idx_user       (user_id),
    INDEX idx_job_rel    (is_job_related),
    INDEX idx_received   (received_at),
    INDEX idx_category   (ai_category)
) ENGINE=InnoDB;

-- ── Cover letters ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cover_letters (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED  NOT NULL,
    application_id  INT UNSIGNED  DEFAULT NULL,
    title           VARCHAR(255)  NOT NULL,
    company         VARCHAR(160)  DEFAULT NULL,
    job_title       VARCHAR(160)  DEFAULT NULL,
    tone            VARCHAR(40)   DEFAULT 'professional',
    body            LONGTEXT      NOT NULL,
    ai_generated    TINYINT(1)    DEFAULT 1,
    version         TINYINT       DEFAULT 1,
    is_favourite    TINYINT(1)    DEFAULT 0,
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_app  (application_id)
) ENGINE=InnoDB;

-- ── AI prompt/response log (for debugging & history) ────────────────
CREATE TABLE IF NOT EXISTS ai_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    feature     VARCHAR(60)   NOT NULL,
    prompt      TEXT          DEFAULT NULL,
    response    TEXT          DEFAULT NULL,
    model       VARCHAR(100)  DEFAULT NULL,
    tokens_used INT           DEFAULT 0,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user    (user_id),
    INDEX idx_feature (feature)
) ENGINE=InnoDB;

-- ── Currency preference ───────────────────────────────────────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS currency VARCHAR(10) DEFAULT 'USD' AFTER theme;
