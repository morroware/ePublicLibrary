-- Phase 3: Highlights & annotations. Created in Phase 1 so the schema is stable.

CREATE TABLE IF NOT EXISTS `highlights` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `book_id`    BIGINT UNSIGNED NOT NULL,
    `cfi_range`  VARCHAR(1000) NOT NULL,
    `text`       TEXT NOT NULL,
    `note`       TEXT NULL DEFAULT NULL,
    `color`      VARCHAR(20) NOT NULL DEFAULT 'yellow',
    `chapter`    VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_highlights_user_book` (`user_id`, `book_id`, `created_at` DESC),
    FULLTEXT KEY `ft_highlights_text` (`text`, `note`),
    CONSTRAINT `fk_highlights_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_highlights_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
