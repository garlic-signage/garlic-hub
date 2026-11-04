CREATE TABLE `user_vip` (
    `UID` INTEGER NOT NULL,
    `vip_name` VARCHAR(50) NOT NULL DEFAULT '',
    `unit_id` VARCHAR(100) NOT NULL DEFAULT '',
    UNIQUE (`UID`, `vip_name`, `unit_id`),
    FOREIGN KEY (`UID`) REFERENCES `user_main` (`UID`) ON DELETE CASCADE
);

CREATE INDEX idx_vip_unit ON user_vip (vip_name, unit_id);
