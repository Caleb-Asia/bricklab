<?php

/**
 * BrickLab Brand Vocabulary V1.0 Data Helpers
 * The Grid • Build Logs • Buildmates • Builders
 */

// ——————————————
// Build Logs & Categories
// ——————————————

function community_post_type_options(): array
{
    return [
        'update' => 'Builder Update',
        'milestone' => 'Build Log',
        'release' => 'Build Log',
        'learning' => 'Builder Update',
    ];
}

function community_post_type_label(string $type): string
{
    return match ($type) {
        'milestone', 'release' => 'Build Log',
        default => 'Builder Update',
    };
}

function community_format_brick_id(string $username): string
{
    return '@' . ltrim(trim($username), '@');
}

// ——————————————
// Build Logs (Posts)
// ——————————————

function community_create_post(PDO $pdo, int $userId, string $body, string $postType, ?int $projectId): int
{
    if ($projectId !== null) {
        $project = $pdo->prepare('SELECT id FROM projects WHERE id = :id AND user_id = :uid LIMIT 1');
        $project->execute(['id' => $projectId, 'uid' => $userId]);
        if (!$project->fetch()) {
            throw new InvalidArgumentException('The linked Build is not owned by this Builder.');
        }
    }

    $statement = $pdo->prepare(
        'INSERT INTO community_posts (user_id, body, post_type, project_id)
         VALUES (:user_id, :body, :post_type, :project_id)'
    );
    $statement->execute([
        'user_id' => $userId,
        'body' => $body,
        'post_type' => $postType,
        'project_id' => $projectId,
    ]);
    return (int) $pdo->lastInsertId();
}

function community_delete_post(PDO $pdo, int $postId, int $userId): bool
{
    $statement = $pdo->prepare(
        'DELETE FROM community_posts WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute(['id' => $postId, 'user_id' => $userId]);
    return $statement->rowCount() > 0;
}

function community_update_post(PDO $pdo, int $postId, int $userId, string $body, string $postType, ?int $projectId): bool
{
    if ($projectId !== null) {
        $project = $pdo->prepare('SELECT id FROM projects WHERE id = :id AND user_id = :uid LIMIT 1');
        $project->execute(['id' => $projectId, 'uid' => $userId]);
        if (!$project->fetch()) {
            throw new InvalidArgumentException('The linked Build is not owned by this Builder.');
        }
    }

    $statement = $pdo->prepare(
        'UPDATE community_posts
         SET body = :body, post_type = :post_type, project_id = :project_id
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'body' => $body,
        'post_type' => $postType,
        'project_id' => $projectId,
        'id' => $postId,
        'user_id' => $userId,
    ]);
    return $statement->rowCount() >= 0;
}

