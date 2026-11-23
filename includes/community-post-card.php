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
$postType = (string) $post['post_type'];

if ($hasLinkedBuild && $postType === 'update') {
    $postTypeLabel = 'Project Build Log';
} else {
    $postTypeLabel = community_post_type_label($postType);
}

$postTypeIcon = community_post_type_icon($postType);
$timeAgo = community_time_ago((string) $post['created_at']);
$likeCount = (int) ($post['like_count'] ?? 0);
$viewerLiked = (int) ($post['viewer_liked'] ?? 0) > 0;
$isOwnPost = $userId !== null && (int) $post['user_id'] === $userId;
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
                <span>@<?= escape_html((string) $post['username']) ?></span>
            </a>
            <div class="community-post-meta-line">
                <span class="community-post-type" data-type="<?= escape_html($postType) ?>">
                    <?= $postTypeIcon ?> <?= escape_html($postTypeLabel) ?>
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
    <?php if ($hasLinkedBuild): ?>
        <div class="community-post-project">
            <span class="community-post-project-icon" aria-hidden="true">📦</span>
            <span>Linked Build: <strong><?= escape_html((string) $post['project_title']) ?></strong></span>
        </div>
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
