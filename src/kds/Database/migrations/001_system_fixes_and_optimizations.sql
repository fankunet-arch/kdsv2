-- ============================================================================
-- TopTea KDS - Database Upgrade Script
-- Version: 1.0.0
-- Date: 2026-01-03
-- Description: System fixes and optimizations from security audit
-- ============================================================================
--
-- IMPORTANT: Please backup your database before running this script!
--
-- This script includes:
-- 1. Create missing login_attempts table
-- 2. Add optimized indexes for performance
-- 3. Add foreign key constraints for data integrity
-- 4. Create tables for new features (password reset, user management, audit log queries)
--
-- ============================================================================

USE `mhdlmskv3gjbpqv3`;

-- ============================================================================
-- PART 1: CREATE MISSING TABLES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Table: login_attempts (CRITICAL - Missing table causing errors)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL COMMENT 'Attempted username',
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP address of attempt',
  `user_agent` VARCHAR(255) DEFAULT NULL COMMENT 'Browser user agent',
  `attempted_at` DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)) COMMENT 'UTC timestamp of attempt',
  `success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=successful login, 0=failed',

  INDEX `idx_username` (`username`),
  INDEX `idx_ip_address` (`ip_address`),
  INDEX `idx_attempted_at` (`attempted_at`),
  INDEX `idx_username_ip_time` (`username`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Login attempt tracking for rate limiting and security';

-- ----------------------------------------------------------------------------
-- Table: pos_cash_movements (POS feature: Cash movement tracking)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_cash_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `store_id` INT UNSIGNED NOT NULL COMMENT 'FK: kds_stores.id',
  `shift_id` BIGINT UNSIGNED NULL COMMENT 'FK: pos_shifts.id (NULL if outside shift)',
  `user_id` INT UNSIGNED NOT NULL COMMENT 'FK: kds_users.id',
  `movement_type` ENUM('ADD', 'REMOVE', 'ADJUST') NOT NULL COMMENT 'ADD=加钞, REMOVE=取钞, ADJUST=调整',
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount (positive for ADD, negative for REMOVE)',
  `reason` VARCHAR(255) NOT NULL COMMENT 'Reason for movement',
  `notes` TEXT NULL COMMENT 'Additional notes',
  `created_at` DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),

  INDEX `idx_store_shift` (`store_id`, `shift_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='POS cash movements (add/remove/adjust cash from register)';

