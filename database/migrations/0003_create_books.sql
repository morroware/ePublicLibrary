-- Books: canonical metadata in the DB; EPUB file on disk.
-- FULLTEXT index on (title, author, description) for search.

CREATE TABLE IF NOT EXISTS `books` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid`              CHAR(36) NOT NULL,
    `slug`              VARCHAR(220) NOT NULL,
    `title`             VARCHAR(500) NOT NULL,
    `subtitle`          VARCHAR(500) NULL DEFAULT NULL,
    `author`            VARCHAR(500) NOT NULL DEFAULT 'Unknown',
    `language`          VARCHAR(10) NULL DEFAULT NULL,
    `publisher`         VARCHAR(255) NULL DEFAULT NULL,
    `published_date`    VARCHAR(50) NULL DEFAULT NULL,
    `isbn`              VARCHAR(20) NULL DEFAULT NULL,
    `description`       TEXT NULL,
    `description_html`  MEDIUMTEXT NULL,
    `storage_path`      VARCHAR(500) NOT NULL,
    `cover_path`        VARCHAR(500) NULL DEFAULT NULL,
    `file_size`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `file_hash`         CHAR(64) NOT NULL,
    `mime_type`         VARCHAR(60) NOT NULL DEFAULT 'application/epub+zip',
    `status`            ENUM('processing','published','hidden','removed') NOT NULL DEFAULT 'published',
    `download_count`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `read_count`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `uploaded_by`       BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_books_uuid`       (`uuid`),
    UNIQUE KEY `uk_books_slug`       (`slug`),
    UNIQUE KEY `uk_books_file_hash`  (`file_hash`),
    KEY `idx_books_status_created`   (`status`, `created_at`),
    KEY `idx_books_author`           (`author`(100)),
    KEY `idx_books_language`         (`language`),
    FULLTEXT KEY `ft_books_search`   (`title`, `author`, `description`),
    CONSTRAINT `fk_books_uploaded_by`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
