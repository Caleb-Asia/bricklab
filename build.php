<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';
require_once __DIR__ . '/includes/community.php';

$buildId = positive_integer($_GET['id'] ?? null);
$build = null;
$buildLogs = [];
$databaseError = '';
$userId = current_user_id();

if ($buildId === null) {
    http_response_code(404);
} else {
    try {
        $statement = $pdo->prepare(
            'SELECT project.id, project.user_id, project.title, project.description,
                    project.category, project.technologies, project.status,
                    project.github_url, project.live_demo_url, project.created_at,
                    user.username, user.display_name, user.avatar_url,
                    (SELECT image.image_path FROM project_images AS image
                     WHERE image.project_id = project.id ORDER BY image.id ASC LIMIT 1) AS cover_image
             FROM projects AS project
             INNER JOIN users AS user ON user.id = project.user_id
             WHERE project.id = :id AND project.is_public = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $buildId]);
        $build = $statement->fetch();

        if ($build !== false) {
            $logs = $pdo->prepare(
                'SELECT post.id, post.user_id, post.body, post.post_type, post.project_id,
                        post.created_at, user.username, user.display_name, user.avatar_url,
                        user.professional_role, project.title AS project_title,
                        project.description AS project_description,
                        project.technologies AS project_technologies,
                        (SELECT image.image_path FROM project_images AS image
                         WHERE image.project_id = project.id ORDER BY image.id ASC LIMIT 1) AS project_cover,
                        (SELECT COUNT(*) FROM post_likes AS pl WHERE pl.post_id = post.id) AS like_count,
                        ' . ($userId !== null
                            ? '(SELECT COUNT(*) FROM post_likes AS vl WHERE vl.post_id = post.id AND vl.user_id = :viewer_id) AS viewer_liked'
                            : '0 AS viewer_liked') . '
                 FROM community_posts AS post
                 INNER JOIN users AS user ON user.id = post.user_id
                 LEFT JOIN projects AS project ON project.id = post.project_id
                 WHERE post.project_id = :build_id
                 ORDER BY post.created_at DESC, post.id DESC'
            );
            $params = ['build_id' => $buildId];
            if ($userId !== null) {
                $params['viewer_id'] = $userId;
            }
            $logs->execute($params);
            $buildLogs = $logs->fetchAll();
        } else {
            http_response_code(404);
        }
    } catch (PDOException $exception) {
        error_log('BrickLab Build Details could not be loaded: ' . $exception->getMessage());
        http_response_code(500);
        $databaseError = 'Build Details could not be loaded right now.';
    }
}

$buildName = $build === null ? 'Build not found' : (string) $build['title'];
$pageTitle = $buildName . ' — Build Details';
$publicActivePage = 'showcase';
$loadCommunityScript = true;
require __DIR__ . '/includes/public-header.php';
?>
<?php if ($build === null): ?>
    <section class="public-container profile-not-found">
        <p class="public-eyebrow">BUILD DETAILS</p>
        <h1><?= escape_html($databaseError !== '' ? 'Build Details unavailable' : 'This Build is not available.') ?></h1>
        <p><?= escape_html($databaseError !== '' ? $databaseError : 'The Build may be private or may no longer exist.') ?></p>
        <a class="btn btn-primary" href="/bricklab/showcase.php">Explore the Build Wall</a>
    </section>
<?php else: ?>
    <?php
    $ownerName = trim((string) ($build['display_name'] ?? '')) ?: (string) $build['username'];
    $cover = showcase_safe_image_source(isset($build['cover_image']) ? (string) $build['cover_image'] : null);
    $technologies = showcase_parse_tags((string) ($build['technologies'] ?? ''));
    ?>
    <section class="public-section public-container build-details" aria-labelledby="build-details-title">
        <div class="build-details-cover surface">
            <img src="<?= escape_html($cover ?? '/bricklab/assets/images/bricklab-project-placeholder.webp') ?>" alt="">
        </div>
        <div class="build-details-copy surface">
            <p class="public-eyebrow"><?= escape_html(showcase_category_options()[$build['category']] ?? 'Build') ?></p>
            <h1 id="build-details-title"><?= escape_html((string) $build['title']) ?></h1>
            <?php if (trim((string) ($build['description'] ?? '')) !== ''): ?>
                <p class="build-details-description"><?= nl2br(escape_html((string) $build['description'])) ?></p>
            <?php endif; ?>
            <?php if ($technologies !== []): ?>
                <ul class="showcase-tag-list" aria-label="Technologies">
                    <?php foreach ($technologies as $technology): ?>
                        <li><?= escape_html($technology) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="build-details-links">
                <a class="btn btn-secondary" href="/bricklab/u/<?= rawurlencode((string) $build['username']) ?>">Builder: <?= escape_html($ownerName) ?></a>
                <?php if (showcase_url_is_valid((string) ($build['github_url'] ?? ''))): ?>
                    <a class="btn btn-secondary" href="<?= escape_html((string) $build['github_url']) ?>" target="_blank" rel="noopener noreferrer">GitHub ↗</a>
                <?php endif; ?>
                <?php if (showcase_url_is_valid((string) ($build['live_demo_url'] ?? ''))): ?>
                    <a class="btn btn-primary" href="<?= escape_html((string) $build['live_demo_url']) ?>" target="_blank" rel="noopener noreferrer">Live demo ↗</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="public-section public-container" aria-labelledby="build-logs-title">
        <div class="public-section-heading">
            <div>
                <p class="public-eyebrow">PROJECT JOURNEY</p>
                <h2 id="build-logs-title">Build Logs</h2>
            </div>
        </div>
        <?php if ($buildLogs !== []): ?>
            <div class="community-feed">
                <?php foreach ($buildLogs as $post): ?>
                    <?php require __DIR__ . '/includes/community-post-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="public-empty-state surface">
                <h3>No Build Logs yet</h3>
                <p>This Builder hasn't shared progress logs for this Build.</p>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
