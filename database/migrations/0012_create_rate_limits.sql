-- Sliding-window rate limiting buckets. One row per bucket.

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `bucket`     VARCHAR(120) NOT NULL,
    `hits`       INT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`bucket`),
    KEY `idx_rate_limits_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
