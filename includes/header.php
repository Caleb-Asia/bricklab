
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

<header class="site-header">
    <div class="container header-inner">

        <a href="/bricklab/" class="brand" aria-label="BrickLab home">
            <span class="brand-icon" aria-hidden="true">B</span>
            <span>Brick<span class="brand-accent">Lab</span></span>
        </a>

        <button
            class="menu-toggle"
            type="button"
            aria-label="Open navigation menu"
            aria-expanded="false"
            aria-controls="main-navigation"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="main-navigation" id="main-navigation" aria-label="Main navigation">
            <a href="/bricklab/"
               class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                Dashboard
            </a>

            <a href="/bricklab/projects.php"
               class="<?= $currentPage === 'projects' ? 'active' : '' ?>">
                My Builds
            </a>

            <a href="/bricklab/showcase.php"
               class="<?= $currentPage === 'showcase' ? 'active' : '' ?>">
                Showcase
            </a>
        </nav>

    </div>
</header>

<main class="container main-content">
