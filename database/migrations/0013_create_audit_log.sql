-- Audit log. Append-only; retention managed administratively.

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id`     BIGINT UNSIGNED NULL DEFAULT NULL,
    `actor_type`   ENUM('user','admin','system','guest') NOT NULL DEFAULT 'user',
    `event`        VARCHAR(80) NOT NULL,
    `subject_type` VARCHAR(40) NULL DEFAULT NULL,
    `subject_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address`   VARBINARY(16) NULL DEFAULT NULL,
    `user_agent`   VARCHAR(255) NULL DEFAULT NULL,
    `metadata`     JSON NULL,
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_event_created`  (`event`, `created_at` DESC),
    KEY `idx_audit_actor`          (`actor_id`, `created_at` DESC),
    KEY `idx_audit_subject`        (`subject_type`, `subject_id`),
    CONSTRAINT `fk_audit_actor`
        FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
