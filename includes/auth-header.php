<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape_html($pageTitle) ?> | BrickLab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&amp;display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="/bricklab/assets/css/style.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-intro" aria-labelledby="auth-intro-title">
            <a class="auth-brand" href="/bricklab/" aria-label="BrickLab dashboard">
                <span class="brand-icon" aria-hidden="true">B</span>
                <span>Brick<span class="brand-accent">Lab</span></span>
            </a>

            <div class="auth-intro-copy">
                <p class="auth-eyebrow">YOUR CREATIVE WORKSHOP</p>
                <h2 id="auth-intro-title"><?= escape_html($authHeadline) ?></h2>
                <p class="auth-intro-description">
                    Plan builds, track your progress, and showcase the projects you bring to life.
                </p>

                <ul class="auth-features" aria-label="What you can do with BrickLab">
                    <li>
                        <span class="auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none">
                                <path d="M5 4.5h10M5 8.5h10M5 12.5h6M4 2.5h12a1 1 0 0 1 1 1v13l-3-2-3 2-3-2-3 2v-13a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        Plan your builds
                    </li>
                    <li>
                        <span class="auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none">
                                <path d="M3 15.5V10m4 5.5V5m4 10.5V8m4 7.5V3.5M2 17h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        Track your progress
                    </li>
                    <li>
                        <span class="auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none">
                                <rect x="2.5" y="3.5" width="15" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/>
                                <circle cx="7" cy="8" r="1.4" stroke="currentColor" stroke-width="1.3"/>
                                <path d="m4 14 4-4 2.5 2.5 2-2 3.5 3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        Showcase your work
                    </li>
                </ul>
            </div>

            <div class="auth-brick-art" aria-hidden="true">
                <span class="auth-art-orbit auth-art-orbit-one"></span>
                <span class="auth-art-orbit auth-art-orbit-two"></span>
                <span class="auth-brick auth-brick-purple">
                    <i></i><i></i><i></i><i></i>
                </span>
                <span class="auth-brick auth-brick-cyan">
                    <i></i><i></i><i></i>
                </span>
                <span class="auth-brick auth-brick-lavender">
                    <i></i><i></i>
                </span>
                <span class="auth-brick auth-brick-float"></span>
            </div>
        </section>

        <section class="auth-card" aria-labelledby="auth-title">
