-- Patch 004: Bestiary and Item Index support

-- Item index (from items.xml)
CREATE TABLE IF NOT EXISTS item_index (
  id INT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  article VARCHAR(64) NULL,
  plural VARCHAR(128) NULL,
  description TEXT NULL,
  weight INT NULL,
  stackable TINYINT(1) DEFAULT 0,
  type VARCHAR(64) NULL,
  attributes JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monster index (from monster XML files)
CREATE TABLE IF NOT EXISTS monster_index (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_path VARCHAR(255) NOT NULL,
  name VARCHAR(128) NOT NULL,
  race VARCHAR(64) NULL,
  experience INT NULL,
  health INT NULL,
  speed INT NULL,
  summonable TINYINT(1) DEFAULT 0,
  convinceable TINYINT(1) DEFAULT 0,
  illusionable TINYINT(1) DEFAULT 0,
  elemental JSON NULL,
  immunities JSON NULL,
  flags JSON NULL,
  outfit JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_monster_file (file_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monster loot (normalized; references item_index by id when possible)
CREATE TABLE IF NOT EXISTS monster_loot (
  monster_id INT NOT NULL,
  item_id INT NULL,
  item_name VARCHAR(128) NULL,
  chance INT NULL,
  count_min INT DEFAULT 1,
  count_max INT DEFAULT 1,
  UNIQUE KEY uniq_monster_loot (monster_id, item_id, item_name),
  CONSTRAINT fk_monster_loot_monster FOREIGN KEY (monster_id) REFERENCES monster_index(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Simple scan logs
CREATE TABLE IF NOT EXISTS index_scan_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('items','monsters') NOT NULL,
  status ENUM('ok','error') NOT NULL,
  message TEXT NULL,
  ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend shop products with item index linkage
ALTER TABLE shop_products
    ADD COLUMN item_index_id INT NULL AFTER id,
    ADD COLUMN meta JSON NULL AFTER is_active;

-- Seed curated shop products if they are not already present
INSERT INTO shop_products (name, item_id, price_coins, is_active, item_index_id, meta)
SELECT 'Gold Coin Pack', 2148, 250, 1, 2148, JSON_OBJECT('count', 1000, 'description', '1000x gold coins')
WHERE NOT EXISTS (SELECT 1 FROM shop_products WHERE name = 'Gold Coin Pack');

INSERT INTO shop_products (name, item_id, price_coins, is_active, item_index_id, meta)
SELECT 'Platinum Coin Pack', 2152, 500, 1, 2152, JSON_OBJECT('count', 100, 'description', '100x platinum coins')
WHERE NOT EXISTS (SELECT 1 FROM shop_products WHERE name = 'Platinum Coin Pack');

INSERT INTO shop_products (name, item_id, price_coins, is_active, item_index_id, meta)
SELECT 'Crystal Coin Pack', 2160, 750, 1, 2160, JSON_OBJECT('count', 10, 'description', '10x crystal coins')
WHERE NOT EXISTS (SELECT 1 FROM shop_products WHERE name = 'Crystal Coin Pack');

INSERT INTO shop_products (name, item_id, price_coins, is_active, item_index_id, meta)
SELECT 'Premium Scroll', 1965, 1200, 1, 1965, JSON_OBJECT('count', 1, 'description', 'Premium scroll redeemable in-game')
WHERE NOT EXISTS (SELECT 1 FROM shop_products WHERE name = 'Premium Scroll');
