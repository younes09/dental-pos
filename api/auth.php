<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'Active') {
                    echo json_encode(['error' => 'Your account is inactive. Please contact the administrator.']);
                    exit;
                }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                echo json_encode(['success' => true]);
            } else {
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
