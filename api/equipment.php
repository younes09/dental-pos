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

            // Ajouter cette validation pour empêcher l'enregistrement d'équipements négatifs :
            if ($purchase_price < 0 || $quantity <= 0) {
                throw new Exception("Le prix et la quantité doivent être strictement positifs.");
            }

            if ($id) {
                // Fix #7: Wrap update + notification in a transaction for atomicity
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    UPDATE equipment SET
                    name = ?, purchase_price = ?, condition_status = ?, quantity = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $purchase_price, $condition_status, $quantity, $id]);

                // Notification inside transaction so it rolls back on failure
                if (in_array($condition_status, ['Poor', 'Needs Repair'])) {
                    $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
                    $eq_msg = "Equipment '$name' status changed to '$condition_status' by " . ($_SESSION['user_name'] ?? 'User') . ".";
                    $notif_type = ($condition_status === 'Needs Repair') ? 'danger' : 'warning';
                    $ins_notif->execute(['Admin', 'Equipment Alert: ' . $name, $eq_msg, $notif_type, '#equipment']);
                }

                $pdo->commit();
                echo json_encode(['success' => 'Equipment updated successfully']);
            } else {
                $pdo->beginTransaction();
                // F10.1: Insert New Equipment
                $stmt = $pdo->prepare("
                    INSERT INTO equipment (name, purchase_price, condition_status, quantity)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$name, $purchase_price, $condition_status, $quantity]);
                $id = $pdo->lastInsertId();

                // F10.2: Vault Integration - Record Expense for NEW equipment
                $account_id = $_POST['account_id'] ?? null;
                $total_cost = $purchase_price * $quantity;
                if ($account_id && $total_cost > 0) {
                    $user_id = $_SESSION['user_id'] ?? 1;

                    $balStmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                    $balStmt->execute([$account_id]);
                    $balance = (float)($balStmt->fetchColumn() ?? 0);

                    if ($balance < $total_cost) {
                        throw new Exception('Insufficient balance in the selected account. Available: ' . number_format($balance, 2));
                    }

                    $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'Equipment', ?, ?)");
                    $stmtTx->execute([
                        $account_id,
                        $total_cost,
                        "Equipment Purchase - $name (Qty: $quantity)",
                        $id,
                        $user_id
                    ]);
                    $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$total_cost, $account_id]);
                }

                // Fix #7: Notification inside transaction for atomicity
                if (in_array($condition_status, ['Poor', 'Needs Repair'])) {
                    $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
                    $eq_msg = "Equipment '$name' status changed to '$condition_status' by " . ($_SESSION['user_name'] ?? 'User') . ".";
                    $notif_type = ($condition_status === 'Needs Repair') ? 'danger' : 'warning';
                    $ins_notif->execute(['Admin', 'Equipment Alert: ' . $name, $eq_msg, $notif_type, '#equipment']);
                }

                $pdo->commit();
                echo json_encode(['success' => 'Equipment added successfully']);
            }
            break;

        case 'delete':
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                throw new Exception('Only Admins can delete equipment.');
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) throw new Exception('Equipment ID is required.');
            $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Equipment deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Equipment API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
