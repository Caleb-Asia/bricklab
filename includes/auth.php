<?php

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $https = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_name('bricklab_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/bricklab',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Unable to start the application session.');
    }
}

start_app_session();

function current_user_id(): ?int
{
    $userId = $_SESSION['user_id'] ?? null;

    if (is_int($userId) && $userId > 0) {
        return $userId;
    }

    if (is_string($userId) && ctype_digit($userId)) {
        $validatedId = filter_var(
            $userId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $validatedId === false ? null : $validatedId;
    }

    return null;
}

function require_authentication(): void
{
    if (current_user_id() !== null) {
        return;
    }

    header('Location: /bricklab/login.php', true, 302);
    exit;
}

function redirect_authenticated_user(): void
{
    if (current_user_id() !== null) {
        header('Location: /bricklab/projects.php', true, 302);
        exit;
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_token_is_valid(mixed $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function set_flash_message(string $type, string $message): void
{
    if (!in_array($type, ['success', 'error'], true)) {
        throw new InvalidArgumentException('Unsupported flash message type.');
    }

    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consume_flash_message(): ?array
{
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);

    if (
        !is_array($message)
        || !isset($message['type'], $message['message'])
        || !in_array($message['type'], ['success', 'error'], true)
        || !is_string($message['message'])
    ) {
        return null;
    }

    return $message;
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function request_string(mixed $value): string
{
    return is_string($value) ? $value : '';
}

function positive_integer(mixed $value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $validatedId = filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $validatedId === false ? null : $validatedId;
}
