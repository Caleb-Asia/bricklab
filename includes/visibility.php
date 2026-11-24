<?php

/**
 * Determine whether a viewer may access a project under the three-state model.
 *
 * Public projects are available to everyone. Private projects are available
 * only to their owner. Unlisted projects are intentionally denied here to
 * non-owners; they require separate direct-token authorization once token
 * routing exists. A NULL visibility value uses the legacy is_public fallback
 * so rows remain compatible before visibility backfill is complete.
 *
 * @param array $project Project row containing user_id and visibility, with
 *                       legacy is_public available when visibility is NULL.
 */
function can_view_project(array $project, ?int $viewerUserId): bool
{
    if ($viewerUserId !== null && isset($project['user_id'])
        && (int) $project['user_id'] === $viewerUserId) {
        return true;
    }

    if (($project['visibility'] ?? null) === 'public') {
        return true;
    }

    return ($project['visibility'] ?? null) === null
        && ($project['is_public'] ?? null) == 1;
}

/**
 * Determine whether a viewer may access a Build Log and its parent Build.
 *
 * Build Log access follows the parent project's visibility. Unlisted token
 * authorization is deliberately not handled here yet; $providedToken is
 * reserved for that later extension.
 *
 * @param array       $buildLog      Build Log row (reserved for future rules).
 * @param array       $parentProject Parent project row.
 * @param int|null    $viewerUserId  Current viewer's user ID, if signed in.
 * @param string|null $providedToken Direct-link token, currently ignored.
 */
function can_view_build_log(
    array $buildLog,
    array $parentProject,
    ?int $viewerUserId,
    ?string $providedToken
): bool {
    return can_view_project($parentProject, $viewerUserId);
}
