<?php

function showcase_category_options(): array
{
    return [
        'web' => 'Web',
        'mobile' => 'Mobile',
        'desktop' => 'Desktop',
        'game' => 'Game',
        'data' => 'Data & AI',
        'tools' => 'Tools',
        'other' => 'Other',
    ];
}

function showcase_parse_tags(string $value): array
{
    $tags = [];
    foreach (explode(',', $value) as $tag) {
        $tag = trim($tag);

        if (
            $tag === ''
            || strlen($tag) > 40
            || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9 .+#-]*\z/', $tag)
        ) {
            continue;
        }

        $tags[strtolower($tag)] = $tag;
    }

    return array_values($tags);
}

function showcase_normalize_tags(string $value): string
{
    return implode(', ', showcase_parse_tags($value));
}

function showcase_text_length(string $value): ?int
{
    $length = preg_match_all('/./us', $value);

    return $length === false ? null : $length;
}

function showcase_url_is_valid(string $value): bool
{
    if ($value === '') {
        return true;
    }

    if (strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($value);

    return is_array($parts)
        && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
        && isset($parts['host'])
        && !isset($parts['user'])
        && !isset($parts['pass'])
        && filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
}

function showcase_safe_image_source(?string $value): ?string
{
    if ($value === null || $value === '' || strlen($value) > 2048) {
        return null;
    }

    if (showcase_url_is_valid($value)) {
        return $value;
    }

    if (
        preg_match('#\A(?:/bricklab/)?(?:assets|uploads)/[A-Za-z0-9._/-]+\z#', $value)
        && !str_contains($value, '..')
    ) {
        return str_starts_with($value, '/') ? $value : '/' . $value;
    }

    return null;
}

function showcase_fetch_projects(
    PDO $pdo,
    array $filters,
    ?int $userId = null,
    int $limit = 9,
    int $offset = 0
): array {
    $conditions = ['project.is_public = 1'];
    $parameters = [];

    if ($userId !== null) {
        $conditions[] = 'project.user_id = :profile_user_id';
        $parameters['profile_user_id'] = $userId;
    }

    if (($filters['category'] ?? 'all') !== 'all') {
        $conditions[] = 'project.category = :category';
        $parameters['category'] = $filters['category'];
    }

    if (($filters['technology'] ?? '') !== '') {
        $conditions[] = 'FIND_IN_SET(:technology, REPLACE(project.technologies, \', \', \',\')) > 0';
        $parameters['technology'] = $filters['technology'];
    }

    if (($filters['search'] ?? '') !== '') {
        $searchConditions = [];
        foreach ([
            'project.title',
            'project.description',
            'project.technologies',
            'user.username',
            'user.display_name',
        ] as $index => $column) {
            $key = 'search_' . $index;
            $searchConditions[] = $column . ' LIKE :' . $key;
            $parameters[$key] = '%' . $filters['search'] . '%';
        }
        $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    }

    $where = implode(' AND ', $conditions);
    $countStatement = $pdo->prepare(
        'SELECT COUNT(*)
        FROM projects AS project
        INNER JOIN users AS user ON user.id = project.user_id
        WHERE ' . $where
    );
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();

    $orderBy = match ($filters['sort'] ?? 'newest') {
        'oldest' => 'project.created_at ASC, project.id ASC',
        'title' => 'project.title ASC, project.id DESC',
        default => 'project.created_at DESC, project.id DESC',
    };
    $statement = $pdo->prepare(
        'SELECT
            project.id,
            project.user_id,
            project.title,
            project.description,
            project.category,
            project.technologies,
            project.github_url,
            project.live_demo_url,
            project.created_at,
            user.username,
            user.display_name,
            user.avatar_url,
            (
                SELECT image.image_path
                FROM project_images AS image
                WHERE image.project_id = project.id
                ORDER BY image.id
                LIMIT 1
            ) AS cover_image
        FROM projects AS project
        INNER JOIN users AS user ON user.id = project.user_id
        WHERE ' . $where . '
        ORDER BY ' . $orderBy . '
        LIMIT :result_limit OFFSET :result_offset'
    );

    foreach ($parameters as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':result_offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    return [
        'total' => $total,
        'items' => $statement->fetchAll(),
    ];
}

function showcase_fetch_technology_options(PDO $pdo): array
{
    $statement = $pdo->query(
        'SELECT DISTINCT project.technologies
        FROM projects AS project
        WHERE project.is_public = 1 AND project.technologies IS NOT NULL'
    );
    $options = [];

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $technologyList) {
        foreach (showcase_parse_tags((string) $technologyList) as $technology) {
            $options[strtolower($technology)] = $technology;
        }
    }

    natcasesort($options);
    return array_values($options);
}

function showcase_fetch_developers(PDO $pdo, int $limit = 12, int $offset = 0): array
{
    $countStatement = $pdo->query(
        'SELECT COUNT(*)
        FROM users AS user
        WHERE EXISTS (
            SELECT 1
            FROM projects AS public_project
            WHERE public_project.user_id = user.id AND public_project.is_public = 1
        )'
    );
    $total = (int) $countStatement->fetchColumn();
    $statement = $pdo->prepare(
        'SELECT
            user.id,
            user.username,
            user.display_name,
            user.professional_role,
            user.location,
            user.avatar_url,
            COUNT(project.id) AS public_project_count
        FROM users AS user
        INNER JOIN projects AS project
            ON project.user_id = user.id AND project.is_public = 1
        GROUP BY user.id
        ORDER BY public_project_count DESC, user.username ASC
        LIMIT :result_limit OFFSET :result_offset'
    );
    $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':result_offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    return [
        'items' => $statement->fetchAll(),
        'total' => $total,
    ];
}

function showcase_avatar_initials(string $displayName): string
{
    $parts = preg_split('/\s+/', trim($displayName)) ?: [];
    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials === '' ? '?' : $initials;
}
