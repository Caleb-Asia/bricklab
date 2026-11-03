
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
$dashboardTaskStats = null;
$dashboardProjects = [];
$dashboardHistory = [];
$dashboardChartData = [
    'buildActivity' => [
        'weekly' => ['labels' => [], 'values' => []],
        'monthly' => ['labels' => [], 'values' => []],
    ],
    'buildStatus' => ['labels' => ['Planning', 'In Progress', 'Completed'], 'values' => []],
    'taskStatus' => ['labels' => ['To-do', 'In Progress', 'Completed'], 'values' => []],
    'taskCompletionActivity' => [
        'weekly' => ['labels' => [], 'values' => []],
        'monthly' => ['labels' => [], 'values' => []],
    ],
];
$dashboardError = '';

try {
    $statement = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_builds,
            COUNT(CASE WHEN status = \'in_progress\' THEN 1 END) AS in_progress_builds,
            COUNT(CASE WHEN status = \'completed\' THEN 1 END) AS completed_builds,
            COUNT(CASE WHEN status = \'planning\' THEN 1 END) AS planning_builds
        FROM projects
        WHERE user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);
    $buildStats = $statement->fetch();
    $dashboardStats = [
        'total_builds' => (int) $buildStats['total_builds'],
        'in_progress_builds' => (int) $buildStats['in_progress_builds'],
        'completed_builds' => (int) $buildStats['completed_builds'],
        'planning_builds' => (int) $buildStats['planning_builds'],
    ];
    $dashboardChartData['buildStatus']['values'] = [
        $dashboardStats['planning_builds'],
        $dashboardStats['in_progress_builds'],
        $dashboardStats['completed_builds'],
    ];

    $statement = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_tasks,
            COUNT(CASE WHEN task.status = \'todo\' THEN 1 END) AS todo_tasks,
            COUNT(CASE WHEN task.status = \'in_progress\' THEN 1 END) AS in_progress_tasks,
            COUNT(CASE WHEN task.status = \'completed\' THEN 1 END) AS completed_tasks
        FROM tasks AS task
        INNER JOIN projects AS project ON project.id = task.project_id
        WHERE project.user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);
    $taskStats = $statement->fetch();
    $dashboardTaskStats = [
        'total_tasks' => (int) $taskStats['total_tasks'],
        'todo_tasks' => (int) $taskStats['todo_tasks'],
        'in_progress_tasks' => (int) $taskStats['in_progress_tasks'],
        'completed_tasks' => (int) $taskStats['completed_tasks'],
    ];
    $dashboardChartData['taskStatus']['values'] = [
        $dashboardTaskStats['todo_tasks'],
        $dashboardTaskStats['in_progress_tasks'],
        $dashboardTaskStats['completed_tasks'],
    ];

    $today = new DateTimeImmutable('today');
    $currentWeek = $today->modify('monday this week');
    $weeklyStart = $currentWeek->modify('-11 weeks');
    $currentMonth = $today->modify('first day of this month');
    $monthlyStart = $currentMonth->modify('-11 months');
    $activityEnd = $today->modify('+1 day');
    $weeklyBuckets = [];
    $monthlyBuckets = [];

    for ($week = $weeklyStart; $week <= $currentWeek; $week = $week->modify('+1 week')) {
        $weeklyBuckets[$week->format('oW')] = $week->format('M j');
    }

    for ($month = $monthlyStart; $month <= $currentMonth; $month = $month->modify('+1 month')) {
        $monthlyBuckets[$month->format('Y-m')] = $month->format('M Y');
    }

    foreach (['buildActivity', 'taskCompletionActivity'] as $activityKey) {
        $dashboardChartData[$activityKey]['weekly'] = [
            'labels' => array_values($weeklyBuckets),
            'values' => array_fill(0, count($weeklyBuckets), 0),
        ];
        $dashboardChartData[$activityKey]['monthly'] = [
            'labels' => array_values($monthlyBuckets),
            'values' => array_fill(0, count($monthlyBuckets), 0),
        ];
    }

    if ($dashboardStats['total_builds'] > 0 || $dashboardTaskStats['total_tasks'] > 0) {
        $statement = $pdo->prepare(
            'SELECT
                \'build_weekly\' AS series,
                DATE_FORMAT(project.created_at, \'%x%v\') AS period_key,
                COUNT(*) AS period_count
            FROM projects AS project
            WHERE project.user_id = :build_weekly_user
                AND project.created_at >= :build_weekly_start
                AND project.created_at < :build_weekly_end
            GROUP BY DATE_FORMAT(project.created_at, \'%x%v\')

            UNION ALL

            SELECT
                \'build_monthly\' AS series,
                DATE_FORMAT(project.created_at, \'%Y-%m\') AS period_key,
                COUNT(*) AS period_count
            FROM projects AS project
            WHERE project.user_id = :build_monthly_user
                AND project.created_at >= :build_monthly_start
                AND project.created_at < :build_monthly_end
            GROUP BY DATE_FORMAT(project.created_at, \'%Y-%m\')

            UNION ALL

            SELECT
                \'task_weekly\' AS series,
                DATE_FORMAT(task.completed_at, \'%x%v\') AS period_key,
                COUNT(*) AS period_count
            FROM tasks AS task
            INNER JOIN projects AS project ON project.id = task.project_id
            WHERE project.user_id = :task_weekly_user
                AND task.status = \'completed\'
                AND task.completed_at IS NOT NULL
                AND task.completed_at >= :task_weekly_start
                AND task.completed_at < :task_weekly_end
            GROUP BY DATE_FORMAT(task.completed_at, \'%x%v\')

            UNION ALL

            SELECT
                \'task_monthly\' AS series,
                DATE_FORMAT(task.completed_at, \'%Y-%m\') AS period_key,
                COUNT(*) AS period_count
            FROM tasks AS task
            INNER JOIN projects AS project ON project.id = task.project_id
            WHERE project.user_id = :task_monthly_user
                AND task.status = \'completed\'
                AND task.completed_at IS NOT NULL
                AND task.completed_at >= :task_monthly_start
                AND task.completed_at < :task_monthly_end
            GROUP BY DATE_FORMAT(task.completed_at, \'%Y-%m\')'
        );
        $statement->execute([
            'build_weekly_user' => $userId,
            'build_weekly_start' => $weeklyStart->format('Y-m-d H:i:s'),
            'build_weekly_end' => $activityEnd->format('Y-m-d H:i:s'),
            'build_monthly_user' => $userId,
            'build_monthly_start' => $monthlyStart->format('Y-m-d H:i:s'),
            'build_monthly_end' => $activityEnd->format('Y-m-d H:i:s'),
            'task_weekly_user' => $userId,
            'task_weekly_start' => $weeklyStart->format('Y-m-d H:i:s'),
            'task_weekly_end' => $activityEnd->format('Y-m-d H:i:s'),
            'task_monthly_user' => $userId,
            'task_monthly_start' => $monthlyStart->format('Y-m-d H:i:s'),
            'task_monthly_end' => $activityEnd->format('Y-m-d H:i:s'),
        ]);

        $seriesConfig = [
            'build_weekly' => ['buildActivity', 'weekly'],
            'build_monthly' => ['buildActivity', 'monthly'],
            'task_weekly' => ['taskCompletionActivity', 'weekly'],
            'task_monthly' => ['taskCompletionActivity', 'monthly'],
        ];
        $seriesOffsets = [
            'build_weekly' => array_flip(array_keys($weeklyBuckets)),
            'task_weekly' => array_flip(array_keys($weeklyBuckets)),
            'build_monthly' => array_flip(array_keys($monthlyBuckets)),
            'task_monthly' => array_flip(array_keys($monthlyBuckets)),
        ];

        foreach ($statement->fetchAll() as $activityRow) {
            $series = (string) $activityRow['series'];
            [$activityKey, $period] = $seriesConfig[$series];
            $periodKey = (string) $activityRow['period_key'];

            if (isset($seriesOffsets[$series][$periodKey])) {
                $dashboardChartData[$activityKey][$period]['values']
                    [$seriesOffsets[$series][$periodKey]] = (int) $activityRow['period_count'];
            }
        }
    }

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

        $statement = $pdo->prepare(
            'SELECT id, title, status, updated_at
            FROM projects
            WHERE user_id = :user_id
            ORDER BY updated_at DESC, id DESC
            LIMIT 4'
        );
        $statement->execute(['user_id' => $userId]);
        $dashboardHistory = $statement->fetchAll();
    }
} catch (PDOException $exception) {
    error_log('BrickLab dashboard data could not be loaded: ' . $exception->getMessage());
    http_response_code(500);
    $dashboardStats = null;
    $dashboardTaskStats = null;
    $dashboardChartData = null;
    $dashboardHistory = [];
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

<?php if ($dashboardStats !== null && $dashboardTaskStats !== null): ?>
<section
    class="dashboard-section dashboard-insights"
    data-dashboard-charts
    data-chart-data="<?= escape_html(json_encode(
        $dashboardChartData,
        JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    )) ?>"
    aria-labelledby="dashboard-insights-title"
>
    <div class="section-heading">
        <div>
            <p class="section-kicker">AT A GLANCE</p>
            <h2 id="dashboard-insights-title">Your progress</h2>
        </div>
    </div>

    <div class="dashboard-chart-layout" data-dashboard-layout>
        <div
            class="dashboard-chart-display surface"
            id="dashboard-chart-panel"
            aria-hidden="true"
            aria-live="polite"
            aria-atomic="true"
        >
            <div class="dashboard-chart-display-heading">
                <div>
                    <p class="section-kicker" data-chart-category-label>SELECT A STATISTIC</p>
                    <h3 data-chart-title>Choose a statistic to explore</h3>
                </div>
                <label class="dashboard-chart-period" data-chart-period-controls hidden>
                    <span>Group by</span>
                    <select data-chart-period aria-label="Group activity by">
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </label>
            </div>

            <div
                class="dashboard-chart-switcher"
                data-chart-switcher
                role="group"
                aria-label="Chart type"
                hidden
            >
                <button type="button" data-chart-view="build-activity" aria-pressed="false">
                    Build Activity
                </button>
                <button type="button" data-chart-view="build-status" aria-pressed="false">
                    Build Status
                </button>
                <button type="button" data-chart-view="task-status" aria-pressed="false">
                    Task Status
                </button>
                <button type="button" data-chart-view="task-completion-activity" aria-pressed="false">
                    Completion Activity
                </button>
            </div>

            <div class="dashboard-chart-visual">
                <canvas
                    class="dashboard-chart-canvas"
                    data-dashboard-canvas
                    role="img"
                    aria-label="Select a statistic to display a chart"
                    hidden
                ></canvas>
                <p class="dashboard-chart-empty" data-chart-empty role="status">
                    Select a build or task statistic to explore your activity.
                </p>
            </div>
        </div>

        <aside class="dashboard-statistics" aria-label="Build and task statistics">
            <button
                class="stat-card dashboard-stat-button"
                type="button"
                data-dashboard-category="builds"
                aria-pressed="false"
                aria-controls="dashboard-chart-panel"
            >
                <span class="stat-top">
                    <span class="dashboard-stat-title">Builds</span>
                    <span class="stat-icon purple-icon" aria-hidden="true">
                        <i data-lucide="blocks">•</i>
                    </span>
                </span>
                <span class="dashboard-stat-list">
                    <span class="dashboard-stat-item">
                        <span>Total builds</span>
                        <strong><?= $dashboardStats['total_builds'] ?></strong>
                    </span>
                    <span class="dashboard-stat-item">
                        <span>In progress</span>
                        <strong><?= $dashboardStats['in_progress_builds'] ?></strong>
                    </span>
                    <span class="dashboard-stat-item">
                        <span>Completed</span>
                        <strong><?= $dashboardStats['completed_builds'] ?></strong>
                    </span>
                </span>
                <span class="dashboard-stat-hint">Click to view build charts</span>
            </button>

            <button
                class="stat-card dashboard-stat-button"
                type="button"
                data-dashboard-category="tasks"
                aria-pressed="false"
                aria-controls="dashboard-chart-panel"
            >
                <span class="stat-top">
                    <span class="dashboard-stat-title">Tasks</span>
                    <span class="stat-icon cyan-icon" aria-hidden="true">
                        <i data-lucide="list-checks">•</i>
                    </span>
                </span>
                <span class="dashboard-stat-list">
                    <span class="dashboard-stat-item">
                        <span>Total tasks</span>
                        <strong><?= $dashboardTaskStats['total_tasks'] ?></strong>
                    </span>
                    <span class="dashboard-stat-item">
                        <span>In progress</span>
                        <strong><?= $dashboardTaskStats['in_progress_tasks'] ?></strong>
                    </span>
                    <span class="dashboard-stat-item">
                        <span>Completed</span>
                        <strong><?= $dashboardTaskStats['completed_tasks'] ?></strong>
                    </span>
                </span>
                <span class="dashboard-stat-hint">Click to view task charts</span>
            </button>
        </aside>
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

<?php if ($dashboardHistory !== []): ?>
<section class="dashboard-section development-history" aria-labelledby="development-history-title">
    <div class="section-heading">
        <div>
            <p class="section-kicker">RECENT ACTIVITY</p>
            <h2 id="development-history-title">Development history</h2>
            <p class="section-subtitle">Your four most recently updated builds.</p>
        </div>
    </div>

    <div class="development-history-list surface">
        <?php foreach ($dashboardHistory as $historyItem): ?>
            <?php
            $historyStatus = (string) ($historyItem['status'] ?? '');
            $historyStatusClass = array_key_exists($historyStatus, $dashboardStatusOptions)
                ? $historyStatus
                : 'unknown';
            $historyStatusLabel = $dashboardStatusOptions[$historyStatus]
                ?? 'Status unavailable';
            $updatedTimestamp = strtotime((string) $historyItem['updated_at']);
            ?>
            <article class="development-history-item">
                <span class="development-history-marker" aria-hidden="true"></span>
                <div class="development-history-details">
                    <h3><?= escape_html((string) $historyItem['title']) ?></h3>
                    <span class="project-status status-<?= escape_html($historyStatusClass) ?>">
                        <?= escape_html($historyStatusLabel) ?>
                    </span>
                </div>
                <time
                    class="development-history-date"
                    datetime="<?= escape_html((string) $historyItem['updated_at']) ?>"
                >
                    <?= $updatedTimestamp === false
                        ? 'Update date unavailable'
                        : 'Updated ' . escape_html(date('M j, Y', $updatedTimestamp)) ?>
                </time>
                <a
                    class="step-link development-history-link"
                    href="/bricklab/projects.php?edit=<?= (int) $historyItem['id'] ?>"
                    aria-label="Edit <?= escape_html((string) $historyItem['title']) ?>"
                >
                    View build
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

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
            <p>Keep build details up to date and track the next steps for every build.</p>
            <a href="/bricklab/projects.php" class="step-link">Open My Builds</a>
            <a href="/bricklab/tasks.php" class="step-link">Manage tasks</a>
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
