-- Indexes to improve character profile lookups.
CREATE INDEX IF NOT EXISTS idx_players_name ON players (name);
CREATE INDEX IF NOT EXISTS idx_players_account ON players (account_id);
CREATE INDEX IF NOT EXISTS idx_deaths_player ON deaths (player_id, time);
CREATE INDEX IF NOT EXISTS idx_player_deaths_player ON player_deaths (player_id, time);
CREATE INDEX IF NOT EXISTS idx_player_items_pid ON player_items (player_id, pid);
