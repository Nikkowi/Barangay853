-- ============================================================
-- Migration: Create barangay_officials table
-- Run this once in your MySQL/phpMyAdmin before using the feature
-- ============================================================

CREATE TABLE IF NOT EXISTS `barangay_officials` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `position`    VARCHAR(150) NOT NULL,
  `gender`      ENUM('Male','Female') NOT NULL DEFAULT 'Male',
  `sort_order`  INT(11)      NOT NULL DEFAULT 0,
  `photo_url`   VARCHAR(255)          DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Optional: seed the existing hardcoded officials so the
-- About page still shows data right away
-- ============================================================

INSERT INTO `barangay_officials` (`name`, `position`, `gender`, `sort_order`) VALUES
  ('Hon. Roger Ferrer',    'Barangay Chairman',         'Male',   1),
  ('Wilfredo Delos Santos','Barangay Secretary',         'Male',   2),
  ('Adey Gregorio',        'Barangay Treasurer',         'Female', 3),
  ('Hon. Gemma Blaquera',  'Kagawad - Health & Sanitation','Female',4),
  ('Hon. Merlita Galon',   'Kagawad - Services',         'Female', 5),
  ('Hon. Mica Grace Villaluz','SK Chairman',             'Female', 6);
