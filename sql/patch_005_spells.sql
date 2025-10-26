-- Patch 005: Spells index support

CREATE TABLE IF NOT EXISTS spells_index (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_path VARCHAR(255) NOT NULL,
  name VARCHAR(128) NOT NULL,
  words VARCHAR(128) NULL,
  level INT NULL,
  mana INT NULL,
  cooldown INT NULL,
  vocations VARCHAR(255) NULL,
  type VARCHAR(64) NULL,
  attributes JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_spell_file (file_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE index_scan_log
    MODIFY kind ENUM('items','monsters','spells') NOT NULL;
