-- DB-backed PHP session handler storage.

CREATE TABLE IF NOT EXISTS `sessions` (
    `id`             CHAR(128) NOT NULL,
    `user_id`        BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address`     VARBINARY(16) NULL DEFAULT NULL,
    `user_agent`     VARCHAR(255) NULL DEFAULT NULL,
    `payload`        MEDIUMBLOB NOT NULL,
    `last_activity`  INT UNSIGNED NOT NULL,
    `expires_at`     INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user`           (`user_id`),
    KEY `idx_sessions_last_activity`  (`last_activity`),
    KEY `idx_sessions_expires`        (`expires_at`),
    CONSTRAINT `fk_sessions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