function community_fetch_feed(PDO $pdo, int $limit = 20, int $offset = 0, ?int $viewerUserId = null, string $feedType = 'buildmates'): array
{
    $feedType = $feedType === 'everyone' ? 'everyone' : 'buildmates';
    $countParams = [];
    $conditions = [];

    if ($feedType === 'buildmates') {
        if ($viewerUserId === null) {
            $conditions[] = '1 = 0';
        } else {
            $countParams['viewer_buildmates_author'] = $viewerUserId;
            $countParams['viewer_requester'] = $viewerUserId;
            $countParams['viewer_addressee'] = $viewerUserId;
            $countParams['viewer_project_owner_count'] = $viewerUserId;
            $conditions[] = '(
                post.user_id = :viewer_buildmates_author
                OR EXISTS (
                    SELECT 1
                    FROM buildmates AS accepted
                    WHERE accepted.status = \'accepted\'
                      AND (
                        (accepted.requester_id = :viewer_requester AND accepted.addressee_id = post.user_id)
                        OR (accepted.addressee_id = :viewer_addressee AND accepted.requester_id = post.user_id)
                      )
                )
            )';
            $conditions[] = '(
                post.project_id IS NULL
                OR project.is_public = 1
                OR post.user_id = :viewer_project_owner_count
            )';
        }
    } else {
        $conditions[] = '(post.project_id IS NULL OR project.is_public = 1)';
    }

    $where = implode(' AND ', $conditions);
    $countStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM community_posts AS post
         LEFT JOIN projects AS project ON project.id = post.project_id
         WHERE ' . $where
    );
    $countStatement->execute($countParams);
    $total = (int) $countStatement->fetchColumn();

    $feedParams = $countParams;
    $likeColumn = ', 0 AS viewer_liked';
    $projectIdColumn = ', CASE
        WHEN post.project_id IS NULL THEN NULL
        WHEN project.is_public = 1 THEN post.project_id
        ELSE NULL
    END AS project_id';
    $projectMetadataColumns = ',
        CASE WHEN post.project_id IS NULL OR project.is_public = 1 THEN project.title ELSE NULL END AS project_title,
        CASE WHEN post.project_id IS NULL OR project.is_public = 1 THEN project.description ELSE NULL END AS project_description,
        CASE WHEN post.project_id IS NULL OR project.is_public = 1 THEN project.technologies ELSE NULL END AS project_technologies,
        CASE
            WHEN post.project_id IS NULL OR project.is_public != 1 THEN NULL
            ELSE (SELECT image.image_path FROM project_images AS image
                  WHERE image.project_id = project.id ORDER BY image.id ASC LIMIT 1)
        END AS project_cover';

    if ($viewerUserId !== null) {
        $feedParams['viewer_like'] = $viewerUserId;
        $feedParams['viewer_project_id'] = $viewerUserId;
        $feedParams['viewer_project_title'] = $viewerUserId;
        $feedParams['viewer_project_description'] = $viewerUserId;
        $feedParams['viewer_project_technologies'] = $viewerUserId;
        $feedParams['viewer_project_cover'] = $viewerUserId;
        $likeColumn = ', (SELECT COUNT(*) FROM post_likes AS vl
                         WHERE vl.post_id = post.id AND vl.user_id = :viewer_like) AS viewer_liked';
        $projectIdColumn = ', CASE
            WHEN post.project_id IS NULL THEN NULL
            WHEN project.is_public = 1 THEN post.project_id
            WHEN post.user_id = :viewer_project_id THEN post.project_id
            ELSE NULL
        END AS project_id';
        $projectMetadataColumns = ',
            CASE
                WHEN post.project_id IS NULL THEN NULL
                WHEN project.is_public = 1 THEN project.title
                WHEN post.user_id = :viewer_project_title THEN project.title
                ELSE NULL
            END AS project_title,
            CASE
                WHEN post.project_id IS NULL THEN NULL
                WHEN project.is_public = 1 THEN project.description
                WHEN post.user_id = :viewer_project_description THEN project.description
                ELSE NULL
            END AS project_description,
            CASE
                WHEN post.project_id IS NULL THEN NULL
                WHEN project.is_public = 1 THEN project.technologies
                WHEN post.user_id = :viewer_project_technologies THEN project.technologies
                ELSE NULL
            END AS project_technologies,
            CASE
                WHEN post.project_id IS NULL THEN NULL
                WHEN project.is_public = 1 THEN (SELECT image.image_path FROM project_images AS image
                    WHERE image.project_id = project.id ORDER BY image.id ASC LIMIT 1)
                WHEN post.user_id = :viewer_project_cover THEN (SELECT image.image_path FROM project_images AS image
                    WHERE image.project_id = project.id ORDER BY image.id ASC LIMIT 1)
                ELSE NULL
            END AS project_cover';
    }

    $sql = 'SELECT
                post.id,
                post.user_id,
                post.body,
                post.post_type,
                post.created_at,
                user.username,
                user.display_name,
                user.avatar_url,
                user.professional_role
                ' . $projectIdColumn . '
                ' . $projectMetadataColumns . ',
                (SELECT COUNT(*) FROM post_likes AS pl WHERE pl.post_id = post.id) AS like_count
                ' . $likeColumn . '
            FROM community_posts AS post
            INNER JOIN users AS user ON user.id = post.user_id
            LEFT JOIN projects AS project ON project.id = post.project_id
            WHERE ' . $where . '
            ORDER BY post.created_at DESC, post.id DESC
            LIMIT :lim OFFSET :off';

    $statement = $pdo->prepare($sql);
    foreach ($feedParams as $key => $value) {
        $statement->bindValue(':' . $key, $value, PDO::PARAM_INT);
    }
    $statement->bindValue(':lim', $limit, PDO::PARAM_INT);
    $statement->bindValue(':off', $offset, PDO::PARAM_INT);
    $statement->execute();

    return [
        'items' => $statement->fetchAll(),
        'total' => $total,
    ];
}

