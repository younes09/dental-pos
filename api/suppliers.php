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

        case 'products':
            $supplier_id = $_GET['id'] ?? null;
            if (!$supplier_id) throw new Exception("Supplier ID required");
            
            // Get all products that Have been purchased from this supplier
            $stmt = $pdo->prepare("
                SELECT 
                    p.id, 
                    p.name, 
                    p.barcode,
                    c.name as category_name,
                    b.name as brand_name,
                    SUM(poi.qty) as total_supplied_qty,
                    AVG(poi.unit_cost) as avg_unit_cost,
                    MAX(po.date) as last_purchase_date
                FROM products p
                JOIN purchase_order_items poi ON p.id = poi.product_id
                JOIN purchase_orders po ON poi.po_id = po.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE po.supplier_id = ?
                GROUP BY p.id
                ORDER BY max(po.date) DESC
            ");
            $stmt->execute([$supplier_id]);
            $products = $stmt->fetchAll();
            echo json_encode(['data' => $products]);
            break;

        case 'save':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'] ?? '';
            $company = $_POST['company'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $email = $_POST['email'] ?? '';

            if (empty($name)) throw new Exception('Supplier name is required.');

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
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
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

            // Get the payment ID
            $payment_id = $pdo->lastInsertId();

            $stmt2 = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
            $stmt2->execute([$amount, $supplier_id]);

            // Auto-update PO payment_status: if supplier balance is now 0,
            // mark all their Received POs that are still Unpaid/Partial as Paid.
            $stmtNewBal = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ?");
            $stmtNewBal->execute([$supplier_id]);
            $newBalance = (float)$stmtNewBal->fetchColumn();

            if ($newBalance <= 0) {
                $stmtPayPO = $pdo->prepare("
                    UPDATE purchase_orders
                    SET payment_status = 'Paid', paid_amount = total
                    WHERE supplier_id = ?
                      AND status = 'Received'
                      AND payment_status IN ('Unpaid', 'Partial')
                ");
                $stmtPayPO->execute([$supplier_id]);
            }

            // F10.2: Vault Integration - Record Expense if account is specified
            $account_id = $_POST['account_id'] ?? null;
            if ($account_id) {
                // Fix #2: Check vault balance before debiting
                $vaultBalStmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                $vaultBalStmt->execute([$account_id]);
                $vaultBalance = (float)$vaultBalStmt->fetchColumn();
                if ($vaultBalance < $amount) {
                    throw new Exception("Insufficient vault balance (" . number_format($vaultBalance, 2) . ") for this payment (" . number_format($amount, 2) . ").");
                }

                $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'SupplierPayment', ?, ?)");
                $stmtTx->execute([
                    $account_id,
                    $amount,
                    "Supplier Debt Payment - " . ($notes ?: "Payment #$payment_id"),
                    $payment_id,
                    $user_id
                ]);

                $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $account_id]);
            }

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
    error_log("Suppliers API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}

