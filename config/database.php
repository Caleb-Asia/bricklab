
<?php

$host = '127.0.0.1';
$port = '3306';
$dbname = 'bricklab';
$username = 'root';
$password = 'H$1gh#7iD@3'; 

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('BrickLab database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to connect to the database. Check the local configuration.');
}
