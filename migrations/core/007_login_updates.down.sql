DROP INDEX idx_templates_seed_key;
ALTER TABLE templates DROP COLUMN seed_key;
DROP TABLE `user_sso`;
DROP TABLE `sso_providers`;
DROP TABLE `user_recovery_codes`;
DROP TABLE `user_two_factor`;
