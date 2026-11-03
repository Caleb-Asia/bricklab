
<?php
require_once __DIR__ . '/includes/auth.php';
require_authentication();
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$userId = current_user_id();
$dashboardStatusOptions = [
    'planning' => 'Planning',
    'in_progress' => 'In progress',
    'completed' => 'Completed',
];
$dashboardStats = null;
$dashboardProjects = [];
$dashboardError = '';

try {
    $statement = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_builds,
            COUNT(CASE WHEN status = :in_progress_status THEN 1 END) AS in_progress_builds,
            COUNT(CASE WHEN status = :completed_status THEN 1 END) AS completed_builds
        FROM projects
        WHERE user_id = :user_id'
    );
    $statement->execute([
        'in_progress_status' => 'in_progress',
        'completed_status' => 'completed',
        'user_id' => $userId,
    ]);
    $stats = $statement->fetch();
    $dashboardStats = [
        'total_builds' => (int) $stats['total_builds'],
        'in_progress_builds' => (int) $stats['in_progress_builds'],
        'completed_builds' => (int) $stats['completed_builds'],
    ];

    if ($dashboardStats['total_builds'] > 0) {
        $statement = $pdo->prepare(
            'SELECT id, title, description, status
            FROM projects
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT 3'
        );
        $statement->execute(['user_id' => $userId]);
        $dashboardProjects = $statement->fetchAll();
    }
} catch (PDOException $exception) {
    error_log('BrickLab dashboard data could not be loaded: ' . $exception->getMessage());
    http_response_code(500);
    $dashboardStats = null;
    $dashboardError = 'Your dashboard data could not be loaded right now. Please try again later.';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Dashboard welcome -->
<section class="dashboard-welcome">
    <div class="welcome-copy">
        <span class="eyebrow">
            <span class="status-dot"></span>
            YOUR CREATIVE WORKSPACE
        </span>

        <h1>Build something <span>awesome.</span></h1>

        <p>
            Plan your next creation, track every step, and turn your
            building ideas into projects worth showcasing.
        </p>

        <a href="/bricklab/projects.php" class="btn btn-primary">
            <span aria-hidden="true">+</span>
            <?= $dashboardError !== ''
                ? 'Open My Builds'
                : (($dashboardStats['total_builds'] ?? 0) > 0 ? 'View My Builds' : 'Start your first build') ?>
        </a>
    </div>

    <div class="welcome-art" aria-hidden="true">
        <canvas class="welcome-canvas"></canvas>
        <div class="art-orbit orbit-one"></div>
        <div class="art-orbit orbit-two"></div>

        <div class="art-block block-purple">
            <span></span><span></span><span></span>
        </div>

        <div class="art-block block-cyan">
            <span></span><span></span><span></span>
        </div>

        <div class="art-block block-light">
            <span></span><span></span><span></span>
        </div>

        <div class="art-glow"></div>
    </div>
</section>

<?php if ($dashboardError !== ''): ?>
    <p class="form-notice form-notice-error" role="alert">
        <?= escape_html($dashboardError) ?>
    </p>
<?php endif; ?>

<?php if ($dashboardStats !== null && $dashboardStats['total_builds'] > 0): ?>
<section class="dashboard-section">
    <div class="section-heading">
        <div>
            <p class="section-kicker">AT A GLANCE</p>
            <h2>Your build overview</h2>
        </div>
    </div>

    <div class="stats-grid">
        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Total builds</span>
                <span class="stat-icon purple-icon" aria-hidden="true">
                    <i data-lucide="blocks">▤</i>
                </span>
            </div>

            <p class="stat-number"><?= $dashboardStats['total_builds'] ?></p>
            <p class="stat-description">All your creations in one place</p>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">In progress</span>
                <span class="stat-icon cyan-icon" aria-hidden="true">
                    <i data-lucide="clock-3">◷</i>
                </span>
            </div>

            <p class="stat-number"><?= $dashboardStats['in_progress_builds'] ?></p>
            <p class="stat-description">Builds currently underway</p>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Completed</span>
                <span class="stat-icon green-icon" aria-hidden="true">
                    <i data-lucide="circle-check">✓</i>
                </span>
            </div>

            <p class="stat-number"><?= $dashboardStats['completed_builds'] ?></p>
            <p class="stat-description">Creations finished so far</p>
        </article>

    </div>
</section>
<?php endif; ?>

<!-- My builds -->
<section class="dashboard-section builds-section" id="my-builds">
    <div class="section-heading">
        <div>
            <p class="section-kicker">YOUR WORKSHOP</p>
            <h2>My builds</h2>
            <p class="section-subtitle">
                Everything you're planning, building, and completing.
            </p>
        </div>

        <a href="/bricklab/projects.php" class="btn btn-secondary">
            + New build
        </a>
    </div>

    <?php if ($dashboardError === '' && $dashboardStats['total_builds'] === 0): ?>
    <div class="empty-state surface">
        <div class="empty-art" aria-hidden="true">
            <span class="empty-brick brick-one"></span>
            <span class="empty-brick brick-two"></span>
            <span class="empty-brick brick-three"></span>
        </div>

        <h3>Your workshop is ready</h3>

        <p>
            Every great creation begins with an idea.
            Create your first build and start tracking your progress here.
        </p>

        <a href="/bricklab/projects.php" class="btn btn-primary">
            Create your first build
        </a>

        <span class="empty-note">
            Your builds will appear here once you create them.
        </span>
    </div>
    <?php elseif ($dashboardStats !== null && $dashboardProjects !== []): ?>
    <div class="project-grid dashboard-project-grid">
        <?php foreach ($dashboardProjects as $project): ?>
            <?php
            $projectStatus = (string) ($project['status'] ?? '');
            $statusClass = array_key_exists($projectStatus, $dashboardStatusOptions)
                ? $projectStatus
                : 'unknown';
            $statusLabel = $dashboardStatusOptions[$projectStatus] ?? 'Status unavailable';
            ?>
            <article class="project-card surface">
                <span class="project-status status-<?= escape_html($statusClass) ?>">
                    <?= escape_html($statusLabel) ?>
                </span>
                <h3><?= escape_html((string) $project['title']) ?></h3>
                <p class="project-description">
                    <?= $project['description'] === null || $project['description'] === ''
                        ? 'No description added yet.'
                        : nl2br(escape_html((string) $project['description'])) ?>
                </p>
                <div class="project-card-actions">
                    <a class="btn btn-secondary" href="/bricklab/projects.php?edit=<?= (int) $project['id'] ?>">
                        Edit build
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php if ($dashboardStats !== null && $dashboardStats['total_builds'] === 0): ?>
<!-- Getting started -->
<section class="dashboard-section getting-started">
    <div class="section-heading">
        <div>
            <p class="section-kicker">GETTING STARTED</p>
            <h2>Your first build, one step at a time</h2>
        </div>
    </div>

    <div class="steps-grid">
        <article class="step-card step-card-primary surface">
            <span class="step-number">01</span>
            <span class="step-icon" aria-hidden="true">✦</span>
            <h3>Create your first build</h3>
            <p>Give your idea a name, set its status, and add a goal to get started.</p>
            <a href="/bricklab/projects.php" class="btn btn-primary">Create a build</a>
        </article>

        <article class="step-card surface">
            <span class="step-number">02</span>
            <span class="step-icon" aria-hidden="true">⌘</span>
            <h3>Organize your progress</h3>
            <p>Keep build details and status up to date from your My Builds page.</p>
            <a href="/bricklab/projects.php" class="step-link">Open My Builds</a>
            <p class="step-note">Task management is not available yet.</p>
        </article>

        <article class="step-card surface">
            <span class="step-number">03</span>
            <span class="step-icon" aria-hidden="true">↗</span>
            <h3>Explore the Showcase</h3>
            <p>When Showcase is available, publishing will always be your choice.</p>
            <p class="step-note">Your builds stay private by default. Showcase is not available yet.</p>
        </article>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
