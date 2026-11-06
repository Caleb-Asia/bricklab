<?php
$projectOwnerName = trim((string) ($project['display_name'] ?? ''));
if ($projectOwnerName === '') {
    $projectOwnerName = (string) $project['username'];
}
$projectAvatar = showcase_safe_image_source(
    isset($project['avatar_url']) ? (string) $project['avatar_url'] : null
);
$projectCover = showcase_safe_image_source(
    isset($project['cover_image']) ? (string) $project['cover_image'] : null
);
$projectTechnologies = showcase_parse_tags((string) ($project['technologies'] ?? ''));
?>
<article class="showcase-project-card surface">
    <?php if ($projectCover !== null): ?>
        <img
            class="showcase-project-cover"
            src="<?= escape_html($projectCover) ?>"
            alt=""
            loading="lazy"
        >
    <?php else: ?>
        <div class="showcase-project-cover showcase-project-cover-placeholder" aria-hidden="true">
            <span class="showcase-cover-brick"></span>
            <span class="showcase-cover-brick"></span>
            <span class="showcase-cover-brick"></span>
        </div>
    <?php endif; ?>
    <div class="showcase-project-content">
        <div class="showcase-project-heading">
            <span class="showcase-category">
                <?= escape_html(showcase_category_options()[$project['category']] ?? 'Other') ?>
            </span>
            <h3><?= escape_html((string) $project['title']) ?></h3>
        </div>
        <p class="showcase-project-description">
            <?= escape_html(trim((string) ($project['description'] ?? '')) !== ''
                ? (string) $project['description']
                : 'A project shared by its developer.') ?>
        </p>
        <?php if ($projectTechnologies !== []): ?>
            <ul class="showcase-tag-list" aria-label="Technologies">
                <?php foreach (array_slice($projectTechnologies, 0, 6) as $technology): ?>
                    <li><?= escape_html($technology) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="showcase-project-owner">
            <a class="showcase-avatar-link" href="/bricklab/u/<?= rawurlencode((string) $project['username']) ?>">
                <?php if ($projectAvatar !== null): ?>
                    <img src="<?= escape_html($projectAvatar) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span aria-hidden="true"><?= escape_html(showcase_avatar_initials($projectOwnerName)) ?></span>
                <?php endif; ?>
            </a>
            <a href="/bricklab/u/<?= rawurlencode((string) $project['username']) ?>">
                <?= escape_html($projectOwnerName) ?>
                <span>@<?= escape_html((string) $project['username']) ?></span>
            </a>
        </div>
        <div class="showcase-project-links">
            <?php if (
                (string) ($project['github_url'] ?? '') !== ''
                && showcase_url_is_valid((string) $project['github_url'])
            ): ?>
                <a href="<?= escape_html((string) $project['github_url']) ?>" target="_blank" rel="noopener noreferrer">
                    GitHub <span aria-hidden="true">↗</span>
                </a>
            <?php endif; ?>
            <?php if (
                (string) ($project['live_demo_url'] ?? '') !== ''
                && showcase_url_is_valid((string) $project['live_demo_url'])
            ): ?>
                <a href="<?= escape_html((string) $project['live_demo_url']) ?>" target="_blank" rel="noopener noreferrer">
                    Live demo <span aria-hidden="true">↗</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</article>
