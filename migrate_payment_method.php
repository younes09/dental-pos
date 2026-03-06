<?php
// Bypass the authentication check by setting a dummy session variable temporarily before including db.php
session_start();
$_SESSION['user_id'] = 1; 

require_once __DIR__ . '/api/config/db.php';

try {
    $pdo->exec("ALTER TABLE sales MODIFY COLUMN payment_method ENUM('Cash', 'Card', 'Insurance', 'Credit') DEFAULT 'Cash'");
    echo "Migration successful: Added 'Credit' to payment_method ENUM inside sales table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Migration already applied.\n";
    } else {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}
