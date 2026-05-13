-- Per-user bookmarks. CFI is an EPUB Canonical Fragment Identifier from epub.js.

CREATE TABLE IF NOT EXISTS `bookmarks` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `book_id`    BIGINT UNSIGNED NOT NULL,
    `cfi`        VARCHAR(500) NOT NULL,
    `chapter`    VARCHAR(255) NULL DEFAULT NULL,
    `label`      VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bookmarks_user_book` (`user_id`, `book_id`, `created_at` DESC),
    CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bookmarks_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
