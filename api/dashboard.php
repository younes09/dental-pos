<?php
require_once 'config/db.php';

header('Content-Type: application/json');

try {
    // 1. KPI Data
    // Today's Revenue & Profit
    $stmt = $pdo->prepare("SELECT SUM(total) as revenue FROM sales WHERE DATE(date) = CURDATE() AND status = 'Completed'");
    $stmt->execute();
    $today_revenue = $stmt->fetch()['revenue'] ?? 0;

    // Yesterday's Revenue
    $stmt = $pdo->prepare("SELECT SUM(total) as revenue FROM sales WHERE DATE(date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status = 'Completed'");
    $stmt->execute();
    $yesterday_revenue = $stmt->fetch()['revenue'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT SUM((si.qty - si.returned_qty) * si.cost_price) as cogs
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.date) = CURDATE() AND s.status = 'Completed'
    ");
    $stmt->execute();
    $today_cogs = $stmt->fetch()['cogs'] ?? 0;
    
    $today_profit = $today_revenue - $today_cogs;

    // Yesterday's Profit
    $stmt = $pdo->prepare("
        SELECT SUM((si.qty - si.returned_qty) * si.cost_price) as cogs
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND s.status = 'Completed'
    ");
    $stmt->execute();
    $yesterday_cogs = $stmt->fetch()['cogs'] ?? 0;
    
    $yesterday_profit = $yesterday_revenue - $yesterday_cogs;

    // Monthly Sales Count (This Month)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE()) AND status = 'Completed'");
    $stmt->execute();
    $monthly_sales = $stmt->fetch()['count'] ?? 0;

    // Last Month's Sales Count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE MONTH(date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND status = 'Completed'");
    $stmt->execute();
    $last_month_sales = $stmt->fetch()['count'] ?? 0;

    // Calculate Growth Percentages
    $revenue_growth = 0;
    if ($yesterday_revenue > 0) {
        $revenue_growth = (($today_revenue - $yesterday_revenue) / $yesterday_revenue) * 100;
    } elseif ($yesterday_revenue == 0 && $today_revenue > 0) {
        $revenue_growth = 100; // New revenue from zero
    }

    $profit_growth = 0;
    if ($yesterday_profit > 0) {
        $profit_growth = (($today_profit - $yesterday_profit) / $yesterday_profit) * 100;
    } elseif ($yesterday_profit == 0 && $today_profit > 0) {
        $profit_growth = 100; // New profit from zero
    }

    $sales_count_growth = 0;
    if ($last_month_sales > 0) {
        $sales_count_growth = (($monthly_sales - $last_month_sales) / $last_month_sales) * 100;
    }

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
                GROUP BY YEAR(date), MONTH(date)
                ORDER BY YEAR(date) ASC, MONTH(date) ASC
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
        JOIN sales s ON si.sale_id = s.id
        WHERE s.status = 'Completed'
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
            'revenue' => (float)$today_revenue,
            'sales' => $monthly_sales,
            'low_stock' => $low_stock,
            'customers' => $customers,
            'pending_po' => $pending_po,
            'profit' => (float)$today_profit,
            'revenue_growth' => round($revenue_growth, 2),
            'profit_growth' => round($profit_growth, 2),
            'sales_growth' => round($sales_count_growth, 2)
        ],
        'chart' => $chart_data,
        'top_products' => $top_products,
        'recent_sales' => $recent_sales,
        'stock_alerts' => $stock_alerts
    ]);

} catch (PDOException $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
// Removed closing tag
