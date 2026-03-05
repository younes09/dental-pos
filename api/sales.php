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
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch batches separately for compatibility instead of JSON_ARRAYAGG
            $batch_stmt = $pdo->prepare("SELECT purchase_type as type, remaining_qty as qty FROM stock_batches WHERE product_id = ? AND remaining_qty > 0 ORDER BY created_at ASC");
            
            foreach ($products as &$product) {
                $batch_stmt->execute([$product['id']]);
                $product['batches'] = json_encode($batch_stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            
            echo json_encode(['products' => $products]);
            break;

        case 'process_sale':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('Invalid data');

            $pdo->beginTransaction();

            $points_earned = isset($data['points_earned']) ? (int)$data['points_earned'] : 0;
            $points_redeemed = isset($data['points_redeemed']) ? (int)$data['points_redeemed'] : 0;
            $customer_id = $data['customer_id'] ?? null;

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
                
                // FIFO Batch Deduction
                $remaining_to_deduct = $item['qty'];
                $batches_stmt = $pdo->prepare("SELECT id, remaining_qty FROM stock_batches WHERE product_id = ? AND remaining_qty > 0 ORDER BY created_at ASC");
                $batches_stmt->execute([$item['id']]);
                $batches = $batches_stmt->fetchAll();
                
                $update_batch_stmt = $pdo->prepare("UPDATE stock_batches SET remaining_qty = ? WHERE id = ?");
                
                foreach ($batches as $batch) {
                    if ($remaining_to_deduct <= 0) break;
                    
                    $available = (int)$batch['remaining_qty'];
                    if ($available >= $remaining_to_deduct) {
                        $new_qty = $available - $remaining_to_deduct;
                        $update_batch_stmt->execute([$new_qty, $batch['id']]);
                        $remaining_to_deduct = 0;
                    } else {
                        $update_batch_stmt->execute([0, $batch['id']]);
                        $remaining_to_deduct -= $available;
                    }
                }
            }

            // 3. Update customer loyalty points
            if ($customer_id && ($points_earned > 0 || $points_redeemed > 0)) {
                $points_stmt = $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? - ? WHERE id = ?");
                $points_stmt->execute([$points_earned, $points_redeemed, $customer_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Sale completed successfully', 'sale_id' => $sale_id]);
            break;

        case 'history':
            $customer_id = $_GET['customer_id'] ?? null;
            $query = "
                SELECT s.*, c.name as customer_name, u.name as user_name
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
            ";
            
            if ($customer_id) {
                $query .= " WHERE s.customer_id = ? ";
                $query .= " ORDER BY s.date DESC";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$customer_id]);
            } else {
                $query .= " ORDER BY s.date DESC";
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            
            $sales = $stmt->fetchAll();
            echo json_encode(['data' => $sales]);
            break;

        case 'sale_details':
            $id = $_GET['id'];
            
            // Get Sale Header
            $stmt = $pdo->prepare("
                SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.name as user_name
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
            $sale = $stmt->fetch();
            
            if (!$sale) {
                echo json_encode(['error' => 'Sale not found']);
                exit;
            }
            
            // Get Sale Items
            $stmt = $pdo->prepare("
                SELECT si.*, p.name as product_name, p.barcode
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = ?
            ");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll();
            
            echo json_encode([
                'sale' => $sale,
                'items' => $items
            ]);
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
