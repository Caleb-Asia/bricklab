<?php

require_once __DIR__ . '/includes/auth.php';
require_authentication();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/showcase.php';

$userId = current_user_id();
if ($userId === null) {
    throw new LogicException('An authenticated user ID is required.');
}

$pageTitle = 'Public profile settings';
$currentPage = 'profile';
$errors = [];
$avatarUploadError = null;
$saved = request_string($_GET['saved'] ?? '') === '1';
$currentAvatarSource = null;
$newAvatarAbsolutePath = null;
$oldAvatarUrl = '';
$values = [
    'display_name' => '',
    'professional_role' => '',
    'location' => '',
    'bio' => '',
    'avatar_url' => '',
    'github_url' => '',
    'linkedin_url' => '',
    'portfolio_url' => '',
    'skills' => '',
];

try {
    $statement = $pdo->prepare(
        'SELECT
            username,
            display_name,
            professional_role,
            location,
            bio,
            avatar_url,
            github_url,
            linkedin_url,
            portfolio_url,
            skills
        FROM users
        WHERE id = :user_id
        LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();
    if ($user === false) {
        throw new LogicException('The authenticated account could not be found.');
    }
    $currentAvatarSource = showcase_safe_image_source((string) ($user['avatar_url'] ?? ''));
    $oldAvatarUrl = (string) ($user['avatar_url'] ?? '');

    foreach ($values as $field => $value) {
        $values[$field] = (string) ($user[$field] ?? '');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!csrf_token_is_valid($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            $errors[] = 'Your session expired. Refresh the page and try again.';
        } else {
            foreach ($values as $field => $value) {
                $values[$field] = trim(request_string($_POST[$field] ?? null));
            }

            $avatarFileSelected = false;
            $avatarUploadInfo = null;
            if (array_key_exists('avatar_file', $_FILES)) {
                $avatarFile = $_FILES['avatar_file'];
                if (!is_array($avatarFile) || !isset($avatarFile['error']) || !is_int($avatarFile['error'])) {
                    $avatarFileSelected = true;
                    $avatarUploadError = 'The selected profile image could not be processed. Please choose it again.';
                } elseif ($avatarFile['error'] !== UPLOAD_ERR_NO_FILE) {
                    $avatarFileSelected = true;
                    if ($avatarFile['error'] !== UPLOAD_ERR_OK) {
                        $avatarUploadError = $avatarFile['error'] === UPLOAD_ERR_INI_SIZE
                            || $avatarFile['error'] === UPLOAD_ERR_FORM_SIZE
                            ? 'Profile images must be 5 MB or smaller.'
                            : 'The profile image could not be uploaded. Please try again.';
                    } elseif (!isset($avatarFile['tmp_name']) || !is_string($avatarFile['tmp_name'])
                        || !is_uploaded_file($avatarFile['tmp_name'])) {
                        $avatarUploadError = 'The selected profile image was not uploaded securely.';
                    } else {
                        $fileSize = filesize($avatarFile['tmp_name']);
                        if ($fileSize === false || $fileSize > 5 * 1024 * 1024) {
                            $avatarUploadError = 'Profile images must be 5 MB or smaller.';
                        } else {
                            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                            $mimeType = $fileInfo->file($avatarFile['tmp_name']);
                            $image = getimagesize($avatarFile['tmp_name']);
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
                                $avatarUploadError = 'Choose a valid JPG, JPEG, PNG or WebP image.';
                            } else {
                                $avatarUploadInfo = [
                                    'temporary_path' => $avatarFile['tmp_name'],
                                    'extension' => $allowedImages[$mimeType]['extension'],
                                ];
                            }
                        }
                    }
                }
            }

            foreach ([
                'display_name' => 80,
                'professional_role' => 100,
                'location' => 100,
                'bio' => 2000,
            ] as $field => $maximumLength) {
                $fieldLength = showcase_text_length($values[$field]);
                if ($fieldLength === null || $fieldLength > $maximumLength) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field))
                        . ' must be ' . $maximumLength . ' characters or fewer.';
                }
            }

            $skillsLength = showcase_text_length($values['skills']);
            if ($skillsLength === null || $skillsLength > 1000) {
                $errors[] = 'Skills must be 1,000 characters or fewer.';
            } elseif (
                $values['skills'] !== ''
                && !preg_match(
                    '/\A[A-Za-z0-9][A-Za-z0-9 .+#-]*(?:\s*,\s*[A-Za-z0-9][A-Za-z0-9 .+#-]*)*\z/',
                    $values['skills']
                )
            ) {
                $errors[] = 'Enter skills as comma-separated tags using letters, numbers, spaces, . + # or -.';
            } else {
                $values['skills'] = showcase_normalize_tags($values['skills']);
            }
            $urlFields = ['github_url', 'linkedin_url', 'portfolio_url'];
            if (!$avatarFileSelected) {
                $urlFields[] = 'avatar_url';
            }
            foreach ($urlFields as $field) {
                $isUnchangedSafeAvatarPath = $field === 'avatar_url'
                    && $values[$field] === $oldAvatarUrl
                    && showcase_safe_image_source($values[$field]) !== null;
                if (!showcase_url_is_valid($values[$field]) && !$isUnchangedSafeAvatarPath) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a valid HTTPS URL.';
                }
            }

            if ($errors === [] && $avatarUploadError === null) {
                if ($avatarUploadInfo !== null) {
                    $avatarDirectory = __DIR__ . '/uploads/avatars';
                    if (
                        !is_dir($avatarDirectory)
                        && !mkdir($avatarDirectory, 0755, true)
                        && !is_dir($avatarDirectory)
                    ) {
                        error_log('BrickLab could not create the profile avatar directory.');
                        $errors[] = 'The profile image could not be saved. Please try again later.';
                    } else {
                        try {
                            $fileName = bin2hex(random_bytes(16)) . '.' . $avatarUploadInfo['extension'];
                        } catch (Random\RandomException $exception) {
                            error_log('BrickLab could not generate a profile avatar filename: ' . $exception->getMessage());
                            $errors[] = 'The profile image could not be saved. Please try again later.';
                            $fileName = '';
                        }

                        if ($errors === []) {
                            $newAvatarAbsolutePath = $avatarDirectory . '/' . $fileName;
                            if (!move_uploaded_file($avatarUploadInfo['temporary_path'], $newAvatarAbsolutePath)) {
                                error_log('BrickLab could not move an uploaded profile avatar into storage.');
                                $newAvatarAbsolutePath = null;
                                $errors[] = 'The profile image could not be saved. Please try again later.';
                            } else {
                                $values['avatar_url'] = '/bricklab/uploads/avatars/' . $fileName;
                            }
                        }
                    }
                }

                if ($errors === []) {
                    $statement = $pdo->prepare(
                        'UPDATE users
                        SET
                            display_name = :display_name,
                            professional_role = :professional_role,
                            location = :location,
                            bio = :bio,
                            avatar_url = :avatar_url,
                            github_url = :github_url,
                            linkedin_url = :linkedin_url,
                            portfolio_url = :portfolio_url,
                            skills = :skills
                        WHERE id = :user_id'
                    );
                    $statement->execute([
                        'display_name' => $values['display_name'] === '' ? null : $values['display_name'],
                        'professional_role' => $values['professional_role'] === '' ? null : $values['professional_role'],
                        'location' => $values['location'] === '' ? null : $values['location'],
                        'bio' => $values['bio'] === '' ? null : $values['bio'],
                        'avatar_url' => $values['avatar_url'] === '' ? null : $values['avatar_url'],
                        'github_url' => $values['github_url'] === '' ? null : $values['github_url'],
                        'linkedin_url' => $values['linkedin_url'] === '' ? null : $values['linkedin_url'],
                        'portfolio_url' => $values['portfolio_url'] === '' ? null : $values['portfolio_url'],
                        'skills' => $values['skills'] === '' ? null : $values['skills'],
                        'user_id' => $userId,
                    ]);

                    if (
                        $oldAvatarUrl !== $values['avatar_url']
                        && preg_match(
                            '#\A/bricklab/uploads/avatars/[a-f0-9]{32}\.(?:jpg|png|webp)\z#',
                            $oldAvatarUrl
                        )
                    ) {
                        $avatarDirectory = realpath(__DIR__ . '/uploads/avatars');
                        $oldAvatarAbsolutePath = realpath(__DIR__ . substr($oldAvatarUrl, strlen('/bricklab/')));
                        if (
                            $avatarDirectory !== false
                            && $oldAvatarAbsolutePath !== false
                            && dirname($oldAvatarAbsolutePath) === $avatarDirectory
                            && is_file($oldAvatarAbsolutePath)
                            && !unlink($oldAvatarAbsolutePath)
                        ) {
                            error_log('BrickLab could not remove a replaced profile avatar.');
                        }
                    }

                    header('Location: /bricklab/profile-settings.php?saved=1', true, 303);
                    exit;
                }
            }
        }
    }
} catch (PDOException $exception) {
    error_log('BrickLab profile settings could not be loaded or saved: ' . $exception->getMessage());
    if ($newAvatarAbsolutePath !== null && is_file($newAvatarAbsolutePath) && !unlink($newAvatarAbsolutePath)) {
        error_log('BrickLab could not remove an unsaved profile avatar.');
    }
    if (isset($_POST['avatar_url'])) {
        $values['avatar_url'] = trim(request_string($_POST['avatar_url']));
    }
    http_response_code(500);
    $errors[] = 'Your profile could not be saved right now. Please try again later.';
}

