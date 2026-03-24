<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list_products';

try {
    switch ($action) {
        case 'list_products':
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.selling_price, p.stock_qty, p.image, p.category_id,
                       (SELECT MIN(expiry_date) FROM stock_batches WHERE product_id = p.id AND remaining_qty > 0) as expiry_date
                FROM products p
                WHERE p.status = 'Active' 
                ORDER BY p.name ASC
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
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Only Admins and Cashiers can process sales.');
            }
            $pdo->beginTransaction();
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['items'])) throw new Exception('Invalid data or empty cart');

            $customer_id = $data['customer_id'] ?? null;
            $user_id = $_SESSION['user_id'] ?? 1;

            // F7.1: Cash Register - Check active session
            $session_stmt = $pdo->prepare("SELECT id FROM cash_sessions WHERE status = 'Open' LIMIT 1");
            $session_stmt->execute();
            $cash_session = $session_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cash_session) {
                throw new Exception('No active cash session found. Please open the register first.');
            }
            $cash_session_id = (int)$cash_session['id'];

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
            $prod_stmt = $pdo->prepare("SELECT id, name, selling_price, purchase_price, stock_qty FROM products WHERE id IN ($placeholders) FOR UPDATE");
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
                $cost = (float)$db_prod['purchase_price'];
                $item_total = $price * $qty;
                $subtotal += $item_total;
                
                $processed_items[] = [
                    'id' => $item_id,
                    'qty' => $qty,
                    'price' => $price,
                    'cost' => $cost,
                    'total' => $item_total
                ];
            }

            // Recalculate and validate discount logic
            // The frontend sends discount_amount which is the TOTAL (manual + points)
            // But we must ensure it doesn't exceed subtotal and is correctly composed.
            $received_total_discount = (float)($data['discount_amount'] ?? 0);
            $points_redeemed = max(0, (int)($data['points_redeemed'] ?? 0));
            
            // Points validation and calculation
            $points_discount = 0;
            if ($customer_id && $points_redeemed > 0) {
                $cust_stmt = $pdo->prepare("SELECT loyalty_points FROM customers WHERE id = ? FOR UPDATE");
                $cust_stmt->execute([$customer_id]);
                $customer_pts = $cust_stmt->fetchColumn() ?: 0;
                
                if ($points_redeemed > $customer_pts) {
                    $points_redeemed = $customer_pts;
                }
                
                $points_discount = $points_redeemed * $point_value;
            } else {
                $points_redeemed = 0;
            }

            // Extract manual discount from the received total and cap it
            $manual_discount = max(0, $received_total_discount - $points_discount);
            
            // Final verified discount amount
            $discount_amount = min($subtotal, $manual_discount + $points_discount);

            $taxable = max(0, $subtotal - $discount_amount);
            
            $invoice_type = $data['invoice_type'] ?? 'BV';
            $payment_method = $data['payment_method'] ?? 'Cash';
            
            // M10: Validate ENUM values server-side
            $valid_methods = ['Cash', 'Card', 'Insurance'];
            $valid_types = ['BV', 'BL'];
            if (!in_array($payment_method, $valid_methods)) throw new Exception('Invalid payment method');
            if (!in_array($invoice_type, $valid_types)) throw new Exception('Invalid invoice type');
            
            // Force tax to 0 for BL
            if ($invoice_type === 'BL') {
                $tax_rate = 0;
            }

            $tax = round($taxable * $tax_rate, 2);
            $total = round($taxable + $tax, 2);

            $paid_amount = (float)($data['paid_amount'] ?? $total);
            if ($paid_amount < 0) throw new Exception('Paid amount cannot be negative');
            $payment_status = 'Paid';
            if ($paid_amount < $total) {
                $payment_status = ($paid_amount > 0) ? 'Partial' : 'Unpaid';
            }

            $points_earned = ($earning_rate > 0) ? floor($total / $earning_rate) : 0;

            // 1. Insert into sales table (F2.4: store points for reversal)
            $stmt = $pdo->prepare("
                INSERT INTO sales 
                (customer_id, user_id, cash_session_id, subtotal, discount, tax, total, paid_amount, payment_status, payment_method, invoice_type, points_earned, points_redeemed) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $customer_id,
                $user_id,
                $cash_session_id,
                $subtotal,
                $discount_amount,
                $tax,
                $total,
                $paid_amount,
                $payment_status,
                $payment_method,
                $invoice_type,
                $points_earned,
                $points_redeemed
            ]);
            $sale_id = $pdo->lastInsertId();

            // 2. Insert items (F1.2: snapshot cost_price) and update stock
            $item_stmt = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_id, qty, unit_price, cost_price, total) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stock_stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");

            foreach ($processed_items as $item) {
                $item_stmt->execute([
                    $sale_id,
                    $item['id'],
                    $item['qty'],
                    $item['price'],
                    $item['cost'],
                    $item['total']
                ]);

                $stock_stmt->execute([$item['qty'], $item['id']]);
                
                // M11: Notification Trigger - Low Stock
                $check_low_stmt = $pdo->prepare("SELECT name, stock_qty, min_stock FROM products WHERE id = ?");
                $check_low_stmt->execute([$item['id']]);
                $prod_status = $check_low_stmt->fetch();
                
                if ($prod_status && $prod_status['stock_qty'] <= $prod_status['min_stock']) {
                    // Check if an unread notification already exists for this product
                    $notif_exists = $pdo->prepare("SELECT id FROM notifications WHERE title LIKE ? AND is_read = 0");
                    $notif_exists->execute(["%Low stock: " . $prod_status['name'] . "%"]);
                    
                    if (!$notif_exists->fetch()) {
                        $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
                        $ins_notif->execute([
                            'Stock Manager', 
                            'Low stock: ' . $prod_status['name'], 
                            'Stock level is now ' . $prod_status['stock_qty'] . '. Minimum is ' . $prod_status['min_stock'], 
                            'warning', 
                            '#stock'
                        ]);
                        
                        // Also for Admin
                        $ins_notif->execute([
                            'Admin', 
                            'Low stock: ' . $prod_status['name'], 
                            'Stock level is now ' . $prod_status['stock_qty'] . '. Minimum is ' . $prod_status['min_stock'], 
                            'warning', 
                            '#stock'
                        ]);
                    }
                }
                
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
                
                
                // If there is debt (total > paid_amount), increase customer balance
                $debt = $total - $paid_amount;
                if ($debt > 0) {
                    $balance_stmt = $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?");
                    $balance_stmt->execute([$debt, $customer_id]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => 'Sale completed successfully', 'sale_id' => $sale_id, 'total' => $total]);
            break;

        case 'cancel_sale':
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                throw new Exception('Only Admins can cancel sales.');
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
            
            // F2.4: Reverse loyalty points
            if ($sale['customer_id']) {
                $pts_earned = (int)($sale['points_earned'] ?? 0);
                $pts_redeemed = (int)($sale['points_redeemed'] ?? 0);
                if ($pts_earned > 0 || $pts_redeemed > 0) {
                    // Subtract earned, add back redeemed
                    $pts_stmt = $pdo->prepare("UPDATE customers SET loyalty_points = GREATEST(0, loyalty_points - ? + ?) WHERE id = ?");
                    $pts_stmt->execute([$pts_earned, $pts_redeemed, $sale['customer_id']]);
                }
                // Reverse debt balance
                $debt = $sale['total'] - $sale['paid_amount'];
                if ($debt > 0) {
                    $bal_stmt = $pdo->prepare("UPDATE customers SET balance = GREATEST(0, balance - ?) WHERE id = ?");
                    $bal_stmt->execute([$debt, $sale['customer_id']]);
                }
            }
            
            // Restore stock
            $items_stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $items_stmt->execute([$sale_id]);
            $items = $items_stmt->fetchAll();
            
            $restore_stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
            $batch_stmt = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, 'BA', ?, ?)");
            
            foreach ($items as $item) {
                // M7: Only restore stock that hasn't already been returned
                $net_qty = $item['qty'] - ($item['returned_qty'] ?? 0);
                if ($net_qty > 0) {
                    $restore_stmt->execute([$net_qty, $item['product_id']]);
                    $batch_stmt->execute([$item['product_id'], $net_qty, $net_qty]);
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => 'Sale cancelled and stock restored']);
            break;

        case 'process_return':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Only Admins and Cashiers can process returns.');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['items']) || !$data['sale_id']) {
                throw new Exception('Invalid return data');
            }

            $pdo->beginTransaction();
            
            $sale_id = (int)$data['sale_id'];
            $reason = $data['reason'] ?? '';
            $user_id = $_SESSION['user_id'] ?? 1;

            // Fetch sale header
            $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? FOR UPDATE");
            $stmt->execute([$sale_id]);
            $sale = $stmt->fetch();
            if (!$sale) throw new Exception('Sale not found');

            $total_return_amount = 0;
            $return_items = [];

            foreach ($data['items'] as $item) {
                $product_id = (int)$item['product_id'];
                $qty_to_return = (int)$item['qty'];

                if ($qty_to_return <= 0) continue;

                // Validate against sale items
                $item_stmt = $pdo->prepare("SELECT id, qty, returned_qty, unit_price FROM sale_items WHERE sale_id = ? AND product_id = ? FOR UPDATE");
                $item_stmt->execute([$sale_id, $product_id]);
                $sale_item = $item_stmt->fetch();

                if (!$sale_item) throw new Exception("Product ID $product_id not found in this sale");

                $max_returnable = $sale_item['qty'] - $sale_item['returned_qty'];
                if ($qty_to_return > $max_returnable) {
                    throw new Exception("Cannot return more than purchased for Product ID $product_id");
                }

                $item_return_value = $qty_to_return * $sale_item['unit_price'];
                $total_return_amount += $item_return_value;

                // Update sale_items
                $update_item_stmt = $pdo->prepare("UPDATE sale_items SET returned_qty = returned_qty + ? WHERE id = ?");
                $update_item_stmt->execute([$qty_to_return, $sale_item['id']]);

                // Increase stock
                $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$qty_to_return, $product_id]);
                
                // Create stock batch for returned items
                $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, 'BA', ?, ?)")
                    ->execute([$product_id, $qty_to_return, $qty_to_return]);

                $return_items[] = [
                    'product_id' => $product_id,
                    'qty' => $qty_to_return,
                    'unit_price' => $sale_item['unit_price']
                ];
            }

            if (empty($return_items)) throw new Exception('No items to return');

            // Insert into sale_returns
            $stmt = $pdo->prepare("INSERT INTO sale_returns (sale_id, user_id, reason, total_amount) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sale_id, $user_id, $reason, $total_return_amount]);
            $return_id = $pdo->lastInsertId();

            // Insert into sale_return_items
            $stmt = $pdo->prepare("INSERT INTO sale_return_items (return_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)");
            foreach ($return_items as $ri) {
                $stmt->execute([$return_id, $ri['product_id'], $ri['qty'], $ri['unit_price']]);
            }

            // Adjust customer balance (decrease debt)
            if ($sale['customer_id']) {
                // We decrease balance (debt) up to the return amount
                // If it's more than the balance, it stays at 0 (or goes negative if we support credits, but let's keep it simple)
                $pdo->prepare("UPDATE customers SET balance = GREATEST(0, balance - ?) WHERE id = ?")
                    ->execute([$total_return_amount, $sale['customer_id']]);
            }

            $pdo->commit();
            
            // M11: Notification Trigger - Customer Return
            $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $ret_msg = "Customer return processed for Sale #$sale_id by " . ($_SESSION['user_name'] ?? 'User') . ". Amount: " . number_format($total_return_amount, 2);
            if ($reason) $ret_msg .= " Reason: $reason";
            $ins_notif->execute(['Admin', 'Customer Return Processed', $ret_msg, 'warning', '#sales_history']);

            echo json_encode(['success' => 'Return processed successfully', 'return_id' => $return_id]);
            break;

        case 'history':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Only Admins and Cashiers can view sales history.');
            }
            $customer_id = $_GET['customer_id'] ?? null;
            $query = "
                SELECT s.*, c.name as customer_name, u.name as user_name,
                       (SELECT COUNT(*) FROM sale_returns WHERE sale_id = s.id) as has_returns
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
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Access denied.');
            }
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Sale ID is required');
            
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
    error_log("Sales API Error: " . $e->getMessage() . " | User: " . ($_SESSION['user_id'] ?? 'unknown'));
    echo json_encode(['error' => 'An error occurred while processing the sale.']);
}
// Removed closing tag
