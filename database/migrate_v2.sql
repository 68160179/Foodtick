-- FOOD TRICKS v2: ร่างสูตร, กดใจ, บันทึกลงคลัง, คอมเมนต์
-- ใช้กับฐานข้อมูลที่ import setup.sql รุ่นแรกไปแล้ว (ข้อมูลเดิมยังอยู่ครบ) รันซ้ำได้ไม่ error
-- ถ้าเริ่มใหม่หมด ใช้ setup.sql อย่างเดียวพอ ไม่ต้องรันไฟล์นี้
-- Angsila: เลือกฐานข้อมูลของตัวเองใน phpMyAdmin ก่อน แล้ว Import ไฟล์นี้
SET NAMES utf8mb4;

-- รันซ้ำได้: คอลัมน์/ดัชนีที่มีอยู่แล้วจะถูกข้าม
ALTER TABLE recipe MODIFY category_id INT NULL, MODIFY image_path VARCHAR(255) NULL;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recipe' AND COLUMN_NAME = 'status') = 0, 'ALTER TABLE recipe ADD COLUMN status ENUM(''draft'',''published'') NOT NULL DEFAULT ''published'' AFTER image_path', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recipe' AND INDEX_NAME = 'idx_recipe_status') = 0, 'ALTER TABLE recipe ADD INDEX idx_recipe_status (status, created_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS recipe_like (
 user_id INT NOT NULL,
 recipe_id INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id, recipe_id),
 INDEX idx_like_recipe (recipe_id),
 CONSTRAINT fk_like_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
 CONSTRAINT fk_like_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_save (
 user_id INT NOT NULL,
 recipe_id INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id, recipe_id),
 INDEX idx_save_recipe (recipe_id),
 CONSTRAINT fk_save_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
 CONSTRAINT fk_save_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_comment (
 id INT AUTO_INCREMENT PRIMARY KEY,
 recipe_id INT NOT NULL,
 user_id INT NOT NULL,
 body VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_comment_recipe (recipe_id, created_at),
 CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
 CONSTRAINT fk_comment_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
