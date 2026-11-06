<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';

$pageTitle = 'Developer Showcase';
$publicActivePage = 'showcase';
$loadShowcaseScript = true;
$loadExhibitionScene = true;
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
$exhibitionProjects = array_slice($featuredProjects, 0, 3);

require __DIR__ . '/includes/public-header.php';
?>
    <section
        class="showcase-hero public-container"
        data-showcase-exhibition
        aria-labelledby="showcase-title"
    >
        <div class="showcase-hero-grid" aria-hidden="true"></div>
        <div class="showcase-exhibition-intro">
            <div class="showcase-hero-copy" data-exhibition-reveal>
                <p class="public-eyebrow"><span></span> THE BRICKLAB SHOWCASE</p>
                <h1 id="showcase-title">Ideas don't stay <span>ideas.</span><br>They become <em>builds.</em></h1>
            </div>
            <div class="showcase-exhibition-support" data-exhibition-reveal>
                <p class="showcase-hero-description">
                    Showcase is where developers share projects, explore ideas, and discover what other creators are building.
                </p>
                <div class="showcase-hero-actions">
                    <a class="btn btn-primary" href="#projects">Explore projects <span aria-hidden="true">↓</span></a>
                    <a class="btn btn-secondary" href="/bricklab/developers.php">Meet developers</a>
                </div>
                <p class="showcase-privacy-note">Every project here was chosen for public sharing by its creator.</p>
            </div>
        </div>

        <div class="showcase-exhibition-stage" role="group" aria-label="Recent public projects">
            <div class="showcase-exhibition-depth" data-exhibition-depth>
                <div class="showcase-exhibition-atmosphere" aria-hidden="true">
                    <span class="showcase-exhibition-orbit showcase-exhibition-orbit-one"></span>
                    <span class="showcase-exhibition-orbit showcase-exhibition-orbit-two"></span>
                    <span class="showcase-exhibition-form showcase-exhibition-form-one"></span>
                    <span class="showcase-exhibition-form showcase-exhibition-form-two"></span>
                    <span class="showcase-exhibition-form showcase-exhibition-form-three"></span>
                    <canvas class="showcase-exhibition-canvas" data-exhibition-canvas></canvas>
                </div>
                <?php foreach ($exhibitionProjects as $panelIndex => $project): ?>
                    <?php
                    $panelOwnerName = trim((string) ($project['display_name'] ?? ''));
                    if ($panelOwnerName === '') {
                        $panelOwnerName = (string) $project['username'];
                    }
                    $panelCover = showcase_safe_image_source(
                        isset($project['cover_image']) ? (string) $project['cover_image'] : null
                    );
                    $panelTechnologies = showcase_parse_tags((string) ($project['technologies'] ?? ''));
                    ?>
                    <article
                        class="showcase-exhibition-panel <?= $panelIndex === 0 ? 'showcase-exhibition-panel-lead' : 'showcase-exhibition-panel-supporting' ?>"
                        data-exhibition-panel
                    >
                        <div class="showcase-exhibition-float" data-exhibition-float>
                            <div class="showcase-exhibition-cover <?= $panelCover === null ? 'showcase-exhibition-cover-placeholder' : '' ?>">
                                <?php if ($panelCover !== null): ?>
                                    <img
                                        src="<?= escape_html($panelCover) ?>"
                                        alt=""
                                        loading="<?= $panelIndex === 0 ? 'eager' : 'lazy' ?>"
                                        <?= $panelIndex === 0 ? 'fetchpriority="high"' : '' ?>
                                    >
                                <?php else: ?>
                                    <span class="showcase-exhibition-cover-mark" aria-hidden="true"></span>
                                <?php endif; ?>
                                <span class="showcase-category">
                                    <?= escape_html(showcase_category_options()[$project['category']] ?? 'Other') ?>
                                </span>
                            </div>
                            <div class="showcase-exhibition-panel-content">
                                <h2><?= escape_html((string) $project['title']) ?></h2>
                                <p><?= escape_html(trim((string) ($project['description'] ?? '')) !== ''
                                    ? (string) $project['description']
                                    : 'A project shared by its developer.') ?></p>
                                <?php if ($panelTechnologies !== []): ?>
                                    <ul class="showcase-exhibition-tags" aria-label="Technologies">
                                        <?php foreach (array_slice($panelTechnologies, 0, 3) as $technology): ?>
                                            <li><?= escape_html($technology) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <div class="showcase-exhibition-credit">
                                    <a href="/bricklab/u/<?= rawurlencode((string) $project['username']) ?>">
                                        <?= escape_html($panelOwnerName) ?>
                                        <span>View creator profile <span aria-hidden="true">↗</span></span>
                                    </a>
                                    <?php if (
                                        (string) ($project['github_url'] ?? '') !== ''
                                        && showcase_url_is_valid((string) $project['github_url'])
                                    ): ?>
                                        <a
                                            class="showcase-exhibition-project-link"
                                            href="<?= escape_html((string) $project['github_url']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >GitHub <span aria-hidden="true">↗</span></a>
                                    <?php endif; ?>
                                    <?php if (
                                        (string) ($project['live_demo_url'] ?? '') !== ''
                                        && showcase_url_is_valid((string) $project['live_demo_url'])
                                    ): ?>
                                        <a
                                            class="showcase-exhibition-project-link"
                                            href="<?= escape_html((string) $project['live_demo_url']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >Live demo <span aria-hidden="true">↗</span></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($databaseError !== ''): ?>
        <div class="public-container">
            <p class="form-notice form-notice-error" role="alert"><?= escape_html($databaseError) ?></p>
        </div>
    <?php endif; ?>

    <section class="public-section public-container showcase-library" id="projects" aria-labelledby="projects-title">
        <div class="public-section-heading">
            <div>
                <p class="public-eyebrow">THE OPEN COLLECTION</p>
                <h2 id="projects-title">Explore projects</h2>
                <p class="showcase-library-description">Search by project or creator, then narrow the collection by technology and category.</p>
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
