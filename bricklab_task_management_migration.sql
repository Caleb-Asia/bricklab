ALTER TABLE `tasks`
    ADD COLUMN `status` ENUM('todo', 'in_progress', 'completed') NOT NULL DEFAULT 'todo' AFTER `is_completed`,
    ADD COLUMN `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium' AFTER `status`,
    ADD COLUMN `completed_at` TIMESTAMP NULL DEFAULT NULL AFTER `due_date`;

-- A legacy completion flag cannot establish the actual completion time.
UPDATE `tasks`
SET `status` = CASE
    WHEN COALESCE(`is_completed`, 0) = 1 THEN 'completed'
    ELSE 'todo'
END;
