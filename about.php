<?php

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'About BrickLab';
$publicActivePage = 'about';

require __DIR__ . '/includes/public-header.php';
?>
    <section class="public-section public-container about-page" aria-labelledby="about-title">
        <header class="about-page-heading">
            <p class="public-eyebrow">ABOUT BRICKLAB</p>
            <h1 id="about-title">A place to build, track, and share.</h1>
            <p>
                BrickLab helps developers bring their projects together, keep track of the work,
                and share the builds they are proud of.
            </p>
        </header>

        <div class="about-feature-grid">
            <article class="about-feature surface">
                <span class="about-feature-number">01</span>
                <h2>Build your way</h2>
                <p>Organize your projects and tasks in a workspace made to keep ideas moving.</p>
                <a href="/bricklab/register.php">Create your workspace <span aria-hidden="true">→</span></a>
            </article>
            <article class="about-feature surface">
                <span class="about-feature-number">02</span>
                <h2>Discover what others make</h2>
                <p>Explore projects their creators have deliberately chosen to publish in the Showcase.</p>
                <a href="/bricklab/showcase.php">Explore the Showcase <span aria-hidden="true">→</span></a>
            </article>
            <article class="about-feature surface">
                <span class="about-feature-number">03</span>
                <h2>Meet the people behind them</h2>
                <p>Visit public developer profiles to learn about creators and browse their shared work.</p>
                <a href="/bricklab/developers.php">Meet the developers <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </section>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
