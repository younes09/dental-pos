<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list_accounts';
$user_id = $_SESSION['user_id'] ?? 1;

try {
    switch ($action) {
        case 'list_accounts':
            $stmt = $pdo->query("SELECT * FROM vault_accounts ORDER BY id ASC");
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'list_transactions':
            $account_id = $_GET['account_id'] ?? null;
            $query = "SELECT vt.*, va.name as account_name, u.name as user_name 
                      FROM vault_transactions vt 
                      JOIN vault_accounts va ON vt.account_id = va.id 
                      JOIN users u ON vt.user_id = u.id";
            $params = [];
            
            if ($account_id) {
                $query .= " WHERE vt.account_id = ?";
                $params[] = $account_id;
            }
            
            $query .= " ORDER BY vt.date DESC LIMIT 100";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'add_transaction':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin'])) {
                throw new Exception('Only administrators can add manual transactions.');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $account_id = $data['account_id'];
            $type = $data['type']; // Income or Expense
            $amount = (float)$data['amount'];
            $description = $data['description'] ?? '';

            // S4: Validate transaction type
            if (!in_array($type, ['Income', 'Expense'])) throw new Exception('Invalid transaction type. Must be Income or Expense.');
            if ($amount <= 0) throw new Exception('Invalid amount.');

            $pdo->beginTransaction();

            // Fix #6: Check vault balance before processing an Expense
            if ($type === 'Expense') {
                $balChk = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                $balChk->execute([$account_id]);
                $currentBal = (float)$balChk->fetchColumn();
                if ($currentBal < $amount) {
                    throw new Exception("Insufficient balance (" . number_format($currentBal, 2) . ") for this expense (" . number_format($amount, 2) . ").");
                }
            }

            $stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$account_id, $type, $amount, $description, $user_id]);

            $balance_change = ($type === 'Income') ? $amount : -$amount;
            $stmt = $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$balance_change, $account_id]);

            $pdo->commit();
            echo json_encode(['success' => 'Transaction recorded successfully']);
            break;

        case 'transfer':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin'])) {
                throw new Exception('Only administrators can perform transfers.');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $from_id = $data['from_id'];
            $to_id = $data['to_id'];
            $amount = (float)$data['amount'];
            $description = $data['description'] ?? 'Virement interne';

            if ($amount <= 0) throw new Exception('Invalid amount.');
            if ($from_id == $to_id) throw new Exception('Source and destination accounts must be different.');

            $pdo->beginTransaction();

            // 1. Check source balance (lock source row)
            $stmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$from_id]);
            $from_balance = $stmt->fetchColumn();
            if ($from_balance === false) throw new Exception('Source account not found.');
            if ((float)$from_balance < $amount) throw new Exception('Insufficient balance in source account.');

            // Fix #6: Lock destination account too to prevent race conditions
            $stmt->execute([$to_id]);
            $to_exists = $stmt->fetchColumn();
            if ($to_exists === false) throw new Exception('Destination account not found.');

            // 2. Record transactions
            $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmtTx->execute([$from_id, 'Transfer_Out', $amount, $description, $user_id]);
            $stmtTx->execute([$to_id, 'Transfer_In', $amount, $description, $user_id]);

            // 3. Update balances
            $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $from_id]);
            $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?")->execute([$amount, $to_id]);

            $pdo->commit();
            echo json_encode(['success' => 'Transfer completed successfully']);
            break;

        case 'add_account':
            if (($_SESSION['user_role'] ?? '') !== 'Admin') throw new Exception('Access denied.');
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'];
            $type = $data['type'];
            $balance = isset($data['balance']) ? (float)$data['balance'] : 0.00;
            $is_default = isset($data['is_default']) && $data['is_default'] ? 1 : 0;
            
            if ($is_default) {
                $pdo->query("UPDATE vault_accounts SET is_default = 0");
            }

            $stmt = $pdo->prepare("INSERT INTO vault_accounts (name, type, balance, is_default) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $type, $balance, $is_default]);
            echo json_encode(['success' => 'Account created successfully']);
            break;

        case 'update_account':
            if (($_SESSION['user_role'] ?? '') !== 'Admin') throw new Exception('Access denied.');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            $name = $data['name'];
            $type = $data['type'];
            $is_default = isset($data['is_default']) && $data['is_default'] ? 1 : 0;

            if ($is_default) {
                $pdo->query("UPDATE vault_accounts SET is_default = 0");
            }

            $stmt = $pdo->prepare("UPDATE vault_accounts SET name = ?, type = ?, is_default = ? WHERE id = ?");
            $stmt->execute([$name, $type, $is_default, $id]);
            echo json_encode(['success' => 'Account updated successfully']);
            break;

        case 'delete_account':
            if (($_SESSION['user_role'] ?? '') !== 'Admin') throw new Exception('Access denied.');
            $id = $_GET['id'] ?? null;
            
            if (!$id) throw new Exception('Account ID is required.');
            
            // Allow deletion only if balance is 0 and no transactions exist, or whatever business rule applies. 
            // For now, let's just do a soft or hard delete. Since `vault_transactions` references `account_id`, 
            // we should probably prevent deletion if there are transactions, or cascade delete (risky for financial data).
            // Let's check for transactions first.
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vault_transactions WHERE account_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cannot delete an account with existing transactions.');
            }

            $stmt = $pdo->prepare("DELETE FROM vault_accounts WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Account deleted successfully']);
            break;

        default:
            throw new Exception('Action non reconnue.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Vault API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
