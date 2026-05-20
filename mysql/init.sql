CREATE DATABASE IF NOT EXISTS omnidrop;
USE omnidrop;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT 0,
    avatar VARCHAR(255) DEFAULT NULL
);

CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    uuid VARCHAR(100) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    real_path VARCHAR(500) NOT NULL,
    size INT DEFAULT 0,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    share_uuid VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) DEFAULT NULL,
    max_downloads INT DEFAULT 0,
    downloads INT DEFAULT 0,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reverse_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    note VARCHAR(255) DEFAULT '',
    is_active BOOLEAN DEFAULT 1,
    max_uses INT DEFAULT 0,
    used_count INT DEFAULT 0,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL
);

-- 預設管理員帳號，密碼為 admin
INSERT INTO users (username, password, is_admin) VALUES ('admin', '$2y$10$U4l8XXCo72K1FVoITK2V8OK69UADR6pq1Q/MR8wnv/pFoM8xhgexi', 1);
INSERT INTO system_settings (setting_key, setting_value) VALUES ('theme_color', '#3b82f6');
