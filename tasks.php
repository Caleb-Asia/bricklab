<?php

require_once __DIR__ . '/includes/auth.php';
require_authentication();
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Tasks';
$currentPage = 'tasks';
$userId = current_user_id();

if ($userId === null) {
    throw new LogicException('An authenticated user ID is required.');
}

$statusOptions = [
    'todo' => 'To do',
    'in_progress' => 'In progress',
    'completed' => 'Completed',
];
$priorityOptions = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
];
$errors = [];
$pageError = '';
$databaseError = '';
$projects = [];
$tasks = [];
$overview = null;
$editTask = null;
$confirmDeleteTask = null;
$formValues = [
    'title' => '',
    'description' => '',
    'project_id' => '',
    'priority' => 'medium',
    'status' => 'todo',
    'due_date' => '',
];
$filters = [
    'status' => request_string($_GET['status'] ?? null),
    'project_id' => positive_integer($_GET['project_id'] ?? null),
    'priority' => request_string($_GET['priority'] ?? null),
];

if (!array_key_exists($filters['status'], $statusOptions) && $filters['status'] !== 'all') {
    $filters['status'] = 'all';
}

if (!array_key_exists($filters['priority'], $priorityOptions) && $filters['priority'] !== 'all') {
    $filters['priority'] = 'all';
}

if ($filters['status'] === '') {
    $filters['status'] = 'all';
}

if ($filters['priority'] === '') {
    $filters['priority'] = 'all';
}

function task_text_length(string $value): ?int
{
    $length = preg_match_all('/./us', $value);

    return $length === false ? null : $length;
}

