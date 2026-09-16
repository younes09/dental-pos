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
$from = ($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))) . ' 00:00:00';
$to = ($_GET['to'] ?? date('Y-m-d')) . ' 23:59:59';
$period = $_GET['period'] ?? 'daily';

try {
    switch ($action) {

        // ---------------------------------------------------------------
        // 1. KPI Summary
        // ---------------------------------------------------------------
        case 'summary':
            // Revenue & profit in range (deducting returns)
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(
                        CASE 
                            WHEN s.subtotal > 0 THEN si.net_subtotal * (1 - (s.discount / s.subtotal))
                            ELSE 0 
                        END
                    ), 0) as revenue,
                    COUNT(s.id)               as sales_count
                FROM sales s
                LEFT JOIN (
                    SELECT 
                        si_inner.sale_id,
                        SUM((si_inner.qty - si_inner.returned_qty) * si_inner.unit_price) as net_subtotal
                    FROM sale_items si_inner
                    JOIN sales s_inner ON si_inner.sale_id = s_inner.id
                    WHERE s_inner.status = 'Completed' AND s_inner.date >= ? AND s_inner.date <= ?
                    GROUP BY si_inner.sale_id
                ) si ON si.sale_id = s.id
                WHERE s.status = 'Completed' AND s.date >= ? AND s.date <= ?
            ");
            $stmt->execute([$from, $to, $from, $to]);
            $rev = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['revenue' => 0, 'sales_count' => 0];
            $avg_order = $rev['sales_count'] > 0 ? ($rev['revenue'] / $rev['sales_count']) : 0;

            // COGS for profit
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM((si.qty - si.returned_qty) * si.cost_price), 0) as cogs
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                WHERE s.status = 'Completed' AND s.date >= ? AND s.date <= ?
            ");
            $stmt->execute([$from, $to]);
            $cogs = ($stmt->fetch(PDO::FETCH_ASSOC) ?: ['cogs' => 0])['cogs'];

            // New customers in range
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as new_customers
                FROM customers
                WHERE created_at >= ? AND created_at <= ?
            ");
            $stmt->execute([$from, $to]);
            $newCust = ($stmt->fetch(PDO::FETCH_ASSOC) ?: ['new_customers' => 0])['new_customers'];

            // Bug #11 Fix: Use item-level revenue to avoid double-counting multi-item sales
            $stmt = $pdo->prepare("
                SELECT c.name, COALESCE(SUM((si.qty - si.returned_qty) * si.unit_price), 0) as cat_revenue
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE s.status = 'Completed' AND s.date >= ? AND s.date <= ?
                GROUP BY c.id
                ORDER BY cat_revenue DESC
                LIMIT 1
            ");
            $stmt->execute([$from, $to]);
            $topCat = ($stmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'N/A'])['name'];

            echo json_encode([
                'revenue'       => (float)$rev['revenue'],
                'profit'        => (float)($rev['revenue'] - $cogs),
                'sales_count'   => (int)$rev['sales_count'],
                'avg_order'     => (float)$avg_order,
                'new_customers' => (int)$newCust,
                'top_category'  => $topCat
            ]);
            break;

        // ---------------------------------------------------------------
        // 2. Revenue & Profit Trend
        // ---------------------------------------------------------------
        case 'revenue_trend':
            $groupExpr = "DATE(s.date)";
            $labelExpr = "DATE_FORMAT(s.date, '%b %d')";
            if ($period === 'weekly') {
                $groupExpr = "YEARWEEK(s.date, 1)";
                $labelExpr = "DATE_FORMAT(MIN(s.date), 'Wk %u %Y')";
            } elseif ($period === 'monthly') {
                $groupExpr = "DATE_FORMAT(s.date, '%Y-%m')";
                $labelExpr = "DATE_FORMAT(s.date, '%b %Y')";
            }

            $stmt = $pdo->prepare("
                SELECT
                    $labelExpr as label,
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
                        si_inner.sale_id,
                        SUM((si_inner.qty - si_inner.returned_qty) * si_inner.unit_price) as net_subtotal,
                        SUM((si_inner.qty - si_inner.returned_qty) * si_inner.cost_price) as net_cogs
                    FROM sale_items si_inner
                    JOIN sales s_inner ON si_inner.sale_id = s_inner.id
                    WHERE s_inner.status = 'Completed' AND s_inner.date >= ? AND s_inner.date <= ?
                    GROUP BY si_inner.sale_id
                ) si ON si.sale_id = s.id
                WHERE s.status = 'Completed' AND s.date >= ? AND s.date <= ?
                GROUP BY $groupExpr
                ORDER BY MIN(s.date) ASC
            ");
            $stmt->execute([$from, $to, $from, $to]);
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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
                WHERE s.status = 'Completed' AND s.date >= ? AND s.date <= ?
                GROUP BY si.product_id
                ORDER BY revenue DESC
                LIMIT 10
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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
                WHERE status = 'Completed' AND date >= ? AND date <= ?
                GROUP BY payment_method
                ORDER BY total DESC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ---------------------------------------------------------------
        // 5. Customer Insights (New vs Returning)
        // ---------------------------------------------------------------
        case 'customer_insights':
            $groupExpr = "DATE(s.date)";
            $labelExpr = "DATE_FORMAT(s.date, '%b %d')";
            if ($period === 'weekly') {
                $groupExpr = "YEARWEEK(s.date, 1)";
                $labelExpr = "DATE_FORMAT(MIN(s.date), 'Wk %u %Y')";
            } elseif ($period === 'monthly') {
                $groupExpr = "DATE_FORMAT(s.date, '%Y-%m')";
                $labelExpr = "DATE_FORMAT(s.date, '%b %Y')";
            }

            $stmt = $pdo->prepare("
                SELECT
                    $labelExpr as label,
                    SUM(CASE WHEN c.created_at >= s.date - INTERVAL 1 DAY THEN 1 ELSE 0 END) as new_customers,
                    SUM(CASE WHEN c.created_at <  s.date - INTERVAL 1 DAY THEN 1 ELSE 0 END) as returning_customers
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.status = 'Completed' AND s.date >= ? AND s.date <= ? AND s.customer_id IS NOT NULL
                GROUP BY $groupExpr
                ORDER BY MIN(s.date) ASC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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
                WHERE status = 'Completed' AND date >= ? AND date <= ?
                GROUP BY HOUR(date), DAYOFWEEK(date)
                ORDER BY dow ASC, hour ASC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
            
        // ---------------------------------------------------------------
        // 7. Sales by Wilaya
        // ---------------------------------------------------------------
        case 'sales_by_wilaya':
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(c.wilaya, 'N/A') as wilaya,
                    SUM(s.total) as revenue,
                    COUNT(s.id) as sales_count
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                WHERE s.status = 'Completed' AND s.date >= ? AND s.date <= ?
                GROUP BY c.wilaya
                ORDER BY revenue DESC
            ");
            $stmt->execute([$from, $to]);
            echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    // Bug #4 Fix: Don't expose SQL error details to client
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
}
