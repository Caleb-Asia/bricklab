<?php
/**
 * BrickLab Build Log card partial.
 * Expects $post (array row) and $userId (int|null, current viewer) in scope.
 */
$postAuthorName = trim((string) ($post['display_name'] ?? ''));
if ($postAuthorName === '') {
    $postAuthorName = (string) $post['username'];
}
$postAvatar = showcase_safe_image_source(
    isset($post['avatar_url']) ? (string) $post['avatar_url'] : null
);
$hasLinkedBuild = trim((string) ($post['project_title'] ?? '')) !== '';
$projectId = positive_integer($post['project_id'] ?? null);
$postType = (string) $post['post_type'];

if ($hasLinkedBuild && $postType === 'update') {
    $postTypeLabel = 'Build Log';
} else {
    $postTypeLabel = community_post_type_label($postType);
}

$timeAgo = community_time_ago((string) $post['created_at']);
$likeCount = (int) ($post['like_count'] ?? 0);
$viewerLiked = (int) ($post['viewer_liked'] ?? 0) > 0;
$isOwnPost = $userId !== null && (int) $post['user_id'] === $userId;
$brickId = community_format_brick_id((string) $post['username']);
$projectTechnologies = showcase_parse_tags((string) ($post['project_technologies'] ?? ''));
$projectCover = showcase_safe_image_source(isset($post['project_cover']) ? (string) $post['project_cover'] : null);
?>
<article class="community-post surface" data-post-id="<?= (int) $post['id'] ?>">
    <div class="community-post-header">
        <a class="community-post-avatar" href="/bricklab/u/<?= rawurlencode((string) $post['username']) ?>">
            <?php if ($postAvatar !== null): ?>
                <img src="<?= escape_html($postAvatar) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span><?= escape_html(showcase_avatar_initials($postAuthorName)) ?></span>
            <?php endif; ?>
        </a>
        <div class="community-post-meta">
            <a class="community-post-author" href="/bricklab/u/<?= rawurlencode((string) $post['username']) ?>">
                <strong><?= escape_html($postAuthorName) ?></strong>
                <span class="community-post-brick-id"><?= escape_html($brickId) ?></span>
            </a>
            <div class="community-post-meta-line">
                <span class="community-post-type" data-type="<?= escape_html($postType) ?>">
                    <?= escape_html($postTypeLabel) ?>
                </span>
                <span class="community-post-time"><?= escape_html($timeAgo) ?></span>
            </div>
        </div>
        <?php if ($isOwnPost): ?>
            <details class="community-post-edit">
                <summary title="Edit Build Log" aria-label="Edit Build Log">Edit</summary>
                <form method="post" action="/bricklab/grid.php" class="community-post-edit-form">
                    <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                    <input type="hidden" name="action" value="edit_post">
                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                    <label>
                        <span>Build Log type</span>
                        <select name="post_type">
                            <?php foreach (community_post_type_options() as $value => $label): ?>
                                <option value="<?= escape_html($value) ?>" <?= $postType === $value ? 'selected' : '' ?>><?= escape_html($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <textarea name="body" maxlength="2000" required><?= escape_html((string) $post['body']) ?></textarea>
                    <input type="hidden" name="project_id" value="<?= (int) ($post['project_id'] ?? 0) ?>">
                    <div class="community-post-edit-actions">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </details>
            <form class="community-post-delete" method="post" action="/bricklab/grid.php">
                <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_post">
                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                <button type="submit" class="community-post-delete-btn" title="Delete Build Log" aria-label="Delete Build Log">×</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="community-post-body">
        <p><?= nl2br(escape_html((string) $post['body'])) ?></p>
    </div>
    <?php if ($hasLinkedBuild && $projectId !== null): ?>
        <a class="community-build-preview" href="/bricklab/build.php?id=<?= $projectId ?>">
            <div class="community-build-preview-cover" aria-hidden="true">
                <?php if ($projectCover !== null): ?>
                    <img src="<?= escape_html($projectCover) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <img src="/bricklab/assets/images/bricklab-project-placeholder.webp" alt="" loading="lazy">
                <?php endif; ?>
            </div>
            <div class="community-build-preview-copy">
                <p class="community-build-preview-label">Build Log</p>
                <h3><?= escape_html((string) $post['project_title']) ?></h3>
                <?php if (trim((string) ($post['project_description'] ?? '')) !== ''): ?>
                    <p class="community-build-preview-description"><?= escape_html((string) $post['project_description']) ?></p>
                <?php endif; ?>
                <?php if ($projectTechnologies !== []): ?>
                    <ul class="community-build-preview-tags" aria-label="Technologies">
                        <?php foreach (array_slice($projectTechnologies, 0, 4) as $technology): ?>
                            <li><?= escape_html($technology) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <span class="community-build-preview-link">View Build <span aria-hidden="true">↗</span></span>
            </div>
        </a>
    <?php endif; ?>
    <div class="community-post-actions">
        <?php if ($userId !== null): ?>
            <form method="post" action="/bricklab/grid.php" class="community-like-form" data-like-form>
                <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                <input type="hidden" name="action" value="like">
                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                <button
                    type="submit"
                    class="community-like-btn<?= $viewerLiked ? ' is-liked' : '' ?>"
                    data-liked="<?= $viewerLiked ? '1' : '0' ?>"
                    aria-label="<?= $viewerLiked ? 'Unlike' : 'Like' ?>"
                >
                    <span class="community-like-icon" aria-hidden="true"><?= $viewerLiked ? '♥' : '♡' ?></span>
                    <span class="community-like-count" data-like-count><?= $likeCount ?></span>
                </button>
            </form>
        <?php else: ?>
            <span class="community-like-static">
                <span aria-hidden="true">♡</span>
                <span><?= $likeCount ?></span>
            </span>
        <?php endif; ?>
    </div>
</article>
