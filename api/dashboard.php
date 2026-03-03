<?php
require_once 'config/db.php';

header('Content-Type: application/json');

try {
    // 1. KPI Data
    // Today's Revenue
    $stmt = $pdo->prepare("SELECT SUM(total) as revenue FROM sales WHERE DATE(date) = CURDATE() AND status = 'Completed'");
    $stmt->execute();
    $today_revenue = $stmt->fetch()['revenue'] ?? 0;

    // Monthly Sales Count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE()) AND status = 'Completed'");
    $stmt->execute();
    $monthly_sales = $stmt->fetch()['count'] ?? 0;

    // Low Stock Items
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE stock_qty <= min_stock AND status = 'Active'");
    $stmt->execute();
    $low_stock = $stmt->fetch()['count'] ?? 0;

    // Active Customers
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers");
    $stmt->execute();
    $customers = $stmt->fetch()['count'] ?? 0;

    // Pending POs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'Pending'");
    $stmt->execute();
    $pending_po = $stmt->fetch()['count'] ?? 0;

    // 2. Sales Chart Data
    $filter = $_GET['filter'] ?? 'daily';
    
    switch ($filter) {
        case 'weekly':
            // Last 4 weeks, grouped by week
            $stmt = $pdo->prepare("
                SELECT DATE_FORMAT(date, 'Week %u') as day, SUM(total) as total 
                FROM sales 
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK) AND status = 'Completed'
                GROUP BY WEEK(date)
                ORDER BY date ASC
            ");
            break;
        case 'monthly':
            // Last 6 months, grouped by month
            $stmt = $pdo->prepare("
                SELECT DATE_FORMAT(date, '%b %Y') as day, SUM(total) as total 
                FROM sales 
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status = 'Completed'
                GROUP BY MONTH(date)
                ORDER BY date ASC
            ");
            break;
        case 'daily':
        default:
            // Last 7 days, grouped by day (original behavior)
            $stmt = $pdo->prepare("
                SELECT DATE_FORMAT(date, '%b %d') as day, SUM(total) as total 
                FROM sales 
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'Completed'
                GROUP BY DATE(date)
                ORDER BY date ASC
            ");
            break;
    }
    
    $stmt->execute();
    $chart_data = $stmt->fetchAll();

    // 3. Top Products
    $stmt = $pdo->prepare("
        SELECT p.name, SUM(si.qty) as total_sold
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        GROUP BY si.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_products = $stmt->fetchAll();

    // 4. Recent Transactions
    $stmt = $pdo->prepare("
        SELECT s.id, c.name as customer_name, s.date, s.total, s.status
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        ORDER BY s.date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_sales = $stmt->fetchAll();

    // 5. Stock Alerts List
    $stmt = $pdo->prepare("
        SELECT name, stock_qty, min_stock
        FROM products
        WHERE stock_qty <= min_stock AND status = 'Active'
        ORDER BY stock_qty ASC
        LIMIT 5
    ");
    $stmt->execute();
    $stock_alerts = $stmt->fetchAll();

    echo json_encode([
        'kpis' => [
            'revenue' => number_format($today_revenue, 2),
            'sales' => $monthly_sales,
            'low_stock' => $low_stock,
            'customers' => $customers,
            'pending_po' => $pending_po,
            'profit' => '24.5' // Static for now, can be calculated
        ],
        'chart' => $chart_data,
        'top_products' => $top_products,
        'recent_sales' => $recent_sales,
        'stock_alerts' => $stock_alerts
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
