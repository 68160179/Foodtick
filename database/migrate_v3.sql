-- FOOD TRICKS v3: รูปโปรไฟล์ + คำแนะนำตัว (bio)
-- ใช้กับฐานข้อมูลที่รัน migrate_v2.sql ไปแล้ว (ข้อมูลเดิมยังอยู่ครบ) รันซ้ำได้ไม่ error
-- ถ้าเริ่มใหม่หมด ใช้ setup.sql อย่างเดียวพอ ไม่ต้องรันไฟล์นี้
SET NAMES utf8mb4;

-- รันซ้ำได้: คอลัมน์ที่มีอยู่แล้วจะถูกข้าม
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_user' AND COLUMN_NAME = 'avatar_path') = 0, 'ALTER TABLE app_user ADD COLUMN avatar_path VARCHAR(255) NULL AFTER password_hash', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_user' AND COLUMN_NAME = 'bio') = 0, 'ALTER TABLE app_user ADD COLUMN bio VARCHAR(160) NULL AFTER avatar_path', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
