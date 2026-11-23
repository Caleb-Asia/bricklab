<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';
require_once __DIR__ . '/includes/community.php';

$username = trim(request_string($_GET['username'] ?? ''));
$filters = [
    'search' => '',
    'category' => 'all',
    'technology' => '',
    'sort' => 'newest',
];
$requestedPage = min(positive_integer($_GET['page'] ?? 1) ?? 1, 1000000);
$pageSize = 9;
$profileBuildCount = 0;
$profileError = '';
$projects = ['items' => [], 'total' => 0];
$profileLogs = ['items' => [], 'total' => 0];
$buildmatesCount = 0;
$buildmateStatus = null; // null | 'sent' | 'received' | 'accepted'
$userId = current_user_id();

$activeTab = request_string($_GET['tab'] ?? 'logs');
if (!in_array($activeTab, ['logs', 'wall', 'projects', 'posts'], true)) {
    $activeTab = 'logs';
}
if ($activeTab === 'posts') {
    $activeTab = 'logs';
}
if ($activeTab === 'projects') {
    $activeTab = 'wall';
}

// Handle Buildmate connection / Like actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
    $action = request_string($_POST['action'] ?? '');
    if (csrf_token_is_valid($_POST['_csrf'] ?? null)) {
        if ($action === 'buildmate') {
            $targetId = positive_integer($_POST['target_id'] ?? null);
            if ($targetId !== null && $targetId !== $userId) {
                try {
                    $newStatus = buildmate_toggle($pdo, $userId, $targetId);
                    $newCount = buildmates_count($pdo, $targetId);
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => $newStatus,
                            'buildmates_count' => $newCount,
                        ]);
                        exit;
                    }
                } catch (PDOException $exception) {
                    error_log('BrickLab buildmate toggle failed: ' . $exception->getMessage());
                }
            }
            header('Location: /bricklab/u/' . rawurlencode($username), true, 303);
            exit;
        } elseif ($action === 'buildmate_accept' || $action === 'buildmate_decline' || $action === 'buildmate_remove') {
            $targetId = positive_integer($_POST['target_id'] ?? null);
            if ($targetId !== null && $targetId !== $userId) {
                try {
                    if ($action === 'buildmate_accept') {
                        buildmate_accept($pdo, $userId, $targetId);
                        set_flash_message('success', 'Buildmate request accepted.');
                    } elseif ($action === 'buildmate_decline') {
                        buildmate_decline($pdo, $userId, $targetId);
                        set_flash_message('success', 'Buildmate request declined.');
                    } else {
                        buildmate_remove($pdo, $userId, $targetId);
                        set_flash_message('success', 'Buildmate connection removed.');
                    }
                } catch (PDOException $exception) {
                    error_log('BrickLab Buildmate action failed: ' . $exception->getMessage());
                    set_flash_message('error', 'The Buildmate action could not be completed.');
                }
            }
            header('Location: /bricklab/u/' . rawurlencode($username), true, 303);
            exit;
        } elseif ($action === 'like') {
            $postId = positive_integer($_POST['post_id'] ?? null);
            if ($postId !== null) {
                try {
                    $liked = community_toggle_like($pdo, $userId, $postId);
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = :pid');
                        $countStmt->execute(['pid' => $postId]);
                        $newCount = (int) $countStmt->fetchColumn();
                        header('Content-Type: application/json');
                        echo json_encode(['liked' => $liked, 'count' => $newCount]);
                        exit;
                    }
                } catch (PDOException $exception) {
                    error_log('BrickLab like toggle failed: ' . $exception->getMessage());
                }
            }
            header('Location: /bricklab/u/' . rawurlencode($username), true, 303);
            exit;
        }
    }
}

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
            LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $profile = $statement->fetch();
        if ($profile === false) {
            http_response_code(404);
            $profile = null;
        }
    } catch (PDOException $exception) {
        error_log('BrickLab Builder profile could not be loaded: ' . $exception->getMessage());
        http_response_code(500);
        $profileError = 'This Builder profile could not be loaded right now. Please try again later.';
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
            $profileBuildCount = (int) $countStatement->fetchColumn();
            $projects = showcase_fetch_projects(
                $pdo,
                $filters,
                (int) $profile['id'],
                $pageSize,
                ($requestedPage - 1) * $pageSize
            );

            // Buildmates count & relationship status
            $buildmatesCount = buildmates_count($pdo, (int) $profile['id']);
            $profileLogs = community_fetch_user_posts($pdo, (int) $profile['id'], 15, 0, $userId);

            if ($userId !== null && $userId !== (int) $profile['id']) {
                $buildmateStatus = buildmate_status($pdo, $userId, (int) $profile['id']);
            }
        } catch (PDOException $exception) {
            error_log('BrickLab Builder profile data could not be loaded: ' . $exception->getMessage());
            http_response_code(500);
            $profileError = 'This Builder profile could not be loaded right now. Please try again later.';
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
                error_log('BrickLab Builder Build Wall page could not be loaded: ' . $exception->getMessage());
                http_response_code(500);
                $profileError = 'This Builder profile could not be loaded right now. Please try again later.';
                $profile = null;
                $projects = ['items' => [], 'total' => 0];
                $profileBuildCount = 0;
            }
        }
    }
}

