
INSERT INTO kds_stores (store_code, store_name, is_active) VALUES ('A1001', 'Test Store', 1);
INSERT INTO kds_users (store_id, username, password_hash, display_name, role, is_active)
VALUES (1, 'admin', SHA2('admin123', 256), 'Admin User', 'manager', 1);
CREATE USER 'toptea_user'@'localhost' IDENTIFIED BY 'toptea_pass';
GRANT ALL PRIVILEGES ON mhdlmskv3gjbpqv3.* TO 'toptea_user'@'localhost';
FLUSH PRIVILEGES;
