-- BrickLab Brand Vocabulary V1.0 Migration: Buildmates Mutual Connections
USE bricklab;

CREATE TABLE IF NOT EXISTS `buildmates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `requester_id` INT NOT NULL,
    `addressee_id` INT NOT NULL,
    `status` ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_buildmate_pair` (`requester_id`, `addressee_id`),
    INDEX `idx_buildmates_addressee` (`addressee_id`, `status`),
    INDEX `idx_buildmates_requester` (`requester_id`, `status`),
    CONSTRAINT `fk_buildmates_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_buildmates_addressee` FOREIGN KEY (`addressee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DESCRIBE buildmates;
