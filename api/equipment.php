<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->prepare("SELECT * FROM equipment ORDER BY id DESC");
            $stmt->execute();
            $equipment = $stmt->fetchAll();
            echo json_encode(['data' => $equipment]);
            break;

        case 'save':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'];
            $purchase_price = (float)$_POST['purchase_price'];
            $condition_status = $_POST['condition_status'] ?? 'New';
            $quantity = (int)$_POST['quantity'];

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE equipment SET 
                    name = ?, purchase_price = ?, condition_status = ?, quantity = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $purchase_price, $condition_status, $quantity, $id]);
                echo json_encode(['success' => 'Equipment updated successfully']);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO equipment (name, purchase_price, condition_status, quantity) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$name, $purchase_price, $condition_status, $quantity]);
                echo json_encode(['success' => 'Equipment added successfully']);
            }
            break;

        case 'delete':
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                throw new Exception('Only Admins can delete equipment.');
            }
            $id = $_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Equipment deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
