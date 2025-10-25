-- Patch 003: account recovery support
-- Attach recovery metadata to legacy game accounts.
ALTER TABLE accounts
  ADD COLUMN recovery_key_hash VARBINARY(64) NULL AFTER password,
  ADD COLUMN recovery_key_created_at DATETIME NULL AFTER recovery_key_hash;

-- Optional: server-side attempt throttle for recovery submissions.
CREATE TABLE IF NOT EXISTS recovery_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_name VARCHAR(64) NOT NULL,
  ip VARBINARY(16) NOT NULL,
  ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
