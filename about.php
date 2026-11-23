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
                BrickLab helps Builders organise their Builds, keep track of the work,
                and share what they are proud of.
            </p>
        </header>

        <div class="about-feature-grid">
            <article class="about-feature surface">
                <span class="about-feature-number">01</span>
                <h2>Build your way</h2>
                <p>Organise your Builds and tasks in The Workshop so ideas keep moving.</p>
                <a href="/bricklab/register.php">Enter The Workshop <span aria-hidden="true">→</span></a>
            </article>
            <article class="about-feature surface">
                <span class="about-feature-number">02</span>
                <h2>Discover what others make</h2>
                <p>Explore Builds that their Builders have deliberately chosen to showcase on the Build Wall.</p>
                <a href="/bricklab/showcase.php">Explore the Build Wall <span aria-hidden="true">→</span></a>
            </article>
            <article class="about-feature surface">
                <span class="about-feature-number">03</span>
                <h2>Meet the people behind them</h2>
                <p>Visit Builder Profiles to learn about creators and browse their shared work.</p>
                <a href="/bricklab/grid.php">Enter The Grid <span aria-hidden="true">→</span></a>
            </article>
        </div>
    </section>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
