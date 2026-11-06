<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';

$pageTitle = 'Developers';
$publicActivePage = 'developers';
$pageSize = 12;
$requestedPage = min(positive_integer($_GET['page'] ?? 1) ?? 1, 1000000);
$developers = ['items' => [], 'total' => 0];
$databaseError = '';

try {
    $developers = showcase_fetch_developers(
        $pdo,
        $pageSize,
        ($requestedPage - 1) * $pageSize
    );
} catch (PDOException $exception) {
    error_log('BrickLab developer directory could not be loaded: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = 'The developer directory could not be loaded right now. Please try again later.';
}

$totalPages = max(1, (int) ceil($developers['total'] / $pageSize));
$currentPage = min($requestedPage, $totalPages);
if ($currentPage !== $requestedPage && $databaseError === '') {
    try {
        $developers = showcase_fetch_developers(
            $pdo,
            $pageSize,
            ($currentPage - 1) * $pageSize
        );
    } catch (PDOException $exception) {
        error_log('BrickLab developer directory page could not be loaded: ' . $exception->getMessage());
        http_response_code(500);
        $databaseError = 'The developer directory could not be loaded right now. Please try again later.';
    }
}

require __DIR__ . '/includes/public-header.php';
?>
    <section class="public-section public-container developer-directory" aria-labelledby="developers-title">
        <header class="developer-directory-heading">
            <p class="public-eyebrow">PEOPLE BEHIND THE BUILDS</p>
            <h1 id="developers-title">Meet the developers</h1>
            <p>Discover BrickLab creators and explore the projects they have chosen to share.</p>
        </header>

        <?php if ($databaseError !== ''): ?>
            <p class="form-notice form-notice-error" role="alert"><?= escape_html($databaseError) ?></p>
        <?php elseif ($developers['items'] !== []): ?>
            <div class="public-section-heading developer-directory-count">
                <span class="public-result-count">
                    <?= $developers['total'] ?> <?= $developers['total'] === 1 ? 'developer' : 'developers' ?>
                </span>
            </div>
            <div class="developer-grid">
                <?php foreach ($developers['items'] as $developer): ?>
                    <?php
                    $developerName = trim((string) ($developer['display_name'] ?? ''));
                    if ($developerName === '') {
                        $developerName = (string) $developer['username'];
                    }
                    $developerAvatar = showcase_safe_image_source(
                        isset($developer['avatar_url']) ? (string) $developer['avatar_url'] : null
                    );
                    ?>
                    <a
                        class="developer-card surface"
                        href="/bricklab/u/<?= rawurlencode((string) $developer['username']) ?>"
                    >
                        <span class="developer-avatar">
                            <?php if ($developerAvatar !== null): ?>
                                <img src="<?= escape_html($developerAvatar) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <?= escape_html(showcase_avatar_initials($developerName)) ?>
                            <?php endif; ?>
                        </span>
                        <span class="developer-card-copy">
                            <strong><?= escape_html($developerName) ?></strong>
                            <span>@<?= escape_html((string) $developer['username']) ?></span>
                            <span><?= escape_html(
                                trim((string) ($developer['professional_role'] ?? '')) ?: 'BrickLab developer'
                            ) ?></span>
                        </span>
                        <span class="developer-project-count">
                            <?= (int) $developer['public_project_count'] ?>
                            <?= (int) $developer['public_project_count'] === 1 ? 'project' : 'projects' ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="showcase-pagination" aria-label="Developer pages">
                    <?php if ($currentPage > 1): ?>
                        <a class="btn btn-secondary" href="?page=<?= $currentPage - 1 ?>">Previous</a>
                    <?php endif; ?>
                    <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
                    <?php if ($currentPage < $totalPages): ?>
                        <a class="btn btn-secondary" href="?page=<?= $currentPage + 1 ?>">
                            Next page <span aria-hidden="true">→</span>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="public-empty-state surface">
                <h2>Creators will appear here</h2>
                <p>Developer profiles become discoverable when their owners publish a project.</p>
                <a class="btn btn-secondary" href="/bricklab/showcase.php">Explore the Showcase</a>
            </div>
        <?php endif; ?>
    </section>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
