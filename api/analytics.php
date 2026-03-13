<?php
require_once 'config/db.php';

header('Content-Type: application/json');

// Admin-only guard
if (($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Analytics are restricted to administrators only.']);
    exit;
}

$action = $_GET['action'] ?? 'summary';
$from   = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to     = $_GET['to']   ?? date('Y-m-d');
$period = $_GET['period'] ?? 'daily';

try {
    switch ($action) {

        // ---------------------------------------------------------------
        // 1. KPI Summary
        // ---------------------------------------------------------------
        case 'summary':
            // Revenue & profit in range
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(s.total), 0) as revenue,
                    COUNT(s.id)               as sales_count,
                    COALESCE(AVG(s.total), 0) as avg_order
                FROM sales s
                WHERE DATE(s.date) BETWEEN ? AND ? AND s.status = 'Completed'
            ");
            $stmt->execute([$from, $to]);
            $rev = $stmt->fetch();

            // COGS for profit
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM((si.qty - si.returned_qty) * si.cost_price), 0) as cogs
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                WHERE DATE(s.date) BETWEEN ? AND ? AND s.status = 'Completed'
            ");
            $stmt->execute([$from, $to]);
            $cogs = $stmt->fetch()['cogs'];

            // New customers in range
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as new_customers
                FROM customers
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$from, $to]);
            $newCust = $stmt->fetch()['new_customers'] ?? 0;

            // Top category by revenue in range
            $stmt = $pdo->prepare("
                SELECT c.name, COALESCE(SUM(s.total), 0) as cat_revenue
                FROM sales s
                JOIN sale_items si ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE DATE(s.date) BETWEEN ? AND ? AND s.status = 'Completed'
                GROUP BY c.id
                ORDER BY cat_revenue DESC
                LIMIT 1
            ");
            $stmt->execute([$from, $to]);
            $topCat = $stmt->fetch()['name'] ?? 'N/A';

            echo json_encode([
                'revenue'       => (float)$rev['revenue'],
                'profit'        => (float)($rev['revenue'] - $cogs),
                'sales_count'   => (int)$rev['sales_count'],
                'avg_order'     => (float)$rev['avg_order'],
                'new_customers' => (int)$newCust,
                'top_category'  => $topCat
            ]);
            break;

        // ---------------------------------------------------------------
        // 2. Revenue & Profit Trend
        // ---------------------------------------------------------------
        case 'revenue_trend':
            switch ($period) {
                case 'weekly':
                    $groupExpr  = "YEARWEEK(s.date, 1)";
                    $labelExpr  = "DATE_FORMAT(MIN(s.date), 'Wk %u %Y')";
                    break;
                case 'monthly':
                    $groupExpr  = "DATE_FORMAT(s.date, '%Y-%m')";
                    $labelExpr  = "DATE_FORMAT(s.date, '%b %Y')";
                    break;
                default: // daily
                    $groupExpr  = "DATE(s.date)";
                    $labelExpr  = "DATE_FORMAT(s.date, '%b %d')";
                    break;
            }

            $stmt = $pdo->prepare("
                SELECT
                    $labelExpr as label,
                    COALESCE(SUM(s.total), 0) as revenue,
                    COALESCE(SUM(s.total) - SUM((si.qty - si.returned_qty) * si.cost_price), 0) as profit
                FROM sales s
                LEFT JOIN sale_items si ON si.sale_id = s.id
                WHERE DATE(s.date) BETWEEN ? AND ? AND s.status = 'Completed'
                GROUP BY $groupExpr
                ORDER BY MIN(s.date) ASC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        // ---------------------------------------------------------------
        // 3. Top Products Performance
        // ---------------------------------------------------------------
        case 'product_performance':
            $stmt = $pdo->prepare("
                SELECT
                    p.name,
                    SUM(si.qty - si.returned_qty)                           as qty_sold,
                    SUM((si.qty - si.returned_qty) * si.unit_price)         as revenue
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN sales s ON si.sale_id = s.id
                WHERE DATE(s.date) BETWEEN ? AND ? AND s.status = 'Completed'
                GROUP BY si.product_id
                ORDER BY revenue DESC
                LIMIT 10
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        // ---------------------------------------------------------------
        // 4. Payment Methods Breakdown
        // ---------------------------------------------------------------
        case 'payment_methods':
            $stmt = $pdo->prepare("
                SELECT
                    payment_method as method,
                    COUNT(*)       as count,
                    SUM(total)     as total
                FROM sales
                WHERE DATE(date) BETWEEN ? AND ? AND status = 'Completed'
                GROUP BY payment_method
                ORDER BY total DESC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        // ---------------------------------------------------------------
        // 5. Customer Insights (New vs Returning)
        // ---------------------------------------------------------------
        case 'customer_insights':
            switch ($period) {
                case 'weekly':
                    $groupExpr = "YEARWEEK(s.date, 1)";
                    $labelExpr = "DATE_FORMAT(MIN(s.date), 'Wk %u %Y')";
                    break;
                case 'monthly':
                    $groupExpr = "DATE_FORMAT(s.date, '%Y-%m')";
                    $labelExpr = "DATE_FORMAT(s.date, '%b %Y')";
                    break;
                default:
                    $groupExpr = "DATE(s.date)";
                    $labelExpr = "DATE_FORMAT(s.date, '%b %d')";
                    break;
            }

            $stmt = $pdo->prepare("
                SELECT
                    $labelExpr as label,
                    SUM(CASE WHEN c.created_at >= s.date - INTERVAL 1 DAY THEN 1 ELSE 0 END) as new_customers,
                    SUM(CASE WHEN c.created_at <  s.date - INTERVAL 1 DAY THEN 1 ELSE 0 END) as returning_customers
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE DATE(s.date) BETWEEN ? AND ? AND s.status = 'Completed' AND s.customer_id IS NOT NULL
                GROUP BY $groupExpr
                ORDER BY MIN(s.date) ASC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        // ---------------------------------------------------------------
        // 6. Hourly Heatmap (hour × day-of-week)
        // ---------------------------------------------------------------
        case 'hourly_heatmap':
            $stmt = $pdo->prepare("
                SELECT
                    HOUR(date)        as hour,
                    DAYOFWEEK(date)   as dow,
                    COUNT(*)          as sales_count
                FROM sales
                WHERE DATE(date) BETWEEN ? AND ? AND status = 'Completed'
                GROUP BY HOUR(date), DAYOFWEEK(date)
                ORDER BY dow ASC, hour ASC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
