<?php

require_once __DIR__ . '/includes/auth.php';
require_authentication();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';

$pageTitle = 'My Builds';
$currentPage = 'projects';
$userId = current_user_id();
$statusOptions = [
    'planning' => 'Planning',
    'in_progress' => 'In progress',
    'completed' => 'Completed',
];
$categoryOptions = showcase_category_options();
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
    'category' => 'other',
    'technologies' => '',
    'github_url' => '',
    'live_demo_url' => '',
    'is_public' => '0',
];

function project_text_length(string $value): ?int
{
    $length = preg_match_all('/./us', $value);

    return $length === false ? null : $length;
}

function fetch_owned_project(PDO $pdo, int $projectId, int $userId): array|false
{
    $statement = $pdo->prepare(
        'SELECT
            p.id,
            p.title,
            p.description,
            p.status,
            p.category,
            p.technologies,
            p.github_url,
            p.live_demo_url,
            p.is_public,
            p.created_at,
            p.updated_at,
            (
                SELECT img.image_path
                FROM project_images AS img
                WHERE img.project_id = p.id
                ORDER BY img.id ASC
                LIMIT 1
            ) AS image_path
        FROM projects AS p
        WHERE p.id = :id AND p.user_id = :user_id
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
            'category' => request_string($_POST['category'] ?? null),
            'technologies' => trim(request_string($_POST['technologies'] ?? null)),
            'github_url' => trim(request_string($_POST['github_url'] ?? null)),
            'live_demo_url' => trim(request_string($_POST['live_demo_url'] ?? null)),
            'is_public' => ($_POST['is_public'] ?? null) === '1' ? '1' : '0',
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
        if (!array_key_exists($formValues['category'], $categoryOptions)) {
            $errors[] = 'Choose a valid project category.';
        }
        $technologiesValid = true;
        if (strlen($formValues['technologies']) > 1000) {
            $errors[] = 'Technologies must be 1,000 characters or fewer.';
            $technologiesValid = false;
        } elseif (
            $formValues['technologies'] !== ''
            && !preg_match(
                '/\A[A-Za-z0-9][A-Za-z0-9 .+#-]*(?:\s*,\s*[A-Za-z0-9][A-Za-z0-9 .+#-]*)*\z/',
                $formValues['technologies']
            )
        ) {
            $errors[] = 'Enter technologies as comma-separated tags using letters, numbers, spaces, . + # or -.';
            $technologiesValid = false;
        }
        if ($technologiesValid) {
            $formValues['technologies'] = showcase_normalize_tags($formValues['technologies']);
        }
        foreach (['github_url', 'live_demo_url'] as $urlField) {
            if (!showcase_url_is_valid($formValues[$urlField])) {
                $errors[] = ucfirst(str_replace('_', ' ', $urlField)) . ' must be a valid HTTPS URL.';
            }
        }

        $removeImage = ($_POST['remove_image'] ?? null) === '1';
        $projectImageUploadInfo = null;

        if (array_key_exists('project_image', $_FILES)) {
            $imageFile = $_FILES['project_image'];
            if (!is_array($imageFile) || !isset($imageFile['error']) || !is_int($imageFile['error'])) {
                $errors[] = 'The selected build picture could not be processed. Please choose it again.';
            } elseif ($imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($imageFile['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = $imageFile['error'] === UPLOAD_ERR_INI_SIZE
                        || $imageFile['error'] === UPLOAD_ERR_FORM_SIZE
                        ? 'Build picture must be 5 MB or smaller.'
                        : 'The build picture could not be uploaded. Please try again.';
                } elseif (!isset($imageFile['tmp_name']) || !is_string($imageFile['tmp_name'])
                    || !is_uploaded_file($imageFile['tmp_name'])) {
                    $errors[] = 'The selected build picture was not uploaded securely.';
                } else {
                    $fileSize = filesize($imageFile['tmp_name']);
                    if ($fileSize === false || $fileSize > 5 * 1024 * 1024) {
                        $errors[] = 'Build picture must be 5 MB or smaller.';
                    } else {
                        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                        $mimeType = $fileInfo->file($imageFile['tmp_name']);
                        $image = getimagesize($imageFile['tmp_name']);
                        $allowedImages = [
                            'image/jpeg' => ['extension' => 'jpg', 'type' => IMAGETYPE_JPEG],
                            'image/png' => ['extension' => 'png', 'type' => IMAGETYPE_PNG],
                            'image/webp' => ['extension' => 'webp', 'type' => IMAGETYPE_WEBP],
                        ];

                        if (
                            !is_string($mimeType)
                            || !isset($allowedImages[$mimeType])
                            || $image === false
                            || ($image['mime'] ?? null) !== $mimeType
                            || ($image[2] ?? null) !== $allowedImages[$mimeType]['type']
                        ) {
                            $errors[] = 'Choose a valid JPG, JPEG, PNG, or WebP image for your build.';
                        } else {
                            $projectImageUploadInfo = [
                                'temporary_path' => $imageFile['tmp_name'],
                                'extension' => $allowedImages[$mimeType]['extension'],
                            ];
                        }
                    }
                }
            }
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
            $savedImagePath = null;
            if ($projectImageUploadInfo !== null) {
                $projectDirectory = __DIR__ . '/uploads/projects';
                if (
                    !is_dir($projectDirectory)
                    && !mkdir($projectDirectory, 0755, true)
                    && !is_dir($projectDirectory)
                ) {
                    error_log('BrickLab could not create the project images directory.');
                    $errors[] = 'The build picture could not be saved. Please try again later.';
                } else {
                    $htaccessPath = $projectDirectory . '/.htaccess';
                    if (!is_file($htaccessPath)) {
                        @file_put_contents(
                            $htaccessPath,
                            "Options -Indexes\nSetHandler default-handler\n\n<FilesMatch \"(?i)\\.(php[0-9]?|phtml|phar|cgi|pl|py|rb|sh|asp|aspx|jsp|exe|dll|com|bat|cmd)$\">\n    Require all denied\n</FilesMatch>\n"
                        );
                    }

                    try {
                        $fileName = bin2hex(random_bytes(16)) . '.' . $projectImageUploadInfo['extension'];
                    } catch (Random\RandomException $exception) {
                        error_log('BrickLab could not generate a project image filename: ' . $exception->getMessage());
                        $errors[] = 'The build picture could not be saved. Please try again later.';
                        $fileName = '';
                    }

                    if ($errors === []) {
                        $destination = $projectDirectory . '/' . $fileName;
                        if (!move_uploaded_file($projectImageUploadInfo['temporary_path'], $destination)) {
                            error_log('BrickLab could not move an uploaded project image into storage.');
                            $errors[] = 'The build picture could not be saved. Please try again.';
                        } else {
                            $savedImagePath = '/bricklab/uploads/projects/' . $fileName;
                        }
                    }
                }
            }
        }

        if ($errors === []) {
            try {
                if ($action === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO projects (
                            user_id,
                            title,
                            description,
                            status,
                            category,
                            technologies,
                            github_url,
                            live_demo_url,
                            is_public
                        )
                        VALUES (
                            :user_id,
                            :title,
                            :description,
                            :status,
                            :category,
                            :technologies,
                            :github_url,
                            :live_demo_url,
                            :is_public
                        )'
                    );
                    $statement->execute([
                        'user_id' => $userId,
                        'title' => $formValues['title'],
                        'description' => $formValues['description'],
                        'status' => $formValues['status'],
                        'category' => $formValues['category'],
                        'technologies' => $formValues['technologies'] === ''
                            ? null
                            : $formValues['technologies'],
                        'github_url' => $formValues['github_url'] === ''
                            ? null
                            : $formValues['github_url'],
                        'live_demo_url' => $formValues['live_demo_url'] === ''
                            ? null
                            : $formValues['live_demo_url'],
                        'is_public' => $formValues['is_public'],
                    ]);

                    $newProjectId = (int) $pdo->lastInsertId();

                    if ($savedImagePath !== null) {
                        $imgStatement = $pdo->prepare(
                            'INSERT INTO project_images (project_id, image_path)
                            VALUES (:project_id, :image_path)'
                        );
                        $imgStatement->execute([
                            'project_id' => $newProjectId,
                            'image_path' => $savedImagePath,
                        ]);
                    }

                    set_flash_message('success', 'Your build has been created.');
                } else {
                    $existingProject = fetch_owned_project($pdo, $postedProjectId, $userId);

                    if ($existingProject === false) {
                        http_response_code(404);
                        $errors[] = 'That project could not be found.';
                    } else {
                        $statement = $pdo->prepare(
                            'UPDATE projects
                            SET
                                title = :title,
                                description = :description,
                                status = :status,
                                category = :category,
                                technologies = :technologies,
                                github_url = :github_url,
                                live_demo_url = :live_demo_url,
                                is_public = :is_public
                            WHERE id = :id AND user_id = :user_id'
                        );
                        $statement->execute([
                            'title' => $formValues['title'],
                            'description' => $formValues['description'],
                            'status' => $formValues['status'],
                            'category' => $formValues['category'],
                            'technologies' => $formValues['technologies'] === ''
                                ? null
                                : $formValues['technologies'],
                            'github_url' => $formValues['github_url'] === ''
                                ? null
                                : $formValues['github_url'],
                            'live_demo_url' => $formValues['live_demo_url'] === ''
                                ? null
                                : $formValues['live_demo_url'],
                            'is_public' => $formValues['is_public'],
                            'id' => $postedProjectId,
                            'user_id' => $userId,
                        ]);

                        $existingImgStmt = $pdo->prepare(
                            'SELECT id, image_path FROM project_images WHERE project_id = :project_id ORDER BY id ASC LIMIT 1'
                        );
                        $existingImgStmt->execute(['project_id' => $postedProjectId]);
                        $existingImageRow = $existingImgStmt->fetch();

                        if ($existingImageRow !== false && ($removeImage || $savedImagePath !== null)) {
                            $oldPath = (string) $existingImageRow['image_path'];
                            if (str_starts_with($oldPath, '/bricklab/uploads/projects/')) {
                                $diskPath = __DIR__ . '/uploads/projects/' . basename($oldPath);
                                if (is_file($diskPath)) {
                                    @unlink($diskPath);
                                }
                            }
                        }

                        if ($removeImage && $savedImagePath === null) {
                            $delStmt = $pdo->prepare('DELETE FROM project_images WHERE project_id = :project_id');
                            $delStmt->execute(['project_id' => $postedProjectId]);
                        } elseif ($savedImagePath !== null) {
                            if ($existingImageRow !== false) {
                                $updateImgStmt = $pdo->prepare(
                                    'UPDATE project_images SET image_path = :image_path WHERE id = :id'
                                );
                                $updateImgStmt->execute([
                                    'image_path' => $savedImagePath,
                                    'id' => (int) $existingImageRow['id'],
                                ]);
                            } else {
                                $insertImgStmt = $pdo->prepare(
                                    'INSERT INTO project_images (project_id, image_path) VALUES (:project_id, :image_path)'
                                );
                                $insertImgStmt->execute([
                                    'project_id' => $postedProjectId,
                                    'image_path' => $savedImagePath,
                                ]);
                            }
                        }

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
                $imageStmt = $pdo->prepare(
                    'SELECT image_path FROM project_images WHERE project_id = :project_id'
                );
                $imageStmt->execute(['project_id' => $postedProjectId]);
                $imagesToDelete = $imageStmt->fetchAll();
                foreach ($imagesToDelete as $imgRow) {
                    $path = (string) ($imgRow['image_path'] ?? '');
                    if (str_starts_with($path, '/bricklab/uploads/projects/')) {
                        $diskPath = __DIR__ . '/uploads/projects/' . basename($path);
                        if (is_file($diskPath)) {
                            @unlink($diskPath);
                        }
                    }
                }

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

$currentProjectImage = null;
if ($editId !== null) {
    try {
        $editProject = fetch_owned_project($pdo, $editId, $userId);
        if ($editProject === false) {
            http_response_code(404);
            $pageError = 'That project could not be found.';
            $editId = null;
        } else {
            $currentProjectImage = showcase_safe_image_source(
                isset($editProject['image_path']) ? (string) $editProject['image_path'] : null
            );
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                $formValues = [
                    'title' => (string) $editProject['title'],
                    'description' => (string) ($editProject['description'] ?? ''),
                    'status' => (string) ($editProject['status'] ?? 'planning'),
                    'category' => (string) ($editProject['category'] ?? 'other'),
                    'technologies' => (string) ($editProject['technologies'] ?? ''),
                    'github_url' => (string) ($editProject['github_url'] ?? ''),
                    'live_demo_url' => (string) ($editProject['live_demo_url'] ?? ''),
                    'is_public' => (int) ($editProject['is_public'] ?? 0) === 1 ? '1' : '0',
                ];
            }
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
        'SELECT
            project.id,
            project.title,
            project.description,
            project.status,
            project.is_public,
            project.created_at,
            (
                SELECT img.image_path
                FROM project_images AS img
                WHERE img.project_id = project.id
                ORDER BY img.id ASC
                LIMIT 1
            ) AS cover_image
        FROM projects AS project
        WHERE project.user_id = :user_id
        ORDER BY project.created_at DESC, project.id DESC'
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

                    <form
                        class="project-form"
                        method="post"
                        action="/bricklab/projects.php"
                        enctype="multipart/form-data"
                    >
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
                            placeholder="e.g. Medieval castle"
                            value="<?= escape_html($formValues['title']) ?>"
                            aria-invalid="<?= in_array('Enter a project title.', $errors, true) ? 'true' : 'false' ?>"
                            required
                        >

                        <label for="project-description">Description</label>
                        <textarea
                            id="project-description"
                            name="description"
                            rows="3"
                            maxlength="5000"
                            placeholder="Add a goal or a few notes about your build."
                        ><?= escape_html($formValues['description']) ?></textarea>

                        <div class="project-image-field">
                            <label for="project-image">Project picture <span>(optional)</span></label>
                            <?php if ($currentProjectImage !== null): ?>
                                <img
                                    class="project-image-preview"
                                    src="<?= escape_html($currentProjectImage) ?>"
                                    alt=""
                                    loading="lazy"
                                >
                            <?php endif; ?>
                            <p id="project-image-help" class="project-image-help">
                                Upload a JPG, PNG, or WebP up to 5 MB. Without one, the default project picture is used.
                            </p>
                            <input
                                id="project-image"
                                name="project_image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                aria-describedby="project-image-help"
                            >
                            <?php if ($currentProjectImage !== null): ?>
                                <label class="project-image-remove" for="remove-project-image">
                                    <input
                                        id="remove-project-image"
                                        name="remove_image"
                                        type="checkbox"
                                        value="1"
                                    >
                                    Remove current picture and use the default
                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="project-showcase-fields">
                            <p class="project-showcase-heading">Showcase details</p>
                            <label for="project-category">Category</label>
                            <select id="project-category" name="category" required>
                                <?php foreach ($categoryOptions as $value => $label): ?>
                                    <option
                                        value="<?= escape_html($value) ?>"
                                        <?= $formValues['category'] === $value ? 'selected' : '' ?>
                                    >
                                        <?= escape_html($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="project-technologies">Technologies <span>(comma-separated)</span></label>
                            <input
                                id="project-technologies"
                                name="technologies"
                                type="text"
                                maxlength="1000"
                                placeholder="PHP, MySQL, JavaScript"
                                value="<?= escape_html($formValues['technologies']) ?>"
                            >

                            <label for="project-github-url">GitHub repository URL <span>(optional)</span></label>
                            <input
                                id="project-github-url"
                                name="github_url"
                                type="url"
                                maxlength="2048"
                                placeholder="https://github.com/you/project"
                                value="<?= escape_html($formValues['github_url']) ?>"
                            >

                            <label for="project-live-demo-url">Live demo URL <span>(optional)</span></label>
                            <input
                                id="project-live-demo-url"
                                name="live_demo_url"
                                type="url"
                                maxlength="2048"
                                placeholder="https://example.com"
                                value="<?= escape_html($formValues['live_demo_url']) ?>"
                            >

                            <label class="project-public-toggle" for="project-is-public">
                                <input
                                    id="project-is-public"
                                    name="is_public"
                                    type="checkbox"
                                    value="1"
                                    <?= $formValues['is_public'] === '1' ? 'checked' : '' ?>
                                >
                                <span>
                                    <strong>Publish in Showcase</strong>
                                    <small>Anyone can view this build and its public project details.</small>
                                </span>
                            </label>
                        </div>

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
                                $projectCover = showcase_safe_image_source(
                                    isset($project['cover_image']) ? (string) $project['cover_image'] : null
                                );
                                ?>
                                <article class="project-card surface">
                                    <?php if ($projectCover !== null): ?>
                                        <img
                                            class="project-card-cover"
                                            src="<?= escape_html($projectCover) ?>"
                                            alt=""
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        <img
                                            class="project-card-cover project-card-cover-fallback"
                                            src="/bricklab/assets/images/bricklab-project-placeholder.webp"
                                            alt=""
                                            loading="lazy"
                                        >
                                    <?php endif; ?>
                                    <div class="project-card-top">
                                        <span class="project-status status-<?= escape_html($statusClass) ?>">
                                            <?= escape_html($statusLabel) ?>
                                        </span>
                                        <?php if ((int) $project['is_public'] === 1): ?>
                                            <span class="project-public-badge">In Showcase</span>
                                        <?php endif; ?>
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
