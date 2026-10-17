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
            WHERE s2.date >= :today_start AND s2.date <= :today_end
            GROUP BY si.sale_id
        ) si ON si.sale_id = s.id
        WHERE s.date >= :today_start2 AND s.date <= :today_end2 AND s.status = 'Completed'
    ");
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $stmt->execute([
        ':today_start' => $today_start,
        ':today_end' => $today_end,
        ':today_start2' => $today_start,
        ':today_end2' => $today_end
    ]);
    $today_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
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
            WHERE s2.date >= :yest_start AND s2.date <= :yest_end
            GROUP BY si.sale_id
        ) si ON si.sale_id = s.id
        WHERE s.date >= :yest_start2 AND s.date <= :yest_end2 AND s.status = 'Completed'
    ");
    $yesterday_start = date('Y-m-d 00:00:00', strtotime('-1 day'));
    $yesterday_end = date('Y-m-d 23:59:59', strtotime('-1 day'));
    $stmt->execute([
        ':yest_start' => $yesterday_start,
        ':yest_end' => $yesterday_end,
        ':yest_start2' => $yesterday_start,
        ':yest_end2' => $yesterday_end
    ]);
    $yesterday_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $yesterday_revenue = (float)($yesterday_data['revenue'] ?? 0);
    $yesterday_profit = (float)($yesterday_data['profit'] ?? 0);

    // Monthly Sales Count (This Month)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE date >= :month_start AND date <= :month_end AND status = 'Completed'");
    $month_start = date('Y-m-01 00:00:00');
    $month_end = date('Y-m-t 23:59:59');
    $stmt->execute([
        ':month_start' => $month_start,
        ':month_end' => $month_end
    ]);
    $monthly_sales = ($stmt->fetch(PDO::FETCH_ASSOC) ?: [])['count'] ?? 0;

    // Last Month's Sales Count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sales WHERE date >= :last_month_start AND date <= :last_month_end AND status = 'Completed'");
    $last_month_start = date('Y-m-01 00:00:00', strtotime('first day of previous month'));
    $last_month_end = date('Y-m-t 23:59:59', strtotime('last day of previous month'));
    $stmt->execute([
        ':last_month_start' => $last_month_start,
        ':last_month_end' => $last_month_end
    ]);
    $last_month_sales = ($stmt->fetch(PDO::FETCH_ASSOC) ?: [])['count'] ?? 0;

    // Calculate Growth Percentages
    $revenue_growth = 0;
    if ($yesterday_revenue != 0) {
        $revenue_growth = (($today_revenue - $yesterday_revenue) / abs($yesterday_revenue)) * 100;
    } elseif ($today_revenue > 0) {
        $revenue_growth = 100; // New revenue from zero
    }

    $profit_growth = 0;
    if ($yesterday_profit != 0) {
        $profit_growth = (($today_profit - $yesterday_profit) / abs($yesterday_profit)) * 100;
    } elseif ($today_profit > 0) {
        $profit_growth = 100; // New profit from zero
    }

    $sales_count_growth = 0;
    if ($last_month_sales > 0) {
        $sales_count_growth = (($monthly_sales - $last_month_sales) / $last_month_sales) * 100;
    } elseif ($last_month_sales == 0 && $monthly_sales > 0) {
        $sales_count_growth = 100;
    }

    // Low Stock Items
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE stock_qty <= min_stock AND status = 'Active'");
    $stmt->execute();
    $low_stock = ($stmt->fetch(PDO::FETCH_ASSOC) ?: [])['count'] ?? 0;

    // Active Customers
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customers");
    $stmt->execute();
    $customers = ($stmt->fetch(PDO::FETCH_ASSOC) ?: [])['count'] ?? 0;

    // Pending POs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'Pending'");
    $stmt->execute();
    $pending_po = ($stmt->fetch(PDO::FETCH_ASSOC) ?: [])['count'] ?? 0;

    // 2. Sales Chart Data
    $filter = $_GET['filter'] ?? 'daily';
    
    switch ($filter) {
        case 'weekly':
            // Bug #10 Fix: Group by YEARWEEK to avoid cross-year week merging
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(MIN(s.date), 'Week %u %Y') as day,
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
                    WHERE s2.date >= :chart_start
                    GROUP BY si.sale_id
                ) si ON si.sale_id = s.id
                WHERE s.date >= :chart_start2 AND s.status = 'Completed'
                GROUP BY YEARWEEK(s.date, 1)
                ORDER BY MIN(s.date) ASC
            ");
            break;
        case 'monthly':
            // Last 6 months, grouped by month
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(s.date, '%b %Y') as day,
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
                    WHERE s2.date >= :chart_start
                    GROUP BY si.sale_id
                ) si ON si.sale_id = s.id
                WHERE s.date >= :chart_start2 AND s.status = 'Completed'
                GROUP BY YEAR(s.date), MONTH(s.date)
                ORDER BY YEAR(s.date) ASC, MONTH(s.date) ASC
            ");
            break;
        case 'daily':
        default:
            // Last 7 days, grouped by day (original behavior)
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(s.date, '%b %d') as day,
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
                    WHERE s2.date >= :chart_start
                    GROUP BY si.sale_id
                ) si ON si.sale_id = s.id
                WHERE s.date >= :chart_start2 AND s.status = 'Completed'
                GROUP BY DATE(s.date)
                ORDER BY s.date ASC
            ");
            break;
    }
    
    $chart_start_val = '';
    switch ($filter) {
        case 'weekly':
            $chart_start_val = date('Y-m-d 00:00:00', strtotime('-4 weeks'));
            break;
        case 'monthly':
            $chart_start_val = date('Y-m-d 00:00:00', strtotime('-6 months'));
            break;
        case 'daily':
        default:
            $chart_start_val = date('Y-m-d 00:00:00', strtotime('-7 days'));
            break;
    }

    $stmt->execute([
        ':chart_start' => $chart_start_val,
        ':chart_start2' => $chart_start_val
    ]);
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
