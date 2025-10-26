-- Placeholder SQL schema for Nexus AAC.

CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(32) NOT NULL UNIQUE,
    email VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS theme_options (
    theme_slug VARCHAR(64) NOT NULL,
    opt_key VARCHAR(64) NOT NULL,
    opt_value TEXT NULL,
    PRIMARY KEY (theme_slug, opt_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS server_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    occurred_at DATETIME NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    signature VARCHAR(128) NULL,
    handled TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_server_events_type_time (event_type, occurred_at),
    KEY idx_server_events_handled (handled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS server_status_snapshot (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    online_count INT NOT NULL DEFAULT 0,
    uptime_seconds INT NULL,
    tps DECIMAL(5,2) NULL,
    world_time VARCHAR(32) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_server_status_snapshot_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS server_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source ENUM('raids', 'globalevents') NOT NULL,
    name VARCHAR(128) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    cron VARCHAR(128) NULL,
    params JSON NULL,
    next_run DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_source_file (source, file_path),
    KEY idx_server_schedule_next_run (next_run)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
