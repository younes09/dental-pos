<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get_status';
$user_id = $_SESSION['user_id'] ?? 1;

try {
    switch ($action) {
        case 'get_status':
            $stmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE status = 'Open' LIMIT 1");
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                // Fetch current expected balance = opening_balance + sum(total) of cash sales in this session
                $sales_stmt = $pdo->prepare("SELECT SUM(paid_amount) FROM sales WHERE cash_session_id = ? AND status != 'Cancelled' AND payment_method = 'Cash'");
                $sales_stmt->execute([$session['id']]);
                $cash_sales = (float)$sales_stmt->fetchColumn() ?: 0;
                
                $session['current_total_sales'] = $cash_sales;
                $session['expected_balance'] = (float)$session['opening_balance'] + $cash_sales;
                
                echo json_encode(['status' => 'open', 'session' => $session]);
            } else {
                echo json_encode(['status' => 'closed']);
            }
            break;

        case 'open_session':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Unauthorized');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $opening_balance = (float)($data['opening_balance'] ?? 0);
            $account_id = $data['account_id'] ?? null;
            
            // Check if any session is already open
            $stmt = $pdo->query("SELECT id FROM cash_sessions WHERE status = 'Open'");
            if ($stmt->fetch()) {
                throw new Exception('A session is already open.');
            }
            
            $pdo->beginTransaction();

            // Record session
            $stmt = $pdo->prepare("INSERT INTO cash_sessions (user_id, opening_balance, expected_balance, status) VALUES (?, ?, ?, 'Open')");
            $stmt->execute([$user_id, $opening_balance, $opening_balance]);
            $session_id = $pdo->lastInsertId();

            // F10.4: Vault Integration - Deduct from treasury if account selected
            if ($account_id && $opening_balance > 0) {
                // Check balance
                $acc_stmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                $acc_stmt->execute([$account_id]);
                $current_bal = (float)$acc_stmt->fetchColumn();

                if ($current_bal < $opening_balance) {
                    throw new Exception("Insufficient balance in the selected account (" . number_format($current_bal, 2) . ")");
                }

                // Record transaction
                $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'CashSession', ?, ?)");
                $tx_stmt->execute([
                    $account_id,
                    $opening_balance,
                    "Cash opening - Session #$session_id",
                    $session_id,
                    $user_id
                ]);

                // Update vault balance
                $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$opening_balance, $account_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Session opened successfully', 'id' => $session_id]);
            break;

        case 'close_session':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Unauthorized');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $closing_balance = (float)($data['closing_balance'] ?? 0);
            $notes = $data['notes'] ?? '';
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE status = 'Open' LIMIT 1 FOR UPDATE");
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new Exception('No open session found.');
            }
            
            // Calculate final expected balance
            $sales_stmt = $pdo->prepare("SELECT SUM(paid_amount) FROM sales WHERE cash_session_id = ? AND status != 'Cancelled' AND payment_method = 'Cash'");
            $sales_stmt->execute([$session['id']]);
            $cash_sales = (float)$sales_stmt->fetchColumn() ?: 0;
            $expected = (float)$session['opening_balance'] + $cash_sales;
            
            $difference = $closing_balance - $expected;
            
            $update_stmt = $pdo->prepare("
                UPDATE cash_sessions 
                SET status = 'Closed', 
                    closing_date = CURRENT_TIMESTAMP, 
                    expected_balance = ?, 
                    closing_balance = ?, 
                    difference = ?, 
                    notes = ? 
                WHERE id = ?
            ");
            $update_stmt->execute([$expected, $closing_balance, $difference, $notes, $session['id']]);
            
            // F10.1: Vault Integration - Transfer ALL cash (opening balance + sales) to selected Vault account
            // The opening balance was deducted from a vault on open, so it must be returned on close
            $vault_id = $data['account_id'] ?? null;

            $total_cash_to_vault = $expected; // Full amount: opening_balance + cash_sales
            if ($vault_id && $total_cash_to_vault > 0) {
                // Record transaction
                $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Income', ?, ?, 'CashSession', ?, ?)");
                $tx_stmt->execute([
                    $vault_id, 
                    $total_cash_to_vault, 
                    "Cash closing - Session #" . $session['id'] . " (Opening: " . number_format((float)$session['opening_balance'], 2) . " + Sales: " . number_format($cash_sales, 2) . ")",
                    $session['id'],
                    $user_id
                ]);
                
                // Update account balance
                $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?")->execute([$total_cash_to_vault, $vault_id]);
            } else {
                error_log("Vault transfer skipped for session " . $session['id'] . ". Vault ID: " . ($vault_id ?: 'NONE') . ", Total: " . $total_cash_to_vault);
            }
            
            $pdo->commit();
            echo json_encode(['success' => 'Session closed successfully and funds transferred to Vault', 'difference' => $difference]);
            break;

        case 'history':
            $stmt = $pdo->prepare("
                SELECT cs.*, u.name as user_name 
                FROM cash_sessions cs 
                JOIN users u ON cs.user_id = u.id 
                ORDER BY cs.opening_date DESC 
                LIMIT 50
            ");
            $stmt->execute();
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
