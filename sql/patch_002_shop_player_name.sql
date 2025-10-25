-- Patch 002: Store the character name for shop orders
ALTER TABLE shop_orders
    ADD COLUMN player_name VARCHAR(255) NOT NULL DEFAULT '' AFTER product_id;
