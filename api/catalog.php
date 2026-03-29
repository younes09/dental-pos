<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$type = $_GET['type'] ?? 'categories'; // 'categories' or 'brands'

// Bug #1 Fix: Use match() to map to safe table names, preventing SQL injection
$table = match($type) {
    'categories' => 'categories',
    'brands' => 'brands',
    default => null,
};

if (!$table) {
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->prepare("SELECT * FROM {$table} ORDER BY name ASC");
            $stmt->execute();
            $data = $stmt->fetchAll();
            echo json_encode(['data' => $data]);
            break;

        case 'save':
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'] ?? '';

            if (empty($name)) {
                echo json_encode(['error' => 'Name is required']);
                exit;
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE {$table} SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                echo json_encode(['success' => ucfirst(substr($type, 0, -1)) . ' updated successfully']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO {$table} (name) VALUES (?)");
                $stmt->execute([$name]);
                echo json_encode(['success' => ucfirst(substr($type, 0, -1)) . ' added successfully']);
            }
            break;

        case 'delete':
            // F4.1: Only Admin can delete catalog items
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                echo json_encode(['error' => 'Only Admins can delete catalog items.']);
                exit;
            }
            // Fix #10: Cast id to int for consistency
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) {
                echo json_encode(['error' => 'ID is required']);
                exit;
            }

            // Check if used in products
            $column = ($type === 'categories') ? 'category_id' : 'brand_id';
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $column = ?");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['error' => 'Cannot delete. This ' . substr($type, 0, -1) . ' is assigned to products.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => ucfirst(substr($type, 0, -1)) . ' deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['error' => 'This name already exists']);
    } else {
        error_log("Catalog API Error: " . $e->getMessage());
        // Bug #4 Fix: Don't expose SQL error details to client
        echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
    }
}
// Removed closing tag
