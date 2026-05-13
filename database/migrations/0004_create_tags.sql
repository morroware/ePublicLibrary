-- Genres, subjects, custom tags. Many-to-many with books.

CREATE TABLE IF NOT EXISTS `tags` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`       VARCHAR(80) NOT NULL,
    `name`       VARCHAR(80) NOT NULL,
    `kind`       ENUM('genre','subject','custom') NOT NULL DEFAULT 'genre',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tags_slug` (`slug`),
    KEY `idx_tags_kind` (`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_tags` (
    `book_id` BIGINT UNSIGNED NOT NULL,
    `tag_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`book_id`, `tag_id`),
    KEY `idx_book_tags_tag` (`tag_id`, `book_id`),
    CONSTRAINT `fk_book_tags_book`
        FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_book_tags_tag`
        FOREIGN KEY (`tag_id`)  REFERENCES `tags`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
