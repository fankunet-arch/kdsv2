-- ============================================================================
-- TopTea KDS - Database Migration Script #001
-- Purpose: Fix Constraints and Add Missing Indexes
-- Date: 2026-01-03
-- Engineer: System Auditor
-- ============================================================================

-- 使用正确的数据库
USE `mhdlmskv3gjbpqv3`;

-- ============================================================================
-- Part 1: 添加 kds_users.role CHECK约束
-- ============================================================================

ALTER TABLE `kds_users`
  ADD CONSTRAINT `chk_kds_user_role`
  CHECK (`role` IN ('staff', 'manager'));

-- ============================================================================
-- Part 2: 添加缺失的外键约束
-- ============================================================================

-- 2.1 pass_redemptions.cashier_user_id -> kds_users.id
-- 检查是否已存在该外键
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = 'mhdlmskv3gjbpqv3'
                  AND TABLE_NAME = 'pass_redemptions'
                  AND CONSTRAINT_NAME = 'fk_redemption_cashier');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `pass_redemptions`
     ADD CONSTRAINT `fk_redemption_cashier`
     FOREIGN KEY (`cashier_user_id`) REFERENCES `kds_users` (`id`) ON DELETE RESTRICT',
    'SELECT "Foreign key fk_redemption_cashier already exists" AS Info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2.2 pos_invoices.user_id -> kds_users.id
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = 'mhdlmskv3gjbpqv3'
                  AND TABLE_NAME = 'pos_invoices'
                  AND CONSTRAINT_NAME = 'fk_invoice_user');

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `pos_invoices`
     ADD CONSTRAINT `fk_invoice_user`
     FOREIGN KEY (`user_id`) REFERENCES `kds_users` (`id`) ON DELETE RESTRICT',
    'SELECT "Foreign key fk_invoice_user already exists" AS Info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2.3 为 pos_eod_reports 添加用户类型字段和触发器
-- 先添加字段（如果不存在）
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = 'mhdlmskv3gjbpqv3'
                      AND TABLE_NAME = 'pos_eod_reports'
                      AND COLUMN_NAME = 'user_type');

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `pos_eod_reports`
     ADD COLUMN `user_type` ENUM(''kds_user'', ''cpsys_user'') NOT NULL DEFAULT ''kds_user'' AFTER `user_id`',
    'SELECT "Column user_type already exists in pos_eod_reports" AS Info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 创建触发器验证用户存在性
DROP TRIGGER IF EXISTS `before_eod_report_insert`;
DROP TRIGGER IF EXISTS `before_eod_report_update`;

DELIMITER $$

CREATE TRIGGER `before_eod_report_insert` BEFORE INSERT ON `pos_eod_reports`
FOR EACH ROW
BEGIN
  DECLARE user_exists INT;

  IF NEW.user_type = 'kds_user' THEN
    SELECT COUNT(*) INTO user_exists FROM kds_users WHERE id = NEW.user_id;
  ELSE
    SELECT COUNT(*) INTO user_exists FROM cpsys_users WHERE id = NEW.user_id;
  END IF;

  IF user_exists = 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Referenced user does not exist in the specified user table';
  END IF;
END$$

CREATE TRIGGER `before_eod_report_update` BEFORE UPDATE ON `pos_eod_reports`
FOR EACH ROW
BEGIN
  DECLARE user_exists INT;

  IF NEW.user_type = 'kds_user' THEN
    SELECT COUNT(*) INTO user_exists FROM kds_users WHERE id = NEW.user_id;
  ELSE
    SELECT COUNT(*) INTO user_exists FROM cpsys_users WHERE id = NEW.user_id;
  END IF;

  IF user_exists = 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Referenced user does not exist in the specified user table';
  END IF;
END$$

DELIMITER ;

-- ============================================================================
-- Part 3: 添加性能优化索引
-- ============================================================================

-- 3.1 kds_material_expiries - 按门店和状态查询
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = 'mhdlmskv3gjbpqv3'
                     AND TABLE_NAME = 'kds_material_expiries'
                     AND INDEX_NAME = 'idx_store_status');

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `kds_material_expiries`
     ADD INDEX `idx_store_status` (`store_id`, `status`)',
    'SELECT "Index idx_store_status already exists" AS Info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3.2 pos_invoices - 按门店和时间范围查询
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = 'mhdlmskv3gjbpqv3'
                     AND TABLE_NAME = 'pos_invoices'
                     AND INDEX_NAME = 'idx_store_issued');

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `pos_invoices`
     ADD INDEX `idx_store_issued` (`store_id`, `issued_at`)',
    'SELECT "Index idx_store_issued already exists" AS Info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3.3 pass_redemptions - 按会员卡和时间查询
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = 'mhdlmskv3gjbpqv3'
                     AND TABLE_NAME = 'pass_redemptions'
                     AND INDEX_NAME = 'idx_pass_redeemed');

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `pass_redemptions`
     ADD INDEX `idx_pass_redeemed` (`member_pass_id`, `redeemed_at`)',
    'SELECT "Index idx_pass_redeemed already exists" AS Info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3.4 kds_users - 按用户名和门店查询
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = 'mhdlmskv3gjbpqv3'
                     AND TABLE_NAME = 'kds_users'
                     AND INDEX_NAME = 'idx_username_store');

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `kds_users`
     ADD INDEX `idx_username_store` (`username`, `store_id`)',
    'SELECT "Index idx_username_store already exists" AS Info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Part 4: 创建登录尝试记录表（用于速率限制）
-- ============================================================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
  PRIMARY KEY (`id`),
  INDEX `idx_username_ip_time` (`username`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='登录尝试记录表（用于速率限制）';

-- ============================================================================
-- Migration Complete
-- ============================================================================

SELECT 'Migration 001 completed successfully!' AS Status;