require __DIR__ . '/includes/header.php';
?>
            <header class="projects-heading">
                <div>
                    <p class="section-kicker">YOUR PUBLIC IDENTITY</p>
                    <h1>Profile settings</h1>
                    <p class="section-subtitle">Tell the BrickLab community a little about yourself.</p>
                </div>
                <form method="post" action="/bricklab/logout.php">
                    <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                    <button class="btn btn-secondary" type="submit">Sign out</button>
                </form>
            </header>

            <?php if ($saved): ?>
                <p class="form-notice form-notice-success" role="status">Your public profile has been updated.</p>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <div class="form-notice form-notice-error" role="alert">
                    <p>Please review the following:</p>
                    <ul><?php foreach ($errors as $error): ?><li><?= escape_html($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <section class="profile-settings-panel surface" aria-labelledby="profile-settings-title">
                <h2 id="profile-settings-title">Public profile</h2>
                <p>This information is visible to anyone who can view your published projects.</p>
                <form class="profile-settings-form" method="post" action="/bricklab/profile-settings.php" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">
                    <label>
                        Display name
                        <input name="display_name" maxlength="80" value="<?= escape_html($values['display_name']) ?>" autocomplete="name">
                    </label>
                    <label>
                        Professional role
                        <input name="professional_role" maxlength="100" value="<?= escape_html($values['professional_role']) ?>" placeholder="e.g. Full-stack developer">
                    </label>
                    <label>
                        Location
                        <input name="location" maxlength="100" value="<?= escape_html($values['location']) ?>" autocomplete="address-level2">
                    </label>
                    <label class="profile-settings-wide">
                        Short bio
                        <textarea name="bio" maxlength="2000" rows="5" placeholder="What do you like to build?"><?= escape_html($values['bio']) ?></textarea>
                    </label>
                    <label class="profile-settings-wide">
                        Avatar URL
                        <input type="text" inputmode="url" name="avatar_url" maxlength="2048" value="<?= escape_html($values['avatar_url']) ?>" placeholder="https://…">
                    </label>
                    <div class="profile-avatar-upload profile-settings-wide">
                        <div class="profile-avatar-preview" id="avatar-preview">
                            <?php if ($currentAvatarSource !== null): ?>
                                <img src="<?= escape_html($currentAvatarSource) ?>" alt="Current profile picture">
                            <?php else: ?>
                                <span>No photo</span>
                            <?php endif; ?>
                        </div>
                        <label>
                            Upload profile picture
                            <input
                                id="avatar-file"
                                type="file"
                                name="avatar_file"
                                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                aria-describedby="avatar-upload-hint avatar-upload-status"
                            >
                            <span class="profile-settings-hint" id="avatar-upload-hint">JPG, JPEG, PNG or WebP. Maximum file size: 5 MB. An uploaded image takes priority over the Avatar URL.</span>
                            <span class="profile-settings-hint" id="avatar-upload-status" role="status" aria-live="polite"><?= escape_html($avatarUploadError ?? '') ?></span>
                        </label>
                    </div>
                    <label>
                        GitHub URL
                        <input type="url" name="github_url" maxlength="2048" value="<?= escape_html($values['github_url']) ?>" placeholder="https://github.com/…">
                    </label>
                    <label>
                        LinkedIn URL
                        <input type="url" name="linkedin_url" maxlength="2048" value="<?= escape_html($values['linkedin_url']) ?>" placeholder="https://linkedin.com/in/…">
                    </label>
                    <label class="profile-settings-wide">
                        Portfolio URL
                        <input type="url" name="portfolio_url" maxlength="2048" value="<?= escape_html($values['portfolio_url']) ?>" placeholder="https://…">
                    </label>
                    <label class="profile-settings-wide">
                        Skills and technologies
                        <input name="skills" maxlength="1000" value="<?= escape_html($values['skills']) ?>" placeholder="PHP, MySQL, JavaScript">
                        <span class="profile-settings-hint">Separate tags with commas. Only letters, numbers, spaces and . + # - are accepted.</span>
                    </label>
                    <div class="profile-settings-actions">
                        <button class="btn btn-primary" type="submit">Save profile</button>
                        <a class="btn btn-secondary" href="/bricklab/showcase.php">View Showcase</a>
                    </div>
                </form>
            </section>
            <script>
                const avatarFileInput = document.getElementById('avatar-file');
                const avatarPreview = document.getElementById('avatar-preview');
                const avatarUploadStatus = document.getElementById('avatar-upload-status');
                const savedAvatarSource = <?= json_encode(
                    $currentAvatarSource,
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                ) ?>;
                let avatarPreviewUrl = null;
                let avatarStatusTimeout = null;

                function showAvatarUploadError(message) {
                    avatarUploadStatus.textContent = message;
                    if (avatarStatusTimeout !== null) {
                        window.clearTimeout(avatarStatusTimeout);
                    }
                    avatarStatusTimeout = window.setTimeout(() => {
                        avatarUploadStatus.textContent = '';
                        avatarStatusTimeout = null;
                    }, 5000);
                }

                if (avatarUploadStatus.textContent.trim() !== '') {
                    showAvatarUploadError(avatarUploadStatus.textContent.trim());
                }

                function restoreSavedAvatarPreview() {
                    avatarPreview.replaceChildren();
                    if (savedAvatarSource) {
                        const savedImage = document.createElement('img');
                        savedImage.src = savedAvatarSource;
                        savedImage.alt = 'Current profile picture';
                        avatarPreview.append(savedImage);
                    } else {
                        const noPhoto = document.createElement('span');
                        noPhoto.textContent = 'No photo';
                        avatarPreview.append(noPhoto);
                    }
                }

                avatarFileInput.addEventListener('change', () => {
                    const file = avatarFileInput.files[0];
                    if (avatarPreviewUrl !== null) {
                        URL.revokeObjectURL(avatarPreviewUrl);
                        avatarPreviewUrl = null;
                    }
                    avatarUploadStatus.textContent = '';
                    if (avatarStatusTimeout !== null) {
                        window.clearTimeout(avatarStatusTimeout);
                        avatarStatusTimeout = null;
                    }
                    if (!file) {
                        restoreSavedAvatarPreview();
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        showAvatarUploadError('This image is larger than 5 MB. Choose a smaller file.');
                        avatarFileInput.value = '';
                        restoreSavedAvatarPreview();
                        return;
                    }
                    const extension = file.name.split('.').pop().toLowerCase();
                    if (
                        !['jpg', 'jpeg', 'png', 'webp'].includes(extension)
                        || (file.type !== '' && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))
                    ) {
                        showAvatarUploadError('Choose a JPG, JPEG, PNG or WebP image.');
                        avatarFileInput.value = '';
                        restoreSavedAvatarPreview();
                        return;
                    }
                    avatarPreviewUrl = URL.createObjectURL(file);
                    avatarPreview.replaceChildren();
                    const previewImage = document.createElement('img');
                    previewImage.src = avatarPreviewUrl;
                    previewImage.alt = 'Selected profile picture preview';
                    avatarPreview.append(previewImage);
                    avatarUploadStatus.textContent = 'Previewing selected image. Save your profile to apply it.';
                });
            </script>
<?php require __DIR__ . '/includes/footer.php'; ?>
