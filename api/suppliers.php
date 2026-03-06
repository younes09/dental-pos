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

        case 'add_payment':
            $supplier_id = $_POST['supplier_id'] ?? null;
            $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
            $method = $_POST['payment_method'] ?? 'Cash';
            $notes = $_POST['notes'] ?? '';
            $user_id = $_SESSION['user_id'] ?? null;

            if (!$supplier_id) throw new Exception("Supplier ID required");
            if ($amount <= 0) throw new Exception("Invalid payment amount");

            $pdo->beginTransaction();

            // Check current balance
            $stmtBal = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? FOR UPDATE");
            $stmtBal->execute([$supplier_id]);
            $currentBalance = (float)$stmtBal->fetchColumn();

            if ($amount > $currentBalance) {
                throw new Exception("Payment amount (" . number_format($amount, 2) . ") exceeds current debt (" . number_format($currentBalance, 2) . ")");
            }

            $stmt = $pdo->prepare("INSERT INTO supplier_payments (supplier_id, amount, payment_method, notes, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$supplier_id, $amount, $method, $notes, $user_id]);

            $stmt2 = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
            $stmt2->execute([$amount, $supplier_id]);

            $pdo->commit();
            echo json_encode(['success' => 'Debt payment recorded successfully']);
            break;

        case 'delete':
            // F4.1: Only Admin can delete suppliers
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                echo json_encode(['error' => 'Only Admins can delete suppliers.']);
                exit;
            }
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
