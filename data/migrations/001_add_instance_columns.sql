ALTER TABLE players ADD COLUMN instance_id INT NOT NULL DEFAULT 0;
CREATE INDEX idx_players_instance ON players(instance_id);
