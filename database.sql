CREATE DATABASE IF NOT EXISTS deped_dts;
USE deped_dts;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop all tables to be safe
DROP TABLE IF EXISTS notifications, cache, cache_locks, jobs, job_batches, failed_jobs, departments, users, user_public_key_histories, password_reset_tokens, sessions, purposes, documents, document_logs, prediction_keywords, daily_department_metrics, database_metrics, report_jobs, integrity_checks;

-- 1. Core Laravel & Infrastructure Tables
CREATE TABLE cache ( `key` VARCHAR(255) PRIMARY KEY, `value` MEDIUMTEXT, `expiration` INT );
CREATE TABLE cache_locks ( `key` VARCHAR(255) PRIMARY KEY, `owner` VARCHAR(255), `expiration` INT );
CREATE TABLE jobs ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, queue VARCHAR(255), payload LONGTEXT, attempts TINYINT UNSIGNED, reserved_at INT UNSIGNED NULL, available_at INT UNSIGNED, created_at INT UNSIGNED );
CREATE TABLE job_batches ( id VARCHAR(255) PRIMARY KEY, name VARCHAR(255), total_jobs INT, pending_jobs INT, failed_jobs INT, failed_job_ids LONGTEXT, options MEDIUMTEXT NULL, cancelled_at INT NULL, created_at INT, finished_at INT NULL );
CREATE TABLE failed_jobs ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid VARCHAR(255) UNIQUE, connection TEXT, queue TEXT, payload LONGTEXT, exception LONGTEXT, failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP );

-- 2. DTS Domain Tables
CREATE TABLE departments ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL );
CREATE TABLE users ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255) UNIQUE, email_verified_at TIMESTAMP NULL, password VARCHAR(255), public_key TEXT NULL, private_key TEXT NULL, security_key_set_at TIMESTAMP NULL, department_id BIGINT UNSIGNED NULL, role ENUM('officer', 'staff', 'admin') DEFAULT 'staff', remember_token VARCHAR(100) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL );
CREATE TABLE user_public_key_histories ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED, public_key TEXT, activated_at TIMESTAMP, deactivated_at TIMESTAMP, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, INDEX idx_user_key_period (user_id, activated_at, deactivated_at) );
CREATE TABLE password_reset_tokens ( email VARCHAR(255) PRIMARY KEY, token VARCHAR(255), created_at TIMESTAMP NULL );
CREATE TABLE sessions ( id VARCHAR(255) PRIMARY KEY, user_id BIGINT UNSIGNED NULL, ip_address VARCHAR(45) NULL, user_agent TEXT NULL, payload LONGTEXT, last_activity INT, INDEX (user_id), INDEX (last_activity) );
CREATE TABLE purposes ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), is_official BOOLEAN DEFAULT FALSE, requirements JSON NULL, suggested_route JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL );

-- 3. Workflow & Tracking Tables
CREATE TABLE documents ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tracking_code VARCHAR(255) UNIQUE, title VARCHAR(255) NULL, details TEXT NULL, guest_info JSON NULL, district VARCHAR(255) NULL, department VARCHAR(255) NULL, purpose_id BIGINT UNSIGNED, decline_reason TEXT NULL, declined_at TIMESTAMP NULL, status VARCHAR(255) DEFAULT 'pending', version INT DEFAULT 1, finalized_route JSON NULL, current_step INT NULL, current_department_id BIGINT UNSIGNED NULL, released_at TIMESTAMP NULL, released_by_user_id BIGINT UNSIGNED NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, FOREIGN KEY (purpose_id) REFERENCES purposes(id) ON DELETE CASCADE, FOREIGN KEY (current_department_id) REFERENCES departments(id) ON DELETE SET NULL, FOREIGN KEY (released_by_user_id) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_status (status), INDEX idx_created_at (created_at), INDEX idx_doc_status_created_composite (status, created_at), INDEX idx_doc_current_dept (current_department_id), INDEX idx_doc_released_at (released_at), INDEX idx_doc_released_by (released_by_user_id) );
CREATE TABLE document_logs ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_id BIGINT UNSIGNED, user_id BIGINT UNSIGNED NULL, action VARCHAR(255), remarks TEXT NULL, hash VARCHAR(255), previous_hash TEXT NULL, signature TEXT NULL, document_state_hash VARCHAR(64) NULL, document_snapshot JSON NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, action_category TINYINT GENERATED ALWAYS AS ( CASE WHEN action = 'Accepted and Document Routing finalized' THEN 1 WHEN action = 'Processing Complete' OR action LIKE 'Document routed to%' THEN 2 ELSE 0 END ) STORED, FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_log_document_id (document_id), INDEX idx_log_user_id (user_id), INDEX idx_log_hash (hash), INDEX idx_log_action (action), INDEX idx_log_created_at (created_at), INDEX idx_log_category (user_id, action_category, document_id, created_at) );
CREATE TABLE prediction_keywords ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, keyword VARCHAR(255), department_id BIGINT UNSIGNED, weight INT DEFAULT 1, document_count INT UNSIGNED DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE, INDEX (keyword) );

-- 4. Analytics & Utilities
CREATE TABLE daily_department_metrics ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, department_id BIGINT UNSIGNED, date DATE, received_count INT UNSIGNED DEFAULT 0, processed_count INT UNSIGNED DEFAULT 0, released_count INT UNSIGNED DEFAULT 0, total_processing_seconds BIGINT UNSIGNED DEFAULT 0, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE, UNIQUE INDEX idx_dept_date_unique (department_id, date), INDEX idx_metrics_date (date) );
CREATE TABLE database_metrics ( id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, connections INT UNSIGNED, avg_query_time_ms DECIMAL(10, 4), slow_queries INT UNSIGNED, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_metrics_created_at (created_at) );
CREATE TABLE report_jobs ( id CHAR(36) PRIMARY KEY, user_id BIGINT UNSIGNED, status VARCHAR(255) DEFAULT 'queued', progress INT UNSIGNED DEFAULT 0, total_documents INT UNSIGNED DEFAULT 0, file_path VARCHAR(255) NULL, filters JSON NULL, error_message TEXT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE );
CREATE TABLE integrity_checks ( id CHAR(36) PRIMARY KEY, user_id BIGINT UNSIGNED, status VARCHAR(255) DEFAULT 'queued', progress INT UNSIGNED DEFAULT 0, results JSON NULL, error_message TEXT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE );

SET FOREIGN_KEY_CHECKS = 1;

-- 5. Notifications
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('success', 'error', 'info', 'warning') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_department (department_id),
    INDEX idx_user (user_id),
    INDEX idx_unread (is_read)
);