-- ----------------------------------------------------------------------------
-- Table: password_reset_tokens (New feature: Password reset)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'FK: kds_users.id',
  `token` VARCHAR(64) NOT NULL COMMENT 'Secure random token',
  `expires_at` DATETIME(6) NOT NULL COMMENT 'Token expiration (UTC)',
  `used` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=token used, 0=unused',
  `created_at` DATETIME(6) NOT NULL DEFAULT (UTC_TIMESTAMP(6)),

  UNIQUE KEY `uk_token` (`token`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_expires_at` (`expires_at`),

  FOREIGN KEY (`user_id`) REFERENCES `kds_users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Password reset tokens';

-- ============================================================================
-- PART 2: ADD PERFORMANCE INDEXES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- kds_users: Optimize login queries
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_username_store_active`
  ON `kds_users`(`username`, `store_id`, `is_active`, `deleted_at`);

CREATE INDEX IF NOT EXISTS `idx_store_active`
  ON `kds_users`(`store_id`, `is_active`, `deleted_at`);

-- ----------------------------------------------------------------------------
-- kds_products: Optimize product lookups
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_product_code_active`
  ON `kds_products`(`product_code`, `is_active`, `deleted_at`);

CREATE INDEX IF NOT EXISTS `idx_status_active`
  ON `kds_products`(`status_id`, `is_active`, `deleted_at`);

-- ----------------------------------------------------------------------------
-- kds_material_expiries: Optimize expiry queries
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_status_expires_store`
  ON `kds_material_expiries`(`status`, `expires_at`, `store_id`);

CREATE INDEX IF NOT EXISTS `idx_store_status`
  ON `kds_material_expiries`(`store_id`, `status`);

-- ----------------------------------------------------------------------------
-- kds_stores: Optimize store lookups
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_store_code_active`
  ON `kds_stores`(`store_code`, `is_active`, `deleted_at`);

-- ----------------------------------------------------------------------------
-- audit_logs: Optimize audit log queries (new feature)
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_created_at`
  ON `audit_logs`(`created_at`);

CREATE INDEX IF NOT EXISTS `idx_actor_user_id`
  ON `audit_logs`(`actor_user_id`, `actor_type`);

CREATE INDEX IF NOT EXISTS `idx_action`
  ON `audit_logs`(`action`);

CREATE INDEX IF NOT EXISTS `idx_target`
  ON `audit_logs`(`target_type`, `target_id`);

-- ----------------------------------------------------------------------------
-- kds_product_recipes: Optimize recipe queries
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_product_sort`
  ON `kds_product_recipes`(`product_id`, `sort_order`);

-- ----------------------------------------------------------------------------
-- kds_recipe_adjustments: Optimize adjustment queries
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_product_material`
  ON `kds_recipe_adjustments`(`product_id`, `material_id`);

-- ============================================================================
-- PART 3: ADD FOREIGN KEY CONSTRAINTS (Data Integrity)
-- ============================================================================

-- Note: MySQL doesn't have "ADD CONSTRAINT IF NOT EXISTS", so we need to
-- check manually or handle errors. In production, verify constraints don't
-- already exist before running.

-- ----------------------------------------------------------------------------
-- kds_users
-- ----------------------------------------------------------------------------
ALTER TABLE `kds_users`
  ADD CONSTRAINT `fk_users_store`
  FOREIGN KEY (`store_id`) REFERENCES `kds_stores`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- kds_material_expiries
-- ----------------------------------------------------------------------------
ALTER TABLE `kds_material_expiries`
  ADD CONSTRAINT `fk_expiries_material`
  FOREIGN KEY (`material_id`) REFERENCES `kds_materials`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `kds_material_expiries`
  ADD CONSTRAINT `fk_expiries_store`
  FOREIGN KEY (`store_id`) REFERENCES `kds_stores`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `kds_material_expiries`
  ADD CONSTRAINT `fk_expiries_handler`
  FOREIGN KEY (`handler_id`) REFERENCES `kds_users`(`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- kds_product_recipes
-- ----------------------------------------------------------------------------
ALTER TABLE `kds_product_recipes`
  ADD CONSTRAINT `fk_recipe_product`
  FOREIGN KEY (`product_id`) REFERENCES `kds_products`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `kds_product_recipes`
  ADD CONSTRAINT `fk_recipe_material`
  FOREIGN KEY (`material_id`) REFERENCES `kds_materials`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `kds_product_recipes`
  ADD CONSTRAINT `fk_recipe_unit`
  FOREIGN KEY (`unit_id`) REFERENCES `kds_units`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- kds_recipe_adjustments
-- ----------------------------------------------------------------------------
ALTER TABLE `kds_recipe_adjustments`
  ADD CONSTRAINT `fk_adjustment_product`
  FOREIGN KEY (`product_id`) REFERENCES `kds_products`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `kds_recipe_adjustments`
  ADD CONSTRAINT `fk_adjustment_material`
  FOREIGN KEY (`material_id`) REFERENCES `kds_materials`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `kds_recipe_adjustments`
  ADD CONSTRAINT `fk_adjustment_unit`
  FOREIGN KEY (`unit_id`) REFERENCES `kds_units`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- kds_product_translations
-- ----------------------------------------------------------------------------
ALTER TABLE `kds_product_translations`
  ADD CONSTRAINT `fk_product_trans_product`
  FOREIGN KEY (`product_id`) REFERENCES `kds_products`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- kds_material_translations
-- ----------------------------------------------------------------------------
ALTER TABLE `kds_material_translations`
  ADD CONSTRAINT `fk_material_trans_material`
  FOREIGN KEY (`material_id`) REFERENCES `kds_materials`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- pos_cash_movements
-- ----------------------------------------------------------------------------
ALTER TABLE `pos_cash_movements`
  ADD CONSTRAINT `fk_cash_movement_store`
  FOREIGN KEY (`store_id`) REFERENCES `kds_stores`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `pos_cash_movements`
  ADD CONSTRAINT `fk_cash_movement_shift`
  FOREIGN KEY (`shift_id`) REFERENCES `pos_shifts`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `pos_cash_movements`
  ADD CONSTRAINT `fk_cash_movement_user`
  FOREIGN KEY (`user_id`) REFERENCES `kds_users`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- ============================================================================
-- PART 4: DATA CLEANUP (Optional - Review before running)
-- ============================================================================

-- Remove orphaned data (data referencing non-existent parents)
-- IMPORTANT: Review these carefully before uncommenting!

-- Clean up expiries with invalid material_id
-- DELETE FROM kds_material_expiries
-- WHERE material_id NOT IN (SELECT id FROM kds_materials);

-- Clean up expiries with invalid store_id
-- DELETE FROM kds_material_expiries
-- WHERE store_id NOT IN (SELECT id FROM kds_stores);

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these after the upgrade to verify everything is correct

-- Check if login_attempts table exists
SELECT
  TABLE_NAME,
  TABLE_ROWS,
  CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'mhdlmskv3gjbpqv3'
  AND TABLE_NAME = 'login_attempts';

-- Check indexes on kds_users
SHOW INDEXES FROM kds_users
WHERE Key_name LIKE 'idx_%';

-- Check foreign keys on kds_material_expiries
SELECT
  CONSTRAINT_NAME,
  TABLE_NAME,
  REFERENCED_TABLE_NAME,
  DELETE_RULE,
  UPDATE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'mhdlmskv3gjbpqv3'
  AND TABLE_NAME = 'kds_material_expiries';

-- ============================================================================
-- ROLLBACK SCRIPT (In case of issues)
-- ============================================================================
-- Save this section separately! Only run if you need to undo changes

/*
-- Drop foreign keys (run in reverse order)
ALTER TABLE `pos_cash_movements` DROP FOREIGN KEY `fk_cash_movement_user`;
ALTER TABLE `pos_cash_movements` DROP FOREIGN KEY `fk_cash_movement_shift`;
ALTER TABLE `pos_cash_movements` DROP FOREIGN KEY `fk_cash_movement_store`;
ALTER TABLE `kds_material_translations` DROP FOREIGN KEY `fk_material_trans_material`;
ALTER TABLE `kds_product_translations` DROP FOREIGN KEY `fk_product_trans_product`;
ALTER TABLE `kds_recipe_adjustments` DROP FOREIGN KEY `fk_adjustment_unit`;
ALTER TABLE `kds_recipe_adjustments` DROP FOREIGN KEY `fk_adjustment_material`;
ALTER TABLE `kds_recipe_adjustments` DROP FOREIGN KEY `fk_adjustment_product`;
ALTER TABLE `kds_product_recipes` DROP FOREIGN KEY `fk_recipe_unit`;
ALTER TABLE `kds_product_recipes` DROP FOREIGN KEY `fk_recipe_material`;
ALTER TABLE `kds_product_recipes` DROP FOREIGN KEY `fk_recipe_product`;
ALTER TABLE `kds_material_expiries` DROP FOREIGN KEY `fk_expiries_handler`;
ALTER TABLE `kds_material_expiries` DROP FOREIGN KEY `fk_expiries_store`;
ALTER TABLE `kds_material_expiries` DROP FOREIGN KEY `fk_expiries_material`;
ALTER TABLE `kds_users` DROP FOREIGN KEY `fk_users_store`;

-- Drop new tables
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `pos_cash_movements`;
DROP TABLE IF EXISTS `login_attempts`;

-- Drop indexes
DROP INDEX `idx_product_material` ON `kds_recipe_adjustments`;
DROP INDEX `idx_product_sort` ON `kds_product_recipes`;
DROP INDEX `idx_target` ON `audit_logs`;
DROP INDEX `idx_action` ON `audit_logs`;
DROP INDEX `idx_actor_user_id` ON `audit_logs`;
DROP INDEX `idx_created_at` ON `audit_logs`;
DROP INDEX `idx_store_code_active` ON `kds_stores`;
DROP INDEX `idx_store_status` ON `kds_material_expiries`;
DROP INDEX `idx_status_expires_store` ON `kds_material_expiries`;
DROP INDEX `idx_status_active` ON `kds_products`;
DROP INDEX `idx_product_code_active` ON `kds_products`;
DROP INDEX `idx_store_active` ON `kds_users`;
DROP INDEX `idx_username_store_active` ON `kds_users`;
*/

-- ============================================================================
-- END OF UPGRADE SCRIPT
-- ============================================================================
-- Please check the verification queries and test your application thoroughly!
-- ============================================================================