$profileName = $profile === null
    ? ''
    : (trim((string) ($profile['display_name'] ?? '')) ?: (string) $profile['username']);
$pageTitle = $profile === null
    ? ($profileError === '' ? 'Builder not found' : 'Profile unavailable')
    : $profileName . ' — Builder Profile';
$publicActivePage = 'grid';
$loadShowcaseScript = true;
$loadCommunityScript = true;
$profileAvatar = $profile === null
    ? null
    : showcase_safe_image_source(isset($profile['avatar_url']) ? (string) $profile['avatar_url'] : null);
$profileSkills = $profile === null ? [] : showcase_parse_tags((string) ($profile['skills'] ?? ''));
$profileBuildCount = $profile === null ? 0 : $profileBuildCount;
$projectPageCount = max(1, (int) ceil($projects['total'] / 9));
$currentProjectPage = min($requestedPage, $projectPageCount);

require __DIR__ . '/includes/public-header.php';
?>
    <?php if ($profile === null): ?>
        <section class="public-container profile-not-found">
            <p class="public-eyebrow">BUILDER PROFILE</p>
            <h1><?= $profileError === ''
                ? "This Builder profile isn't available."
                : "We couldn't load this Builder profile." ?></h1>
            <p><?= escape_html($profileError !== ''
                ? $profileError
                : 'The Builder profile may not exist or may have been renamed.') ?></p>
            <a class="btn btn-primary" href="/bricklab/grid.php">Enter The Grid</a>
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
                    <p class="public-eyebrow">A BRICKLAB BUILDER</p>
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
                    <div class="profile-stats-row">
                        <span class="profile-stat">
                            <strong data-buildmate-count><?= $buildmatesCount ?></strong>
                            <span><?= $buildmatesCount === 1 ? 'Buildmate' : 'Buildmates' ?></span>
                        </span>
                        <span class="profile-stat">
                            <strong><?= $profileBuildCount ?></strong>
                            <span><?= $profileBuildCount === 1 ? 'Build' : 'Builds' ?></span>
                        </span>
                    </div>

                    <?php if ($userId !== null && $userId !== (int) $profile['id']): ?>
                        <?php
                        $buttonText = match ($buildmateStatus) {
                            'accepted' => 'Remove Buildmate',
                            'sent' => 'Cancel Request',
                            'received' => 'Accept Request',
                            default => 'Add Buildmate',
                        };
                        $buttonClass = ($buildmateStatus === 'accepted' || $buildmateStatus === 'sent')
                            ? 'btn-secondary'
                            : 'btn-primary';
                        ?>
                        <form method="post" class="profile-follow-form">
                            <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                            <input type="hidden" name="target_id" value="<?= (int) $profile['id'] ?>">
                            <?php if ($buildmateStatus === 'received'): ?>
                                <button type="submit" name="action" value="buildmate_accept" class="btn btn-primary">Accept Request</button>
                                <button type="submit" name="action" value="buildmate_decline" class="btn btn-secondary">Decline Request</button>
                            <?php else: ?>
                                <?php if ($buildmateStatus === 'accepted'): ?>
                                    <button type="submit" name="action" value="buildmate_remove" class="btn btn-secondary">Remove Buildmate</button>
                                <?php elseif ($buildmateStatus === 'sent'): ?>
                                    <button type="submit" name="action" value="buildmate_remove" class="btn btn-secondary">Cancel Request</button>
                                <?php else: ?>
                            <button
                                type="submit"
                                class="btn <?= $buttonClass ?>"
                                name="action"
                                value="buildmate"
                                data-buildmate-status=""
                            >
                                <?= escape_html($buttonText) ?>
                            </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </form>
                    <?php elseif (current_user_id() === (int) $profile['id']): ?>
                        <a class="btn btn-secondary" href="/bricklab/profile-settings.php">Edit Profile</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Brand Tabs: Build Logs & Build Wall -->
        <div class="profile-tabs public-container">
            <a
                class="profile-tab<?= $activeTab === 'logs' ? ' profile-tab-active' : '' ?>"
                href="/bricklab/u/<?= rawurlencode((string) $profile['username']) ?>?tab=logs"
            >Build Logs</a>
            <a
                class="profile-tab<?= $activeTab === 'wall' ? ' profile-tab-active' : '' ?>"
                href="/bricklab/u/<?= rawurlencode((string) $profile['username']) ?>?tab=wall"
            >Build Wall</a>
        </div>

        <?php if ($activeTab === 'logs'): ?>
            <!-- Build Logs Tab -->
            <section class="public-section public-container profile-posts-section" aria-labelledby="profile-logs-title">
                <div class="public-section-heading">
                    <div>
                        <p class="public-eyebrow">DEVELOPMENT JOURNEY</p>
                        <h2 id="profile-logs-title"><?= escape_html($profileName) ?>'s Build Logs</h2>
                    </div>
                </div>
                <?php if ($profileLogs['items'] !== []): ?>
                    <div class="community-feed" data-community-feed>
                        <?php foreach ($profileLogs['items'] as $post): ?>
                            <?php require __DIR__ . '/includes/community-post-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="public-empty-state surface">
                        <h3>No Build Logs yet</h3>
                        <p><?= escape_html($profileName) ?> hasn't published any Build Logs or updates yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <!-- Build Wall Tab -->
            <section class="public-section public-container profile-projects" aria-labelledby="profile-wall-title">
                <div class="public-section-heading">
                    <div>
                        <p class="public-eyebrow">THE BUILD WALL</p>
                        <h2 id="profile-wall-title"><?= escape_html($profileName) ?>'s Builds</h2>
                    </div>
                </div>
                <form class="profile-project-filters showcase-filters surface" method="get">
                    <input type="hidden" name="tab" value="wall">
                    <label class="showcase-search-field">
                        <span>Search Builds</span>
                        <input
                            type="search"
                            name="search"
                            maxlength="100"
                            value="<?= escape_html($filters['search']) ?>"
                            placeholder="Find a Build"
                        >
                    </label>
                    <label>
                        <span>Sort by</span>
                        <select name="sort">
                            <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Recently shared</option>
                            <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                            <option value="title" <?= $filters['sort'] === 'title' ? 'selected' : '' ?>>Build name</option>
                        </select>
                    </label>
                    <div class="showcase-filter-actions">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <?php if ($filters['search'] !== '' || $filters['sort'] !== 'newest'): ?>
                            <a class="btn btn-secondary" href="/bricklab/u/<?= rawurlencode((string) $profile['username']) ?>?tab=wall">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($projects['items'] !== []): ?>
                    <div class="showcase-project-grid profile-build-wall">
                        <?php foreach ($projects['items'] as $projectIndex => $project): ?>
                            <?php $project['wall_size'] = $projectIndex % 7 === 0 ? 'featured' : ($projectIndex % 4 === 2 ? 'compact' : 'standard'); ?>
                            <?php require __DIR__ . '/includes/public-project-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($projectPageCount > 1): ?>
                        <nav class="showcase-pagination" aria-label="Build Wall pages">
                            <?php if ($currentProjectPage > 1): ?>
                                <a class="btn btn-secondary" href="?<?= escape_html(http_build_query(array_filter([
                                    'tab' => 'wall',
                                    'search' => $filters['search'],
                                    'sort' => $filters['sort'] === 'newest' ? '' : $filters['sort'],
                                    'page' => $currentProjectPage - 1,
                                ], static fn (string|int $value): bool => $value !== ''))) ?>">Previous</a>
                            <?php endif; ?>
                            <span>Page <?= $currentProjectPage ?> of <?= $projectPageCount ?></span>
                            <?php if ($currentProjectPage < $projectPageCount): ?>
                                <a class="btn btn-secondary" href="?<?= escape_html(http_build_query(array_filter([
                                    'tab' => 'wall',
                                    'search' => $filters['search'],
                                    'sort' => $filters['sort'] === 'newest' ? '' : $filters['sort'],
                                    'page' => $currentProjectPage + 1,
                                ], static fn (string|int $value): bool => $value !== ''))) ?>">More Builds <span aria-hidden="true">→</span></a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="public-empty-state surface">
                        <h3><?= $filters['search'] !== '' ? 'No Builds match this search' : 'No public Builds yet' ?></h3>
                        <p><?= $filters['search'] !== '' ? 'Try a different title or clear your search.' : escape_html($profileName) . ' hasn\'t showcased any Builds on the Build Wall yet.' ?></p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
