-- JutForm database schema
--
-- WARNING: Do not modify this file to make schema or data changes.
-- This file only runs once on a fresh database initialisation and will NOT
-- be re-executed on an existing database. Use migration scripts in
-- backend/migrations/ instead (see README for instructions).

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(128),
    role ENUM('admin', 'user') DEFAULT 'user',
    timezone VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    is_public TINYINT(1) DEFAULT 0,
    fields_json LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    data_json LONGTEXT,
    ip_address VARCHAR(45),
    submitted_at DATETIME NOT NULL,
    INDEX idx_form_id (form_id),
    FOREIGN KEY (form_id) REFERENCES forms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS form_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    setting_key VARCHAR(128) NOT NULL,
    value VARCHAR(255),
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_form_setting (form_id, setting_key),
    FOREIGN KEY (form_id) REFERENCES forms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS app_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(255) NOT NULL,
    value VARCHAR(200),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS scheduled_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    body TEXT,
    scheduled_at DATETIME NOT NULL,
    sent_at DATETIME DEFAULT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    INDEX idx_form_id (form_id),
    INDEX idx_status_scheduled (status, scheduled_at),
    FOREIGN KEY (form_id) REFERENCES forms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    url VARCHAR(2048) NOT NULL,
    method VARCHAR(10) NOT NULL DEFAULT 'POST',
    events VARCHAR(255) DEFAULT 'submission.created',
    is_active TINYINT(1) DEFAULT 1,
    secret_token VARCHAR(128),
    last_triggered_at DATETIME DEFAULT NULL,
    last_status_code INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_form_id (form_id),
    FOREIGN KEY (form_id) REFERENCES forms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(128) NOT NULL,
    entity_type VARCHAR(64),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS form_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    resource_type VARCHAR(64) NOT NULL,
    resource_data TEXT,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_form_resource (form_id, resource_type),
    FOREIGN KEY (form_id) REFERENCES forms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    submission_id INT,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(512) NOT NULL,
    mime_type VARCHAR(128),
    file_size INT,
    uploaded_at DATETIME NOT NULL,
    INDEX idx_form_id (form_id),
    FOREIGN KEY (form_id) REFERENCES forms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(64) NULL,
    status ENUM('approved', 'declined', 'error') NOT NULL,
    gateway_hash VARCHAR(64) NOT NULL,
    paid_at DATETIME NOT NULL,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS form_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    date DATE NOT NULL,
    views INT NOT NULL DEFAULT 0,
    submissions INT NOT NULL DEFAULT 0,
    avg_fill_time DECIMAL(6,2) NOT NULL DEFAULT 0,
    country_code CHAR(2) NOT NULL,
    INDEX idx_form_date (form_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS field_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS countries (
    code CHAR(2) PRIMARY KEY,
    name VARCHAR(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
