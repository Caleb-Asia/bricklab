-- BrickLab Community — social feed, posts, and follower system.
-- Run once after the showcase migration has been applied.

-- Community posts: status updates, project announcements, learning logs.
CREATE TABLE IF NOT EXISTS `community_posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `body` TEXT NOT NULL,
    `post_type` ENUM('update', 'release', 'learning', 'milestone') NOT NULL DEFAULT 'update',
    `project_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_community_posts_user` (`user_id`, `created_at` DESC),
    INDEX `idx_community_posts_feed` (`created_at` DESC, `id` DESC),
    CONSTRAINT `fk_community_posts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_community_posts_project`
        FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Follower / following (called "networkers" in the UI).
CREATE TABLE IF NOT EXISTS `follows` (
    `follower_id` INT NOT NULL,
    `following_id` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`follower_id`, `following_id`),
    INDEX `idx_follows_following` (`following_id`, `follower_id`),
    CONSTRAINT `fk_follows_follower`
        FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_follows_following`
        FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Post likes (simple engagement).
CREATE TABLE IF NOT EXISTS `post_likes` (
    `user_id` INT NOT NULL,
    `post_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `post_id`),
    INDEX `idx_post_likes_post` (`post_id`),
    CONSTRAINT `fk_post_likes_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_post_likes_post`
        FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Verify
DESCRIBE community_posts;
DESCRIBE follows;
DESCRIBE post_likes;
