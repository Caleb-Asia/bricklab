<?php

declare(strict_types=1);

/**
 * Manual regression check for cross-user project deletion authorization.
 *
 * Configure these environment variables before running:
 *   BRICKLAB_TEST_USER_A_EMAIL
 *   BRICKLAB_TEST_USER_A_PASSWORD
 *   BRICKLAB_TEST_USER_B_EMAIL
 *   BRICKLAB_TEST_USER_B_PASSWORD
 * Optional:
 *   BRICKLAB_BASE_URL (defaults to http://localhost/bricklab)
 *
 * The script creates a private test Build and image under user A. It leaves
 * them in place after the check so the result can be inspected manually.
 */

require_once dirname(__DIR__, 2) . '/config/database.php';

function required_environment_value(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Set the required environment variable ' . $name . '.');
    }

    return $value;
}

function request_url(string $baseUrl, string $path, string $cookieJar, ?array $postFields = null): array
{
    $curl = curl_init($baseUrl . $path);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize cURL.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($postFields !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
    }

    $response = curl_exec($curl);
    if ($response === false) {
        $message = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('HTTP request failed: ' . $message);
    }

    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $locationMatch);

    return [
        'status' => $status,
        'headers' => $headers,
        'body' => $body,
        'location' => isset($locationMatch[1]) ? trim($locationMatch[1]) : null,
    ];
}

function csrf_token_from_html(string $html): string
{
    $document = new DOMDocument();
    $previousSetting = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previousSetting);

    $xpath = new DOMXPath($document);
    $inputs = $xpath->query('//input[@name="_csrf"]');
    if ($inputs === false || $inputs->length === 0) {
        throw new RuntimeException('Could not find the CSRF token in the page.');
    }

    $token = $inputs->item(0)?->attributes?->getNamedItem('value')?->nodeValue;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('The page contained an empty CSRF token.');
    }

    return $token;
}

function sign_in(string $baseUrl, string $cookieJar, string $email, string $password): void
{
    $loginPage = request_url($baseUrl, '/login.php', $cookieJar);
    if ($loginPage['status'] !== 200) {
        throw new RuntimeException('Could not load the login page (HTTP ' . $loginPage['status'] . ').');
    }
    $token = csrf_token_from_html($loginPage['body']);

    $loginResponse = request_url($baseUrl, '/login.php', $cookieJar, [
        '_csrf' => $token,
        'email' => $email,
        'password' => $password,
    ]);
    if (
        $loginResponse['status'] !== 303
        || $loginResponse['location'] === null
        || !str_contains($loginResponse['location'], '/projects.php')
    ) {
        throw new RuntimeException('Login failed for ' . $email . ' (HTTP ' . $loginResponse['status'] . ').');
    }
}

function report_result(bool $passed, string $message): void
{
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $message . PHP_EOL;
}

$cookieJars = [];
$temporaryImage = null;
$createdProjectId = null;
$exitCode = 1;

