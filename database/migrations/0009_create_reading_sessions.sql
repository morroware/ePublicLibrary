-- Reading sessions for Phase 4 stats. One row per session bracket.

CREATE TABLE IF NOT EXISTS `reading_sessions` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `book_id`          BIGINT UNSIGNED NOT NULL,
    `started_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at`         TIMESTAMP NULL DEFAULT NULL,
    `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `pages_read`       INT UNSIGNED NOT NULL DEFAULT 0,
    `start_cfi`        VARCHAR(500) NULL DEFAULT NULL,
    `end_cfi`          VARCHAR(500) NULL DEFAULT NULL,
    `device_label`     VARCHAR(80) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user_started` (`user_id`, `started_at` DESC),
    KEY `idx_sessions_user_book`    (`user_id`, `book_id`),
    CONSTRAINT `fk_reading_sessions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reading_sessions_book`
        FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
