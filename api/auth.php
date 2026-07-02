<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            // Rate limiting check
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $key = md5($ip);
            $filePath = dirname(__DIR__) . '/database/dpos_rate_limit_' . $key . '.json';
            $now = time();
            $window = 60;
            $maxAttempts = 5;

            $data = ['attempts' => []];
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            // Filter out old attempts
            $data['attempts'] = array_filter($data['attempts'], function($t) use ($now, $window) {
                return ($now - $t) < $window;
            });

            if (count($data['attempts']) >= $maxAttempts) {
                $oldest = min($data['attempts']);
                $timeLeft = $window - ($now - $oldest);
                http_response_code(429);
                echo json_encode(['error' => "Too many login attempts. Please try again after " . max(1, $timeLeft) . " seconds."]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'Active') {
                    echo json_encode(['error' => 'Your account is inactive. Please contact the administrator.']);
                    exit;
                }
                // Clear attempts on successful login
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                echo json_encode(['success' => true]);
            } else {
                $data['attempts'][] = $now;
                file_put_contents($filePath, json_encode($data));
                echo json_encode(['error' => 'Invalid email or password']);
            }
            break;

        case 'check':
            if (isset($_SESSION['user_id'])) {
                echo json_encode([
                    'logged_in' => true,
                    'user' => [
                        'name' => $_SESSION['user_name'],
                        'role' => $_SESSION['user_role']
                    ]
                ]);
            } else {
                echo json_encode(['logged_in' => false]);
            }
            break;

        case 'logout':
            // Fix #13: Require POST to prevent CSRF logout via GET image tags
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Logout requires POST method.']);
                exit;
            }
            session_destroy();
            echo json_encode(['success' => true, 'redirect' => '../login.php']);
            exit;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Auth error: " . $e->getMessage());
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
}
// Removed closing tag
