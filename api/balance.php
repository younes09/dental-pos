<?php
require_once 'config/db.php';

header('Content-Type: application/json');

// Only Admin can access financial data
if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Accès restreint aux administrateurs.']);
    exit;
}

try {
    // 1. Vault Balances
    $stmtVault = $pdo->query("SELECT SUM(balance) FROM vault_accounts");
    $vault_balance = (float)$stmtVault->fetchColumn();

    // 2. Inventory Value (Purchase Price * Qty)
    $stmtStock = $pdo->query("SELECT SUM(purchase_price * stock_qty) FROM products WHERE status = 'Active'");
    $inventory_value = (float)$stmtStock->fetchColumn();

    // 3. Accounts Receivable (Customer Balances)
    $stmtReceivable = $pdo->query("SELECT SUM(balance) FROM customers");
    $accounts_receivable = (float)$stmtReceivable->fetchColumn();

    // 4. Accounts Payable (Supplier Balances)
    $stmtPayable = $pdo->query("SELECT SUM(balance) FROM suppliers");
    $accounts_payable = (float)$stmtPayable->fetchColumn();

    // 5. Equipment Fixed Assets
    $stmtEquipment = $pdo->query("SELECT SUM(purchase_price * quantity) FROM equipment");
    $equipment_value = (float)$stmtEquipment->fetchColumn();

    // Total Assets
    $total_assets = $vault_balance + $inventory_value + $accounts_receivable + $equipment_value;
    
    // Total Liabilities
    $total_liabilities = $accounts_payable;

    // Net Capital
    $net_capital = $total_assets - $total_liabilities;

    echo json_encode([
        'data' => [
            'vault_balance' => $vault_balance,
            'inventory_value' => $inventory_value,
            'accounts_receivable' => $accounts_receivable,
            'accounts_payable' => $accounts_payable,
            'equipment_value' => $equipment_value,
            'total_assets' => $total_assets,
            'total_liabilities' => $total_liabilities,
            'net_capital' => $net_capital,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (PDOException $e) {
    error_log("Balance API Error: " . $e->getMessage());
    // Bug #4 Fix: Don't expose SQL error details to client
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
}
