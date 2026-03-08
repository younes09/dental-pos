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
            session_destroy();
            header('Location: ../login.php');
            exit;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
// Removed closing tag