function community_fetch_user_posts(PDO $pdo, int $userId, int $limit = 20, int $offset = 0, ?int $viewerUserId = null): array
{
    $likeColumn = $viewerUserId !== null
        ? ', (SELECT COUNT(*) FROM post_likes AS vl WHERE vl.post_id = post.id AND vl.user_id = :viewer_id) AS viewer_liked'
        : ', 0 AS viewer_liked';
    $params = ['profile_user_id' => $userId];
    if ($viewerUserId !== null) {
        $params['viewer_id'] = $viewerUserId;
    }

    $visibilityCondition = $viewerUserId === $userId
        ? '1 = 1'
        : '(post.project_id IS NULL OR project.is_public = 1)';

    $countStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM community_posts AS post
         LEFT JOIN projects AS project ON project.id = post.project_id
         WHERE post.user_id = :uid AND ' . $visibilityCondition
    );
    $countStatement->execute(['uid' => $userId]);
    $total = (int) $countStatement->fetchColumn();

    $projectMetadataColumns = $viewerUserId === $userId
        ? ', CASE WHEN post.project_id IS NULL THEN NULL ELSE post.project_id END AS project_id,
           CASE WHEN post.project_id IS NULL THEN NULL ELSE project.title END AS project_title,
           CASE WHEN post.project_id IS NULL THEN NULL ELSE project.description END AS project_description,
           CASE WHEN post.project_id IS NULL THEN NULL ELSE project.technologies END AS project_technologies,
           CASE WHEN post.project_id IS NULL THEN NULL ELSE (
               SELECT image.image_path FROM project_images AS image
               WHERE image.project_id = project.id ORDER BY image.id ASC LIMIT 1
           ) END AS project_cover'
        : ', CASE
               WHEN post.project_id IS NULL THEN NULL
               WHEN project.is_public = 1 THEN post.project_id
               ELSE NULL
           END AS project_id,
           CASE
               WHEN post.project_id IS NULL THEN NULL
               WHEN project.is_public = 1 THEN project.title
               ELSE NULL
           END AS project_title,
           CASE
               WHEN post.project_id IS NULL THEN NULL
               WHEN project.is_public = 1 THEN project.description
               ELSE NULL
           END AS project_description,
           CASE
               WHEN post.project_id IS NULL THEN NULL
               WHEN project.is_public = 1 THEN project.technologies
               ELSE NULL
           END AS project_technologies,
           CASE
               WHEN post.project_id IS NULL OR project.is_public != 1 THEN NULL
               ELSE (SELECT image.image_path FROM project_images AS image
                     WHERE image.project_id = project.id ORDER BY image.id ASC LIMIT 1)
           END AS project_cover';

    $sql = 'SELECT
                post.id,
                post.user_id,
                post.body,
                post.post_type,
                post.created_at,
                user.username,
                user.display_name,
                user.avatar_url,
                user.professional_role,
                ' . ltrim($projectMetadataColumns, ',') . ',
                (SELECT COUNT(*) FROM post_likes AS pl WHERE pl.post_id = post.id) AS like_count
                ' . $likeColumn . '
            FROM community_posts AS post
            INNER JOIN users AS user ON user.id = post.user_id
            LEFT JOIN projects AS project ON project.id = post.project_id
            WHERE post.user_id = :profile_user_id
              AND ' . $visibilityCondition . '
            ORDER BY post.created_at DESC, post.id DESC
            LIMIT :lim OFFSET :off';

    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value, PDO::PARAM_INT);
    }
    $statement->bindValue(':lim', $limit, PDO::PARAM_INT);
    $statement->bindValue(':off', $offset, PDO::PARAM_INT);
    $statement->execute();

    return [
        'items' => $statement->fetchAll(),
        'total' => $total,
    ];
}

// ——————————————
// Likes
// ——————————————

