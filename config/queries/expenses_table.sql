-- Run this in phpMyAdmin while selecting the brgy_data database
-- Creates the expenses table for the Fund Transparency module

CREATE TABLE IF NOT EXISTS `expenses` (
  `id`            INT(11)        NOT NULL AUTO_INCREMENT,
  `reference_no`  VARCHAR(50)    NOT NULL,
  `date`          DATE           NOT NULL,
  `category`      ENUM('senior','health','infra','event','admin','other') NOT NULL DEFAULT 'other',
  `title`         VARCHAR(255)   NOT NULL,
  `description`   TEXT           DEFAULT NULL,
  `amount`        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `approved_by`   VARCHAR(150)   DEFAULT NULL,
  `fiscal_year`   YEAR           NOT NULL DEFAULT (YEAR(CURDATE())),
  `posted_by`     VARCHAR(100)   DEFAULT 'Admin',
  `status`        ENUM('Posted','Draft') NOT NULL DEFAULT 'Posted',
  `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date`        (`date`),
  KEY `idx_category`    (`category`),
  KEY `idx_fiscal_year` (`fiscal_year`),
  KEY `idx_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: seed the annual budget into a separate config row
-- You can manage this from the admin panel
CREATE TABLE IF NOT EXISTS `budget_config` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `fiscal_year`  YEAR          NOT NULL,
  `annual_budget` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `notes`        TEXT          DEFAULT NULL,
  `updated_by`   VARCHAR(100)  DEFAULT 'Admin',
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiscal_year` (`fiscal_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default 2025 budget (edit the amount as needed)
INSERT IGNORE INTO `budget_config` (`fiscal_year`, `annual_budget`, `notes`, `updated_by`)
VALUES (2025, 500000.00, 'Initial budget allocation for FY 2025', 'Admin');
