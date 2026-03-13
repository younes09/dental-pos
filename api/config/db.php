<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_file = basename($_SERVER['PHP_SELF']);
if ($current_file !== 'auth.php' && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized access. Please log in.']));
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'dental_pos');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed. Please contact the administrator.']));
}
// Removed closing tag to prevent accidental whitespace/output before headers sent
