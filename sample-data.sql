-- Sample schema and data for jayshah88/yii2-multilevel-category
-- ---------------------------------------------------------------------------
-- Each row stores the id of its parent category in the `root` column.
-- A value of 0 means the row is a top level (root) category.
-- The index on `root` keeps the parent lookups fast.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tbl_category` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `root` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Id of the parent category, 0 = top level',
  `title` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tbl_category_root` (`root`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data matching the examples in the README
INSERT INTO `tbl_category` (`id`, `root`, `title`) VALUES
(1, 0, 'Test 1'),
(2, 1, 'Child 1');
