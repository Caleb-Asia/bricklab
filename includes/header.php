<?php

$isPublicShell = $isPublicShell ?? false;
$navigationPage = $navigationPage ?? ($currentPage ?? 'dashboard');
$pageTitle = $pageTitle ?? 'Dashboard';
$isSignedIn = current_user_id() !== null;
$brandHref = $isSignedIn ? '/bricklab/' : '/bricklab/showcase.php';
$brandLabel = $isSignedIn ? 'BrickLab dashboard' : 'BrickLab Showcase';
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
<body<?= $isPublicShell ? ' class="public-site"' : '' ?>>
<div class="cursor-glow" aria-hidden="true"></div>
<div class="app-layout<?= $isPublicShell ? ' public-app-layout' : '' ?>">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <header class="mobile-header<?= $isPublicShell ? ' public-mobile-header' : '' ?>">
        <a href="<?= escape_html($brandHref) ?>" class="brand" aria-label="<?= escape_html($brandLabel) ?>">
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

    <div class="main-wrapper<?= $isPublicShell ? ' public-main-wrapper' : '' ?>">
        <main class="<?= $isPublicShell ? 'public-main' : 'main-content container' ?>">
