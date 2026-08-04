<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'sales';
$from = ($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))) . ' 00:00:00';
$to = ($_GET['to'] ?? date('Y-m-d')) . ' 23:59:59';

// F4.1: Only Admin can access reports
if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Reports are restricted to administrators only.']);
    exit;
}

try {
    switch ($type) {
        case 'sales':
            $stmt = $pdo->prepare("
                SELECT s.id, s.invoice_number, c.name as customer, s.date, s.subtotal, s.discount, s.tax, s.total, s.payment_method
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.date >= ? AND s.date <= ? AND s.status = 'Completed'
                ORDER BY s.date DESC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'stock':
            $stmt = $pdo->prepare("
                SELECT p.name, c.name as category, p.purchase_price, p.selling_price, p.stock_qty, (p.purchase_price * p.stock_qty) as inventory_value
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY inventory_value DESC
            ");
            $stmt->execute();
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'customers':
            $stmt = $pdo->prepare("
                SELECT c.name, c.phone, c.email, c.balance, c.loyalty_points,
                       COALESCE(s.total_orders, 0) as total_orders,
                       COALESCE(s.total_spent, 0) as total_spent
                FROM customers c
                LEFT JOIN (
                    SELECT customer_id, COUNT(*) as total_orders, SUM(total) as total_spent
                    FROM sales
                    GROUP BY customer_id
                ) s ON c.id = s.customer_id
                ORDER BY total_spent DESC
            ");
            $stmt->execute();
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'suppliers':
            $stmt = $pdo->prepare("
                SELECT s.name, s.company, s.email,
                       COALESCE(po.total_orders, 0) as total_orders,
                       COALESCE(po.total_purchases, 0) as total_purchases
                FROM suppliers s
                LEFT JOIN (
                    SELECT supplier_id, COUNT(*) as total_orders, SUM(total) as total_purchases
                    FROM purchase_orders
                    GROUP BY supplier_id
                ) po ON s.id = po.supplier_id
                ORDER BY total_purchases DESC
            ");
            $stmt->execute();
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            echo json_encode(['error' => 'Invalid report type']);
            break;
    }
} catch (PDOException $e) {
    error_log("Reports API Error: " . $e->getMessage());
    // Bug #4 Fix: Don't expose SQL error details to client
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
}
// Removed closing tag
