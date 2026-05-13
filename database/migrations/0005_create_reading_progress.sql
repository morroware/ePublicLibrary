-- One row per (user, book). Tracks current reading position.

CREATE TABLE IF NOT EXISTS `reading_progress` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `book_id`          BIGINT UNSIGNED NOT NULL,
    `cfi`              VARCHAR(500) NOT NULL DEFAULT '',
    `percentage`       DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `current_chapter`  VARCHAR(255) NULL DEFAULT NULL,
    `last_read_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at`       TIMESTAMP NULL DEFAULT NULL,
    `finished_at`      TIMESTAMP NULL DEFAULT NULL,
    `total_time_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_progress_user_book` (`user_id`, `book_id`),
    KEY `idx_progress_user_last_read`  (`user_id`, `last_read_at` DESC),
    CONSTRAINT `fk_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_progress_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
