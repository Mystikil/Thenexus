-- Patch 002: Link website users to TFS accounts
ALTER TABLE `website_users`
    ADD COLUMN `account_id` INT NULL AFTER `id`;

ALTER TABLE `website_users`
    MODIFY COLUMN `email` VARCHAR(255) NULL;

ALTER TABLE `website_users`
    MODIFY COLUMN `pass_hash` VARCHAR(255) NULL;

CREATE INDEX `idx_website_users_account_id`
    ON `website_users` (`account_id`);

ALTER TABLE `accounts`
    ADD UNIQUE KEY `uniq_accounts_name` (`name`);
