
<?php
require_once __DIR__ . '/includes/auth.php';
require_authentication();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

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
            Start your first build
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

<!-- Dashboard heading -->
<section class="dashboard-section">
    <div class="section-heading">
        <div>
            <p class="section-kicker">AT A GLANCE</p>
            <h2>Your build overview</h2>
        </div>

        <span class="updated-label">
            Your journey starts here
        </span>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">

        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Total builds</span>
                <span class="stat-icon purple-icon" aria-hidden="true">
                    <i data-lucide="blocks">▤</i>
                </span>
            </div>

            <p class="stat-number">0</p>
            <p class="stat-description">All your creations in one place</p>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">In progress</span>
                <span class="stat-icon cyan-icon" aria-hidden="true">
                    <i data-lucide="clock-3">◷</i>
                </span>
            </div>

            <p class="stat-number">0</p>
            <p class="stat-description">Builds currently underway</p>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Completed</span>
                <span class="stat-icon green-icon" aria-hidden="true">
                    <i data-lucide="circle-check">✓</i>
                </span>
            </div>

            <p class="stat-number">0</p>
            <p class="stat-description">Creations finished so far</p>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Tasks completed</span>
                <span class="stat-icon orange-icon" aria-hidden="true">
                    <i data-lucide="list-checks">☑</i>
                </span>
            </div>

            <p class="stat-number">0</p>
            <p class="stat-description">Steps completed across builds</p>
        </article>

    </div>
</section>

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
</section>

<!-- Getting started -->
<section class="dashboard-section getting-started">
    <div class="section-heading">
        <div>
            <p class="section-kicker">THE BRICKLAB METHOD</p>
            <h2>From idea to showcase</h2>
        </div>
    </div>

    <div class="steps-grid">
        <article class="step-card surface">
            <span class="step-number">01</span>
            <span class="step-icon" aria-hidden="true">✦</span>
            <h3>Plan your build</h3>
            <p>Give your project a name, description, and clear goal.</p>
        </article>

        <article class="step-card surface">
            <span class="step-number">02</span>
            <span class="step-icon" aria-hidden="true">⌘</span>
            <h3>Track your progress</h3>
            <p>Break your project into manageable tasks and milestones.</p>
        </article>

        <article class="step-card surface">
            <span class="step-number">03</span>
            <span class="step-icon" aria-hidden="true">↗</span>
            <h3>Showcase your work</h3>
            <p>Add photos and share your finished creations with others.</p>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
