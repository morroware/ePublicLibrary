-- Users: readers and admins. Guest is the absence of a session.

CREATE TABLE IF NOT EXISTS `users` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`                CHAR(36) NOT NULL,
    `email`               VARCHAR(254) NOT NULL,
    `username`            VARCHAR(40) NOT NULL,
    `display_name`        VARCHAR(120) NULL,
    `password_hash`       VARCHAR(255) NOT NULL,
    `role`                ENUM('reader','admin') NOT NULL DEFAULT 'reader',
    `status`              ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
    `email_verified_at`   TIMESTAMP NULL DEFAULT NULL,
    `last_login_at`       TIMESTAMP NULL DEFAULT NULL,
    `last_login_ip`       VARBINARY(16) NULL DEFAULT NULL,
    `failed_login_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`        TIMESTAMP NULL DEFAULT NULL,
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_uuid`     (`uuid`),
    UNIQUE KEY `uk_users_email`    (`email`),
    UNIQUE KEY `uk_users_username` (`username`),
    KEY `idx_users_role_status`    (`role`, `status`),
    KEY `idx_users_locked_until`   (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
