<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'sales';
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y-m-d');

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
                SELECT s.id, c.name as customer, s.date, s.subtotal, s.discount, s.tax, s.total, s.payment_method
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE DATE(s.date) BETWEEN ? AND ? AND s.status = 'Completed'
                ORDER BY s.date DESC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'stock':
            $stmt = $pdo->prepare("
                SELECT p.name, c.name as category, p.purchase_price, p.selling_price, p.stock_qty, (p.purchase_price * p.stock_qty) as inventory_value
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY inventory_value DESC
            ");
            $stmt->execute();
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'customers':
            $stmt = $pdo->prepare("
                SELECT name, phone, email, balance, loyalty_points, 
                (SELECT COUNT(*) FROM sales WHERE customer_id = customers.id) as total_orders,
                (SELECT SUM(total) FROM sales WHERE customer_id = customers.id) as total_spent
                FROM customers
                ORDER BY total_spent DESC
            ");
            $stmt->execute();
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'suppliers':
            $stmt = $pdo->prepare("
                SELECT name, company, email, 
                (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = suppliers.id) as total_orders,
                (SELECT SUM(total) FROM purchase_orders WHERE supplier_id = suppliers.id) as total_purchases
                FROM suppliers
                ORDER BY total_purchases DESC
            ");
            $stmt->execute();
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        default:
            echo json_encode(['error' => 'Invalid report type']);
            break;
    }
} catch (PDOException $e) {
    error_log("Reports API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
// Removed closing tag
