-- BrickLab Build visibility Phase 1A
-- Schema changes only. The visibility backfill will be performed separately,
-- after the PHP compatibility release has been deployed.
--
-- IMPORTANT: Do not enable Unlisted in the UI or mark Builds Unlisted until
-- token-based direct-link routing and authorization have been implemented.
-- The unlisted_token column stores the token; this migration does not generate
-- tokens and does not make existing ID-based routes safe for Unlisted access.
--
-- Keep is_public and its existing index during the compatibility period.

ALTER TABLE `projects`
    ADD COLUMN `visibility` ENUM('public', 'private', 'unlisted')
        NULL DEFAULT NULL
        AFTER `is_public`,
    ADD COLUMN `unlisted_token` CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL DEFAULT NULL
        AFTER `visibility`,
    ADD UNIQUE KEY `uq_projects_unlisted_token` (`unlisted_token`),
    ADD KEY `idx_projects_visibility_category_created`
        (`visibility`, `category`, `created_at`);
