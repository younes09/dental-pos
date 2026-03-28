<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Added subquery or join for last visit and total purchases
            $stmt = $pdo->prepare("
                SELECT c.*, 
                (SELECT date FROM sales WHERE customer_id = c.id ORDER BY date DESC LIMIT 1) as last_visit,
                (SELECT COALESCE(SUM(total), 0) FROM sales WHERE customer_id = c.id AND (status != 'Cancelled' OR status IS NULL)) as total_purchases
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
            $name = $_POST['name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $email = $_POST['email'] ?? '';

            if (empty($name)) throw new Exception('Customer name is required.');

            if ($id) {
                // Fix #12: Do NOT allow overwriting balance/loyalty_points via this form
                // These are updated only through dedicated payment/sale actions
                $stmt = $pdo->prepare("UPDATE customers SET name=?, phone=?, email=? WHERE id=?");
                $stmt->execute([$name, $phone, $email, $id]);
                echo json_encode(['success' => 'Customer updated successfully']);
            } else {
                // Fix #12: Force balance=0 and loyalty_points=0 for new customers
                $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, balance, loyalty_points) VALUES (?, ?, ?, 0, 0)");
                $stmt->execute([$name, $phone, $email]);
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
            // Bug #9 Fix: Cast id to int for consistency and defense-in-depth
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
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

            // S7: Validate payment doesn't exceed current debt
            $stmtBal = $pdo->prepare("SELECT balance FROM customers WHERE id = ? FOR UPDATE");
            $stmtBal->execute([$customer_id]);
            $currentBalance = (float)$stmtBal->fetchColumn();

            if ($amount > $currentBalance) {
                throw new Exception("Payment amount (" . number_format($amount, 2) . ") exceeds current debt (" . number_format($currentBalance, 2) . ")");
            }

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
                    "Customer Debt Payment - " . ($notes ?: "Payment #$payment_id"),
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
    error_log("Customers API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}

