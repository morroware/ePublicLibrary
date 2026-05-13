-- Split-token storage: remember-me, password reset, email verification.
-- Cookie = "selector:verifier"; DB stores selector + sha256(verifier).

CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `purpose`    ENUM('remember','password_reset','email_verify','invite') NOT NULL,
    `selector`   CHAR(16) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `used_at`    TIMESTAMP NULL DEFAULT NULL,
    `revoked_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_auth_tokens_selector` (`selector`),
    KEY `idx_auth_tokens_user_purpose`   (`user_id`, `purpose`),
    KEY `idx_auth_tokens_expires`        (`expires_at`),
    CONSTRAINT `fk_auth_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
