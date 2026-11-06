<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';

$pageTitle = 'Developer Showcase';
$publicActivePage = 'showcase';
$loadAuthScene = true;
$loadShowcaseScript = true;
$categories = showcase_category_options();
$databaseError = '';
$featuredProjects = [];
$projects = ['items' => [], 'total' => 0];
$technologyOptions = [];
$filters = [
    'search' => trim(request_string($_GET['search'] ?? null)),
    'category' => request_string($_GET['category'] ?? 'all'),
    'technology' => request_string($_GET['technology'] ?? ''),
    'sort' => request_string($_GET['sort'] ?? 'newest'),
];
$requestedPage = min(positive_integer($_GET['page'] ?? 1) ?? 1, 1000000);
$pageSize = 9;

if (strlen($filters['search']) > 100) {
    $filters['search'] = substr($filters['search'], 0, 100);
}
if (!isset($categories[$filters['category']]) && $filters['category'] !== 'all') {
    $filters['category'] = 'all';
}
if (!in_array($filters['sort'], ['newest', 'oldest', 'title'], true)) {
    $filters['sort'] = 'newest';
}

try {
    $technologyOptions = showcase_fetch_technology_options($pdo);
    if ($filters['technology'] !== '') {
        $selectedTechnology = null;
        foreach ($technologyOptions as $technology) {
            if (strcasecmp($technology, $filters['technology']) === 0) {
                $selectedTechnology = $technology;
                break;
            }
        }
        $filters['technology'] = $selectedTechnology ?? '';
    }

    $projectOffset = ($requestedPage - 1) * $pageSize;
    $projects = showcase_fetch_projects($pdo, $filters, null, $pageSize, $projectOffset);
    $featuredProjects = showcase_fetch_projects(
        $pdo,
        ['category' => 'all', 'technology' => '', 'search' => '', 'sort' => 'newest'],
        null,
        3
    )['items'];
} catch (PDOException $exception) {
    error_log('BrickLab Showcase data could not be loaded: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = 'The Showcase could not be loaded right now. Please try again later.';
}

$totalPages = max(1, (int) ceil($projects['total'] / $pageSize));
$currentPage = min($requestedPage, $totalPages);
if ($currentPage !== $requestedPage && $databaseError === '') {
    try {
        $projects = showcase_fetch_projects(
            $pdo,
            $filters,
            null,
            $pageSize,
            ($currentPage - 1) * $pageSize
        );
    } catch (PDOException $exception) {
        error_log('BrickLab Showcase page could not be loaded: ' . $exception->getMessage());
        http_response_code(500);
        $databaseError = 'The Showcase could not be loaded right now. Please try again later.';
    }
}
$queryValues = array_filter([
    'search' => $filters['search'],
    'category' => $filters['category'] === 'all' ? '' : $filters['category'],
    'technology' => $filters['technology'],
    'sort' => $filters['sort'] === 'newest' ? '' : $filters['sort'],
], static fn (string $value): bool => $value !== '');

require __DIR__ . '/includes/public-header.php';
?>
    <section class="showcase-hero public-container">
        <div class="showcase-hero-copy">
            <p class="public-eyebrow"><span></span> THE BRICKLAB SHOWCASE</p>
            <h1>Built by developers.<br><span>Shared with the world.</span></h1>
            <p class="showcase-hero-description">
                Discover thoughtful projects, meet the developers behind them, and find inspiration for what you can build next.
            </p>
            <div class="showcase-hero-actions">
                <a class="btn btn-primary" href="#projects">Explore projects <span aria-hidden="true">↓</span></a>
                <a class="btn btn-secondary" href="/bricklab/developers.php">Meet the developers</a>
            </div>
            <p class="showcase-privacy-note">Every project here was chosen for public sharing by its creator.</p>
        </div>
        <div class="showcase-hero-art" aria-hidden="true">
            <div class="showcase-art-orbit showcase-art-orbit-one"></div>
            <div class="showcase-art-orbit showcase-art-orbit-two"></div>
            <div class="auth-brick-art" data-auth-scene="workshop">
                <canvas class="auth-scene-canvas"></canvas>
                <div class="auth-scene-fallback">
                    <span class="auth-art-orbit auth-art-orbit-one"></span>
                    <span class="auth-art-orbit auth-art-orbit-two"></span>
                    <span class="auth-brick auth-brick-purple"><i></i><i></i><i></i><i></i></span>
                    <span class="auth-brick auth-brick-cyan"><i></i><i></i><i></i></span>
                    <span class="auth-brick auth-brick-lavender"><i></i><i></i></span>
                    <span class="auth-brick auth-brick-float"></span>
                </div>
            </div>
            <span class="showcase-art-caption">Ideas take shape here</span>
        </div>
        <div class="showcase-hero-grid" aria-hidden="true"></div>
    </section>

    <?php if ($databaseError !== ''): ?>
        <div class="public-container">
            <p class="form-notice form-notice-error" role="alert"><?= escape_html($databaseError) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($featuredProjects !== []): ?>
        <section class="public-section public-container" aria-labelledby="featured-title">
            <div class="public-section-heading">
                <div>
                    <p class="public-eyebrow">A FEW TO GET YOU STARTED</p>
                    <h2 id="featured-title">Featured projects</h2>
                </div>
                <a class="public-text-link" href="#projects">Explore all projects <span aria-hidden="true">→</span></a>
            </div>
            <div class="showcase-project-grid showcase-featured-grid">
                <?php foreach ($featuredProjects as $project): ?>
                    <?php require __DIR__ . '/includes/public-project-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="public-section public-container" id="projects" aria-labelledby="projects-title">
        <div class="public-section-heading">
            <div>
                <p class="public-eyebrow">FIND YOUR NEXT INSPIRATION</p>
                <h2 id="projects-title">Explore projects</h2>
            </div>
            <span class="public-result-count">
                <?= $projects['total'] ?> <?= $projects['total'] === 1 ? 'project' : 'projects' ?>
            </span>
        </div>

        <form class="showcase-filters surface" method="get" action="/bricklab/showcase.php" data-showcase-filters>
            <label class="showcase-search-field">
                <span>Search projects</span>
                <input
                    type="search"
                    name="search"
                    maxlength="100"
                    value="<?= escape_html($filters['search']) ?>"
                    placeholder="Search projects or developers"
                >
            </label>
            <label>
                <span>Technology</span>
                <select name="technology">
                    <option value="">All technologies</option>
                    <?php foreach ($technologyOptions as $technology): ?>
                        <option value="<?= escape_html($technology) ?>" <?= $filters['technology'] === $technology ? 'selected' : '' ?>>
                            <?= escape_html($technology) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Category</span>
                <select name="category">
                    <option value="all">All categories</option>
                    <?php foreach ($categories as $value => $label): ?>
                        <option value="<?= escape_html($value) ?>" <?= $filters['category'] === $value ? 'selected' : '' ?>>
                            <?= escape_html($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                <?php if ($queryValues !== []): ?>
                    <a class="btn btn-secondary" href="/bricklab/showcase.php">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($projects['items'] !== []): ?>
            <div class="showcase-project-grid">
                <?php foreach ($projects['items'] as $project): ?>
                    <?php require __DIR__ . '/includes/public-project-card.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="showcase-pagination" aria-label="Project pages">
                    <?php if ($currentPage > 1): ?>
                        <a class="btn btn-secondary" href="?<?= escape_html(http_build_query($queryValues + ['page' => $currentPage - 1])) ?>">
                            Previous
                        </a>
                    <?php endif; ?>
                    <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
                    <?php if ($currentPage < $totalPages): ?>
                        <a class="btn btn-secondary" href="?<?= escape_html(http_build_query($queryValues + ['page' => $currentPage + 1])) ?>">
                            Load more <span aria-hidden="true">→</span>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="public-empty-state surface">
                <span class="public-empty-mark" aria-hidden="true"></span>
                <h3><?= $queryValues === [] ? 'The showcase is just getting started' : 'No projects match those filters' ?></h3>
                <p>
                    <?= $queryValues === []
                        ? 'When developers publish a project, it will find a home here.'
                        : 'Try a different search or clear some filters to discover more.' ?>
                </p>
                <?php if ($queryValues !== []): ?>
                    <a class="btn btn-secondary" href="/bricklab/showcase.php">Clear filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

<?php require __DIR__ . '/includes/public-footer.php'; ?>
