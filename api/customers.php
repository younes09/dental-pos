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
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Customer ID required');
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
            $account_id = $_POST['account_id'] ?? null; // Optional: specify which account received the money

            if (!$customer_id) throw new Exception("Customer ID required");
            if ($amount <= 0) throw new Exception("Invalid payment amount");

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO customer_payments (customer_id, amount, payment_method, notes, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $amount, $method, $notes, $user_id]);
            $payment_id = $pdo->lastInsertId();

            $stmtUpdate = $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?");
            $stmtUpdate->execute([$amount, $customer_id]);

            // F10.2: Vault Integration - Record Income if account is specified
            if ($account_id) {
                $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Income', ?, ?, 'CustomerPayment', ?, ?)");
                $stmtTx->execute([
                    $account_id,
                    $amount,
                    "Paiement Dette Client - " . ($notes ?: "Paiement #$payment_id"),
                    $payment_id,
                    $user_id
                ]);

                $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?")->execute([$amount, $account_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Debt payment recorded successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?>
