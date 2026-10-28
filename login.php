<?php

require_once __DIR__ . '/includes/auth.php';
redirect_authenticated_user();
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Sign in';
$errorMessage = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = strtolower(trim(request_string($_POST['email'] ?? null)));
    $password = request_string($_POST['password'] ?? null);

    if (!csrf_token_is_valid($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'Your session expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errorMessage = 'Enter a valid email address and password.';
    } else {
        try {
            $statement = $pdo->prepare(
                'SELECT id, username, password_hash
                FROM users
                WHERE email = :email
                LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
        } catch (PDOException $exception) {
            error_log('BrickLab login query failed: ' . $exception->getMessage());
            http_response_code(500);
            $errorMessage = 'Sign in is temporarily unavailable. Please try again later.';
            $user = false;
        }

        if (
            $errorMessage === ''
            && is_array($user)
            && password_verify($password, (string) $user['password_hash'])
        ) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = (string) $user['username'];
            set_flash_message('success', 'Welcome back.');
            header('Location: /bricklab/projects.php', true, 303);
            exit;
        }

        if ($errorMessage === '') {
            $errorMessage = 'Email or password is incorrect.';
        }
    }
}

$authHeadline = 'Welcome back to your workshop.';
require __DIR__ . '/includes/auth-header.php';
?>
            <p class="section-kicker">WELCOME BACK</p>
            <h1 id="auth-title">Sign in to BrickLab</h1>
            <p class="auth-description">Pick up where your next build begins.</p>

            <?php if ($errorMessage !== ''): ?>
                <p class="form-notice form-notice-error" role="alert">
                    <?= escape_html($errorMessage) ?>
                </p>
            <?php endif; ?>

            <?php if (isset($_GET['logged_out'])): ?>
                <p class="form-notice form-notice-success" role="status">
                    You have been signed out.
                </p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="/bricklab/login.php">
                <input type="hidden" name="_csrf" value="<?= escape_html(csrf_token()) ?>">

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
                    autocomplete="current-password"
                    required
                >

                <button class="btn btn-primary" type="submit">Sign in</button>
            </form>

            <p class="auth-switch">
                New to BrickLab?
                <a href="/bricklab/register.php">Create an account</a>
            </p>
<?php require __DIR__ . '/includes/auth-footer.php'; ?>
