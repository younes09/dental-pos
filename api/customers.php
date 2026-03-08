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
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Access denied.');
            }
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
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Customer deleted successfully']);
            break;

        case 'add_payment':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Access denied.');
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request method");
            
            $customer_id = $_POST['customer_id'] ?? null;
            $amount = (float)($_POST['amount'] ?? 0);
            $method = $_POST['payment_method'] ?? 'Cash';
            $notes = $_POST['notes'] ?? '';
            $user_id = $_SESSION['user_id'] ?? 1;

            if (!$customer_id) throw new Exception("Customer ID required");
            if ($amount <= 0) throw new Exception("Invalid payment amount");

            $pdo->beginTransaction();

            // Check current balance
            $stmtBal = $pdo->prepare("SELECT balance FROM customers WHERE id = ? FOR UPDATE");
            $stmtBal->execute([$customer_id]);
            $currentBalance = (float)$stmtBal->fetchColumn();

            if ($amount > $currentBalance) {
                throw new Exception("Payment amount (" . number_format($amount, 2) . ") exceeds current debt (" . number_format($currentBalance, 2) . ")");
            }

            $stmt = $pdo->prepare("INSERT INTO customer_payments (customer_id, amount, payment_method, notes, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $amount, $method, $notes, $user_id]);

            $stmtUpdate = $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?");
            $stmtUpdate->execute([$amount, $customer_id]);

            $pdo->commit();
            echo json_encode(['success' => 'Debt payment recorded successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
