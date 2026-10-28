<?php

require_once __DIR__ . '/includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the sign-out form to end your session.');
}

if (!csrf_token_is_valid($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    exit('Your session expired. Refresh the page and try again.');
}

$_SESSION = [];
$cookie = session_get_cookie_params();

if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
header('Location: /bricklab/login.php?logged_out=1', true, 303);
exit;
