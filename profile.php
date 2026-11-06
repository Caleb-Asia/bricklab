<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';

$username = trim(request_string($_GET['username'] ?? ''));
$filters = [
    'search' => '',
    'category' => 'all',
    'technology' => '',
    'sort' => 'newest',
];
$requestedPage = min(positive_integer($_GET['page'] ?? 1) ?? 1, 1000000);
$pageSize = 9;
$profileProjectCount = 0;
$profileError = '';
$projects = ['items' => [], 'total' => 0];

if (!preg_match('/\A[A-Za-z0-9_-]{3,50}\z/', $username)) {
    http_response_code(404);
    $profile = null;
} else {
    try {
        $statement = $pdo->prepare(
            'SELECT
                user.id,
                user.username,
                user.display_name,
                user.professional_role,
                user.location,
                user.bio,
                user.avatar_url,
                user.github_url,
                user.linkedin_url,
                user.portfolio_url,
                user.skills
            FROM users AS user
            WHERE user.username = :username
                AND EXISTS (
                    SELECT 1 FROM projects AS public_project
                    WHERE public_project.user_id = user.id
                        AND public_project.is_public = 1
                )
            LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $profile = $statement->fetch();
        if ($profile === false) {
            http_response_code(404);
            $profile = null;
        }
    } catch (PDOException $exception) {
        error_log('BrickLab developer profile could not be loaded: ' . $exception->getMessage());
        http_response_code(500);
        $profileError = 'This developer profile could not be loaded right now. Please try again later.';
        $profile = null;
    }

    $filters['search'] = trim(request_string($_GET['search'] ?? ''));
    $filters['sort'] = request_string($_GET['sort'] ?? 'newest');
    if (strlen($filters['search']) > 100) {
        $filters['search'] = substr($filters['search'], 0, 100);
    }
    if (!in_array($filters['sort'], ['newest', 'oldest', 'title'], true)) {
        $filters['sort'] = 'newest';
    }
    if ($profile !== null) {
        try {
            $countStatement = $pdo->prepare(
                'SELECT COUNT(*)
                FROM projects
                WHERE user_id = :user_id AND is_public = 1'
            );
            $countStatement->execute(['user_id' => (int) $profile['id']]);
            $profileProjectCount = (int) $countStatement->fetchColumn();
            $projects = showcase_fetch_projects(
                $pdo,
                $filters,
                (int) $profile['id'],
                $pageSize,
                ($requestedPage - 1) * $pageSize
            );
        } catch (PDOException $exception) {
            error_log('BrickLab developer projects could not be loaded: ' . $exception->getMessage());
            http_response_code(500);
            $profileError = 'This developer profile could not be loaded right now. Please try again later.';
            $profile = null;
        }
    }
    if ($profile !== null) {
        $projectPageCount = max(1, (int) ceil($projects['total'] / $pageSize));
        $currentProjectPage = min($requestedPage, $projectPageCount);
        if ($currentProjectPage !== $requestedPage) {
            try {
                $projects = showcase_fetch_projects(
                    $pdo,
                    $filters,
                    (int) $profile['id'],
                    $pageSize,
                    ($currentProjectPage - 1) * $pageSize
                );
            } catch (PDOException $exception) {
                error_log('BrickLab developer projects page could not be loaded: ' . $exception->getMessage());
                http_response_code(500);
                $profileError = 'This developer profile could not be loaded right now. Please try again later.';
                $profile = null;
                $projects = ['items' => [], 'total' => 0];
                $profileProjectCount = 0;
            }
        }
    }
}

$pageTitle = $profile === null
    ? ($profileError === '' ? 'Developer not found' : 'Profile unavailable')
    : (trim((string) ($profile['display_name'] ?? '')) ?: (string) $profile['username']);
$publicActivePage = 'developers';
$loadShowcaseScript = true;
$profileName = $profile === null
    ? ''
    : (trim((string) ($profile['display_name'] ?? '')) ?: (string) $profile['username']);
$profileAvatar = $profile === null
    ? null
    : showcase_safe_image_source(isset($profile['avatar_url']) ? (string) $profile['avatar_url'] : null);
$profileSkills = $profile === null ? [] : showcase_parse_tags((string) ($profile['skills'] ?? ''));
$profileProjectCount = $profile === null ? 0 : $profileProjectCount;
$projectPageCount = max(1, (int) ceil($projects['total'] / 9));
$currentProjectPage = min($requestedPage, $projectPageCount);

require __DIR__ . '/includes/public-header.php';
?>
    <?php if ($profile === null): ?>
        <section class="public-container profile-not-found">
            <p class="public-eyebrow">PROFILE UNAVAILABLE</p>
            <h1><?= $profileError === ''
                ? 'This developer profile isn’t available.'
                : 'We couldn’t load this developer profile.' ?></h1>
            <p><?= escape_html($profileError !== ''
                ? $profileError
                : 'The profile may not exist or may not have any public projects yet.') ?></p>
            <a class="btn btn-primary" href="/bricklab/developers.php">Browse developers</a>
        </section>
    <?php else: ?>
        <section class="profile-hero public-container">
            <div class="profile-identity surface">
                <div class="profile-avatar">
                    <?php if ($profileAvatar !== null): ?>
                        <img src="<?= escape_html($profileAvatar) ?>" alt="" fetchpriority="high">
                    <?php else: ?>
                        <?= escape_html(showcase_avatar_initials($profileName)) ?>
                    <?php endif; ?>
                </div>
                <div class="profile-identity-copy">
                    <p class="public-eyebrow">BRICKLAB DEVELOPER</p>
                    <h1><?= escape_html($profileName) ?></h1>
                    <p class="profile-username">@<?= escape_html((string) $profile['username']) ?></p>
                    <?php if (trim((string) ($profile['professional_role'] ?? '')) !== ''): ?>
                        <p class="profile-role"><?= escape_html((string) $profile['professional_role']) ?></p>
                    <?php endif; ?>
                    <?php if (trim((string) ($profile['location'] ?? '')) !== ''): ?>
                        <p class="profile-location"><span aria-hidden="true">⌖</span> <?= escape_html((string) $profile['location']) ?></p>
                    <?php endif; ?>
                    <?php if (trim((string) ($profile['bio'] ?? '')) !== ''): ?>
                        <p class="profile-bio"><?= nl2br(escape_html((string) $profile['bio'])) ?></p>
                    <?php endif; ?>
                    <?php if ($profileSkills !== []): ?>
                        <ul class="showcase-tag-list profile-skills" aria-label="Skills">
                            <?php foreach ($profileSkills as $skill): ?>
                                <li><?= escape_html($skill) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <div class="profile-social-links">
                        <?php foreach ([
                            'GitHub' => 'github_url',
                            'LinkedIn' => 'linkedin_url',
                            'Portfolio' => 'portfolio_url',
                        ] as $label => $field): ?>
                            <?php if (showcase_url_is_valid((string) ($profile[$field] ?? '')) && $profile[$field] !== ''): ?>
                                <a href="<?= escape_html((string) $profile[$field]) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= escape_html($label) ?> <span aria-hidden="true">↗</span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="profile-identity-actions">
                    <span class="profile-stat">
                        <strong><?= $profileProjectCount ?></strong>
                        <span><?= $profileProjectCount === 1 ? 'public project' : 'public projects' ?></span>
                    </span>
                    <?php if (current_user_id() === (int) $profile['id']): ?>
                        <a class="btn btn-secondary" href="/bricklab/profile-settings.php">Edit profile</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="public-section public-container profile-projects" aria-labelledby="profile-projects-title">
            <div class="public-section-heading">
                <div>
                    <p class="public-eyebrow">BUILT AND SHARED</p>
                    <h2 id="profile-projects-title"><?= escape_html($profileName) ?>’s projects</h2>
                </div>
            </div>
            <form class="profile-project-filters showcase-filters surface" method="get">
                <label class="showcase-search-field">
                    <span>Search projects</span>
                    <input
                        type="search"
                        name="search"
                        maxlength="100"
                        value="<?= escape_html($filters['search']) ?>"
                        placeholder="Find a project"
                    >
                </label>
                <label>
                    <span>Sort by</span>
                    <select name="sort">
                        <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Recently shared</option>
                        <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                        <option value="title" <?= $filters['sort'] === 'title' ? 'selected' : '' ?>>Project name</option>
                    </select>
                </label>
                <div class="showcase-filter-actions">
                    <button class="btn btn-primary" type="submit">Search</button>
                    <?php if ($filters['search'] !== '' || $filters['sort'] !== 'newest'): ?>
                        <a class="btn btn-secondary" href="/bricklab/u/<?= rawurlencode((string) $profile['username']) ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($projects['items'] !== []): ?>
                <div class="showcase-project-grid">
                    <?php foreach ($projects['items'] as $project): ?>
                        <?php require __DIR__ . '/includes/public-project-card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($projectPageCount > 1): ?>
                    <nav class="showcase-pagination" aria-label="Project pages">
                        <?php if ($currentProjectPage > 1): ?>
                            <a class="btn btn-secondary" href="?<?= escape_html(http_build_query(array_filter([
                                'search' => $filters['search'],
                                'sort' => $filters['sort'] === 'newest' ? '' : $filters['sort'],
                                'page' => $currentProjectPage - 1,
                            ], static fn (string|int $value): bool => $value !== ''))) ?>">Previous</a>
                        <?php endif; ?>
                        <span>Page <?= $currentProjectPage ?> of <?= $projectPageCount ?></span>
                        <?php if ($currentProjectPage < $projectPageCount): ?>
                            <a class="btn btn-secondary" href="?<?= escape_html(http_build_query(array_filter([
                                'search' => $filters['search'],
                                'sort' => $filters['sort'] === 'newest' ? '' : $filters['sort'],
                                'page' => $currentProjectPage + 1,
                            ], static fn (string|int $value): bool => $value !== ''))) ?>">Load more <span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="public-empty-state surface">
                    <h3>No projects match this search</h3>
                    <p>Try a different title or clear your search.</p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
