<?php
require_once 'config/db.php';

header('Content-Type: application/json');

try {
    // 1. KPI Data
    // Today's Revenue & Profit (with returns/refunds deducted)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN s.subtotal > 0 THEN si.net_subtotal * (1 - (s.discount / s.subtotal))
                    ELSE 0 
                END
            ), 0) as revenue,
            COALESCE(SUM(
                CASE 
                    WHEN s.subtotal > 0 THEN si.net_subtotal * (1 - (s.discount / s.subtotal))
                    ELSE 0 
                END - si.net_cogs
            ), 0) as profit
        FROM sales s
        LEFT JOIN (
            SELECT 
                si.sale_id,
                SUM((si.qty - si.returned_qty) * si.unit_price) as net_subtotal,
                SUM((si.qty - si.returned_qty) * si.cost_price) as net_cogs
            FROM sale_items si
            JOIN sales s2 ON si.sale_id = s2.id
            WHERE s2.date >= CURDATE() AND s2.date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            GROUP BY si.sale_id
        ) si ON si.sale_id = s.id
        WHERE s.date >= CURDATE() AND s.date < DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND s.status = 'Completed'
    ");
    $stmt->execute();
    $today_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_revenue = (float)($today_data['revenue'] ?? 0);
    $today_profit = (float)($today_data['profit'] ?? 0);

    // Yesterday's Revenue & Profit (with returns/refunds deducted)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN s.subtotal > 0 THEN si.net_subtotal * (1 - (s.discount / s.subtotal))
                    ELSE 0 
                END
            ), 0) as revenue,
            COALESCE(SUM(
                CASE 
                    WHEN s.subtotal > 0 THEN si.net_subtotal * (1 - (s.discount / s.subtotal))
                    ELSE 0 
                END - si.net_cogs
            ), 0) as profit
        FROM sales s
        LEFT JOIN (
            SELECT 
                si.sale_id,
                SUM((si.qty - si.returned_qty) * si.unit_price) as net_subtotal,
                SUM((si.qty - si.returned_qty) * si.cost_price) as net_cogs
            FROM sale_items si
            JOIN sales s2 ON si.sale_id = s2.id
            WHERE s2.date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND s2.date < CURDATE()
            GROUP BY si.sale_id
        ) si ON si.sale_id = s.id
        WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND s.date < CURDATE() AND s.status = 'Completed'
    ");
    $stmt->execute();
    $yesterday_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $yesterday_revenue = (float)($yesterday_data['revenue'] ?? 0);
    $yesterday_profit = (float)($yesterday_data['profit'] ?? 0);

    // Monthly Sales Count (This Month)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND date < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) AND status = 'Completed'");
    $stmt->execute();
    $monthly_sales = (int)($stmt->fetchColumn() ?: 0);

    // Last Month's Sales Count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) AND date < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status = 'Completed'");
    $stmt->execute();
    $last_month_sales = (int)($stmt->fetchColumn() ?: 0);

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
    $low_stock = (int)($stmt->fetchColumn() ?: 0);

    // Active Customers
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers");
    $stmt->execute();
    $customers = (int)($stmt->fetchColumn() ?: 0);

    // Pending POs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'Pending'");
    $stmt->execute();
    $pending_po = (int)($stmt->fetchColumn() ?: 0);

    // 2. Sales Chart Data
    $filter = $_GET['filter'] ?? 'daily';
    
    if ($filter === 'weekly') {
        $interval = '4 WEEK';
        $group_by = 'YEARWEEK(s.date, 1)';
        $date_select = "DATE_FORMAT(MIN(s.date), 'Week %u %Y')";
    } elseif ($filter === 'monthly') {
        $interval = '6 MONTH';
        $group_by = 'YEAR(s.date), MONTH(s.date)';
        $date_select = "DATE_FORMAT(MIN(s.date), '%b %Y')";
    } else {
        $interval = '7 DAY';
        $group_by = 'DATE(s.date)';
        $date_select = "DATE_FORMAT(MIN(s.date), '%b %d')";
    }
    
    $stmt = $pdo->prepare("
        SELECT
            {$date_select} as day,
            COALESCE(SUM(
                CASE
                    WHEN s.subtotal > 0 THEN si.net_subtotal * (1 - (s.discount / s.subtotal))
                    ELSE 0
                END
            ), 0) as total
        FROM sales s
        LEFT JOIN (
            SELECT
                si.sale_id,
                SUM((si.qty - si.returned_qty) * si.unit_price) as net_subtotal
            FROM sale_items si
            JOIN sales s2 ON si.sale_id = s2.id
            WHERE s2.date >= DATE_SUB(CURDATE(), INTERVAL {$interval})
            GROUP BY si.sale_id
        ) si ON si.sale_id = s.id
        WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL {$interval}) AND s.status = 'Completed'
        GROUP BY {$group_by}
        ORDER BY MIN(s.date) ASC
    ");

    $stmt->execute();
    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Recent Transactions
    $stmt = $pdo->prepare("
        SELECT s.id, s.invoice_number, c.name as customer_name, s.date, s.total, s.status
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        ORDER BY s.date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Stock Alerts List
    $stmt = $pdo->prepare("
        SELECT name, stock_qty, min_stock
        FROM products
        WHERE stock_qty <= min_stock AND status = 'Active'
        ORDER BY stock_qty ASC
        LIMIT 5
    ");
    $stmt->execute();
    $stock_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    // Bug #4 Fix: Don't expose SQL error details to client
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
}
// Removed closing tag
