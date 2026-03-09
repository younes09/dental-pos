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
                throw new Exception('Seul l\'administrateur peut ajouter des transactions manuelles.');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $account_id = $data['account_id'];
            $type = $data['type']; // Income or Expense
            $amount = (float)$data['amount'];
            $description = $data['description'] ?? '';

            if ($amount <= 0) throw new Exception('Montant invalide.');

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$account_id, $type, $amount, $description, $user_id]);

            $balance_change = ($type === 'Income') ? $amount : -$amount;
            $stmt = $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$balance_change, $account_id]);

            $pdo->commit();
            echo json_encode(['success' => 'Transaction enregistrée avec succès']);
            break;

        case 'transfer':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin'])) {
                throw new Exception('Seul l\'administrateur peut effectuer des virements.');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $from_id = $data['from_id'];
            $to_id = $data['to_id'];
            $amount = (float)$data['amount'];
            $description = $data['description'] ?? 'Virement interne';

            if ($amount <= 0) throw new Exception('Montant invalide.');
            if ($from_id == $to_id) throw new Exception('Les comptes source et destination doivent être différents.');

            $pdo->beginTransaction();

            // 1. Check source balance
            $stmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$from_id]);
            $from_balance = (float)$stmt->fetchColumn();
            if ($from_balance < $amount) throw new Exception('Solde insuffisant dans le compte source.');

            // 2. Record transactions
            $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmtTx->execute([$from_id, 'Transfer_Out', $amount, $description, $user_id]);
            $stmtTx->execute([$to_id, 'Transfer_In', $amount, $description, $user_id]);

            // 3. Update balances
            $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $from_id]);
            $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?")->execute([$amount, $to_id]);

            $pdo->commit();
            echo json_encode(['success' => 'Virement effectué avec succès']);
            break;

        case 'add_account':
            if (($_SESSION['user_role'] ?? '') !== 'Admin') throw new Exception('Accès refusé.');
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'];
            $type = $data['type'];
            
            $stmt = $pdo->prepare("INSERT INTO vault_accounts (name, type) VALUES (?, ?)");
            $stmt->execute([$name, $type]);
            echo json_encode(['success' => 'Compte créé avec succès']);
            break;

        default:
            throw new Exception('Action non reconnue.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
