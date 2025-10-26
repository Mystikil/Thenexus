-- Placeholder SQL schema for Nexus AAC.

CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(32) NOT NULL UNIQUE,
    email VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS website_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) DEFAULT NULL,
    pass_hash VARCHAR(255) DEFAULT NULL,
    account_id INT UNSIGNED DEFAULT NULL,
    role VARCHAR(16) NOT NULL DEFAULT 'user',
    theme_preference VARCHAR(64) DEFAULT NULL,
    twofa_secret VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_wu_email (email),
    UNIQUE KEY uniq_wu_account_id (account_id),
    KEY idx_wu_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS web_accounts (
    account_id INT NOT NULL PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    created INT NOT NULL DEFAULT (UNIX_TIMESTAMP()),
    points INT NOT NULL DEFAULT 0,
    country VARCHAR(2) NULL,
    flags JSON NULL,
    UNIQUE KEY uq_web_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS theme_options (
    theme_slug VARCHAR(64) NOT NULL,
    opt_key VARCHAR(64) NOT NULL,
    opt_value TEXT NULL,
    PRIMARY KEY (theme_slug, opt_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(190) NOT NULL,
    `value` TEXT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS index_scan_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(32) NOT NULL,
    status VARCHAR(16) NOT NULL,
    message TEXT NOT NULL,
    ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kind_ts (kind, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_index (
    id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    article VARCHAR(32) DEFAULT NULL,
    plural VARCHAR(255) DEFAULT NULL,
    description TEXT NULL,
    weight INT DEFAULT NULL,
    stackable TINYINT(1) NOT NULL DEFAULT 0,
    type VARCHAR(64) DEFAULT NULL,
    attributes TEXT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS monster_index (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    race VARCHAR(64) DEFAULT NULL,
    experience INT UNSIGNED NOT NULL DEFAULT 0,
    health INT UNSIGNED DEFAULT NULL,
    speed INT UNSIGNED DEFAULT NULL,
    summonable TINYINT(1) NOT NULL DEFAULT 0,
    convinceable TINYINT(1) NOT NULL DEFAULT 0,
    illusionable TINYINT(1) NOT NULL DEFAULT 0,
    elemental TEXT NULL,
    immunities TEXT NULL,
    flags TEXT NULL,
    outfit TEXT NULL,
    strategy TEXT NULL,
    location TEXT NULL,
    UNIQUE KEY uniq_monster_file (file_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS monster_loot (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monster_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED DEFAULT NULL,
    item_name VARCHAR(255) DEFAULT NULL,
    chance INT DEFAULT NULL,
    count_min INT NOT NULL DEFAULT 1,
    count_max INT NOT NULL DEFAULT 1,
    UNIQUE KEY uniq_monster_loot (monster_id, item_id, item_name),
    KEY idx_loot_monster (monster_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS spells_index (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    words VARCHAR(255) DEFAULT NULL,
    level INT DEFAULT NULL,
    mana INT DEFAULT NULL,
    cooldown INT DEFAULT NULL,
    vocations TEXT DEFAULT NULL,
    type VARCHAR(64) DEFAULT NULL,
    attributes TEXT DEFAULT NULL,
    UNIQUE KEY uniq_spell_file (file_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(128) NOT NULL,
    event_type VARCHAR(64) DEFAULT NULL,
    trigger_type VARCHAR(32) DEFAULT NULL,
    interval_seconds INT UNSIGNED DEFAULT NULL,
    time_of_day VARCHAR(32) DEFAULT NULL,
    script VARCHAR(255) DEFAULT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    raw_attributes TEXT NULL,
    UNIQUE KEY uniq_event_name (event_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS server_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(128) NOT NULL,
    payload TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rcon_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    command VARCHAR(255) NOT NULL,
    payload TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    available_at TIMESTAMP NULL DEFAULT NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_status_available (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings(`key`,`value`) VALUES
('widgets_left_default','["top_levels","top_guilds","vote_links"]'),
('widgets_right_default','["online","server_status","recent_deaths"]');
