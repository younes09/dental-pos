<?php
/**
 * Migration: Add paid_at to sales and create sale_payments table
 */
require_once __DIR__ . '/../api/config/db.php';

header('Content-Type: text/plain');

try {
    echo "=== Starting Migration: Sale Payments & Payment Status Tracking ===\n";

    // 1. Add paid_at column to sales if not present
    $colCheck = $pdo->query("SHOW COLUMNS FROM sales LIKE 'paid_at'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL AFTER payment_status");
        echo "[SUCCESS] Added 'paid_at' column to 'sales' table.\n";
    } else {
        echo "[INFO] Column 'paid_at' already exists in 'sales'.\n";
    }

    // 2. Create sale_payments table
    $sqlSalePayments = "
        CREATE TABLE IF NOT EXISTS sale_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            customer_payment_id INT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            payment_method ENUM('Cash', 'Card', 'Bank Transfer', 'Cheque', 'Insurance') DEFAULT 'Cash',
            notes TEXT,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_payment_id) REFERENCES customer_payments(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sqlSalePayments);
    echo "[SUCCESS] Table 'sale_payments' created or verified.\n";

    // 3. Backfill paid_at for existing Paid sales
    $affectedPaidAt = $pdo->exec("UPDATE sales SET paid_at = date WHERE payment_status = 'Paid' AND paid_at IS NULL");
    echo "[INFO] Backfilled 'paid_at' on $affectedPaidAt existing paid sales.\n";

    // 4. Backfill initial payments in sale_payments for existing sales
    $sqlBackfill = "
        INSERT INTO sale_payments (sale_id, customer_payment_id, amount, payment_method, notes, user_id, created_at)
        SELECT s.id, NULL, s.paid_amount, s.payment_method, 'Initial Checkout Payment', s.user_id, s.date
        FROM sales s
        WHERE s.paid_amount > 0 
          AND NOT EXISTS (SELECT 1 FROM sale_payments sp WHERE sp.sale_id = s.id)
    ";
    $affectedBackfill = $pdo->exec($sqlBackfill);
    echo "[INFO] Seeded $affectedBackfill initial payments into 'sale_payments'.\n";

    echo "=== Migration Finished Successfully ===\n";
} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
}
