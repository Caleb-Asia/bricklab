<?php

// Backward compatibility redirect to The Grid
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$destination = '/bricklab/grid.php' . ($queryString !== '' ? '?' . $queryString : '');
header('Location: ' . $destination, true, 301);
exit;
