
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

    <link rel="stylesheet" href="/bricklab/assets/css/style.css">
</head>
<body>

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
                    <span class="nav-icon" aria-hidden="true">â–¦</span>
                    <span>Dashboard</span>
                </a>

                <a
                    href="/bricklab/projects.php"
                    class="sidebar-link <?= $currentPage === 'projects' ? 'active' : '' ?>"
                    <?= $currentPage === 'projects' ? 'aria-current="page"' : '' ?>
                >
                    <span class="nav-icon" aria-hidden="true">â–¤</span>
                    <span>My Builds</span>
                </a>

                <a
                    href="/bricklab/tasks.php"
                    class="sidebar-link <?= $currentPage === 'tasks' ? 'active' : '' ?>"
                    <?= $currentPage === 'tasks' ? 'aria-current="page"' : '' ?>
                >
                    <span class="nav-icon" aria-hidden="true">â˜‘</span>
                    <span>Tasks</span>
                </a>

                <a
                    href="/bricklab/showcase.php"
                    class="sidebar-link <?= $currentPage === 'showcase' ? 'active' : '' ?>"
                    <?= $currentPage === 'showcase' ? 'aria-current="page"' : '' ?>
                >
                    <span class="nav-icon" aria-hidden="true">â–§</span>
                    <span>Showcase</span>
                </a>

            </nav>
        </div>

        <div class="sidebar-bottom">
            <div class="sidebar-tip">
                <span class="tip-icon" aria-hidden="true">âœ¦</span>
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