function task_due_date_is_valid(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();

    return $date !== false
        && ($dateErrors === false
            || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
}

function fetch_owned_task(PDO $pdo, int $taskId, int $userId): array|false
{
    $statement = $pdo->prepare(
        'SELECT
            task.id,
            task.project_id,
            task.title,
            task.description,
            task.status,
            task.priority,
            task.is_completed,
            task.due_date,
            task.completed_at,
            task.created_at,
            project.title AS project_title
        FROM tasks AS task
        INNER JOIN projects AS project ON project.id = task.project_id
        WHERE task.id = :task_id AND project.user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'task_id' => $taskId,
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
            'project_id' => request_string($_POST['project_id'] ?? null),
            'priority' => request_string($_POST['priority'] ?? null),
            'status' => request_string($_POST['status'] ?? null),
            'due_date' => request_string($_POST['due_date'] ?? null),
        ];

        $titleLength = task_text_length($formValues['title']);
        $descriptionLength = task_text_length($formValues['description']);
        $projectId = positive_integer($formValues['project_id']);

        if ($formValues['title'] === '') {
            $errors[] = 'Enter a task title.';
        } elseif ($titleLength === null || $titleLength > 150) {
            $errors[] = 'Task titles must be 150 characters or fewer.';
        }

        if ($descriptionLength === null || $descriptionLength > 5000) {
            $errors[] = 'Descriptions must be 5,000 characters or fewer.';
        }

        if ($projectId === null) {
            $errors[] = 'Choose one of your builds.';
        }

        if (!array_key_exists($formValues['priority'], $priorityOptions)) {
            $errors[] = 'Choose a valid task priority.';
        }

        if (!array_key_exists($formValues['status'], $statusOptions)) {
            $errors[] = 'Choose a valid task status.';
        }

        if (
            $formValues['due_date'] !== ''
            && !task_due_date_is_valid($formValues['due_date'])
        ) {
            $errors[] = 'Enter a valid due date.';
        }

        $postedTaskId = null;
        if ($action === 'update') {
            $postedTaskId = positive_integer($_POST['task_id'] ?? null);

            if ($postedTaskId === null) {
                http_response_code(404);
                $errors[] = 'That task could not be found.';
            }
        }

        if ($errors === []) {
            try {
                $projectStatement = $pdo->prepare(
                    'SELECT id FROM projects WHERE id = :project_id AND user_id = :user_id LIMIT 1'
                );
                $projectStatement->execute([
                    'project_id' => $projectId,
                    'user_id' => $userId,
                ]);

                if ($projectStatement->fetchColumn() === false) {
                    http_response_code(404);
                    $errors[] = 'Choose one of your builds.';
                } elseif ($action === 'update') {
                    $existingTask = fetch_owned_task($pdo, $postedTaskId, $userId);

                    if ($existingTask === false) {
                        http_response_code(404);
                        $errors[] = 'That task could not be found.';
                    } else {
                        $statement = $pdo->prepare(
                            'UPDATE tasks
                            SET
                                project_id = :project_id,
                                title = :title,
                                description = :description,
                                priority = :priority,
                                due_date = :due_date,
                                completed_at = CASE
                                    WHEN :completion_status = \'completed\'
                                        AND status <> \'completed\'
                                        THEN CURRENT_TIMESTAMP
                                    WHEN :preserved_status = \'completed\'
                                        THEN completed_at
                                    ELSE NULL
                                END,
                                is_completed = :is_completed,
                                status = :status
                            WHERE id = :task_id
                                AND project_id IN (
                                    SELECT id FROM projects WHERE user_id = :user_id
                                )'
                        );
                        $statement->execute([
                            'project_id' => $projectId,
                            'title' => $formValues['title'],
                            'description' => $formValues['description'] === ''
                                ? null
                                : $formValues['description'],
                            'priority' => $formValues['priority'],
                            'due_date' => $formValues['due_date'] === ''
                                ? null
                                : $formValues['due_date'],
                            'completion_status' => $formValues['status'],
                            'preserved_status' => $formValues['status'],
                            'is_completed' => $formValues['status'] === 'completed' ? 1 : 0,
                            'status' => $formValues['status'],
                            'task_id' => $postedTaskId,
                            'user_id' => $userId,
                        ]);
                        set_flash_message('success', 'Your task has been updated.');
                    }
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO tasks (
                            project_id,
                            title,
                            description,
                            is_completed,
                            status,
                            priority,
                            due_date,
                            completed_at
                        ) VALUES (
                            :project_id,
                            :title,
                            :description,
                            :is_completed,
                            :status,
                            :priority,
                            :due_date,
                            CASE
                                WHEN :completion_status = \'completed\' THEN CURRENT_TIMESTAMP
                                ELSE NULL
                            END
                        )'
                    );
                    $statement->execute([
                        'project_id' => $projectId,
                        'title' => $formValues['title'],
                        'description' => $formValues['description'] === ''
                            ? null
                            : $formValues['description'],
                        'is_completed' => $formValues['status'] === 'completed' ? 1 : 0,
                        'status' => $formValues['status'],
                        'priority' => $formValues['priority'],
                        'due_date' => $formValues['due_date'] === ''
                            ? null
                            : $formValues['due_date'],
                        'completion_status' => $formValues['status'],
                    ]);
                    set_flash_message('success', 'Your task has been created.');
                }

                if ($errors === []) {
                    header('Location: /bricklab/tasks.php', true, 303);
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('BrickLab task save failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Your task could not be saved right now. Please try again.';
            }
        }
    } elseif ($action === 'delete') {
        $taskId = positive_integer($_POST['task_id'] ?? null);

        if ($taskId === null) {
            http_response_code(404);
            $errors[] = 'That task could not be found.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'DELETE task
                    FROM tasks AS task
                    INNER JOIN projects AS project ON project.id = task.project_id
                    WHERE task.id = :task_id AND project.user_id = :user_id'
                );
                $statement->execute([
                    'task_id' => $taskId,
                    'user_id' => $userId,
                ]);

                if ($statement->rowCount() === 1) {
                    set_flash_message('success', 'Your task has been deleted.');
                    header('Location: /bricklab/tasks.php', true, 303);
                    exit;
                }

                http_response_code(404);
                $errors[] = 'That task could not be found.';
            } catch (PDOException $exception) {
                error_log('BrickLab task deletion failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Your task could not be deleted right now. Please try again.';
            }
        }
    } else {
        http_response_code(400);
        $errors[] = 'That request could not be processed.';
    }
}

