
<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | BrickLab</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&amp;display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="/bricklab/assets/css/style.css">
</head>
<body>
    
<div class="cursor-glow" aria-hidden="true"></div>

<div class="app-layout">

    <!-- Sidebar navigation -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-brand-row">
            <a href="/bricklab/" class="brand" aria-label="BrickLab dashboard">
                <span class="brand-icon" aria-hidden="true">B</span>
                <span class="brand-name">
                    Brick<span class="brand-accent">Lab</span>
                </span>
            </a>

            <button
                class="sidebar-collapse-toggle"
                id="sidebar-collapse-toggle"
                type="button"
                aria-label="Collapse sidebar"
                aria-expanded="true"
                title="Collapse sidebar"
            >
                <span aria-hidden="true">&laquo;</span>
            </button>
        </div>

        <div class="sidebar-section">
            <p class="sidebar-label">WORKSPACE</p>

            <nav class="sidebar-navigation" aria-label="Main navigation">

                <a
                    href="/bricklab/"
                    class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"
                    <?= $currentPage === 'dashboard' ? 'aria-current="page"' : '' ?>
                >
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="3" width="7" height="7" rx="1.5"
                                stroke="currentColor" stroke-width="1.8"/>
                            <rect x="14" y="3" width="7" height="7" rx="1.5"
                                stroke="currentColor" stroke-width="1.8"/>
                            <rect x="3" y="14" width="7" height="7" rx="1.5"
                                stroke="currentColor" stroke-width="1.8"/>
                            <rect x="14" y="14" width="7" height="7" rx="1.5"
                                stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </span>
                    <span>Dashboard</span>
                </a>

                <a
                    href="/bricklab/projects.php"
                    class="sidebar-link <?= $currentPage === 'projects' ? 'active' : '' ?>"
                    <?= $currentPage === 'projects' ? 'aria-current="page"' : '' ?>
                >
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="5" y="4" width="14" height="16" rx="1.5"
                                stroke="currentColor" stroke-width="1.8"/>
                            <path d="M8 9h8M8 13h8M8 17h5"
                                stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span>My Builds</span>
                </a>

                <a
                    href="/bricklab/tasks.php"
                    class="sidebar-link <?= $currentPage === 'tasks' ? 'active' : '' ?>"
                    <?= $currentPage === 'tasks' ? 'aria-current="page"' : '' ?>
                >
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="4" y="4" width="16" height="16" rx="3"
                                stroke="currentColor" stroke-width="1.8"/>
                            <path d="m8 12 2.5 2.5L16 9"
                                stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Tasks</span>
                </a>

                <a
                    href="/bricklab/showcase.php"
                    class="sidebar-link <?= $currentPage === 'showcase' ? 'active' : '' ?>"
                    <?= $currentPage === 'showcase' ? 'aria-current="page"' : '' ?>
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

            </nav>
        </div>

        <div class="sidebar-bottom">
            <div class="sidebar-tip">
                <span class="tip-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path
                            d="m12 2 2.8 7.2L22 12l-7.2 2.8L12 22l-2.8-7.2L2 12l7.2-2.8L12 2Z"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linejoin="round"
                        />
                    </svg>
                </span>
                <p>Every great build starts with one brick.</p>
            </div>
        </div>

    </aside>

    <!-- Mobile header -->
    <header class="mobile-header">
        <a href="/bricklab/" class="brand" aria-label="BrickLab dashboard">
            <span class="brand-icon" aria-hidden="true">B</span>
            <span class="brand-name">
                Brick<span class="brand-accent">Lab</span>
            </span>
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

    <!-- Mobile sidebar backdrop -->
    <button
        class="sidebar-backdrop"
        id="sidebar-backdrop"
        type="button"
        aria-label="Close navigation menu"
        tabindex="-1"
        hidden
    ></button>

    <!-- Main page content begins -->
    <div class="main-wrapper">
        <main class="main-content container">
