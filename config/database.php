<?php

$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$getEnvironmentValue = static function (string $name): ?string {
    $value = getenv($name);

    if ($value !== false) {
        return $value;
    }

    if (array_key_exists($name, $_ENV)) {
        return (string) $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER)) {
        return (string) $_SERVER[$name];
    }

    return null;
};

if (is_file($envFile)) {
    if (!is_readable($envFile)) {
        error_log('Unable to read the local .env file.');
        http_response_code(500);
        exit('Database configuration is unavailable. Check the local .env file.');
    }

    $envLines = file($envFile, FILE_IGNORE_NEW_LINES);

    if ($envLines === false) {
        error_log('Unable to read the local .env file.');
        http_response_code(500);
        exit('Database configuration is unavailable. Check the local .env file.');
    }

    foreach ($envLines as $lineNumber => $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separator = strpos($line, '=');

        if ($separator === false) {
            error_log('Invalid local .env entry at line ' . ($lineNumber + 1) . '.');
            http_response_code(500);
            exit('Database configuration is invalid. Check the local .env file.');
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $name)) {
            error_log('Invalid local .env key at line ' . ($lineNumber + 1) . '.');
            http_response_code(500);
            exit('Database configuration is invalid. Check the local .env file.');
        }

        if (strlen($value) >= 2) {
            $firstCharacter = $value[0];
            $lastCharacter = substr($value, -1);

            if (
                ($firstCharacter === '"' && $lastCharacter === '"')
                || ($firstCharacter === "'" && $lastCharacter === "'")
            ) {
                $value = substr($value, 1, -1);
            }
        }

        if (
            $getEnvironmentValue($name) === null
            && putenv($name . '=' . $value)
        ) {
            $_ENV[$name] = $value;
        }
    }
}

$requiredSettings = [
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
];
$settings = [];
$missingSettings = [];

foreach ($requiredSettings as $setting) {
    $value = $getEnvironmentValue($setting);

    if ($value === null || ($setting !== 'DB_PASSWORD' && trim($value) === '')) {
        $missingSettings[] = $setting;
        continue;
    }

    $settings[$setting] = $value;
}

if ($missingSettings !== []) {
    error_log(
        'Database configuration is missing environment variables: '
        . implode(', ', $missingSettings)
    );
    http_response_code(500);
    exit('Database configuration is missing. Set the required DB_* values in the local .env file.');
}

$dsn = 'mysql:host=' . $settings['DB_HOST']
    . ';port=' . $settings['DB_PORT']
    . ';dbname=' . $settings['DB_DATABASE']
    . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, $settings['DB_USERNAME'], $settings['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to connect to the database. Check the server configuration.');
}
