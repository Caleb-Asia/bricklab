<?php

require_once __DIR__ . '/includes/auth.php';
require_authentication();
require_once __DIR__ . '/config/database.php';

$pageTitle = 'My Builds';
$currentPage = 'projects';
$userId = current_user_id();
$statusOptions = [
    'planning' => 'Planning',
    'in_progress' => 'In progress',
    'completed' => 'Completed',
];
$errors = [];
$pageError = '';
$databaseError = '';
$editId = null;
$confirmDeleteId = null;
$editProject = null;
$confirmDeleteProject = null;
$formValues = [
    'title' => '',
    'description' => '',
    'status' => 'planning',
];

function project_text_length(string $value): ?int
{
    $length = preg_match_all('/./us', $value);

    return $length === false ? null : $length;
}

function fetch_owned_project(PDO $pdo, int $projectId, int $userId): array|false
{
    $statement = $pdo->prepare(
        'SELECT id, title, description, status, created_at, updated_at
        FROM projects
        WHERE id = :id AND user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $projectId,
        'user_id' => $userId,
    ]);

    return $statement->fetch();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = request_string($_POST['action'] ?? null);

    if (!csrf_token_is_valid($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'create' || $action === 'update') {
        $formValues = [
            'title' => trim(request_string($_POST['title'] ?? null)),
            'description' => trim(request_string($_POST['description'] ?? null)),
            'status' => request_string($_POST['status'] ?? null),
        ];

        $titleLength = project_text_length($formValues['title']);
        $descriptionLength = project_text_length($formValues['description']);

        if ($formValues['title'] === '') {
            $errors[] = 'Enter a project title.';
        } elseif ($titleLength === null || $titleLength > 100) {
            $errors[] = 'Project titles must be 100 characters or fewer.';
        }

        if ($descriptionLength === null || $descriptionLength > 5000) {
            $errors[] = 'Descriptions must be 5,000 characters or fewer.';
        }

        if (!array_key_exists($formValues['status'], $statusOptions)) {
            $errors[] = 'Choose a valid project status.';
        }

        $postedProjectId = null;
        if ($action === 'update') {
            $postedProjectId = positive_integer($_POST['project_id'] ?? null);
            if ($postedProjectId === null) {
                http_response_code(404);
                $errors[] = 'That project could not be found.';
            } else {
                $editId = $postedProjectId;
            }
        }

        if ($errors === []) {
            try {
                if ($action === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO projects (user_id, title, description, status)
                        VALUES (:user_id, :title, :description, :status)'
                    );
                    $statement->execute([
                        'user_id' => $userId,
                        'title' => $formValues['title'],
                        'description' => $formValues['description'],
                        'status' => $formValues['status'],
                    ]);

                    set_flash_message('success', 'Your build has been created.');
                } else {
                    $existingProject = fetch_owned_project($pdo, $postedProjectId, $userId);

                    if ($existingProject === false) {
                        http_response_code(404);
                        $errors[] = 'That project could not be found.';
                    } else {
                        $statement = $pdo->prepare(
                            'UPDATE projects
                            SET title = :title, description = :description, status = :status
                            WHERE id = :id AND user_id = :user_id'
                        );
                        $statement->execute([
                            'title' => $formValues['title'],
                            'description' => $formValues['description'],
                            'status' => $formValues['status'],
                            'id' => $postedProjectId,
                            'user_id' => $userId,
                        ]);

                        set_flash_message('success', 'Your build has been updated.');
                    }
                }

                if ($errors === []) {
                    header('Location: /bricklab/projects.php', true, 303);
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('BrickLab project save failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Your build could not be saved right now. Please try again.';
            }
        }
    } elseif ($action === 'delete') {
        $postedProjectId = positive_integer($_POST['project_id'] ?? null);

        if ($postedProjectId === null) {
            http_response_code(404);
            $errors[] = 'That project could not be found.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'DELETE FROM projects WHERE id = :id AND user_id = :user_id'
                );
                $statement->execute([
                    'id' => $postedProjectId,
                    'user_id' => $userId,
                ]);

                if ($statement->rowCount() === 1) {
                    set_flash_message(
                        'success',
                        'Your build and its related tasks and images have been deleted.'
                    );
                    header('Location: /bricklab/projects.php', true, 303);
                    exit;
                }

                http_response_code(404);
                $errors[] = 'That project could not be found.';
            } catch (PDOException $exception) {
                error_log('BrickLab project deletion failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Your build could not be deleted right now. Please try again.';
            }
        }
    } else {
        http_response_code(400);
        $errors[] = 'That request could not be processed.';
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if (isset($_GET['edit'])) {
        $editId = positive_integer($_GET['edit']);
        if ($editId === null) {
            http_response_code(404);
            $pageError = 'That project could not be found.';
        }
    }

    if (isset($_GET['confirm_delete'])) {
        $confirmDeleteId = positive_integer($_GET['confirm_delete']);
        if ($confirmDeleteId === null) {
            http_response_code(404);
            $pageError = 'That project could not be found.';
        }
    }
}

if ($editId !== null) {
    try {
        $editProject = fetch_owned_project($pdo, $editId, $userId);
        if ($editProject === false) {
            http_response_code(404);
            $pageError = 'That project could not be found.';
            $editId = null;
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $formValues = [
                'title' => (string) $editProject['title'],
                'description' => (string) ($editProject['description'] ?? ''),
                'status' => (string) ($editProject['status'] ?? 'planning'),
            ];
        }
    } catch (PDOException $exception) {
        error_log('BrickLab project lookup failed: ' . $exception->getMessage());
        http_response_code(500);
        $pageError = 'This build could not be loaded right now. Please try again later.';
    }
}

if ($confirmDeleteId !== null) {
    try {
        $confirmDeleteProject = fetch_owned_project($pdo, $confirmDeleteId, $userId);
        if ($confirmDeleteProject === false) {
            http_response_code(404);
            $pageError = 'That project could not be found.';
            $confirmDeleteId = null;
        }
    } catch (PDOException $exception) {
        error_log('BrickLab delete confirmation lookup failed: ' . $exception->getMessage());
        http_response_code(500);
        $pageError = 'This build could not be loaded right now. Please try again later.';
    }
}

$projects = [];
try {
    $statement = $pdo->prepare(
        'SELECT id, title, description, status, created_at
        FROM projects
        WHERE user_id = :user_id
        ORDER BY created_at DESC, id DESC'
    );
    $statement->execute(['user_id' => $userId]);
    $projects = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('BrickLab project list failed: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = 'Your builds could not be loaded right now. Please try again later.';
}

$flashMessage = consume_flash_message();

require __DIR__ . '/includes/header.php';
?>
            <header class="projects-heading">
                <div>
                    <p class="section-kicker">YOUR WORKSHOP</p>
                    <h1>My Builds</h1>
                    <p class="section-subtitle">Plan, track, and manage your creations.</p>
                </div>
                <a class="btn btn-secondary" href="/bricklab/">Back to dashboard</a>
            </header>

            <?php if ($flashMessage !== null): ?>
                <p
                    class="form-notice form-notice-<?= escape_html($flashMessage['type']) ?>"
                    role="<?= $flashMessage['type'] === 'error' ? 'alert' : 'status' ?>"
                >
                    <?= escape_html($flashMessage['message']) ?>
                </p>
            <?php endif; ?>

            <?php if ($pageError !== ''): ?>
                <p class="form-notice form-notice-error" role="alert">
                    <?= escape_html($pageError) ?>
                </p>
            <?php endif; ?>

            <?php if ($databaseError !== ''): ?>
                <p class="form-notice form-notice-error" role="alert">
                    <?= escape_html($databaseError) ?>
                </p>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="form-notice form-notice-error" role="alert">
                    <p>Please review the following:</p>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape_html($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($confirmDeleteProject !== null): ?>
                <section class="project-form-panel" aria-labelledby="delete-title">
                    <h2 id="delete-title">Delete this build?</h2>
                    <p>
                        You are about to delete
                        <strong><?= escape_html((string) $confirmDeleteProject['title']) ?></strong>.
                        Its related tasks and images will also be deleted. This cannot be undone.
                    </p>
                    <div class="project-form-actions">
                        <form method="post" action="/bricklab/projects.php">
                            <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input
                                type="hidden"
                                name="project_id"
                                value="<?= (int) $confirmDeleteProject['id'] ?>"
                            >
                            <button class="btn btn-danger" type="submit">Delete build</button>
                        </form>
                        <a class="btn btn-secondary" href="/bricklab/projects.php">Keep build</a>
                    </div>
                </section>
            <?php elseif ($databaseError === ''): ?>
                <section class="project-form-panel" aria-labelledby="project-form-title">
                    <h2 id="project-form-title">
                        <?= $editProject !== null ? 'Edit build' : 'Start a new build' ?>
                    </h2>

                    <form class="project-form" method="post" action="/bricklab/projects.php">
                        <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                        <input
                            type="hidden"
                            name="action"
                            value="<?= $editProject !== null ? 'update' : 'create' ?>"
                        >
                        <?php if ($editProject !== null): ?>
                            <input
                                type="hidden"
                                name="project_id"
                                value="<?= (int) $editProject['id'] ?>"
                            >
                        <?php endif; ?>

                        <label for="project-title">Title</label>
                        <input
                            id="project-title"
                            name="title"
                            type="text"
                            maxlength="100"
                            value="<?= escape_html($formValues['title']) ?>"
                            aria-invalid="<?= in_array('Enter a project title.', $errors, true) ? 'true' : 'false' ?>"
                            required
                        >

                        <label for="project-description">Description</label>
                        <textarea
                            id="project-description"
                            name="description"
                            rows="4"
                            maxlength="5000"
                        ><?= escape_html($formValues['description']) ?></textarea>

                        <label for="project-status">Status</label>
                        <select id="project-status" name="status" required>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option
                                    value="<?= escape_html($value) ?>"
                                    <?= $formValues['status'] === $value ? 'selected' : '' ?>
                                >
                                    <?= escape_html($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="project-form-actions">
                            <button class="btn btn-primary" type="submit">
                                <?= $editProject !== null ? 'Save changes' : 'Create build' ?>
                            </button>
                            <?php if ($editProject !== null): ?>
                                <a class="btn btn-secondary" href="/bricklab/projects.php">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="project-list-section" aria-labelledby="project-list-title">
                <div class="project-list-heading">
                    <h2 id="project-list-title">Your builds</h2>
                    <?php if ($databaseError === ''): ?>
                        <span><?= count($projects) ?> <?= count($projects) === 1 ? 'build' : 'builds' ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($databaseError === ''): ?>
                    <?php if ($projects === []): ?>
                        <div class="project-empty-state surface">
                            <span class="project-empty-icon" aria-hidden="true">✦</span>
                            <h3>Your workshop is ready</h3>
                            <p>Create a build above to keep its goal and progress in one place.</p>
                        </div>
                    <?php else: ?>
                        <div class="project-grid">
                            <?php foreach ($projects as $project): ?>
                                <?php
                                $status = (string) ($project['status'] ?? '');
                                $statusClass = array_key_exists($status, $statusOptions)
                                    ? $status
                                    : 'unknown';
                                $statusLabel = $statusOptions[$status] ?? 'Status unavailable';
                                $createdTimestamp = strtotime((string) $project['created_at']);
                                ?>
                                <article class="project-card surface">
                                    <div class="project-card-top">
                                        <span class="project-status status-<?= escape_html($statusClass) ?>">
                                            <?= escape_html($statusLabel) ?>
                                        </span>
                                        <span class="project-created">
                                            <?= $createdTimestamp === false
                                                ? 'Date unavailable'
                                                : 'Created ' . date('M j, Y', $createdTimestamp) ?>
                                        </span>
                                    </div>

                                    <h3><?= escape_html((string) $project['title']) ?></h3>
                                    <p class="project-description">
                                        <?= $project['description'] === null || $project['description'] === ''
                                            ? 'No description added yet.'
                                            : nl2br(escape_html((string) $project['description'])) ?>
                                    </p>

                                    <div class="project-card-actions">
                                        <a
                                            class="btn btn-secondary"
                                            href="/bricklab/projects.php?edit=<?= (int) $project['id'] ?>"
                                        >
                                            Edit
                                        </a>
                                        <a
                                            class="btn btn-danger-outline"
                                            href="/bricklab/projects.php?confirm_delete=<?= (int) $project['id'] ?>"
                                        >
                                            Delete
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/includes/footer.php'; ?>
