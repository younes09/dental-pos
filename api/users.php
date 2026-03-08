<?php
require_once 'config/db.php';

header('Content-Type: application/json');

// Only Admins can manage users
if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access. Admins only.']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT id, name, email, phone, role, status, created_at FROM users ORDER BY name ASC");
            $users = $stmt->fetchAll();
            echo json_encode(['data' => $users]);
            break;

        case 'save':
            $data = $_POST;
            $id = $data['id'] ?? null;
            $name = $data['name'];
            $email = $data['email'];
            $phone = $data['phone'] ?? null;
            $role = $data['role'];
            $status = $data['status'] ?? 'Active';
            $password = $data['password'] ?? null;

            if ($id) {
                // Update
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $role, $status, $hashed_password, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $role, $status, $id]);
                }
                echo json_encode(['success' => 'User updated successfully']);
            } else {
                // Create
                if (empty($password)) throw new Exception('Password is required for new users');
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, role, status, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $role, $status, $hashed_password]);
                echo json_encode(['success' => 'User created successfully']);
            }
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('User ID required');
            if ($id == $_SESSION['user_id']) throw new Exception('You cannot delete your own account');

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'User deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