try {
    if (!extension_loaded('curl') || !class_exists(DOMDocument::class)) {
        throw new RuntimeException('This script requires PHP extensions curl and dom.');
    }

    $userAEmail = required_environment_value('BRICKLAB_TEST_USER_A_EMAIL');
    $userAPassword = required_environment_value('BRICKLAB_TEST_USER_A_PASSWORD');
    $userBEmail = required_environment_value('BRICKLAB_TEST_USER_B_EMAIL');
    $userBPassword = required_environment_value('BRICKLAB_TEST_USER_B_PASSWORD');
    if (strcasecmp($userAEmail, $userBEmail) === 0) {
        throw new RuntimeException('Test users A and B must be distinct accounts.');
    }

    $baseUrl = rtrim(getenv('BRICKLAB_BASE_URL') ?: 'http://localhost/bricklab', '/');
    $userStatement = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $userStatement->execute(['email' => strtolower(trim($userAEmail))]);
    $userAId = $userStatement->fetchColumn();
    $userStatement->execute(['email' => strtolower(trim($userBEmail))]);
    $userBId = $userStatement->fetchColumn();
    if ($userAId === false || $userBId === false || (int) $userAId === (int) $userBId) {
        throw new RuntimeException('Both distinct test accounts must exist in the configured database.');
    }

    $temporaryImage = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . 'bricklab-cross-user-delete-' . bin2hex(random_bytes(8)) . '.png';
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pJcAAAAASUVORK5CYII=',
        true
    );
    if ($png === false || file_put_contents($temporaryImage, $png) === false) {
        throw new RuntimeException('Could not prepare the temporary PNG upload.');
    }

    $cookieJars = [
        tempnam(sys_get_temp_dir(), 'bricklab-user-a-'),
        tempnam(sys_get_temp_dir(), 'bricklab-user-b-'),
    ];
    if (!is_string($cookieJars[0]) || !is_string($cookieJars[1])) {
        throw new RuntimeException('Could not create temporary session cookie jars.');
    }

    sign_in($baseUrl, $cookieJars[0], $userAEmail, $userAPassword);
    $projectsPageA = request_url($baseUrl, '/projects.php', $cookieJars[0]);
    if ($projectsPageA['status'] !== 200) {
        throw new RuntimeException('User A could not load the Workshop project form.');
    }
    $title = 'Manual cross-user deletion check ' . bin2hex(random_bytes(5));
    $createResponse = request_url($baseUrl, '/projects.php', $cookieJars[0], [
        '_csrf' => csrf_token_from_html($projectsPageA['body']),
        'action' => 'create',
        'title' => $title,
        'description' => 'Temporary project created by the manual authorization check.',
        'status' => 'planning',
        'category' => 'web',
        'technologies' => 'PHP',
        'github_url' => '',
        'live_demo_url' => '',
        'is_public' => '0',
        'project_image' => new CURLFile($temporaryImage, 'image/png', 'test.png'),
    ]);
    if ($createResponse['status'] !== 303) {
        throw new RuntimeException('User A project creation failed (HTTP ' . $createResponse['status'] . ').');
    }

    $projectStatement = $pdo->prepare(
        'SELECT id FROM projects WHERE user_id = :user_id AND title = :title ORDER BY id DESC LIMIT 1'
    );
    $projectStatement->execute(['user_id' => (int) $userAId, 'title' => $title]);
    $createdProjectId = $projectStatement->fetchColumn();
    if ($createdProjectId === false) {
        throw new RuntimeException('Could not find user A’s newly created project in the database.');
    }
    $createdProjectId = (int) $createdProjectId;

    $imageStatement = $pdo->prepare(
        'SELECT image_path FROM project_images WHERE project_id = :project_id ORDER BY id ASC LIMIT 1'
    );
    $imageStatement->execute(['project_id' => $createdProjectId]);
    $imagePath = $imageStatement->fetchColumn();
    if (!is_string($imagePath) || !str_starts_with($imagePath, '/bricklab/uploads/projects/')) {
        throw new RuntimeException('The test project did not receive an expected uploaded image path.');
    }
    $imageFile = dirname(__DIR__, 2) . '/uploads/projects/' . basename($imagePath);
    if (!is_file($imageFile)) {
        throw new RuntimeException('The test project image file is missing before the attempted delete.');
    }

    sign_in($baseUrl, $cookieJars[1], $userBEmail, $userBPassword);
    $projectsPageB = request_url($baseUrl, '/projects.php', $cookieJars[1]);
    if ($projectsPageB['status'] !== 200) {
        throw new RuntimeException('User B could not load the Workshop page for a CSRF token.');
    }
    $deleteResponse = request_url($baseUrl, '/projects.php', $cookieJars[1], [
        '_csrf' => csrf_token_from_html($projectsPageB['body']),
        'action' => 'delete',
        'project_id' => (string) $createdProjectId,
    ]);

    $isRedirect = $deleteResponse['status'] >= 300 && $deleteResponse['status'] < 400;
    $hasNotFoundError = str_contains($deleteResponse['body'], 'That project could not be found.');
    $responseRejected = !$isRedirect && (
        $deleteResponse['status'] === 404
        || ($deleteResponse['status'] >= 200 && $deleteResponse['status'] < 300 && $hasNotFoundError)
    );

    $projectStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM projects WHERE id = :id AND user_id = :user_id'
    );
    $projectStatement->execute(['id' => $createdProjectId, 'user_id' => (int) $userAId]);
    $projectStillExists = (int) $projectStatement->fetchColumn() === 1;
    $imageStillExists = is_file($imageFile);

    report_result(
        $responseRejected,
        'User B’s cross-user delete was rejected (HTTP ' . $deleteResponse['status'] . ').'
    );
    report_result($projectStillExists, 'User A’s project record still exists.');
    report_result($imageStillExists, 'User A’s project image file still exists.');

    if (!$responseRejected || !$projectStillExists || !$imageStillExists) {
        throw new RuntimeException('One or more cross-user deletion assertions failed.');
    }

    echo 'Test project ID: ' . $createdProjectId . PHP_EOL;
    echo 'The test Build and image were left in user A’s account for inspection.' . PHP_EOL;
    $exitCode = 0;
} catch (Throwable $exception) {
    report_result(false, $exception->getMessage());
    if ($createdProjectId !== null) {
        echo 'A test project may have been created; inspect user A’s Workshop for the manual test title.' . PHP_EOL;
    }
} finally {
    if (is_string($temporaryImage) && is_file($temporaryImage)) {
        @unlink($temporaryImage);
    }
    foreach ($cookieJars as $cookieJar) {
        if (is_string($cookieJar) && is_file($cookieJar)) {
            @unlink($cookieJar);
        }
    }
}

exit($exitCode);
