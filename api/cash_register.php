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
                // Fetch current expected balance = opening_balance + sum(total) of cash sales in this session - cash returns
                $sales_stmt = $pdo->prepare("SELECT SUM(paid_amount) FROM sales WHERE cash_session_id = ? AND status != 'Cancelled' AND payment_method = 'Cash'");
                $sales_stmt->execute([$session['id']]);
                $cash_sales = (float) $sales_stmt->fetchColumn() ?: 0;

                // Cash returns in this session
                $returns_stmt = $pdo->prepare("
                    SELECT SUM(sr.total_amount) 
                    FROM sale_returns sr
                    JOIN sales s ON sr.sale_id = s.id
                    WHERE s.cash_session_id = ? AND s.payment_method = 'Cash'
                ");
                $returns_stmt->execute([$session['id']]);
                $cash_returns = (float) $returns_stmt->fetchColumn() ?: 0;

                // Fetch current Caisse vault balance
                $caisse_stmt = $pdo->query("SELECT balance FROM vault_accounts WHERE name = 'Caisse' OR type = 'Cash' LIMIT 1");
                $caisse_balance = (float) $caisse_stmt->fetchColumn();

                $net_sales = $cash_sales - $cash_returns;

                $session['current_total_sales'] = $cash_sales;
                $session['current_total_returns'] = $cash_returns;
                $session['expected_balance'] = $caisse_balance + $net_sales;

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
            $opening_balance = (float) ($data['opening_balance'] ?? 0);
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
                // 1. Find the "Caisse" account
                $caisse_stmt = $pdo->query("SELECT id FROM vault_accounts WHERE name = 'Caisse' OR type = 'Cash' LIMIT 1");
                $caisse_acc = $caisse_stmt->fetch();
                if (!$caisse_acc) {
                    throw new Exception("The 'Caisse' account was not found in the treasury.");
                }
                $caisse_id = $caisse_acc['id'];

                if ($account_id == $caisse_id) {
                    throw new Exception("Source and destination ('Caisse') accounts must be different.");
                }

                // 2. Check source balance
                $acc_stmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                $acc_stmt->execute([$account_id]);
                $current_bal = (float) $acc_stmt->fetchColumn();

                if ($current_bal < $opening_balance) {
                    throw new Exception("Insufficient balance in the selected account (" . number_format($current_bal, 2) . ")");
                }

                $description = "Cash opening - Session #$session_id";

                // 3. Record transactions (Transfer_Out and Transfer_In)
                $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, ?, ?, ?, 'CashSession', ?, ?)");

                // Transfer Out from source
                $tx_stmt->execute([$account_id, 'Transfer_Out', $opening_balance, $description, $session_id, $user_id]);

                // Transfer In to Caisse
                $tx_stmt->execute([$caisse_id, 'Transfer_In', $opening_balance, $description, $session_id, $user_id]);

                // 4. Update balances
                $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$opening_balance, $account_id]);
                $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?")->execute([$opening_balance, $caisse_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Session opened successfully', 'id' => $session_id]);
            break;

        case 'close_session':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Unauthorized');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $closing_balance = (float) ($data['closing_balance'] ?? 0);
            $transfer_amount = isset($data['transfer_amount']) && $data['transfer_amount'] !== '' ? (float) $data['transfer_amount'] : $closing_balance;
            $notes = $data['notes'] ?? '';

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE status = 'Open' LIMIT 1 FOR UPDATE");
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                throw new Exception('No open session found.');
            }

            if ($transfer_amount > $closing_balance) {
                throw new Exception('Transfer amount cannot exceed the counted closing balance.');
            }
            if ($transfer_amount < 0) {
                throw new Exception('Transfer amount cannot be negative.');
            }

            // Calculate final expected balance
            $sales_stmt = $pdo->prepare("SELECT SUM(paid_amount) FROM sales WHERE cash_session_id = ? AND status != 'Cancelled' AND payment_method = 'Cash'");
            $sales_stmt->execute([$session['id']]);
            $cash_sales = (float) $sales_stmt->fetchColumn() ?: 0;

            $returns_stmt = $pdo->prepare("
                SELECT SUM(sr.total_amount) 
                FROM sale_returns sr
                JOIN sales s ON sr.sale_id = s.id
                WHERE s.cash_session_id = ? AND s.payment_method = 'Cash'
            ");
            $returns_stmt->execute([$session['id']]);
            $cash_returns = (float) $returns_stmt->fetchColumn() ?: 0;

            $net_sales = $cash_sales - $cash_returns;
            
            // Find the "Caisse" account first
            $caisse_stmt = $pdo->query("SELECT id FROM vault_accounts WHERE name = 'Caisse' OR type = 'Cash' LIMIT 1");
            $caisse_acc = $caisse_stmt->fetch();
            $caisse_id = $caisse_acc ? $caisse_acc['id'] : null;

            $caisse_balance = 0;
            if ($caisse_id) {
                // Fetch and lock Caisse balance
                $caisseBalStmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                $caisseBalStmt->execute([$caisse_id]);
                $caisse_balance = (float) $caisseBalStmt->fetchColumn();
            }

            $expected = $caisse_balance + $net_sales;

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

            // F10.1: Vault Integration - Ensure accurate Caisse balance before transfer
            $vault_id = $data['account_id'] ?? null;
            $total_cash_to_vault = $transfer_amount;

            if ($caisse_id) {
                $caisseBalance = $caisse_balance;

                // 1. Record Net Sales into Caisse
                if ($net_sales > 0) {
                    $desc = "Cash Sales - Session #" . $session['id'];
                    $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Income', ?, ?, 'CashSession', ?, ?)");
                    $tx_stmt->execute([$caisse_id, $net_sales, $desc, $session['id'], $user_id]);
                    $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?")->execute([$net_sales, $caisse_id]);
                    $caisseBalance += $net_sales;
                } elseif ($net_sales < 0) {
                    $amount = abs($net_sales);
                    // Fix #4: Validate Caisse balance before deducting net returns
                    if ($caisseBalance < $amount) {
                        throw new Exception("Insufficient Caisse balance (" . number_format($caisseBalance, 2) . ") for net returns deduction (" . number_format($amount, 2) . ").");
                    }
                    $desc = "Net Cash Returns - Session #" . $session['id'];
                    $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'CashSession', ?, ?)");
                    $tx_stmt->execute([$caisse_id, $amount, $desc, $session['id'], $user_id]);
                    $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $caisse_id]);
                    $caisseBalance -= $amount;
                }

                // 2. Record Discrepancy into Caisse
                if ($difference > 0) {
                    $desc = "Cash Overage - Session #" . $session['id'];
                    $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Income', ?, ?, 'CashSession', ?, ?)");
                    $tx_stmt->execute([$caisse_id, $difference, $desc, $session['id'], $user_id]);
                    $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?")->execute([$difference, $caisse_id]);
                    $caisseBalance += $difference;
                } elseif ($difference < 0) {
                    $amount = abs($difference);
                    // Fix #5: Validate Caisse balance before deducting cash shortage
                    if ($caisseBalance < $amount) {
                        throw new Exception("Insufficient Caisse balance (" . number_format($caisseBalance, 2) . ") for cash shortage deduction (" . number_format($amount, 2) . ").");
                    }
                    $desc = "Cash Shortage - Session #" . $session['id'];
                    $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'CashSession', ?, ?)");
                    $tx_stmt->execute([$caisse_id, $amount, $desc, $session['id'], $user_id]);
                    $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $caisse_id]);
                    $caisseBalance -= $amount;
                }

                // 3. Transfer ALL cash to selected Vault account (if applicable)
                if ($vault_id && $total_cash_to_vault > 0) {
                    if ($vault_id == $caisse_id) {
                        throw new Exception("Source ('Caisse') and destination accounts must be different.");
                    }

                    // Fix #2: Validate Caisse balance before closing transfer
                    $finalCaisseStmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                    $finalCaisseStmt->execute([$caisse_id]);
                    $finalCaisseBalance = (float) $finalCaisseStmt->fetchColumn();
                    if ($finalCaisseBalance < $total_cash_to_vault) {
                        throw new Exception("Insufficient Caisse balance (" . number_format($finalCaisseBalance, 2) . ") to transfer closing amount (" . number_format($total_cash_to_vault, 2) . ").");
                    }

                    $description = "Cash closing - Session #" . $session['id'] . " (Closing Balance: " . number_format($closing_balance, 2) . ")";
                    // $description = "Cash closing - Session #" . $session['id'] . " (Opening: " . number_format((float)$session['opening_balance'], 2) . " + Sales: " . number_format($cash_sales, 2) . ")";

                    // 2. Record transactions (Transfer_Out from Caisse and Transfer_In to Destination)
                    $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, ?, ?, ?, 'CashSession', ?, ?)");

                    // Transfer Out from Caisse
                    $tx_stmt->execute([$caisse_id, 'Transfer_Out', $total_cash_to_vault, $description, $session['id'], $user_id]);

                    // Transfer In to Destination
                    $tx_stmt->execute([$vault_id, 'Transfer_In', $total_cash_to_vault, $description, $session['id'], $user_id]);

                    $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$total_cash_to_vault, $caisse_id]);
                    $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?")->execute([$total_cash_to_vault, $vault_id]);
                }
            } else {
                error_log("Vault transfer skipped for session " . $session['id'] . ". Vault ID: " . ($vault_id ?: 'NONE') . ", Total: " . $total_cash_to_vault);
            }

            // M11: Notification Trigger - Cash Session Closed
            $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $diff_text = ($difference != 0) ? " (Difference: " . number_format($difference, 2) . ")" : "";
            $msg = "Cash session #" . $session['id'] . " has been closed by " . ($_SESSION['user_name'] ?? 'User') . "." . $diff_text;
            $type = ($difference != 0) ? 'warning' : 'info';
            $ins_notif->execute(['Admin', 'Cash Session Closed', $msg, $type, '#cash_register']);

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
    if ($pdo->inTransaction())
        $pdo->rollBack();
    error_log("Cash Register API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
