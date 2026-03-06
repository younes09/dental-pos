<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Added subquery or join for last visit (latest sale date)
            $stmt = $pdo->prepare("
                SELECT c.*, 
                (SELECT date FROM sales WHERE customer_id = c.id ORDER BY date DESC LIMIT 1) as last_visit
                FROM customers c
                ORDER BY c.id DESC
            ");
            $stmt->execute();
            $customers = $stmt->fetchAll();
            echo json_encode(['data' => $customers]);
            break;

        case 'save':
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'];
            $phone = $_POST['phone'];
            $email = $_POST['email'];
            $balance = $_POST['balance'];
            $loyalty_points = $_POST['loyalty_points'];

            if ($id) {
                $stmt = $pdo->prepare("UPDATE customers SET name=?, phone=?, email=?, balance=?, loyalty_points=? WHERE id=?");
                $stmt->execute([$name, $phone, $email, $balance, $loyalty_points, $id]);
                echo json_encode(['success' => 'Customer updated successfully']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, balance, loyalty_points) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $email, $balance, $loyalty_points]);
                $newId = $pdo->lastInsertId();
                echo json_encode(['success' => 'Customer added successfully', 'id' => $newId]);
            }
            break;

        case 'delete':
            // F4.1: Only Admin can delete customers
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                echo json_encode(['error' => 'Only Admins can delete customers.']);
                exit;
            }
            $id = $_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Customer deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
