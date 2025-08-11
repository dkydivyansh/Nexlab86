-- Create database if not exists
CREATE DATABASE IF NOT EXISTS u815229119_api;
USE u815229119_api;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    status ENUM('active', 'deactivated', 'pending') DEFAULT 'pending',
    verification_token VARCHAR(64),
    verification_expires TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_verification_token (verification_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data entries table
CREATE TABLE IF NOT EXISTS data_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    value TEXT NOT NULL,
    note TEXT,
    access_key VARCHAR(64),
    require_auth BOOLEAN DEFAULT FALSE,
    is_disabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_require_auth (require_auth)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API logs table
CREATE TABLE IF NOT EXISTS api_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    data_entry_id INT,
    ip_address VARCHAR(45) NOT NULL,
    request_method VARCHAR(10) NOT NULL,
    request_data TEXT,
    response_code INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (data_entry_id) REFERENCES data_entries(id) ON DELETE SET NULL,
    INDEX idx_data_entry_id (data_entry_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin logs table
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create default admin user (password: admin123)
INSERT INTO users (username, email, password, role) 
VALUES (
    'admin', 
    'admin@example.com', 
    '$2y$10$8KzO7O4s1kGM8YnVhxZIe.cg1G4CgqXgPQoZqYnvB0OMmkwLYkf4G',
    'admin'
) ON DUPLICATE KEY UPDATE id=id;

-- Add triggers for data consistency
DELIMITER //

CREATE TRIGGER before_user_delete
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    -- Log admin deletion in admin_logs
    IF OLD.role = 'admin' THEN
        INSERT INTO admin_logs (admin_id, action, details)
        VALUES (NULL, 'ADMIN_DELETED', CONCAT('Admin user deleted: ', OLD.username));
    END IF;
END//

DELIMITER ; 