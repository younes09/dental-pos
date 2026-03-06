<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list_products';

try {
    switch ($action) {
        case 'list_products':
            $stmt = $pdo->prepare("
                SELECT id, name, selling_price, stock_qty, expiry_date, image, category_id
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
            if (!$data || empty($data['items'])) throw new Exception('Invalid data or empty cart');

            $pdo->beginTransaction();

            $customer_id = $data['customer_id'] ?? null;
            $user_id = $_SESSION['user_id'] ?? 1;

            // Recalculate totals server-side
            $subtotal = 0;
            
            // Get tax rate and point value from settings
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('vat_rate', 'loyalty_point_value', 'loyalty_earning_rate')");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $tax_rate = (float)($settings['vat_rate'] ?? 0) / 100;
            $point_value = (float)($settings['loyalty_point_value'] ?? 1);
            $earning_rate = (float)($settings['loyalty_earning_rate'] ?? 100);

            // Fetch products and lock them for update to prevent race conditions
            $product_ids = array_column($data['items'], 'id');
            if (empty($product_ids)) throw new Exception('No items provided');
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            $prod_stmt = $pdo->prepare("SELECT id, name, selling_price, stock_qty FROM products WHERE id IN ($placeholders) FOR UPDATE");
            $prod_stmt->execute($product_ids);
            
            $db_products = [];
            while ($row = $prod_stmt->fetch()) {
                $db_products[$row['id']] = $row;
            }

            // Calculate subtotal
            $processed_items = [];
            foreach ($data['items'] as $item) {
                $qty = (int)$item['qty'];
                $item_id = (int)$item['id'];
                
                if ($qty <= 0) {
                    throw new Exception("Invalid quantity for product ID: " . $item_id);
                }
                if (!isset($db_products[$item_id])) {
                    throw new Exception("Product not found: " . $item_id);
                }
                
                $db_prod = $db_products[$item_id];
                if ($db_prod['stock_qty'] < $qty) {
                    throw new Exception("Insufficient stock for: " . $db_prod['name']);
                }
                
                $price = (float)$db_prod['selling_price'];
                $item_total = $price * $qty;
                $subtotal += $item_total;
                
                $processed_items[] = [
                    'id' => $item_id,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $item_total
                ];
            }

            // Trust but verify discount logic
            $discount_amount = max(0, min($subtotal, (float)($data['discount_amount'] ?? 0)));
            $points_redeemed = max(0, (int)($data['points_redeemed'] ?? 0));
            
            // Points validation
            if ($customer_id && $points_redeemed > 0) {
                $cust_stmt = $pdo->prepare("SELECT loyalty_points FROM customers WHERE id = ? FOR UPDATE");
                $cust_stmt->execute([$customer_id]);
                $customer_pts = $cust_stmt->fetchColumn() ?: 0;
                
                if ($points_redeemed > $customer_pts) {
                    $points_redeemed = $customer_pts;
                }
                
                $points_discount = $points_redeemed * $point_value;
                $discount_amount += $points_discount;
                if ($discount_amount > $subtotal) {
                    $discount_amount = $subtotal; // Cap it
                }
            } else {
                $points_redeemed = 0;
            }

            $taxable = max(0, $subtotal - $discount_amount);
            
            // Check invoice type for special BL rules
            $invoice_type = $data['invoice_type'] ?? 'BV';
            $payment_method = $data['payment_method'] ?? 'Cash';
            
            // Force tax to 0 for BL
            if ($invoice_type === 'BL') {
                $tax_rate = 0;
            }

            $tax = round($taxable * $tax_rate, 2);
            $total = round($taxable + $tax, 2);

            $points_earned = ($earning_rate > 0) ? floor($total / $earning_rate) : 0;

            // 1. Insert into sales table
            $stmt = $pdo->prepare("
                INSERT INTO sales 
                (customer_id, user_id, subtotal, discount, tax, total, payment_method, invoice_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $customer_id,
                $user_id,
                $subtotal,
                $discount_amount,
                $tax,
                $total,
                $payment_method,
                $invoice_type
            ]);
            $sale_id = $pdo->lastInsertId();

            // 2. Insert items and update stock
            $item_stmt = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_id, qty, unit_price, total) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stock_stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");

            foreach ($processed_items as $item) {
                $item_stmt->execute([
                    $sale_id,
                    $item['id'],
                    $item['qty'],
                    $item['price'],
                    $item['total']
                ]);

                $stock_stmt->execute([$item['qty'], $item['id']]);
                
                // FIFO Batch Deduction
                $remaining_to_deduct = $item['qty'];
                $batches_stmt = $pdo->prepare("SELECT id, remaining_qty FROM stock_batches WHERE product_id = ? AND remaining_qty > 0 ORDER BY created_at ASC FOR UPDATE");
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

            // 3. Update customer loyalty points and balance
            if ($customer_id) {
                if ($points_earned > 0 || $points_redeemed > 0) {
                    $points_stmt = $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? - ? WHERE id = ?");
                    $points_stmt->execute([$points_earned, $points_redeemed, $customer_id]);
                }
                
                // If payment method is Credit, increase customer balance (debt)
                if ($payment_method === 'Credit') {
                    $balance_stmt = $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?");
                    $balance_stmt->execute([$total, $customer_id]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => 'Sale completed successfully', 'sale_id' => $sale_id, 'total' => $total]);
            break;

        case 'cancel_sale':
            if (($_SESSION['user_role'] ?? '') === 'Cashier') {
                throw new Exception('Only Admins or Managers can cancel sales.');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $sale_id = $data['sale_id'] ?? null;
            if (!$sale_id) throw new Exception('Sale ID required');
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? FOR UPDATE");
            $stmt->execute([$sale_id]);
            $sale = $stmt->fetch();
            
            if (!$sale) throw new Exception('Sale not found');
            if ($sale['status'] === 'Cancelled') throw new Exception('Sale is already cancelled');
            
            // Mark as cancelled
            $pdo->prepare("UPDATE sales SET status = 'Cancelled' WHERE id = ?")->execute([$sale_id]);
            
            // Restore stock
            $items_stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $items_stmt->execute([$sale_id]);
            $items = $items_stmt->fetchAll();
            
            $restore_stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
            $batch_stmt = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, 'BA', ?, ?)");
            
            foreach ($items as $item) {
                // Restore stock
                $restore_stmt->execute([$item['qty'], $item['product_id']]);
                // Restore into a new valid batch
                $batch_stmt->execute([$item['product_id'], $item['qty'], $item['qty']]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => 'Sale cancelled and stock restored']);
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
// Removed closing tag
