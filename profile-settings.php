<?php

require_once __DIR__ . '/includes/auth.php';
require_authentication();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';

$userId = current_user_id();
if ($userId === null) {
    throw new LogicException('An authenticated user ID is required.');
}

$pageTitle = 'Public profile settings';
$currentPage = 'profile';
$errors = [];
$saved = request_string($_GET['saved'] ?? '') === '1';
$values = [
    'display_name' => '',
    'professional_role' => '',
    'location' => '',
    'bio' => '',
    'avatar_url' => '',
    'github_url' => '',
    'linkedin_url' => '',
    'portfolio_url' => '',
    'skills' => '',
];

try {
    $statement = $pdo->prepare(
        'SELECT
            username,
            display_name,
            professional_role,
            location,
            bio,
            avatar_url,
            github_url,
            linkedin_url,
            portfolio_url,
            skills
        FROM users
        WHERE id = :user_id
        LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();
    if ($user === false) {
        throw new LogicException('The authenticated account could not be found.');
    }

    foreach ($values as $field => $value) {
        $values[$field] = (string) ($user[$field] ?? '');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!csrf_token_is_valid($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            $errors[] = 'Your session expired. Refresh the page and try again.';
        } else {
            foreach ($values as $field => $value) {
                $values[$field] = trim(request_string($_POST[$field] ?? null));
            }

            foreach ([
                'display_name' => 80,
                'professional_role' => 100,
                'location' => 100,
                'bio' => 2000,
            ] as $field => $maximumLength) {
                $fieldLength = showcase_text_length($values[$field]);
                if ($fieldLength === null || $fieldLength > $maximumLength) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field))
                        . ' must be ' . $maximumLength . ' characters or fewer.';
                }
            }

            $skillsLength = showcase_text_length($values['skills']);
            if ($skillsLength === null || $skillsLength > 1000) {
                $errors[] = 'Skills must be 1,000 characters or fewer.';
            } elseif (
                $values['skills'] !== ''
                && !preg_match(
                    '/\A[A-Za-z0-9][A-Za-z0-9 .+#-]*(?:\s*,\s*[A-Za-z0-9][A-Za-z0-9 .+#-]*)*\z/',
                    $values['skills']
                )
            ) {
                $errors[] = 'Enter skills as comma-separated tags using letters, numbers, spaces, . + # or -.';
            } else {
                $values['skills'] = showcase_normalize_tags($values['skills']);
            }
            foreach (['avatar_url', 'github_url', 'linkedin_url', 'portfolio_url'] as $field) {
                if (!showcase_url_is_valid($values[$field])) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a valid HTTPS URL.';
                }
            }

            if ($errors === []) {
                $statement = $pdo->prepare(
                    'UPDATE users
                    SET
                        display_name = :display_name,
                        professional_role = :professional_role,
                        location = :location,
                        bio = :bio,
                        avatar_url = :avatar_url,
                        github_url = :github_url,
                        linkedin_url = :linkedin_url,
                        portfolio_url = :portfolio_url,
                        skills = :skills
                    WHERE id = :user_id'
                );
                $statement->execute([
                    'display_name' => $values['display_name'] === '' ? null : $values['display_name'],
                    'professional_role' => $values['professional_role'] === '' ? null : $values['professional_role'],
                    'location' => $values['location'] === '' ? null : $values['location'],
                    'bio' => $values['bio'] === '' ? null : $values['bio'],
                    'avatar_url' => $values['avatar_url'] === '' ? null : $values['avatar_url'],
                    'github_url' => $values['github_url'] === '' ? null : $values['github_url'],
                    'linkedin_url' => $values['linkedin_url'] === '' ? null : $values['linkedin_url'],
                    'portfolio_url' => $values['portfolio_url'] === '' ? null : $values['portfolio_url'],
                    'skills' => $values['skills'] === '' ? null : $values['skills'],
                    'user_id' => $userId,
                ]);
                header('Location: /bricklab/profile-settings.php?saved=1', true, 303);
                exit;
            }
        }
    }
} catch (PDOException $exception) {
    error_log('BrickLab profile settings could not be loaded or saved: ' . $exception->getMessage());
    http_response_code(500);
    $errors[] = 'Your profile could not be saved right now. Please try again later.';
}

require __DIR__ . '/includes/header.php';
?>
            <header class="projects-heading">
                <div>
                    <p class="section-kicker">YOUR PUBLIC IDENTITY</p>
                    <h1>Profile settings</h1>
                    <p class="section-subtitle">Tell the BrickLab community a little about yourself.</p>
                </div>
                <a class="btn btn-secondary" href="/bricklab/">Back to dashboard</a>
            </header>

            <?php if ($saved): ?>
                <p class="form-notice form-notice-success" role="status">Your public profile has been updated.</p>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <div class="form-notice form-notice-error" role="alert">
                    <p>Please review the following:</p>
                    <ul><?php foreach ($errors as $error): ?><li><?= escape_html($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <section class="profile-settings-panel surface" aria-labelledby="profile-settings-title">
                <h2 id="profile-settings-title">Public profile</h2>
                <p>This information is visible to anyone who can view your published projects.</p>
                <form class="profile-settings-form" method="post" action="/bricklab/profile-settings.php">
                    <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                    <label>
                        Display name
                        <input name="display_name" maxlength="80" value="<?= escape_html($values['display_name']) ?>" autocomplete="name">
                    </label>
                    <label>
                        Professional role
                        <input name="professional_role" maxlength="100" value="<?= escape_html($values['professional_role']) ?>" placeholder="e.g. Full-stack developer">
                    </label>
                    <label>
                        Location
                        <input name="location" maxlength="100" value="<?= escape_html($values['location']) ?>" autocomplete="address-level2">
                    </label>
                    <label class="profile-settings-wide">
                        Short bio
                        <textarea name="bio" maxlength="2000" rows="5" placeholder="What do you like to build?"><?= escape_html($values['bio']) ?></textarea>
                    </label>
                    <label class="profile-settings-wide">
                        Avatar URL
                        <input type="url" name="avatar_url" maxlength="2048" value="<?= escape_html($values['avatar_url']) ?>" placeholder="https://…">
                    </label>
                    <label>
                        GitHub URL
                        <input type="url" name="github_url" maxlength="2048" value="<?= escape_html($values['github_url']) ?>" placeholder="https://github.com/…">
                    </label>
                    <label>
                        LinkedIn URL
                        <input type="url" name="linkedin_url" maxlength="2048" value="<?= escape_html($values['linkedin_url']) ?>" placeholder="https://linkedin.com/in/…">
                    </label>
                    <label class="profile-settings-wide">
                        Portfolio URL
                        <input type="url" name="portfolio_url" maxlength="2048" value="<?= escape_html($values['portfolio_url']) ?>" placeholder="https://…">
                    </label>
                    <label class="profile-settings-wide">
                        Skills and technologies
                        <input name="skills" maxlength="1000" value="<?= escape_html($values['skills']) ?>" placeholder="PHP, MySQL, JavaScript">
                        <span class="profile-settings-hint">Separate tags with commas. Only letters, numbers, spaces and . + # - are accepted.</span>
                    </label>
                    <div class="profile-settings-actions">
                        <button class="btn btn-primary" type="submit">Save profile</button>
                        <a class="btn btn-secondary" href="/bricklab/showcase.php">View Showcase</a>
                    </div>
                </form>
            </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
