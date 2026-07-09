<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list_products';

try {
    switch ($action) {
        case 'list_products':
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.selling_price, p.purchase_price, p.stock_qty, p.min_stock, p.barcode, p.image, p.category_id, p.brand_id,
                       c.name as category_name, b.name as brand_name,
                       (SELECT MIN(expiry_date) FROM stock_batches WHERE product_id = p.id AND remaining_qty > 0) as expiry_date
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.status = 'Active' 
                ORDER BY p.name ASC
            ");
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch batches separately for compatibility instead of JSON_ARRAYAGG
            // Fix N+1 Query: Fetch batches in bulk by chunking product IDs
            $product_ids = array_column($products, 'id');
            
            // Initialize batches to empty JSON arrays initially
            foreach ($products as &$product) {
                $product['batches'] = '[]';
            }
            unset($product); // break reference

            if (!empty($product_ids)) {
                $batches_by_product = [];
                $chunks = array_chunk($product_ids, 500);

                foreach ($chunks as $chunk) {
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    $batch_stmt = $pdo->prepare("
                        SELECT product_id, purchase_type as type, remaining_qty as qty
                        FROM stock_batches
                        WHERE product_id IN ($placeholders) AND remaining_qty > 0
                        ORDER BY created_at ASC
                    ");
                    $batch_stmt->execute($chunk);
                    $all_batches = $batch_stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($all_batches as $batch) {
                        $pid = $batch['product_id'];
                        unset($batch['product_id']); // remove product_id so it matches the original format
                        $batches_by_product[$pid][] = $batch;
                    }
                }

                // Re-assign batches back to products
                foreach ($products as &$product) {
                    if (isset($batches_by_product[$product['id']])) {
                        $product['batches'] = json_encode($batches_by_product[$product['id']]);
                    }
                }
                unset($product); // break reference
            }
            
            echo json_encode(['products' => $products]);
            break;

        case 'get_batches':
            $product_id = intval($_GET['product_id'] ?? 0);
            if (!$product_id) throw new Exception('product_id is required');

            $stmt = $pdo->prepare("
                SELECT id,
                       purchase_type   AS document_type,
                       initial_qty     AS quantity,
                       remaining_qty,
                       expiry_date,
                       DATE(created_at) AS received_date
                FROM stock_batches
                WHERE product_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$product_id]);
            $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add a human-readable batch reference
            foreach ($batches as &$b) {
                $b['batch_number'] = 'BT-' . str_pad($b['id'], 4, '0', STR_PAD_LEFT);
            }

            echo json_encode(['success' => true, 'batches' => $batches]);
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
            // Validate prices against frontend to prevent desynchronization (Issue 8)
            $frontend_total_price = 0;
            foreach ($data['items'] as $item) {
                $frontend_total_price += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 0);
            }

            // Calculate subtotal
            $db_total_price = 0;
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
                $db_total_price += $price * $qty;
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

            if (abs($frontend_total_price - $db_total_price) > 0.05) {
                throw new Exception("Price desynchronization detected. Some product prices have changed. Please refresh your cart.");
            }

            // Recalculate and validate discount logic
            $manual_discount_amount = isset($data['manual_discount_amount']) && $data['manual_discount_amount'] !== null ? (float)$data['manual_discount_amount'] : null;
            if ($manual_discount_amount !== null) {
                $manual_discount = round(max(0, min($subtotal, $manual_discount_amount)), 2);
            } else {
                $manual_discount_percent = max(0, min(100, (float)($data['manual_discount_percent'] ?? 0)));
                $manual_discount = round($subtotal * ($manual_discount_percent / 100), 2);
            }

            $points_redeemed = max(0, (int)($data['points_redeemed'] ?? 0));
            $points_discount = 0;

            if ($customer_id && $points_redeemed > 0) {
                $cust_stmt = $pdo->prepare("SELECT loyalty_points FROM customers WHERE id = ? FOR UPDATE");
                $cust_stmt->execute([$customer_id]);
                $customer_pts = (int)($cust_stmt->fetchColumn() ?: 0);

                if ($points_redeemed > $customer_pts) {
                    $points_redeemed = $customer_pts;
                }

                // Cap points to prevent total discount from exceeding subtotal
                $max_allowed_points_discount = max(0, $subtotal - $manual_discount);
                $max_allowed_points = floor($max_allowed_points_discount / $point_value);
                if ($points_redeemed > $max_allowed_points) {
                    $points_redeemed = $max_allowed_points;
                }

                $points_discount = $points_redeemed * $point_value;
            } else {
                $points_redeemed = 0;
            }

            $discount_amount = min($subtotal, $manual_discount + $points_discount);

            $taxable = max(0, $subtotal - $discount_amount);
            
            $invoice_type = $data['invoice_type'] ?? 'BV';
            $payment_method = $data['payment_method'] ?? 'Cash';
            
            // M10: Validate ENUM values server-side
            $valid_methods = ['Cash', 'Card'];
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

            if ($payment_status !== 'Paid' && empty($customer_id)) {
                throw new Exception('Debt is only allowed for registered customers. Please select a customer or pay the full amount.');
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

            // Generate and update invoice number
            $prefix = ($invoice_type === 'BL') ? 'BL' : 'FAC';
            $year = date('Y');
            $invoice_number = $prefix . '-' . $year . '-' . str_pad($sale_id, 4, '0', STR_PAD_LEFT);
            $update_invoice_stmt = $pdo->prepare("UPDATE sales SET invoice_number = ? WHERE id = ?");
            $update_invoice_stmt->execute([$invoice_number, $sale_id]);

            // 2. Insert items (FIFO cost snapshot) and update stock
            $item_stmt = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_id, qty, unit_price, cost_price, total) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stock_stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");

            foreach ($processed_items as $item) {
                // FIFO Batch Deduction & Cost calculation
                $remaining_to_deduct = $item['qty'];
                $batches_stmt = $pdo->prepare("SELECT id, remaining_qty, purchase_price FROM stock_batches WHERE product_id = ? AND remaining_qty > 0 ORDER BY created_at ASC FOR UPDATE");
                $batches_stmt->execute([$item['id']]);
                $batches = $batches_stmt->fetchAll();
                
                $update_batch_stmt = $pdo->prepare("UPDATE stock_batches SET remaining_qty = ? WHERE id = ?");
                
                $total_item_cost = 0.00;
                $deducted_qty = 0;
                
                foreach ($batches as $batch) {
                    if ($remaining_to_deduct <= 0) break;
                    
                    $available = (int)$batch['remaining_qty'];
                    $batch_price = (float)$batch['purchase_price'];
                    
                    if ($available >= $remaining_to_deduct) {
                        $new_qty = $available - $remaining_to_deduct;
                        $update_batch_stmt->execute([$new_qty, $batch['id']]);
                        
                        $total_item_cost += $remaining_to_deduct * $batch_price;
                        $deducted_qty += $remaining_to_deduct;
                        $remaining_to_deduct = 0;
                    } else {
                        $update_batch_stmt->execute([0, $batch['id']]);
                        
                        $total_item_cost += $available * $batch_price;
                        $deducted_qty += $available;
                        $remaining_to_deduct -= $available;
                    }
                }

                // Negative stock/out of sync fallback
                if ($remaining_to_deduct > 0) {
                    $fallback_price = $item['cost']; // Snapshot purchase price of the product
                    $total_item_cost += $remaining_to_deduct * $fallback_price;
                    $deducted_qty += $remaining_to_deduct;
                    
                    $adj_batch_stmt = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty, purchase_price) VALUES (?, 'BA', ?, ?, ?)");
                    $adj_batch_stmt->execute([$item['id'], -$remaining_to_deduct, -$remaining_to_deduct, $fallback_price]);
                }

                $actual_cost_price = $deducted_qty > 0 ? ($total_item_cost / $deducted_qty) : 0.00;

                // Insert the sale item with the actual cost
                $item_stmt->execute([
                    $sale_id,
                    $item['id'],
                    $item['qty'],
                    $item['price'],
                    $actual_cost_price,
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

            // 4. Update quotation status if generated from a quotation
            if (!empty($data['quotation_id'])) {
                $quotation_id = (int)$data['quotation_id'];
                $q_stmt = $pdo->prepare("UPDATE quotations SET status = 'Converted' WHERE id = ?");
                $q_stmt->execute([$quotation_id]);
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
                $debt_initial = $sale['total'] - $sale['paid_amount'];
                if ($debt_initial > 0) {
                    $cust_stmt = $pdo->prepare("SELECT balance FROM customers WHERE id = ? FOR UPDATE");
                    $cust_stmt->execute([$sale['customer_id']]);
                    $current_balance = (float)($cust_stmt->fetchColumn() ?: 0);
                    
                    $debt_to_reduce = min($current_balance, $debt_initial);
                    
                    $bal_stmt = $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?");
                    $bal_stmt->execute([$debt_to_reduce, $sale['customer_id']]);
                    
                    $net_refund = $sale['paid_amount'] + ($debt_initial - $debt_to_reduce);
                } else {
                    $net_refund = $sale['paid_amount'];
                }
            } else {
                $net_refund = $sale['paid_amount'];
            }

            // Refund logic
            if ($net_refund > 0) {
                // Check if the sale's session is still open
                $session_stmt = $pdo->prepare("SELECT status FROM cash_sessions WHERE id = ?");
                $session_stmt->execute([$sale['cash_session_id']]);
                $session_status = $session_stmt->fetchColumn();
                
                if ($session_status !== 'Open') {
                    // Record expense in vault_transactions
                    $caisse_stmt = $pdo->query("SELECT id FROM vault_accounts WHERE name = 'Caisse' OR type = 'Cash' LIMIT 1");
                    $caisse_acc = $caisse_stmt->fetch();
                    $caisse_id = $caisse_acc ? $caisse_acc['id'] : null;
                    
                    if ($caisse_id) {
                        $desc = "Refund for Cancelled Sale " . ($sale['invoice_number'] ?: "#" . $sale['id']) . " (from closed session)";
                        $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'SaleCancellation', ?, ?)");
                        $tx_stmt->execute([$caisse_id, $net_refund, $desc, $sale['id'], $_SESSION['user_id'] ?? 1]);
                        
                        // Update vault account balance
                        $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$net_refund, $caisse_id]);
                    }
                }
            }
            
            // Restore stock
            $items_stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $items_stmt->execute([$sale_id]);
            $items = $items_stmt->fetchAll();
            
            $restore_stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
            $batch_stmt = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty, purchase_price) VALUES (?, ?, ?, ?, ?)");
            
            // Helper statement to find the most recent batch type for a product to keep fiscal nature
            $type_stmt = $pdo->prepare("SELECT purchase_type FROM stock_batches WHERE product_id = ? ORDER BY id DESC LIMIT 1");
            
            foreach ($items as $item) {
                // M7: Only restore stock that hasn't already been returned
                $net_qty = $item['qty'] - ($item['returned_qty'] ?? 0);
                if ($net_qty > 0) {
                    $type_stmt->execute([$item['product_id']]);
                    $purchase_type = $type_stmt->fetchColumn() ?: 'BA';

                    $restore_stmt->execute([$net_qty, $item['product_id']]);
                    $batch_stmt->execute([$item['product_id'], $purchase_type, $net_qty, $net_qty, $item['cost_price']]);
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

            $original_subtotal = (float)$sale['subtotal'];
            $total_discount    = (float)$sale['discount'];
            $discount_ratio    = ($original_subtotal > 0) ? (1 - ($total_discount / $original_subtotal)) : 1;
            
            $original_taxable  = max(0, $original_subtotal - $total_discount);
            $effective_tax_rate = ($original_taxable > 0) ? ((float)$sale['tax'] / $original_taxable) : 0;

            foreach ($data['items'] as $item) {
                $product_id = (int)$item['product_id'];
                $qty_to_return = (int)$item['qty'];

                if ($qty_to_return <= 0) continue;

                // Validate against sale items
                $item_stmt = $pdo->prepare("SELECT id, qty, returned_qty, unit_price, cost_price FROM sale_items WHERE sale_id = ? AND product_id = ? FOR UPDATE");
                $item_stmt->execute([$sale_id, $product_id]);
                $sale_item = $item_stmt->fetch();

                if (!$sale_item) throw new Exception("Product ID $product_id not found in this sale");

                $max_returnable = $sale_item['qty'] - $sale_item['returned_qty'];
                if ($qty_to_return > $max_returnable) {
                    throw new Exception("Cannot return more than purchased for Product ID $product_id");
                }

                // Compute HT and TTC return value
                $item_return_value_ht = round($qty_to_return * $sale_item['unit_price'] * $discount_ratio, 2);
                $item_return_value_ttc = round($item_return_value_ht * (1 + $effective_tax_rate), 2);
                $total_return_amount += $item_return_value_ttc;
                $update_item_stmt = $pdo->prepare("UPDATE sale_items SET returned_qty = returned_qty + ? WHERE id = ?");
                $update_item_stmt->execute([$qty_to_return, $sale_item['id']]);

                // Increase stock
                $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$qty_to_return, $product_id]);
                
                // Retrieve the most recent batch type for this product to keep fiscal nature
                $type_stmt = $pdo->prepare("SELECT purchase_type FROM stock_batches WHERE product_id = ? ORDER BY id DESC LIMIT 1");
                $type_stmt->execute([$product_id]);
                $purchase_type = $type_stmt->fetchColumn() ?: 'BA';

                // Create stock batch for returned items
                $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty, purchase_price) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$product_id, $purchase_type, $qty_to_return, $qty_to_return, $sale_item['cost_price']]);

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

            // Adjust customer balance (decrease debt, can become negative representing a store credit)
            if ($sale['customer_id']) {
                $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?")
                    ->execute([$total_return_amount, $sale['customer_id']]);
            } else {
                // Anonymous refund: record expense in vault_transactions if the session is closed
                if ($total_return_amount > 0) {
                    $session_stmt = $pdo->prepare("SELECT status FROM cash_sessions WHERE id = ?");
                    $session_stmt->execute([$sale['cash_session_id']]);
                    $session_status = $session_stmt->fetchColumn();
                    
                    if ($session_status !== 'Open') {
                        $caisse_stmt = $pdo->query("SELECT id FROM vault_accounts WHERE name = 'Caisse' OR type = 'Cash' LIMIT 1");
                        $caisse_acc = $caisse_stmt->fetch();
                        $caisse_id = $caisse_acc ? $caisse_acc['id'] : null;
                        
                        if ($caisse_id) {
                            $desc = "Refund for Return on Sale " . ($sale['invoice_number'] ?: "#" . $sale['id']) . " (from closed session)";
                            $tx_stmt = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'SaleReturn', ?, ?)");
                            $tx_stmt->execute([$caisse_id, $total_return_amount, $desc, $return_id, $user_id]);
                            
                            // Update vault account balance
                            $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$total_return_amount, $caisse_id]);
                        }
                    }
                }
            }

            // Fix #7: Notification moved BEFORE commit() to be part of the transaction
            $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $ret_msg = "Customer return processed for Sale " . ($sale['invoice_number'] ?: "#$sale_id") . " by " . ($_SESSION['user_name'] ?? 'User') . ". Amount: " . number_format($total_return_amount, 2);
            if ($reason) $ret_msg .= " Reason: $reason";
            $ins_notif->execute(['Admin', 'Customer Return Processed', $ret_msg, 'warning', '#sales_history']);

            $pdo->commit();
            echo json_encode(['success' => 'Return processed successfully', 'return_id' => $return_id]);
            break;

        case 'history':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Only Admins and Cashiers can view sales history.');
            }
            $customer_id = $_GET['customer_id'] ?? null;
            $query = "
                SELECT s.*, c.name as customer_name, u.name as user_name,
                       (SELECT COUNT(*) FROM sale_returns WHERE sale_id = s.id) as has_returns,
                       COALESCE(
                           CASE 
                               WHEN s.subtotal > 0 THEN (si.net_subtotal * (1 - (s.discount / s.subtotal))) - si.net_cogs
                               ELSE -si.net_cogs
                           END, 
                           0
                       ) as profit
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN (
                    SELECT 
                        sale_id,
                        SUM((qty - returned_qty) * unit_price) as net_subtotal,
                        SUM((qty - returned_qty) * cost_price) as net_cogs
                    FROM sale_items
                    GROUP BY sale_id
                ) si ON si.sale_id = s.id
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
                SELECT s.*, c.name as customer_name, c.phone as customer_phone, 
                       c.wilaya as customer_wilaya, c.balance as customer_balance, 
                       u.name as user_name
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
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Sales API Error: " . $e->getMessage() . " | User: " . ($_SESSION['user_id'] ?? 'unknown'));
    // Bug #5 Fix: Only mask PDO errors, not business exceptions
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Sales API Error: " . $e->getMessage() . " | User: " . ($_SESSION['user_id'] ?? 'unknown'));
    echo json_encode(['error' => $e->getMessage()]);
}
// Removed closing tag
