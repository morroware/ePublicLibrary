-- User-defined shelves. System shelves (Favorites, Want to Read, Finished) are
-- auto-seeded for each new user via the application, not via migration.

CREATE TABLE IF NOT EXISTS `collections` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `name`        VARCHAR(120) NOT NULL,
    `slug`        VARCHAR(140) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `is_public`   TINYINT(1) NOT NULL DEFAULT 0,
    `is_system`   TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_collections_user_slug` (`user_id`, `slug`),
    KEY `idx_collections_public` (`is_public`),
    CONSTRAINT `fk_collections_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `collection_books` (
    `collection_id` BIGINT UNSIGNED NOT NULL,
    `book_id`       BIGINT UNSIGNED NOT NULL,
    `position`      INT UNSIGNED NOT NULL DEFAULT 0,
    `added_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`collection_id`, `book_id`),
    KEY `idx_collection_books_position` (`collection_id`, `position`),
    CONSTRAINT `fk_collection_books_collection`
        FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_collection_books_book`
        FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
