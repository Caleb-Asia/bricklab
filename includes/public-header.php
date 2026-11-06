<?php
$pageTitle = $pageTitle ?? 'Showcase';
$publicActivePage = $publicActivePage ?? 'showcase';
$isSignedIn = current_user_id() !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f0d18">
    <title><?= escape_html($pageTitle) ?> | BrickLab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&amp;display=swap"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="/bricklab/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>"
    >
</head>
<body class="public-site">
    <div class="cursor-glow" aria-hidden="true"></div>
    <div class="app-layout public-app-layout">
        <aside class="sidebar public-sidebar" id="sidebar">
            <div class="sidebar-brand-row">
                <a class="brand" href="/bricklab/showcase.php" aria-label="BrickLab Showcase">
                    <span class="brand-icon" aria-hidden="true">B</span>
                    <span class="brand-name">Brick<span class="brand-accent">Lab</span></span>
                </a>
            </div>
            <div class="sidebar-section">
                <p class="sidebar-label">DISCOVER</p>
                <nav class="sidebar-navigation" aria-label="Main navigation">
                    <a
                        class="sidebar-link <?= $publicActivePage === 'showcase' ? 'active' : '' ?>"
                        href="/bricklab/showcase.php"
                        <?= $publicActivePage === 'showcase' ? 'aria-current="page"' : '' ?>
                    >
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="3"
                                    stroke="currentColor" stroke-width="1.8"/>
                                <circle cx="8.5" cy="8.5" r="1.5"
                                    stroke="currentColor" stroke-width="1.5"/>
                                <path d="m4 17 5-5 3 3 3-4 5 6"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Showcase</span>
                    </a>
                    <a
                        class="sidebar-link <?= $publicActivePage === 'developers' ? 'active' : '' ?>"
                        href="/bricklab/developers.php"
                        <?= $publicActivePage === 'developers' ? 'aria-current="page"' : '' ?>
                    >
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="9" cy="8" r="3.5"
                                    stroke="currentColor" stroke-width="1.8"/>
                                <path d="M3 20v-1.5A4.5 4.5 0 0 1 7.5 14h3A4.5 4.5 0 0 1 15 18.5V20"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round"/>
                                <path d="M16 4.8a3.5 3.5 0 0 1 0 6.4M17 14.2a4.5 4.5 0 0 1 4 4.3V20"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span>Developers</span>
                    </a>
                    <a
                        class="sidebar-link <?= $publicActivePage === 'about' ? 'active' : '' ?>"
                        href="/bricklab/about.php"
                        <?= $publicActivePage === 'about' ? 'aria-current="page"' : '' ?>
                    >
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9"
                                    stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 11v5m0-8h.01"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span>About</span>
                    </a>
                </nav>
            </div>
            <div class="sidebar-bottom public-sidebar-bottom">
                <?php if ($isSignedIn): ?>
                    <a class="sidebar-link" href="/bricklab/">
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3v-10.5Z"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Your workspace</span>
                    </a>
                    <a class="sidebar-link" href="/bricklab/profile-settings.php">
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="8" r="3.5"
                                    stroke="currentColor" stroke-width="1.8"/>
                                <path d="M4.5 21a7.5 7.5 0 0 1 15 0"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span>Profile settings</span>
                    </a>
                <?php else: ?>
                    <a class="sidebar-link" href="/bricklab/login.php">
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M10 17l5-5-5-5m5 5H3"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"
                                    stroke="currentColor" stroke-width="1.8"
                                    stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span>Sign in</span>
                    </a>
                    <a class="btn btn-primary public-sidebar-join" href="/bricklab/register.php">Join BrickLab</a>
                <?php endif; ?>
            </div>
        </aside>

        <header class="mobile-header public-mobile-header">
            <a href="/bricklab/showcase.php" class="brand" aria-label="BrickLab Showcase">
                <span class="brand-icon" aria-hidden="true">B</span>
                <span class="brand-name">Brick<span class="brand-accent">Lab</span></span>
            </a>
            <button
                class="menu-toggle"
                id="menu-toggle"
                type="button"
                aria-label="Open navigation menu"
                aria-expanded="false"
                aria-controls="sidebar"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>
        </header>
        <button
            class="sidebar-backdrop"
            id="sidebar-backdrop"
            type="button"
            aria-label="Close navigation menu"
            tabindex="-1"
            hidden
        ></button>
        <div class="main-wrapper public-main-wrapper">
            <main class="public-main">
