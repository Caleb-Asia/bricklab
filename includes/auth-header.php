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
        <a class="auth-brand" href="/bricklab/" aria-label="BrickLab dashboard">
            <span class="brand-icon" aria-hidden="true">B</span>
            <span>Brick<span class="brand-accent">Lab</span></span>
        </a>
        <section class="auth-card" aria-labelledby="auth-title">
