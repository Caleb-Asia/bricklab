USE bricklab;

-- Add Developer Profile fields
ALTER TABLE users
    ADD COLUMN display_name VARCHAR(80) NULL AFTER username,
    ADD COLUMN professional_role VARCHAR(100) NULL AFTER display_name,
    ADD COLUMN location VARCHAR(100) NULL AFTER professional_role,
    ADD COLUMN bio TEXT NULL AFTER location,
    ADD COLUMN avatar_url VARCHAR(2048) NULL AFTER bio,
    ADD COLUMN github_url VARCHAR(2048) NULL AFTER avatar_url,
    ADD COLUMN linkedin_url VARCHAR(2048) NULL AFTER github_url,
    ADD COLUMN portfolio_url VARCHAR(2048) NULL AFTER linkedin_url,
    ADD COLUMN skills TEXT NULL AFTER portfolio_url;


-- Add Showcase fields to projects
ALTER TABLE projects
    ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'other' AFTER description,
    ADD COLUMN technologies TEXT NULL AFTER category,
    ADD COLUMN github_url VARCHAR(2048) NULL AFTER technologies,
    ADD COLUMN live_demo_url VARCHAR(2048) NULL AFTER github_url,
    ADD INDEX idx_projects_public_category_created
        (is_public, category, created_at);


-- Verify the changes
DESCRIBE users;
DESCRIBE projects;

SHOW INDEX FROM projects
WHERE Key_name = 'idx_projects_public_category_created';