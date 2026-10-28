<?php

require_once __DIR__ . '/includes/auth.php';
redirect_authenticated_user();
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Create account';
$errorMessage = '';
$username = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim(request_string($_POST['username'] ?? null));
    $email = strtolower(trim(request_string($_POST['email'] ?? null)));
    $password = request_string($_POST['password'] ?? null);
    $passwordConfirmation = request_string($_POST['password_confirmation'] ?? null);

    if (!csrf_token_is_valid($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'Your session expired. Refresh the page and try again.';
    } elseif (!preg_match('/\A[A-Za-z0-9_-]{3,50}\z/', $username)) {
        $errorMessage = 'Username must be 3–50 characters using letters, numbers, underscores, or hyphens.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errorMessage = 'Enter a valid email address no longer than 100 characters.';
    } elseif (strlen($password) < 12) {
        $errorMessage = 'Choose a password with at least 12 characters.';
    } elseif (!hash_equals($password, $passwordConfirmation)) {
        $errorMessage = 'The password confirmation does not match.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            error_log('BrickLab could not hash a new account password.');
            http_response_code(500);
            $errorMessage = 'Account creation is temporarily unavailable. Please try again later.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'INSERT INTO users (username, email, password_hash)
                    VALUES (:username, :email, :password_hash)'
                );
                $statement->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                ]);

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                set_flash_message('success', 'Your account is ready. Create your first build.');
                header('Location: /bricklab/projects.php', true, 303);
                exit;
            } catch (PDOException $exception) {
                $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

                if ($sqlState === '23000') {
                    $errorMessage = 'That username or email address is already in use.';
                } else {
                    error_log('BrickLab account creation failed: ' . $exception->getMessage());
                    http_response_code(500);
                    $errorMessage = 'Account creation is temporarily unavailable. Please try again later.';
                }
            }
        }
    }
}

$authHeadline = 'Your next idea starts here.';
$authScene = 'blueprint';
require __DIR__ . '/includes/auth-header.php';
?>
            <p class="section-kicker">START BUILDING</p>
            <h1 id="auth-title">Create your account</h1>
            <p class="auth-description">Make a BrickLab account to save and track your builds.</p>

            <?php if ($errorMessage !== ''): ?>
                <p class="form-notice form-notice-error" role="alert">
                    <?= escape_html($errorMessage) ?>
                </p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="/bricklab/register.php">
                <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">

                <label for="username">Username</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    autocomplete="username"
                    minlength="3"
                    maxlength="50"
                    pattern="[A-Za-z0-9_\x2D]{3,50}"
                    value="<?= escape_html($username) ?>"
                    required
                >

                <label for="email">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    maxlength="100"
                    value="<?= escape_html($email) ?>"
                    required
                >

                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    minlength="12"
                    required
                >

                <label for="password_confirmation">Confirm password</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    minlength="12"
                    required
                >

                <button class="btn btn-primary" type="submit">Create account</button>
            </form>

            <p class="auth-switch">
                Already have an account?
                <a href="/bricklab/login.php">Sign in</a>
            </p>
<?php require __DIR__ . '/includes/auth-footer.php'; ?>
