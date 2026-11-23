<?php
$isSignedIn = current_user_id() !== null;
$navigationPage = $navigationPage ?? 'dashboard';
$brandHref = $isSignedIn ? '/bricklab/' : '/bricklab/grid.php';
$brandLabel = $isSignedIn ? 'BrickLab Workshop' : 'BrickLab — The Grid';

$navigationItems = $isSignedIn
    ? [
        'THE WORKSHOP' => [
            'dashboard' => ['Workshop', '/bricklab/', 'dashboard'],
            'projects' => ['My Builds', '/bricklab/projects.php', 'projects'],
            'tasks' => ['Task Board', '/bricklab/tasks.php', 'tasks'],
        ],
        'COMMUNITY' => [
            'grid' => ['The Grid', '/bricklab/grid.php', 'grid'],
            'build-wall' => ['Build Wall', '/bricklab/showcase.php', 'showcase'],
        ],
    ]
    : [
        'COMMUNITY' => [
            'grid' => ['The Grid', '/bricklab/grid.php', 'grid'],
            'build-wall' => ['Build Wall', '/bricklab/showcase.php', 'showcase'],
        ],
    ];

$navigationIcons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>',
    'projects' => '<rect x="5" y="4" width="14" height="16" rx="1.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 9h8M8 13h8M8 17h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'tasks' => '<rect x="4" y="4" width="16" height="16" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="m8 12 2.5 2.5L16 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    'grid' => '<path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    'build-wall' => '<rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor" stroke-width="1.8"/><circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor" stroke-width="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    'profile' => '<circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.8"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'sign-in' => '<path d="M10 17l5-5-5-5m5 5H3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand-row">
        <a href="<?= escape_html($brandHref) ?>" class="brand" aria-label="<?= escape_html($brandLabel) ?>">
            <span class="brand-icon" aria-hidden="true">B</span>
            <span class="brand-name">Brick<span class="brand-accent">Lab</span></span>
        </a>
    </div>

    <?php foreach ($navigationItems as $section => $items): ?>
        <div class="sidebar-section">
            <p class="sidebar-label"><?= escape_html($section) ?></p>
            <nav class="sidebar-navigation" aria-label="<?= escape_html(ucfirst(strtolower($section))) ?> navigation">
                <?php foreach ($items as $key => [$label, $href, $pageKey]): ?>
                    <a
                        href="<?= escape_html($href) ?>"
                        class="sidebar-link <?= $navigationPage === $pageKey ? 'active' : '' ?>"
                        <?= $navigationPage === $pageKey ? 'aria-current="page"' : '' ?>
                    >
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><?= $navigationIcons[$key] ?></svg>
                        </span>
                        <span><?= escape_html($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    <?php endforeach; ?>

    <div class="sidebar-bottom">
        <?php if ($isSignedIn): ?>
            <a
                href="/bricklab/profile-settings.php"
                class="sidebar-link <?= $navigationPage === 'profile' ? 'active' : '' ?>"
                <?= $navigationPage === 'profile' ? 'aria-current="page"' : '' ?>
            >
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><?= $navigationIcons['profile'] ?></svg>
                </span>
                <span>Profile settings</span>
            </a>
        <?php else: ?>
            <a class="sidebar-link" href="/bricklab/login.php">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><?= $navigationIcons['sign-in'] ?></svg>
                </span>
                <span>Sign in</span>
            </a>
            <a class="btn btn-primary public-sidebar-join" href="/bricklab/register.php">Join BrickLab</a>
        <?php endif; ?>

        <?php if ($isSignedIn): ?>
            <div class="sidebar-tip">
                <span class="tip-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="m12 2 2.8 7.2L22 12l-7.2 2.8L12 22l-2.8-7.2L2 12l7.2-2.8L12 2Z"
                            stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    </svg>
                </span>
                <p>From first brick to final build.</p>
            </div>
        <?php endif; ?>
    </div>
</aside>