function community_toggle_like(PDO $pdo, int $userId, int $postId): bool
{
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM post_likes WHERE user_id = :uid AND post_id = :pid'
    );
    $check->execute(['uid' => $userId, 'pid' => $postId]);

    if ((int) $check->fetchColumn() > 0) {
        $pdo->prepare(
            'DELETE FROM post_likes WHERE user_id = :uid AND post_id = :pid'
        )->execute(['uid' => $userId, 'pid' => $postId]);
        return false; // unliked
    }

    $pdo->prepare(
        'INSERT INTO post_likes (user_id, post_id) VALUES (:uid, :pid)'
    )->execute(['uid' => $userId, 'pid' => $postId]);
    return true; // liked
}

// ——————————————
// Buildmates (Mutual Connections)
// ——————————————

/**
 * Returns relationship status between viewer and target:
 * - null: no connection
 * - 'accepted': mutually connected Buildmates
 * - 'sent': viewer sent a connection request to target (pending)
 * - 'received': target sent a connection request to viewer (pending)
 */
function buildmate_status(PDO $pdo, int $viewerId, int $targetId): ?string
{
    if ($viewerId === $targetId) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT requester_id, addressee_id, status FROM buildmates
         WHERE (requester_id = :v1 AND addressee_id = :t1)
            OR (requester_id = :t2 AND addressee_id = :v2)
         LIMIT 1'
    );
    $stmt->execute([
        'v1' => $viewerId,
        't1' => $targetId,
        't2' => $targetId,
        'v2' => $viewerId,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    if ($row['status'] === 'accepted') {
        return 'accepted';
    }

    if ($row['status'] === 'pending') {
        return ((int) $row['requester_id'] === $viewerId) ? 'sent' : 'received';
    }

    return null;
}

/**
 * Handle Buildmate actions (request, accept, disconnect)
 */
function buildmate_toggle(PDO $pdo, int $viewerId, int $targetId): string
{
    if ($viewerId === $targetId) {
        return 'self';
    }

    $currentStatus = buildmate_status($pdo, $viewerId, $targetId);

    if ($currentStatus === null) {
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare(
                'DELETE FROM buildmates
                 WHERE (requester_id = :v1 AND addressee_id = :t1)
                    OR (requester_id = :t2 AND addressee_id = :v2)'
            );
            $delete->execute(['v1' => $viewerId, 't1' => $targetId, 't2' => $targetId, 'v2' => $viewerId]);
            $pdo->prepare(
                'INSERT INTO buildmates (requester_id, addressee_id, status)
                 VALUES (:req, :addr, "pending")'
            )->execute(['req' => $viewerId, 'addr' => $targetId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        return 'sent';
    }

    if ($currentStatus === 'received') {
        // Accept the incoming connection request
        $stmt = $pdo->prepare(
            'UPDATE buildmates
             SET status = "accepted"
             WHERE requester_id = :target AND addressee_id = :viewer'
        );
        $stmt->execute(['target' => $targetId, 'viewer' => $viewerId]);
        return 'accepted';
    }

    // If 'sent' or 'accepted', toggle removes/cancels the connection
    $stmt = $pdo->prepare(
        'DELETE FROM buildmates
         WHERE (requester_id = :v1 AND addressee_id = :t1)
            OR (requester_id = :t2 AND addressee_id = :v2)'
    );
    $stmt->execute([
        'v1' => $viewerId,
        't1' => $targetId,
        't2' => $targetId,
        'v2' => $viewerId,
    ]);
    return 'none';
}

function buildmate_accept(PDO $pdo, int $viewerId, int $targetId): bool
{
    $statement = $pdo->prepare(
        'UPDATE buildmates
         SET status = "accepted"
         WHERE requester_id = :target AND addressee_id = :viewer AND status = "pending"'
    );
    $statement->execute(['target' => $targetId, 'viewer' => $viewerId]);
    return $statement->rowCount() > 0;
}

function buildmate_decline(PDO $pdo, int $viewerId, int $targetId): bool
{
    $statement = $pdo->prepare(
        'UPDATE buildmates
         SET status = "declined"
         WHERE requester_id = :target AND addressee_id = :viewer AND status = "pending"'
    );
    $statement->execute(['target' => $targetId, 'viewer' => $viewerId]);
    return $statement->rowCount() > 0;
}

function buildmate_remove(PDO $pdo, int $viewerId, int $targetId): bool
{
    $statement = $pdo->prepare(
        'DELETE FROM buildmates
         WHERE ((requester_id = :v1 AND addressee_id = :t1)
            OR (requester_id = :t2 AND addressee_id = :v2))
           AND status IN ("pending", "accepted")'
    );
    $statement->execute(['v1' => $viewerId, 't1' => $targetId, 't2' => $targetId, 'v2' => $viewerId]);
    return $statement->rowCount() > 0;
}

function buildmates_pending_requests(PDO $pdo, int $userId, int $limit = 20): array
{
    $statement = $pdo->prepare(
        'SELECT user.id, user.username, user.display_name, user.avatar_url,
                request.created_at
         FROM buildmates AS request
         INNER JOIN users AS user ON user.id = request.requester_id
         WHERE request.addressee_id = :uid AND request.status = "pending"
         ORDER BY request.created_at DESC
         LIMIT :lim'
    );
    $statement->bindValue(':uid', $userId, PDO::PARAM_INT);
    $statement->bindValue(':lim', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function buildmates_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM buildmates
         WHERE (requester_id = :u1 OR addressee_id = :u2)
           AND status = "accepted"'
    );
    $stmt->execute(['u1' => $userId, 'u2' => $userId]);
    return (int) $stmt->fetchColumn();
}

function buildmates_pending_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM buildmates
         WHERE addressee_id = :uid AND status = "pending"'
    );
    $stmt->execute(['uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

// ——————————————
// Builders to Discover & Search
// ——————————————

function community_fetch_builders_to_discover(PDO $pdo, int $limit = 5, ?int $excludeUserId = null): array
{
    $excludeClause = $excludeUserId !== null ? 'WHERE user.id != :exclude_id' : '';
    $sql = 'SELECT
                user.id,
                user.username,
                user.display_name,
                user.professional_role,
                user.avatar_url,
                (SELECT COUNT(*) FROM projects AS p WHERE p.user_id = user.id AND p.is_public = 1) AS build_count
            FROM users AS user
            ' . $excludeClause . '
            ORDER BY build_count DESC, user.id DESC
            LIMIT :lim';

    $statement = $pdo->prepare($sql);
    if ($excludeUserId !== null) {
        $statement->bindValue(':exclude_id', $excludeUserId, PDO::PARAM_INT);
    }
    $statement->bindValue(':lim', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function community_search_developers(PDO $pdo, string $query, int $limit = 12): array
{
    $query = trim($query);
    if (str_starts_with($query, '@')) {
        $query = substr($query, 1);
    }
    $query = trim($query);

    if ($query === '') {
        return [];
    }

    $escapedQuery = str_replace(
        ['!', '%', '_'],
        ['!!', '!%', '!_'],
        $query
    );
    $pattern = '%' . $escapedQuery . '%';

    $statement = $pdo->prepare(
        'SELECT
            user.id,
            user.username,
            user.display_name,
            user.professional_role,
            user.avatar_url
        FROM users AS user
        WHERE LOWER(user.username) = LOWER(:exact_username)
           OR LOWER(user.display_name) = LOWER(:exact_display_name)
           OR user.username LIKE :username_partial ESCAPE \'!\'
           OR user.display_name LIKE :display_name_partial ESCAPE \'!\'
        ORDER BY
            CASE
                WHEN LOWER(user.username) = LOWER(:order_exact_username) THEN 0
                WHEN LOWER(user.display_name) = LOWER(:order_exact_display_name) THEN 1
                ELSE 2
            END,
            user.username ASC
        LIMIT :lim'
    );
    $statement->bindValue(':exact_username', $query);
    $statement->bindValue(':exact_display_name', $query);
    $statement->bindValue(':username_partial', $pattern);
    $statement->bindValue(':display_name_partial', $pattern);
    $statement->bindValue(':order_exact_username', $query);
    $statement->bindValue(':order_exact_display_name', $query);
    $statement->bindValue(':lim', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

// Alias for search_developers to match brand vocabulary
function community_search_builders(PDO $pdo, string $query, int $limit = 12): array
{
    return community_search_developers($pdo, $query, $limit);
}

function community_time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . ($mins === 1 ? ' min ago' : ' mins ago');
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ($hours === 1 ? ' hour ago' : ' hours ago');
    }
    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days . ($days === 1 ? ' day ago' : ' days ago');
    }

    return date('M j, Y', $timestamp);
}
