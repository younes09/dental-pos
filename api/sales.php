<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list_products';

try {
    switch ($action) {
        case 'list_products':
            $stmt = $pdo->prepare("
                SELECT id, name, selling_price, stock_qty, image, category_id 
                FROM products 
                WHERE status = 'Active' 
                ORDER BY name ASC
            ");
            $stmt->execute();
            $products = $stmt->fetchAll();
            echo json_encode(['products' => $products]);
            break;

        case 'process_sale':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('Invalid data');

            $pdo->beginTransaction();

            // 1. Insert into sales table
            $stmt = $pdo->prepare("
                INSERT INTO sales 
                (customer_id, user_id, subtotal, discount, tax, total, payment_method) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['customer_id'] ?? null,
                1, // Default Admin for now
                $data['subtotal'],
                $data['discount_amount'],
                $data['tax'],
                $data['total'],
                $data['payment_method']
            ]);
            $sale_id = $pdo->lastInsertId();

            // 2. Insert items and update stock
            $item_stmt = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_id, qty, unit_price, total) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stock_stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");

            foreach ($data['items'] as $item) {
                // Check stock availability
                $chk_stmt = $pdo->prepare("SELECT stock_qty FROM products WHERE id = ?");
                $chk_stmt->execute([$item['id']]);
                $current_stock = $chk_stmt->fetch()['stock_qty'];

                if ($current_stock < $item['qty']) {
                    throw new Exception("Insufficient stock for product ID: " . $item['id']);
                }

                $item_stmt->execute([
                    $sale_id,
                    $item['id'],
                    $item['qty'],
                    $item['price'],
                    $item['qty'] * $item['price']
                ]);

                $stock_stmt->execute([$item['qty'], $item['id']]);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Sale completed successfully', 'sale_id' => $sale_id]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?>
