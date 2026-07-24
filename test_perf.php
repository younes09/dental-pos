<?php
require_once 'api/config/db.php';

// Disable ONLY_FULL_GROUP_BY temporarily if needed, just to measure query
$pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

$from = '2023-01-01';
$to = '2023-12-31';

$start = microtime(true);
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
$end = microtime(true);
echo "Original query time: " . ($end - $start) . " seconds\n";

$start = microtime(true);
$toDate = $to . ' 23:59:59';
$fromDate = $from . ' 00:00:00';
$stmt2 = $pdo->prepare("
    SELECT
        payment_method as method,
        COUNT(*)       as count,
        SUM(total)     as total
    FROM sales
    WHERE date >= ? AND date <= ? AND status = 'Completed'
    GROUP BY payment_method
    ORDER BY total DESC
");
$stmt2->execute([$fromDate, $toDate]);
$end = microtime(true);
echo "SARGable query time: " . ($end - $start) . " seconds\n";
