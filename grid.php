<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';
require_once __DIR__ . '/includes/community.php';

$pageTitle = 'The Grid';
$publicActivePage = 'grid';
$loadCommunityScript = true;
$databaseError = '';
$userId = current_user_id();
$posts = ['items' => [], 'total' => 0];
$userBuilds = [];
$searchQuery = trim(request_string($_GET['q'] ?? ''));
$searchResults = null;
$buildersToDiscover = [];
$pendingBuildmateRequests = [];

// Handle POST actions (publish build log, delete build log, like, buildmate toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
    $action = request_string($_POST['action'] ?? '');

    if (!csrf_token_is_valid($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        $databaseError = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'create_post') {
        $body = trim(request_string($_POST['body'] ?? ''));
        $postType = request_string($_POST['post_type'] ?? 'update');
        $projectId = positive_integer($_POST['project_id'] ?? null);

        if ($body === '' || strlen($body) > 2000) {
            set_flash_message('error', 'Build Log must be between 1 and 2,000 characters.');
        } elseif (!isset(community_post_type_options()[$postType])) {
            set_flash_message('error', 'Invalid Build Log type.');
        } else {
            try {
                community_create_post($pdo, $userId, $body, $postType, $projectId);
                set_flash_message('success', 'Your Build Log is published on The Grid!');
            } catch (InvalidArgumentException $exception) {
                set_flash_message('error', $exception->getMessage());
            } catch (PDOException $exception) {
                error_log('BrickLab build log could not be created: ' . $exception->getMessage());
                set_flash_message('error', 'Your Build Log could not be published. Please try again.');
            }
        }
        header('Location: /bricklab/grid.php', true, 303);
        exit;
    } elseif ($action === 'delete_post') {
        $postId = positive_integer($_POST['post_id'] ?? null);
        if ($postId !== null) {
            try {
                community_delete_post($pdo, $postId, $userId);
                set_flash_message('success', 'Build Log removed.');
            } catch (PDOException $exception) {
                error_log('BrickLab build log could not be deleted: ' . $exception->getMessage());
                set_flash_message('error', 'Build Log could not be deleted.');
            }
        }
        header('Location: /bricklab/grid.php', true, 303);
        exit;
    } elseif ($action === 'edit_post') {
        $postId = positive_integer($_POST['post_id'] ?? null);
        $body = trim(request_string($_POST['body'] ?? ''));
        $postType = request_string($_POST['post_type'] ?? 'update');
        $projectId = positive_integer($_POST['project_id'] ?? null);
        if ($postId === null || $body === '' || strlen($body) > 2000) {
            set_flash_message('error', 'Build Log must be between 1 and 2,000 characters.');
        } elseif (!isset(community_post_type_options()[$postType])) {
            set_flash_message('error', 'Invalid Build Log type.');
        } else {
            try {
                community_update_post($pdo, $postId, $userId, $body, $postType, $projectId);
                set_flash_message('success', 'Build Log updated.');
            } catch (InvalidArgumentException $exception) {
                set_flash_message('error', $exception->getMessage());
            } catch (PDOException $exception) {
                error_log('BrickLab build log could not be updated: ' . $exception->getMessage());
                set_flash_message('error', 'Build Log could not be updated.');
            }
        }
        header('Location: /bricklab/grid.php', true, 303);
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
        header('Location: /bricklab/grid.php', true, 303);
        exit;
    } elseif ($action === 'buildmate') {
        $targetId = positive_integer($_POST['target_id'] ?? null);
        if ($targetId !== null && $targetId !== $userId) {
            try {
                $status = buildmate_toggle($pdo, $userId, $targetId);
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    $newCount = buildmates_count($pdo, $targetId);
                    header('Content-Type: application/json');
                    echo json_encode(['status' => $status, 'buildmates_count' => $newCount]);
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('BrickLab buildmate toggle failed: ' . $exception->getMessage());
            }
        }
        header('Location: /bricklab/grid.php', true, 303);
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
        header('Location: /bricklab/grid.php', true, 303);
        exit;
    }
}

