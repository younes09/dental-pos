<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Include count of purchase orders
            $stmt = $pdo->prepare("
                SELECT s.*, 
                (SELECT SUM(total) FROM purchase_orders WHERE supplier_id = s.id) as total_purchases
                FROM suppliers s
                ORDER BY s.id DESC
            ");
            $stmt->execute();
            $suppliers = $stmt->fetchAll();
            echo json_encode(['data' => $suppliers]);
            break;

        case 'save':
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'];
            $company = $_POST['company'];
            $phone = $_POST['phone'];
            $email = $_POST['email'];

            if ($id) {
                $stmt = $pdo->prepare("UPDATE suppliers SET name=?, company=?, phone=?, email=? WHERE id=?");
                $stmt->execute([$name, $company, $phone, $email, $id]);
                echo json_encode(['success' => 'Supplier updated successfully']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO suppliers (name, company, phone, email) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $company, $phone, $email]);
                echo json_encode(['success' => 'Supplier added successfully']);
            }
            break;

        case 'delete':
            $id = $_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Supplier deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