try {
    $statement = $pdo->prepare(
        'SELECT id, title
        FROM projects
        WHERE user_id = :user_id
        ORDER BY title ASC, id ASC'
    );
    $statement->execute(['user_id' => $userId]);
    $projects = $statement->fetchAll();

    $statement = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_tasks,
            COUNT(CASE WHEN task.status = \'todo\' THEN 1 END) AS todo_tasks,
            COUNT(CASE WHEN task.status = \'in_progress\' THEN 1 END) AS in_progress_tasks,
            COUNT(CASE WHEN task.status = \'completed\' THEN 1 END) AS completed_tasks
        FROM tasks AS task
        INNER JOIN projects AS project ON project.id = task.project_id
        WHERE project.user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);
    $overviewRow = $statement->fetch();
    $overview = [
        'total' => (int) $overviewRow['total_tasks'],
        'todo' => (int) $overviewRow['todo_tasks'],
        'in_progress' => (int) $overviewRow['in_progress_tasks'],
        'completed' => (int) $overviewRow['completed_tasks'],
    ];

    if (
        ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && request_string($_POST['action'] ?? null) === 'update'
    ) {
        $editId = positive_integer($_POST['task_id'] ?? null);

        if ($editId !== null) {
            $editTask = fetch_owned_task($pdo, $editId, $userId);

            if ($editTask === false) {
                http_response_code(404);
                $pageError = 'That task could not be found.';
            }
        }
    } elseif (isset($_GET['edit'])) {
        $editId = positive_integer($_GET['edit']);

        if ($editId === null) {
            http_response_code(404);
            $pageError = 'That task could not be found.';
        } else {
            $editTask = fetch_owned_task($pdo, $editId, $userId);

            if ($editTask === false) {
                http_response_code(404);
                $pageError = 'That task could not be found.';
            } else {
                $formValues = [
                    'title' => (string) $editTask['title'],
                    'description' => (string) ($editTask['description'] ?? ''),
                    'project_id' => (string) $editTask['project_id'],
                    'priority' => (string) $editTask['priority'],
                    'status' => (string) $editTask['status'],
                    'due_date' => (string) ($editTask['due_date'] ?? ''),
                ];
            }
        }
    } elseif (isset($_GET['confirm_delete'])) {
        $deleteId = positive_integer($_GET['confirm_delete']);

        if ($deleteId === null) {
            http_response_code(404);
            $pageError = 'That task could not be found.';
        } else {
            $confirmDeleteTask = fetch_owned_task($pdo, $deleteId, $userId);

            if ($confirmDeleteTask === false) {
                http_response_code(404);
                $pageError = 'That task could not be found.';
            }
        }
    }

    $conditions = ['project.user_id = :user_id'];
    $parameters = ['user_id' => $userId];

    if ($filters['status'] !== 'all') {
        $conditions[] = 'task.status = :filter_status';
        $parameters['filter_status'] = $filters['status'];
    }

    if ($filters['project_id'] !== null) {
        $conditions[] = 'task.project_id = :filter_project_id';
        $parameters['filter_project_id'] = $filters['project_id'];
    }

    if ($filters['priority'] !== 'all') {
        $conditions[] = 'task.priority = :filter_priority';
        $parameters['filter_priority'] = $filters['priority'];
    }

    $statement = $pdo->prepare(
        'SELECT
            task.id,
            task.project_id,
            task.title,
            task.description,
            task.status,
            task.priority,
            task.due_date,
            task.created_at,
            project.title AS project_title
        FROM tasks AS task
        INNER JOIN projects AS project ON project.id = task.project_id
        WHERE ' . implode(' AND ', $conditions) . '
        ORDER BY
            (task.status = \'completed\') ASC,
            (task.due_date IS NULL) ASC,
            task.due_date ASC,
            task.created_at DESC,
            task.id DESC'
    );
    $statement->execute($parameters);
    $tasks = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('BrickLab task page data could not be loaded: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = 'Your tasks could not be loaded right now. Please try again later.';
    $projects = [];
    $tasks = [];
    $overview = null;
}

$flashMessage = consume_flash_message();

require __DIR__ . '/includes/header.php';
?>
            <header class="projects-heading tasks-heading">
                <div>
                    <p class="section-kicker">YOUR WORKSHOP</p>
                    <h1>Tasks</h1>
                    <p class="section-subtitle">Keep the next steps for every build moving.</p>
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

            <?php if ($overview !== null): ?>
                <section class="task-overview-grid" aria-label="Task overview">
                    <?php foreach ([
                        'total' => ['Total tasks', 'All your next steps'],
                        'todo' => ['To do', 'Waiting to get started'],
                        'in_progress' => ['In progress', 'Currently underway'],
                        'completed' => ['Completed', 'Tasks finished'],
                    ] as $key => [$label, $description]): ?>
                        <article class="task-overview-card surface">
                            <span class="task-overview-label"><?= escape_html($label) ?></span>
                            <strong class="task-overview-number"><?= $overview[$key] ?></strong>
                            <span class="task-overview-description">
                                <?= escape_html($description) ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if ($confirmDeleteTask !== null): ?>
                <section class="task-form-panel surface" aria-labelledby="delete-task-title">
                    <h2 id="delete-task-title">Delete this task?</h2>
                    <p>
                        You are about to delete
                        <strong><?= escape_html((string) $confirmDeleteTask['title']) ?></strong>.
                        This cannot be undone.
                    </p>
                    <div class="task-form-actions">
                        <form method="post" action="/bricklab/tasks.php">
                            <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input
                                type="hidden"
                                name="task_id"
                                value="<?= (int) $confirmDeleteTask['id'] ?>"
                            >
                            <button class="btn btn-danger" type="submit">Delete task</button>
                        </form>
                        <a class="btn btn-secondary" href="/bricklab/tasks.php">Keep task</a>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($projects !== [] && $databaseError === ''): ?>
                <section class="task-form-panel surface" id="task-form" aria-labelledby="task-form-title">
                    <h2 id="task-form-title">
                        <?= $editTask !== null ? 'Edit task' : 'Add a task' ?>
                    </h2>

                    <form class="task-form" method="post" action="/bricklab/tasks.php">
                        <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                        <input
                            type="hidden"
                            name="action"
                            value="<?= $editTask !== null ? 'update' : 'create' ?>"
                        >
                        <?php if ($editTask !== null): ?>
                            <input
                                type="hidden"
                                name="task_id"
                                value="<?= (int) $editTask['id'] ?>"
                            >
                        <?php endif; ?>

                        <div class="task-form-grid">
                            <div class="task-form-field task-form-field-wide">
                                <label for="task-title">Title</label>
                                <input
                                    id="task-title"
                                    name="title"
                                    type="text"
                                    maxlength="150"
                                    placeholder="e.g. Sort the roof pieces"
                                    value="<?= escape_html($formValues['title']) ?>"
                                    required
                                >
                            </div>

                            <div class="task-form-field">
                                <label for="task-project">Build</label>
                                <select id="task-project" name="project_id" required>
                                    <option value="">Choose a build</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option
                                            value="<?= (int) $project['id'] ?>"
                                            <?= (string) $project['id'] === $formValues['project_id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= escape_html((string) $project['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="task-form-field">
                                <label for="task-priority">Priority</label>
                                <select id="task-priority" name="priority" required>
                                    <?php foreach ($priorityOptions as $value => $label): ?>
                                        <option
                                            value="<?= escape_html($value) ?>"
                                            <?= $formValues['priority'] === $value ? 'selected' : '' ?>
                                        >
                                            <?= escape_html($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="task-form-field">
                                <label for="task-status">Status</label>
                                <select id="task-status" name="status" required>
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option
                                            value="<?= escape_html($value) ?>"
                                            <?= $formValues['status'] === $value ? 'selected' : '' ?>
                                        >
                                            <?= escape_html($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="task-form-field">
                                <label for="task-due-date">Due date <span>(optional)</span></label>
                                <input
                                    id="task-due-date"
                                    name="due_date"
                                    type="date"
                                    value="<?= escape_html($formValues['due_date']) ?>"
                                >
                            </div>

                            <div class="task-form-field task-form-field-wide">
                                <label for="task-description">Description <span>(optional)</span></label>
                                <textarea
                                    id="task-description"
                                    name="description"
                                    rows="3"
                                    maxlength="5000"
                                    placeholder="Add a note about this step."
                                ><?= escape_html($formValues['description']) ?></textarea>
                            </div>
                        </div>

                        <div class="task-form-actions">
                            <button class="btn btn-primary" type="submit">
                                <?= $editTask !== null ? 'Save changes' : 'Create task' ?>
                            </button>
                            <?php if ($editTask !== null): ?>
                                <a class="btn btn-secondary" href="/bricklab/tasks.php">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            <?php elseif ($projects === [] && $databaseError === ''): ?>
                <section class="task-empty-state surface" aria-labelledby="task-no-builds-title">
                    <span class="project-empty-icon" aria-hidden="true">✦</span>
                    <h2 id="task-no-builds-title">Start with a build</h2>
                    <p>Tasks are connected to your builds. Create a build first, then add its next steps here.</p>
                    <a class="btn btn-primary" href="/bricklab/projects.php">Create a build</a>
                </section>
            <?php endif; ?>

            <section class="task-list-section" aria-labelledby="task-list-title">
                <div class="task-list-heading">
                    <div>
                        <p class="section-kicker">YOUR WORKSHOP</p>
                        <h2 id="task-list-title">Your tasks</h2>
                    </div>
                    <?php if ($overview !== null): ?>
                        <span><?= $overview['total'] ?> <?= $overview['total'] === 1 ? 'task' : 'tasks' ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($projects !== [] && $databaseError === ''): ?>
                    <form class="task-filters" method="get" action="/bricklab/tasks.php">
                        <div>
                            <label for="filter-status">Status</label>
                            <select id="filter-status" name="status">
                                <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>
                                    All tasks
                                </option>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option
                                        value="<?= escape_html($value) ?>"
                                        <?= $filters['status'] === $value ? 'selected' : '' ?>
                                    >
                                        <?= escape_html($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="filter-project">Build</label>
                            <select id="filter-project" name="project_id">
                                <option value="">All builds</option>
                                <?php foreach ($projects as $project): ?>
                                    <option
                                        value="<?= (int) $project['id'] ?>"
                                        <?= $filters['project_id'] === (int) $project['id']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= escape_html((string) $project['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="filter-priority">Priority</label>
                            <select id="filter-priority" name="priority">
                                <option value="all" <?= $filters['priority'] === 'all' ? 'selected' : '' ?>>
                                    All priorities
                                </option>
                                <?php foreach ($priorityOptions as $value => $label): ?>
                                    <option
                                        value="<?= escape_html($value) ?>"
                                        <?= $filters['priority'] === $value ? 'selected' : '' ?>
                                    >
                                        <?= escape_html($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button class="btn btn-secondary" type="submit">Apply filters</button>
                    </form>
                <?php endif; ?>

                <?php if ($databaseError === ''): ?>
                    <?php if ($tasks === []): ?>
                        <div class="task-empty-state surface">
                            <span class="project-empty-icon" aria-hidden="true">✓</span>
                            <h3><?= $overview !== null && $overview['total'] > 0
                                ? 'No tasks match these filters'
                                : 'No tasks yet' ?></h3>
                            <p><?= $overview !== null && $overview['total'] > 0
                                ? 'Try another status, build, or priority.'
                                : 'Add a task above to keep your next build steps organized.' ?></p>
                            <?php if ($filters['status'] !== 'all'
                                || $filters['project_id'] !== null
                                || $filters['priority'] !== 'all'): ?>
                                <a class="btn btn-secondary" href="/bricklab/tasks.php">Clear filters</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                $taskStatus = (string) $task['status'];
                                $taskStatusClass = $taskStatus === 'todo' ? 'planning' : $taskStatus;
                                $taskPriority = (string) $task['priority'];
                                $dueTimestamp = $task['due_date'] === null
                                    ? false
                                    : strtotime((string) $task['due_date']);
                                $isOverdue = $dueTimestamp !== false
                                    && $taskStatus !== 'completed'
                                    && date('Y-m-d', $dueTimestamp) < date('Y-m-d');
                                ?>
                                <article class="task-card surface">
                                    <div class="task-card-main">
                                        <div class="task-card-heading">
                                            <h3><?= escape_html((string) $task['title']) ?></h3>
                                        </div>

                                        <?php if ($task['description'] !== null && $task['description'] !== ''): ?>
                                            <p class="task-description">
                                                <?= nl2br(escape_html((string) $task['description'])) ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="task-meta">
                                            <a href="/bricklab/projects.php?edit=<?= (int) $task['project_id'] ?>">
                                                <?= escape_html((string) $task['project_title']) ?>
                                            </a>
                                            <span class="task-priority task-priority-<?= escape_html($taskPriority) ?>">
                                                <?= escape_html($priorityOptions[$taskPriority] ?? 'Priority unavailable') ?>
                                                priority
                                            </span>
                                            <?php if ($dueTimestamp === false): ?>
                                                <span>No due date</span>
                                            <?php else: ?>
                                                <span class="<?= $isOverdue ? 'task-overdue' : '' ?>">
                                                    <?= $isOverdue ? 'Overdue · ' : 'Due ' ?>
                                                    <?= escape_html(date('M j, Y', $dueTimestamp)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="task-card-actions">
                                        <span class="task-status-badge status-<?= escape_html($taskStatusClass) ?>">
                                            <?= escape_html($statusOptions[$taskStatus] ?? 'Status unavailable') ?>
                                        </span>
                                        <div class="task-card-links">
                                            <a
                                                class="btn btn-secondary"
                                                href="/bricklab/tasks.php?edit=<?= (int) $task['id'] ?>"
                                            >
                                                Edit
                                            </a>
                                            <a
                                                class="btn btn-danger-outline"
                                                href="/bricklab/tasks.php?<?= escape_html(http_build_query([
                                                    'confirm_delete' => (int) $task['id'],
                                                    'status' => $filters['status'],
                                                    'project_id' => $filters['project_id'] ?? '',
                                                    'priority' => $filters['priority'],
                                                ])) ?>"
                                            >
                                                Delete
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