// Fetch feed, user builds & suggestions
try {
    $pageSize = 20;
    $requestedPage = min(positive_integer($_GET['page'] ?? 1) ?? 1, 1000000);
    $posts = community_fetch_feed($pdo, $pageSize, ($requestedPage - 1) * $pageSize, $userId);

    if ($userId !== null) {
        $buildStmt = $pdo->prepare(
            'SELECT id, title FROM projects WHERE user_id = :uid ORDER BY title ASC'
        );
        $buildStmt->execute(['uid' => $userId]);
        $userBuilds = $buildStmt->fetchAll();
    }

    if ($searchQuery !== '') {
        $searchResults = community_search_builders($pdo, $searchQuery);
    }

    $buildersToDiscover = community_fetch_builders_to_discover($pdo, 5, $userId);
    if ($userId !== null) {
        $pendingBuildmateRequests = buildmates_pending_requests($pdo, $userId, 10);
    }
} catch (PDOException $exception) {
    error_log('BrickLab The Grid data could not be loaded: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = 'The Grid could not be loaded right now. Please try again later.';
}

$totalPages = max(1, (int) ceil($posts['total'] / $pageSize));
$currentPage = min($requestedPage, $totalPages);
$flash = consume_flash_message();

require __DIR__ . '/includes/public-header.php';
?>
    <section class="community-page public-container" aria-labelledby="grid-title">
        <div class="community-layout">
            <!-- Left sidebar: Publish Build Log + Discover Builders -->
            <aside class="community-sidebar">
                <?php if ($userId !== null): ?>
                    <div class="community-compose surface" data-community-compose>
                        <p class="public-eyebrow">PUBLISH A BUILD LOG</p>
                        <form method="post" action="/bricklab/grid.php" class="community-compose-form">
                            <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                            <input type="hidden" name="action" value="create_post">
                            <textarea
                                name="body"
                                maxlength="2000"
                                rows="4"
                                placeholder="What are you building or learning today?"
                                required
                                aria-label="Build Log content"
                            ></textarea>
                            <div class="community-compose-controls">
                                <label>
                                    <span>Log type</span>
                                    <select name="post_type">
                                        <?php foreach (community_post_type_options() as $value => $label): ?>
                                            <option value="<?= escape_html($value) ?>"><?= escape_html(community_post_type_icon($value) . ' ' . $label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <?php if ($userBuilds !== []): ?>
                                    <label>
                                        <span>Link a Build</span>
                                        <select name="project_id">
                                            <option value="">General Update</option>
                                            <?php foreach ($userBuilds as $build): ?>
                                                <option value="<?= (int) $build['id'] ?>"><?= escape_html((string) $build['title']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-primary" type="submit">Publish Build Log</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="community-join-cta surface">
                        <p class="public-eyebrow">ENTER THE GRID</p>
                        <h3>Join fellow Builders.</h3>
                        <p>Sign in to document your development journey, connect with Buildmates, and showcase your Builds.</p>
                        <a class="btn btn-primary" href="/bricklab/login.php">Sign in</a>
                        <a class="btn btn-secondary" href="/bricklab/register.php">Become a Builder</a>
                    </div>
                <?php endif; ?>

                <div class="community-search surface">
                    <p class="public-eyebrow">DISCOVER BUILDERS</p>
                    <form method="get" action="/bricklab/grid.php" class="community-search-form">
                        <label>
                            <span>Search Builders by name or BrickID</span>
                            <input
                                type="search"
                                name="q"
                                maxlength="100"
                                value="<?= escape_html($searchQuery) ?>"
                                placeholder="@username or display name"
                            >
                        </label>
                        <button class="btn btn-secondary" type="submit">Search</button>
                    </form>
                    <?php if ($searchResults !== null): ?>
                        <div class="community-search-results" role="region" aria-label="Search results">
                            <?php if ($searchResults === []): ?>
                                <p class="community-search-empty">No Builders found matching "<strong><?= escape_html($searchQuery) ?></strong>"</p>
                            <?php else: ?>
                                <?php foreach ($searchResults as $result): ?>
                                    <?php
                                    $resultName = trim((string) ($result['display_name'] ?? ''));
                                    if ($resultName === '') {
                                        $resultName = (string) $result['username'];
                                    }
                                    $resultAvatar = showcase_safe_image_source(
                                        isset($result['avatar_url']) ? (string) $result['avatar_url'] : null
                                    );
                                    ?>
                                    <a class="community-search-result" href="/bricklab/u/<?= rawurlencode((string) $result['username']) ?>">
                                        <span class="community-search-avatar">
                                            <?php if ($resultAvatar !== null): ?>
                                                <img src="<?= escape_html($resultAvatar) ?>" alt="" loading="lazy">
                                            <?php else: ?>
                                                <?= escape_html(showcase_avatar_initials($resultName)) ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="community-search-info">
                                            <strong><?= escape_html($resultName) ?></strong>
                                            <span>@<?= escape_html((string) $result['username']) ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>

            <!-- Main feed (Grid Activity) -->
            <div class="community-feed-main">
                <header class="community-feed-header">
                    <div>
                        <p class="public-eyebrow"><span></span> THE GRID</p>
                        <h1 id="grid-title">Discover what's being <span>built.</span></h1>
                        <p class="community-feed-description">Grid Activity: Build Logs, project milestones, and updates from Builders.</p>
                    </div>
                </header>

                <?php if ($flash !== null): ?>
                    <p class="form-notice form-notice-<?= escape_html($flash['type']) ?>" role="status"><?= escape_html($flash['message']) ?></p>
                <?php endif; ?>

                <?php if ($databaseError !== ''): ?>
                    <p class="form-notice form-notice-error" role="alert"><?= escape_html($databaseError) ?></p>
                <?php elseif ($posts['items'] !== []): ?>
                    <div class="community-feed" data-community-feed>
                        <?php foreach ($posts['items'] as $post): ?>
                            <?php require __DIR__ . '/includes/community-post-card.php'; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="showcase-pagination" aria-label="Feed pages">
                            <?php if ($currentPage > 1): ?>
                                <a class="btn btn-secondary" href="?page=<?= $currentPage - 1 ?><?= $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '' ?>">Previous</a>
                            <?php endif; ?>
                            <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
                            <?php if ($currentPage < $totalPages): ?>
                                <a class="btn btn-secondary" href="?page=<?= $currentPage + 1 ?><?= $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '' ?>">
                                    More Build Logs <span aria-hidden="true">→</span>
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="public-empty-state surface">
                        <span class="public-empty-mark" aria-hidden="true"></span>
                        <h3>The Grid is just getting started</h3>
                        <p>Be the first Builder to share what you're creating, learning, or releasing.</p>
                        <?php if ($userId === null): ?>
                            <a class="btn btn-primary" href="/bricklab/register.php">Become a Builder</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right sidebar: Builders to Discover & Brand Info -->
            <aside class="community-sidebar-right">
                <?php if ($buildersToDiscover !== []): ?>
                    <div class="community-discover-card surface">
                        <p class="public-eyebrow">BUILDERS TO DISCOVER</p>
                        <div class="builders-discover-list">
                            <?php foreach ($buildersToDiscover as $discoverBuilder): ?>
                                <?php
                                $dName = trim((string) ($discoverBuilder['display_name'] ?? '')) ?: (string) $discoverBuilder['username'];
                                $dAvatar = showcase_safe_image_source(isset($discoverBuilder['avatar_url']) ? (string) $discoverBuilder['avatar_url'] : null);
                                ?>
                                <a class="builder-discover-item" href="/bricklab/u/<?= rawurlencode((string) $discoverBuilder['username']) ?>">
                                    <span class="community-search-avatar">
                                        <?php if ($dAvatar !== null): ?>
                                            <img src="<?= escape_html($dAvatar) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <?= escape_html(showcase_avatar_initials($dName)) ?>
                                        <?php endif; ?>
                                    </span>
                                    <div class="builder-discover-copy">
                                        <strong><?= escape_html($dName) ?></strong>
                                        <span>@<?= escape_html((string) $discoverBuilder['username']) ?></span>
                                    </div>
                                    <span class="builder-discover-builds"><?= (int) $discoverBuilder['build_count'] ?> <?= (int) $discoverBuilder['build_count'] === 1 ? 'Build' : 'Builds' ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($pendingBuildmateRequests !== []): ?>
                    <div class="community-discover-card surface">
                        <p class="public-eyebrow">PENDING REQUESTS</p>
                        <div class="builders-discover-list">
                            <?php foreach ($pendingBuildmateRequests as $request): ?>
                                <?php $requestName = trim((string) ($request['display_name'] ?? '')) ?: (string) $request['username']; ?>
                                <div class="builder-discover-item">
                                    <div class="builder-discover-copy">
                                        <strong><?= escape_html($requestName) ?></strong>
                                        <span>@<?= escape_html((string) $request['username']) ?></span>
                                    </div>
                                    <form method="post" action="/bricklab/grid.php" class="buildmate-request-actions">
                                        <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                                        <input type="hidden" name="target_id" value="<?= (int) $request['id'] ?>">
                                        <button class="btn btn-primary" type="submit" name="action" value="buildmate_accept">Accept</button>
                                        <button class="btn btn-secondary" type="submit" name="action" value="buildmate_decline">Decline</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="community-info-card surface">
                    <span class="community-info-mark" aria-hidden="true">✳</span>
                    <p class="public-eyebrow">THE BRICKLAB JOURNEY</p>
                    <h3>From first brick to final build.</h3>
                    <p>A creative development workshop where Builders organise projects, track progress, and showcase what they create.</p>
                    <div class="community-info-links">
                        <a class="btn btn-secondary" href="/bricklab/showcase.php">Explore Build Wall</a>
                        <?php if ($userId !== null): ?>
                            <a class="btn btn-primary" href="/bricklab/projects.php">The Workshop</a>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </section>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
