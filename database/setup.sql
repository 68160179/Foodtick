SET NAMES utf8mb4;

DROP TABLE IF EXISTS recipe_comment;
DROP TABLE IF EXISTS recipe_save;
DROP TABLE IF EXISTS recipe_like;
DROP TABLE IF EXISTS recipe_step;
DROP TABLE IF EXISTS recipe_ingredient;
DROP TABLE IF EXISTS recipe;
DROP TABLE IF EXISTS category;
DROP TABLE IF EXISTS app_user;

CREATE TABLE app_user (
 id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(30) NOT NULL UNIQUE,
 display_name VARCHAR(60) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 avatar_path VARCHAR(255) NULL,
 bio VARCHAR(160) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE category (
 id INT AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(30) NOT NULL UNIQUE,
 name VARCHAR(50) NOT NULL,
 emoji VARCHAR(16) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipe (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 category_id INT NULL,
 title VARCHAR(150) NOT NULL,
 description TEXT,
 image_path VARCHAR(255) NULL,
 status ENUM('draft','published') NOT NULL DEFAULT 'published',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_recipe_status (status, created_at),
 CONSTRAINT fk_recipe_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
 CONSTRAINT fk_recipe_category FOREIGN KEY (category_id) REFERENCES category(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ช่องที่ 1: วัตถุดิบ + ปริมาณ
CREATE TABLE recipe_ingredient (
 id INT AUTO_INCREMENT PRIMARY KEY,
 recipe_id INT NOT NULL,
 name VARCHAR(120) NOT NULL,
 quantity VARCHAR(60) NOT NULL DEFAULT '',
 sort_order INT NOT NULL DEFAULT 0,
 CONSTRAINT fk_ingredient_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ช่องที่ 2: วิธีการทำ (ทีละขั้น)
CREATE TABLE recipe_step (
 id INT AUTO_INCREMENT PRIMARY KEY,
 recipe_id INT NOT NULL,
 step_no INT NOT NULL,
 instruction TEXT NOT NULL,
 CONSTRAINT fk_step_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- กดใจ (1 คนกดได้ 1 ครั้งต่อสูตร)
CREATE TABLE recipe_like (
 user_id INT NOT NULL,
 recipe_id INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id, recipe_id),
 INDEX idx_like_recipe (recipe_id),
 CONSTRAINT fk_like_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
 CONSTRAINT fk_like_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- บันทึกสูตรลงคลังของตัวเอง
CREATE TABLE recipe_save (
 user_id INT NOT NULL,
 recipe_id INT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id, recipe_id),
 INDEX idx_save_recipe (recipe_id),
 CONSTRAINT fk_save_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
 CONSTRAINT fk_save_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- คอมเมนต์ในโพสต์
CREATE TABLE recipe_comment (
 id INT AUTO_INCREMENT PRIMARY KEY,
 recipe_id INT NOT NULL,
 user_id INT NOT NULL,
 body VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_comment_recipe (recipe_id, created_at),
 CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
 CONSTRAINT fk_comment_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- มีแค่หมวดหมู่ ไม่มีสูตรตัวอย่าง: ถ้ายังไม่มีใครโพสต์ หน้าเว็บจะว่าง
INSERT INTO category (slug, name, emoji, sort_order) VALUES
 ('fried','ทอด','🍳',1),
 ('steamed','นึ่ง','♨️',2),
 ('boiled','ต้ม','🍲',3),
 ('stirfry','ผัด','🥘',4),
 ('stewed','ตุ๋น','🫕',5),
 ('grilled','ย่าง','🍢',6),
 ('toasted','ปิ้ง','🔥',7),
 ('baked','อบ','🥧',8),
 ('curry','แกง','🍛',9),
 ('salad','ยำ / สลัด','🥗',10),
 ('dessert','ของหวาน','🧁',11),
 ('drink','เครื่องดื่ม','🧪',12);
