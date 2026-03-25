<?php
require_once 'config/db.php';

header('Content-Type: application/json');

// Only Admins can manage salaries
if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access. Admins only.']);
    exit;
}

$action = $_GET['action'] ?? 'list_staff';
$user_id = $_SESSION['user_id'] ?? 1;

try {
    switch ($action) {
        case 'list_staff':
            $stmt = $pdo->query("SELECT * FROM staff ORDER BY name ASC");
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'save_staff':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = !empty($data['id']) ? $data['id'] : null;
            $name = $data['name'];
            $position = !empty($data['position']) ? $data['position'] : null;
            $phone = !empty($data['phone']) ? $data['phone'] : null;
            $email = !empty($data['email']) ? $data['email'] : null;
            $base_salary = (float)($data['base_salary'] ?? 0);
            $status = $data['status'] ?? 'Active';
            $hiring_date = !empty($data['hiring_date']) ? $data['hiring_date'] : null;

            if ($id) {
                $stmt = $pdo->prepare("UPDATE staff SET name = ?, position = ?, phone = ?, email = ?, base_salary = ?, status = ?, hiring_date = ? WHERE id = ?");
                $stmt->execute([$name, $position, $phone, $email, $base_salary, $status, $hiring_date, $id]);
                echo json_encode(['success' => 'Staff member updated successfully']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO staff (name, position, phone, email, base_salary, status, hiring_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $position, $phone, $email, $base_salary, $status, $hiring_date]);
                echo json_encode(['success' => 'Staff member added successfully']);
            }
            break;

        case 'delete_staff':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Staff ID required');
            $pdo->beginTransaction();
            // Delete payments first
            $pdo->prepare("DELETE FROM salary_payments WHERE staff_id = ?")->execute([$id]);
            // Then delete staff
            $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => 'Staff member and their history deleted successfully']);
            break;

        case 'list_payments':
            $staff_id = $_GET['staff_id'] ?? null;
            $query = "SELECT sp.*, s.name as staff_name, va.name as account_name 
                      FROM salary_payments sp 
                      LEFT JOIN staff s ON sp.staff_id = s.id 
                      LEFT JOIN vault_accounts va ON sp.vault_account_id = va.id";
            $params = [];
            if ($staff_id) {
                $query .= " WHERE sp.staff_id = ?";
                $params[] = $staff_id;
            }
            $query .= " ORDER BY sp.payment_date DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'add_payment':
            $data = json_decode(file_get_contents('php://input'), true);
            $staff_id = $data['staff_id'] ?? null;
            $vault_account_id = $data['vault_account_id'] ?? null;
            $amount = (float)($data['amount'] ?? 0);
            $payment_date = $data['payment_date'] ?? date('Y-m-d');
            $period_month = (int)($data['period_month'] ?? date('m'));
            $period_year = (int)($data['period_year'] ?? date('Y'));
            $payment_method = $data['payment_method'] ?? 'Cash';
            $notes = $data['notes'] ?? '';

            if (!$staff_id || !$vault_account_id) throw new Exception('Sélectionnez un membre et un compte de trésorerie.');
            if ($amount <= 0) throw new Exception('Montant invalide.');

            $pdo->beginTransaction();

            // 1. Record salary payment
            $stmt = $pdo->prepare("INSERT INTO salary_payments (staff_id, vault_account_id, amount, payment_date, period_month, period_year, payment_method, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$staff_id, $vault_account_id, $amount, $payment_date, $period_month, $period_year, $payment_method, $notes, $user_id]);
            $payment_id = $pdo->lastInsertId();
            
            // 2. Record expense in vault_transactions
            $staff_stmt = $pdo->prepare("SELECT name FROM staff WHERE id = ?");
            $staff_stmt->execute([$staff_id]);
            $staff_name = $staff_stmt->fetchColumn();
            
            $month_name = date("F", mktime(0, 0, 0, $period_month, 10));
            $tx_description = "Salary: $staff_name ($month_name $period_year)";
            
            $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'Salary', ?, ?)");
            $stmtTx->execute([$vault_account_id, $amount, $tx_description, $payment_id, $user_id]);

            // 3. Update vault balance
            $stmtBal = $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?");
            $stmtBal->execute([$amount, $vault_account_id]);

            $pdo->commit();
            echo json_encode(['success' => 'Salary payment recorded and reflected in treasury']);
            break;

        case 'delete_payment':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Payment ID required');
            $stmt = $pdo->prepare("DELETE FROM salary_payments WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Payment record deleted successfully']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Salary API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
