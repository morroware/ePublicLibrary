-- Book ratings and reviews. One review per (user, book).

CREATE TABLE IF NOT EXISTS `reviews` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `book_id`    BIGINT UNSIGNED NOT NULL,
    `rating`     TINYINT UNSIGNED NOT NULL,
    `title`      VARCHAR(200) NULL DEFAULT NULL,
    `body`       TEXT NULL DEFAULT NULL,
    `status`     ENUM('published','hidden','flagged') NOT NULL DEFAULT 'published',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_reviews_user_book`   (`user_id`, `book_id`),
    KEY `idx_reviews_book_status`       (`book_id`, `status`, `created_at` DESC),
    CONSTRAINT `chk_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5),
    CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
