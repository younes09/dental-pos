<?php
require_once 'config/db.php';

header('Content-Type: application/json');

// All logged-in users can manage their own profile
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please login.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) throw new Exception('User not found');
            
            echo json_encode(['success' => true, 'data' => $user]);
            break;

        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('Invalid data provided');

            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $new_password = $data['new_password'] ?? null;
            $current_password = $data['current_password'] ?? null;

            if (empty($name) || empty($email)) {
                throw new Exception('Name and Email are required');
            }

            // If updating password, verify current one
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    throw new Exception('Current password is required to set a new one');
                }

                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception('Incorrect current password');
                }

                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $hashed_password, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $user_id]);
            }

            // Update session name if changed
            $_SESSION['user_name'] = $name;

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Profile API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
