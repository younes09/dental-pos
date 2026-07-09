<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            $stmt = $pdo->prepare("
                SELECT po.*, s.name as supplier_name, s.company as supplier_company
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                ORDER BY po.date DESC, po.id DESC
            ");
            $stmt->execute();
            $pos = $stmt->fetchAll();
            echo json_encode(['data' => $pos]);
            break;

        case 'save':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data || empty($data['items'])) {
                echo json_encode(['error' => 'Invalid data received']);
                exit;
            }

            $pdo->beginTransaction();

            $supplier_id = $data['supplier_id'];
            $date = $data['date'] ?? date('Y-m-d');
            $status = $data['status'] ?? 'Pending';
            $items = $data['items'];
            $purchase_type = $data['purchase_type'] ?? 'BA'; // Default to BA if not provided
            $paid_amount = isset($data['paid_amount']) ? (float)$data['paid_amount'] : 0;
            
            // Recalculate total server-side
            $total = 0;
            $items_clean = [];
            foreach ($items as $item) {
                $qty = (int)$item['qty'];
                $cost = (float)$item['unit_cost'];
                if ($qty <= 0) throw new Exception("Invalid PO quantity");
                if ($cost < 0) throw new Exception("Invalid unit cost");
                $total += ($qty * $cost);
                $item['qty'] = $qty;
                $item['unit_cost'] = $cost;
                $items_clean[] = $item;
            }
            $items = $items_clean;

            // Determine payment_status
            $payment_status = 'Unpaid';
            if ($status === 'Received') {
                // Guard: no overpayment
                if ($paid_amount > $total) {
                    $pdo->rollBack();
                    echo json_encode(['error' => 'Payment amount cannot exceed order total.']);
                    exit;
                }
                // Guard: sufficient account balance
                $account_id = $data['account_id'] ?? null;
                if ($account_id && $paid_amount > 0) {
                    $balStmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                    $balStmt->execute([$account_id]);
                    $acctBalance = (float)($balStmt->fetchColumn() ?? 0);
                    if ($paid_amount > $acctBalance) {
                        $pdo->rollBack();
                        echo json_encode(['error' => 'Insufficient account balance. Available: ' . number_format($acctBalance, 2) . ', Required: ' . number_format($paid_amount, 2)]);
                        exit;
                    }
                }

                if ($paid_amount >= $total && $total > 0) {
                    $payment_status = 'Paid';
                } elseif ($paid_amount > 0) {
                    $payment_status = 'Partial';
                }
            } else {
                $paid_amount = 0; // Don't allow prepayments for pending orders (keep it simple for now)
            }

            $stmt = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, date, status, total, paid_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$supplier_id, $date, $status, $total, $paid_amount, $payment_status]);
            $po_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, received_qty, unit_cost, old_unit_cost) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty, expiry_date, purchase_price) VALUES (?, ?, ?, ?, ?, ?)");

            // Pre-fetch old prices in bulk to avoid N+1 queries.
            $old_prices_map = [];
            $product_ids_for_query = array_unique(array_column($items, 'product_id'));
            if (!empty($product_ids_for_query)) {
                // Batch queries to avoid placeholder limits
                $chunks = array_chunk($product_ids_for_query, 500);
                foreach ($chunks as $chunk) {
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    $stmtGetOldPrices = $pdo->prepare("SELECT id, purchase_price FROM products WHERE id IN ($placeholders)");
                    $stmtGetOldPrices->execute($chunk);
                    while ($row = $stmtGetOldPrices->fetch(PDO::FETCH_ASSOC)) {
                        $old_prices_map[$row['id']] = $row['purchase_price'];
                    }
                }
            }

            foreach ($items as $item) {
                $received_qty = ($status === 'Received') ? $item['qty'] : 0;
                
                $old_price = isset($old_prices_map[$item['product_id']]) ? $old_prices_map[$item['product_id']] : 0;
                
                $stmtItem->execute([$po_id, $item['product_id'], $item['qty'], $received_qty, $item['unit_cost'], $old_price]);
                
                if ($status === 'Received') {
                    $stmtUpdateProduct->execute([$item['qty'], $item['unit_cost'], $item['product_id']]);
                    $stmtBatch->execute([$item['product_id'], $purchase_type, $item['qty'], $item['qty'], $item['expiry_date'] ?? null, $item['unit_cost']]);
                }
            }

            // If received, update supplier balance based on unpaid amount and record vault transaction
            if ($status === 'Received') {
                $debt = $total - $paid_amount;
                if ($debt > 0) {
                    $stmtSupplier = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?");
                    $stmtSupplier->execute([$debt, $supplier_id]);
                }

                $account_id = $data['account_id'] ?? null;
                if ($account_id && $paid_amount > 0) {
                    $user_id = $_SESSION['user_id'] ?? 1;
                    $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'PurchaseOrder', ?, ?)");
                    $stmtTx->execute([
                        $account_id,
                        $paid_amount,
                        "Supplier Order Payment #$po_id (Direct Receipt)",
                        $po_id,
                        $user_id
                    ]);

                    $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$paid_amount, $account_id]);
                }
            }

            // Fix #8: Notifications moved BEFORE commit() to be part of the transaction
            if ($status === 'Pending') {
                $supplier_name = $data['supplier_name'] ?? '';
                if (empty($supplier_name)) {
                    $stmtSuppName = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
                    $stmtSuppName->execute([$supplier_id]);
                    $supplier_name = $stmtSuppName->fetchColumn() ?: 'Unknown';
                }

                $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
                $po_msg = "New Purchase Order #$po_id has been created for " . $supplier_name . ". Status: Pending.";
                $ins_notif->execute(['Admin', 'New PO: #' . $po_id, $po_msg, 'info', '#purchase_orders']);
                $ins_notif->execute(['Stock Manager', 'New PO: #' . $po_id, $po_msg, 'info', '#purchase_orders']);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Purchase order saved successfully', 'id' => $po_id]);
            break;

        case 'receive_order':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data || !isset($data['po_id']) || !isset($data['purchase_type']) || !isset($data['items'])) {
                echo json_encode(['error' => 'Invalid data']);
                exit;
            }

            $pdo->beginTransaction();

            $po_id = $data['po_id'];
            $purchase_type = $data['purchase_type'];
            $items_to_receive = $data['items'];
            $paid_amount = isset($data['paid_amount']) ? (float)$data['paid_amount'] : 0;
            $user_id = $_SESSION['user_id'] ?? 1;

            // Check if PO exists and is not already fully received (lock row to prevent race conditions - Issue 15)
            $stmt = $pdo->prepare("SELECT supplier_id, status, total, paid_amount FROM purchase_orders WHERE id = ? FOR UPDATE");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch();

            if (!$po) throw new Exception("Order not found");
            if ($po['status'] === 'Received') throw new Exception("Order already received");

            // Prepare statements
            $stmtUpdateItemRow = $pdo->prepare("UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE id = ? AND po_id = ?");
            $stmtGetItemCost = $pdo->prepare("SELECT unit_cost FROM purchase_order_items WHERE id = ?");
            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty, expiry_date, purchase_price) VALUES (?, ?, ?, ?, ?, ?)");

            $total_received_value = 0;

            foreach ($items_to_receive as $recv_item) {
                $item_id = $recv_item['item_id'];
                $product_id = $recv_item['product_id'];
                $recv_qty_now = (int)$recv_item['receiving_qty'];

                if ($recv_qty_now <= 0) continue;

                // F5.4: Cap over-receiving — check remaining receivable
                $cap_stmt = $pdo->prepare("SELECT qty, received_qty FROM purchase_order_items WHERE id = ? FOR UPDATE");
                $cap_stmt->execute([$item_id]);
                $cap_data = $cap_stmt->fetch();
                if (!$cap_data) continue;
                
                $max_receivable = (int)$cap_data['qty'] - (int)$cap_data['received_qty'];
                if ($max_receivable <= 0) continue; // Already fully received
                if ($recv_qty_now > $max_receivable) {
                    $recv_qty_now = $max_receivable; // Cap to remaining
                }

                // Update the PO item's received qty
                $stmtUpdateItemRow->execute([$recv_qty_now, $item_id, $po_id]);

                // Get unit cost to update product purchase price
                $stmtGetItemCost->execute([$item_id]);
                $item_cost = $stmtGetItemCost->fetchColumn();

                // Update product stock and creation batch
                $stmtUpdateProduct->execute([$recv_qty_now, $item_cost, $product_id]);
                $stmtBatch->execute([$product_id, $purchase_type, $recv_qty_now, $recv_qty_now, $recv_item['expiry_date'] ?? null, $item_cost]);

                $total_received_value += ($recv_qty_now * $item_cost);
            }

            // Check if all items are fully received
            $stmtCheck = $pdo->prepare("
                SELECT SUM(qty) as total_qty, SUM(received_qty) as total_received 
                FROM purchase_order_items 
                WHERE po_id = ?
            ");
            $stmtCheck->execute([$po_id]);
            $qty_data = $stmtCheck->fetch();

            $new_status = 'Partial';
            if ($qty_data && $qty_data['total_received'] >= $qty_data['total_qty']) {
                $new_status = 'Received';
            }

            // Fix #3: Guard against overpayment BEFORE any DB writes
            if ($paid_amount > 0 && $paid_amount > $total_received_value) {
                throw new Exception('Payment amount cannot exceed the value of items being received.');
            }

            // Update PO status, paid amount
            $new_total_paid = $po['paid_amount'] + $paid_amount;
            $payment_status = 'Unpaid';
            if ($new_total_paid >= $po['total'] && $po['total'] > 0) {
                $payment_status = 'Paid';
            } elseif ($new_total_paid > 0) {
                $payment_status = 'Partial';
            }

            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ?, paid_amount = ?, payment_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $new_total_paid, $payment_status, $po_id]);

            // Update supplier balance
            $net_debt_increase = $total_received_value - $paid_amount;
            if ($net_debt_increase != 0) {
                $stmtSupplier = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?");
                $stmtSupplier->execute([$net_debt_increase, $po['supplier_id']]);
            }

            // F10.3: Vault Integration - Record Expense if account is specified
            $account_id = $data['account_id'] ?? null;
            if ($account_id && $paid_amount > 0) {
                // Guard: sufficient account balance
                $balStmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                $balStmt->execute([$account_id]);
                $acctBalance = (float)($balStmt->fetchColumn() ?? 0);
                if ($paid_amount > $acctBalance) {
                    $pdo->rollBack();
                    echo json_encode(['error' => 'Insufficient account balance. Available: ' . number_format($acctBalance, 2) . ', Required: ' . number_format($paid_amount, 2)]);
                    exit;
                }

                $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'PurchaseOrder', ?, ?)");
                $stmtTx->execute([
                    $account_id,
                    $paid_amount,
                    "Supplier Order Payment #$po_id",
                    $po_id,
                    $user_id
                ]);

                $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$paid_amount, $account_id]);
            }

            // M11: Notification Trigger - PO Received (INSIDE transaction)
            $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $po_msg = "Order #$po_id has been " . (($new_status === 'Received') ? 'fully received.' : 'partially received.');
            $ins_notif->execute(['Admin', 'PO Received: #' . $po_id, $po_msg, 'success', '#purchase_orders']);
            $ins_notif->execute(['Stock Manager', 'PO Received: #' . $po_id, $po_msg, 'success', '#purchase_orders']);

            $pdo->commit();

            $msg = ($new_status === 'Received') ? 'Order fully received' : 'Order partially received';
            echo json_encode(['success' => $msg]);
            break;

        case 'process_return':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['items']) || !$data['po_id']) {
                throw new Exception('Invalid return data');
            }

            $pdo->beginTransaction();

            $po_id = (int)$data['po_id'];
            $reason = $data['reason'] ?? '';
            $user_id = $_SESSION['user_id'] ?? 1;

            // Fetch PO header
            $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch();
            if (!$po) throw new Exception('Purchase Order not found');

            $total_return_amount = 0;
            $return_items = [];

            foreach ($data['items'] as $item) {
                $product_id = (int)$item['product_id'];
                $qty_to_return = (int)$item['qty'];

                if ($qty_to_return <= 0) continue;

                // Validate against PO items
                $item_stmt = $pdo->prepare("SELECT id, received_qty, returned_qty, unit_cost FROM purchase_order_items WHERE po_id = ? AND product_id = ? FOR UPDATE");
                $item_stmt->execute([$po_id, $product_id]);
                $po_item = $item_stmt->fetch();

                if (!$po_item) throw new Exception("Product ID $product_id not found in this Purchase Order");

                $max_returnable = $po_item['received_qty'] - $po_item['returned_qty'];
                if ($qty_to_return > $max_returnable) {
                    throw new Exception("Cannot return more than received for Product ID $product_id");
                }

                $item_return_value = $qty_to_return * $po_item['unit_cost'];
                $total_return_amount += $item_return_value;

                // Update purchase_order_items
                $update_item_stmt = $pdo->prepare("UPDATE purchase_order_items SET returned_qty = returned_qty + ? WHERE id = ?");
                $update_item_stmt->execute([$qty_to_return, $po_item['id']]);

                // Decrease stock
                $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?")->execute([$qty_to_return, $product_id]);
                
                // FIFO Batch Deduction for return
                $remaining_to_deduct = $qty_to_return;
                $batches_stmt = $pdo->prepare("SELECT id, remaining_qty FROM stock_batches WHERE product_id = ? AND remaining_qty > 0 ORDER BY created_at DESC FOR UPDATE"); // Deduct from newest first for returns? Or FIFO? Usually returns to supplier are the ones just received. Let's use FIFO for consistency with sales, or LIFO for returns? FIFO is safer for stock valuation.
                $batches_stmt->execute([$product_id]);
                $batches = $batches_stmt->fetchAll();
                
                $update_batch_stmt = $pdo->prepare("UPDATE stock_batches SET remaining_qty = ? WHERE id = ?");
                foreach ($batches as $batch) {
                    if ($remaining_to_deduct <= 0) break;
                    $available = (int)$batch['remaining_qty'];
                    if ($available >= $remaining_to_deduct) {
                        $update_batch_stmt->execute([$available - $remaining_to_deduct, $batch['id']]);
                        $remaining_to_deduct = 0;
                    } else {
                        $update_batch_stmt->execute([0, $batch['id']]);
                        $remaining_to_deduct -= $available;
                    }
                }

                $return_items[] = [
                    'product_id' => $product_id,
                    'qty' => $qty_to_return,
                    'unit_cost' => $po_item['unit_cost']
                ];
            }

            if (empty($return_items)) throw new Exception('No items to return');

            // Insert into purchase_returns
            $stmt = $pdo->prepare("INSERT INTO purchase_returns (po_id, user_id, reason, total_amount) VALUES (?, ?, ?, ?)");
            $stmt->execute([$po_id, $user_id, $reason, $total_return_amount]);
            $return_id = $pdo->lastInsertId();

            // Insert into purchase_return_items
            $stmt = $pdo->prepare("INSERT INTO purchase_return_items (return_id, product_id, qty, unit_cost) VALUES (?, ?, ?, ?)");
            foreach ($return_items as $ri) {
                $stmt->execute([$return_id, $ri['product_id'], $ri['qty'], $ri['unit_cost']]);
            }

            // Adjust supplier balance (decrease debt)
            if ($po['supplier_id']) {
                $pdo->prepare("UPDATE suppliers SET balance = GREATEST(0, balance - ?) WHERE id = ?")
                    ->execute([$total_return_amount, $po['supplier_id']]);
            }

            // M11: Notification Trigger - Supplier Return (INSIDE transaction)
            $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $ret_msg = "Return to supplier processed for PO #$po_id. Amount: " . number_format($total_return_amount, 2);
            if ($reason) $ret_msg .= " Reason: $reason";
            
            $ins_notif->execute(['Admin', 'Supplier Return Processed', $ret_msg, 'warning', '#purchase_orders']);
            $ins_notif->execute(['Stock Manager', 'Supplier Return Processed', $ret_msg, 'warning', '#purchase_orders']);

            $pdo->commit();

            echo json_encode(['success' => 'Supplier return processed successfully', 'return_id' => $return_id]);
            break;

        case 'get_details':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) throw new Exception('Purchase Order ID is required.');
            
            // Get PO header
            $stmt = $pdo->prepare("
                SELECT po.*, s.name as supplier_name, s.company as supplier_company, s.phone as supplier_phone, s.email as supplier_email
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                WHERE po.id = ?
            ");
            $stmt->execute([$id]);
            $po = $stmt->fetch();
            
            if (!$po) {
                echo json_encode(['error' => 'Purchase order not found']);
                exit;
            }
            
            // Get PO items
            $stmt = $pdo->prepare("
                SELECT poi.*, p.name as product_name, p.barcode
                FROM purchase_order_items poi
                JOIN products p ON poi.product_id = p.id
                WHERE poi.po_id = ?
            ");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll();
            
            echo json_encode([
                'order' => $po,
                'items' => $items
            ]);
            break;

        case 'delete':
            // F4.1: Only Admin can delete purchase orders
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                throw new Exception('Only Admins can delete purchase orders.');
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) throw new Exception('Purchase Order ID is required.');
            
            $pdo->beginTransaction();

            $po_stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE");
            $po_stmt->execute([$id]);
            $po = $po_stmt->fetch();

            if (!$po) {
                throw new Exception('Purchase Order not found.');
            }

            // Reverse stock and financials if order has received stock
            if ($po['status'] === 'Received' || $po['status'] === 'Partial') {
                $items_stmt = $pdo->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? AND received_qty > 0 FOR UPDATE");
                $items_stmt->execute([$id]);
                $received_items = $items_stmt->fetchAll();

                if (!empty($received_items)) {
                    // Check if enough stock exists to reverse
                    $stmtGetProductStock = $pdo->prepare("SELECT name, stock_qty FROM products WHERE id = ? FOR UPDATE");
                    foreach ($received_items as $item) {
                        $stmtGetProductStock->execute([$item['product_id']]);
                        $product = $stmtGetProductStock->fetch();
                        
                        $net_received = $item['received_qty'] - $item['returned_qty'];
                        if ($net_received > 0) {
                            if (!$product || $product['stock_qty'] < $net_received) {
                                $p_name = $product ? $product['name'] : 'Unknown Product';
                                $curr_stock = $product ? $product['stock_qty'] : 0;
                                throw new Exception("Cannot delete purchase order. Product '{$p_name}' has only {$curr_stock} units in stock, but {$net_received} units need to be reversed.");
                            }
                        }
                    }

                    // Process reversals
                    $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                    $stmtGetBatches = $pdo->prepare("SELECT id, remaining_qty FROM stock_batches WHERE product_id = ? AND remaining_qty > 0 ORDER BY created_at DESC FOR UPDATE");
                    $stmtUpdateBatch = $pdo->prepare("UPDATE stock_batches SET remaining_qty = ? WHERE id = ?");

                    $total_received_value = 0;
                    $total_returned_value = 0;

                    foreach ($received_items as $item) {
                        $net_received = $item['received_qty'] - $item['returned_qty'];
                        if ($net_received > 0) {
                            $stmtUpdateProduct->execute([$net_received, $item['product_id']]);

                            // Deduct from batches using LIFO order
                            $remaining_to_deduct = $net_received;
                            $stmtGetBatches->execute([$item['product_id']]);
                            $batches = $stmtGetBatches->fetchAll();

                            foreach ($batches as $batch) {
                                if ($remaining_to_deduct <= 0) break;
                                $available = (int)$batch['remaining_qty'];
                                if ($available >= $remaining_to_deduct) {
                                    $stmtUpdateBatch->execute([$available - $remaining_to_deduct, $batch['id']]);
                                    $remaining_to_deduct = 0;
                                } else {
                                    $stmtUpdateBatch->execute([0, $batch['id']]);
                                    $remaining_to_deduct -= $available;
                                }
                            }
                        }
                        $total_received_value += ($item['received_qty'] * $item['unit_cost']);
                        $total_returned_value += ($item['returned_qty'] * $item['unit_cost']);
                    }

                    // Adjust supplier debt if supplier exists
                    if ($po['supplier_id']) {
                        $net_debt = ($total_received_value - $total_returned_value) - (float)$po['paid_amount'];
                        if ($net_debt > 0) {
                            $stmtSupplier = $pdo->prepare("UPDATE suppliers SET balance = GREATEST(0, balance - ?) WHERE id = ?");
                            $stmtSupplier->execute([$net_debt, $po['supplier_id']]);
                        }
                    }

                    // Refund vault payments
                    $stmtTx = $pdo->prepare("SELECT account_id, amount FROM vault_transactions WHERE related_type = 'PurchaseOrder' AND related_id = ? AND type = 'Expense' FOR UPDATE");
                    $stmtTx->execute([$id]);
                    $vault_txs = $stmtTx->fetchAll();

                    if (!empty($vault_txs)) {
                        $user_id = $_SESSION['user_id'] ?? 1;
                        $stmtRefundTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Income', ?, ?, 'PurchaseOrder', ?, ?)");
                        $stmtUpdateAcct = $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?");

                        foreach ($vault_txs as $tx) {
                            $stmtUpdateAcct->execute([$tx['amount'], $tx['account_id']]);
                            $stmtRefundTx->execute([
                                $tx['account_id'],
                                $tx['amount'],
                                "Refund for Deleted Purchase Order #$id",
                                $id,
                                $user_id
                            ]);
                        }
                    }
                }
            }

            // Delete associated returns first (due to foreign key constraint)
            $pdo->prepare("DELETE FROM purchase_returns WHERE po_id = ?")->execute([$id]);

            // Deleting the PO (cascades to purchase_order_items)
            $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
            $stmt->execute([$id]);

            // Clear references to the deleted PO in vault transactions
            $pdo->prepare("UPDATE vault_transactions SET related_id = NULL, description = CONCAT(description, ' (PO Deleted)') WHERE related_type = 'PurchaseOrder' AND related_id = ?")->execute([$id]);

            $pdo->commit();
            echo json_encode(['success' => 'Purchase order and associated returns deleted successfully, calculations adjusted.']);
            break;

        case 'cancel':
            // Only Admin can cancel purchase orders
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                throw new Exception('Only Admins can cancel purchase orders.');
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            if (!$id) throw new Exception('Purchase Order ID is required.');

            $pdo->beginTransaction();

            // Fetch PO details FOR UPDATE
            $po_stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE");
            $po_stmt->execute([$id]);
            $po = $po_stmt->fetch();

            if (!$po) {
                throw new Exception('Purchase Order not found.');
            }

            $po_status = $po['status'];

            if ($po_status === 'Cancelled') {
                throw new Exception('This purchase order is already cancelled.');
            }

            // F2.3 / Custom: Block cancel if returns are processed
            $returns_check = $pdo->prepare("SELECT COUNT(*) FROM purchase_returns WHERE po_id = ?");
            $returns_check->execute([$id]);
            if ((int)$returns_check->fetchColumn() > 0) {
                throw new Exception('Cannot cancel a purchase order that has processed returns. Adjust/delete the returns first.');
            }

            // Retrieve received items FOR UPDATE
            $items_stmt = $pdo->prepare("SELECT * FROM purchase_order_items WHERE po_id = ? AND received_qty > 0 FOR UPDATE");
            $items_stmt->execute([$id]);
            $received_items = $items_stmt->fetchAll();

            // Guard: Check if we have enough stock to reverse the PO
            if (!empty($received_items)) {
                $stmtGetProductStock = $pdo->prepare("SELECT name, stock_qty FROM products WHERE id = ? FOR UPDATE");
                foreach ($received_items as $item) {
                    $stmtGetProductStock->execute([$item['product_id']]);
                    $product = $stmtGetProductStock->fetch();
                    if (!$product || $product['stock_qty'] < $item['received_qty']) {
                        $p_name = $product ? $product['name'] : 'Unknown Product';
                        $curr_stock = $product ? $product['stock_qty'] : 0;
                        throw new Exception("Cannot cancel purchase order. Product '{$p_name}' has only {$curr_stock} units in stock, but {$item['received_qty']} units need to be reversed.");
                    }
                }

                // Process reversals
                $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                $stmtGetBatches = $pdo->prepare("SELECT id, remaining_qty FROM stock_batches WHERE product_id = ? AND remaining_qty > 0 ORDER BY created_at DESC FOR UPDATE");
                $stmtUpdateBatch = $pdo->prepare("UPDATE stock_batches SET remaining_qty = ? WHERE id = ?");

                $total_received_value = 0;

                foreach ($received_items as $item) {
                    // Update product stock
                    $stmtUpdateProduct->execute([$item['received_qty'], $item['product_id']]);

                    // Deduct from batches using LIFO order
                    $remaining_to_deduct = (int)$item['received_qty'];
                    $stmtGetBatches->execute([$item['product_id']]);
                    $batches = $stmtGetBatches->fetchAll();

                    foreach ($batches as $batch) {
                        if ($remaining_to_deduct <= 0) break;
                        $available = (int)$batch['remaining_qty'];
                        if ($available >= $remaining_to_deduct) {
                            $stmtUpdateBatch->execute([$available - $remaining_to_deduct, $batch['id']]);
                            $remaining_to_deduct = 0;
                        } else {
                            $stmtUpdateBatch->execute([0, $batch['id']]);
                            $remaining_to_deduct -= $available;
                        }
                    }

                    $total_received_value += ($item['received_qty'] * $item['unit_cost']);
                }

                // Adjust supplier debt if supplier exists
                if ($po['supplier_id']) {
                    $debt = $total_received_value - (float)$po['paid_amount'];
                    if ($debt > 0) {
                        $stmtSupplier = $pdo->prepare("UPDATE suppliers SET balance = GREATEST(0, balance - ?) WHERE id = ?");
                        $stmtSupplier->execute([$debt, $po['supplier_id']]);
                    }
                }

                // Refund vault transactions if any payments were recorded
                $stmtTx = $pdo->prepare("SELECT account_id, amount FROM vault_transactions WHERE related_type = 'PurchaseOrder' AND related_id = ? AND type = 'Expense' FOR UPDATE");
                $stmtTx->execute([$id]);
                $vault_txs = $stmtTx->fetchAll();

                if (!empty($vault_txs)) {
                    $user_id = $_SESSION['user_id'] ?? 1;
                    $stmtRefundTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Income', ?, ?, 'PurchaseOrder', ?, ?)");
                    $stmtUpdateAcct = $pdo->prepare("UPDATE vault_accounts SET balance = balance + ? WHERE id = ?");

                    foreach ($vault_txs as $tx) {
                        $stmtUpdateAcct->execute([$tx['amount'], $tx['account_id']]);
                        $stmtRefundTx->execute([
                            $tx['account_id'],
                            $tx['amount'],
                            "Refund for Cancelled Purchase Order #$id",
                            $id,
                            $user_id
                        ]);
                    }
                }
            }

            // Update PO status to Cancelled
            $stmtUpdatePO = $pdo->prepare("UPDATE purchase_orders SET status = 'Cancelled' WHERE id = ?");
            $stmtUpdatePO->execute([$id]);

            // Notifications
            $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
            $po_msg = "Purchase Order #$id has been Cancelled. Stock and financial balances reversed.";
            $ins_notif->execute(['Admin', 'PO Cancelled: #' . $id, $po_msg, 'danger', '#purchase_orders']);
            $ins_notif->execute(['Stock Manager', 'PO Cancelled: #' . $id, $po_msg, 'danger', '#purchase_orders']);

            $pdo->commit();
            echo json_encode(['success' => 'Purchase order has been cancelled successfully.']);
            break;

        case 'get_products':
            $stmt = $pdo->query("SELECT id, name, barcode, purchase_price, stock_qty, min_stock FROM products ORDER BY name ASC");
            $products = $stmt->fetchAll();
            echo json_encode(['data' => $products]);
            break;
            
        case 'get_suppliers':
            $stmt = $pdo->query("SELECT id, name, company FROM suppliers ORDER BY name ASC");
            $suppliers = $stmt->fetchAll();
            echo json_encode(['data' => $suppliers]);
            break;

        case 'parse_invoice':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            if (!isset($_FILES['invoice_file'])) {
                throw new Exception("Aucun fichier n'a été fourni.");
            }

            $stmtKey = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
            $stmtKey->execute();
            $apiKey = $stmtKey->fetchColumn();
            if (empty($apiKey)) {
                echo json_encode(['error_code' => 'MISSING_GEMINI_KEY', 'error' => 'Gemini API key is not configured. Please add it in settings.']);
                exit;
            }

            $file = $_FILES['invoice_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Erreur lors de l'upload du fichier : " . $file['error']);
            }

            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowed_mimes)) {
                throw new Exception("Format de fichier non supporté. Veuillez uploader un PDF ou une image (JPG, PNG, WEBP).");
            }

            $file_data = file_get_contents($file['tmp_name']);
            $base64_data = base64_encode($file_data);

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . urlencode($apiKey);

            $prompt = "Analyze this purchase invoice. Extract:
1. The name of the supplier (field 'supplier_name').
2. The date of the invoice (field 'date' in YYYY-MM-DD format. Use today's date if not found or unclear).
3. The list of items in the invoice. For each item, extract the product name (field 'product_name'), the quantity (field 'qty'), and the unit purchase price (field 'unit_cost').

You must reply ONLY with a valid JSON object matching the following structure (no markdown formatting, no code blocks):
{
  \"supplier_name\": \"...\",
  \"date\": \"YYYY-MM-DD\",
  \"items\": [
    {
      \"product_name\": \"...\",
      \"qty\": 123,
      \"unit_cost\": 45.67
    }
  ]
}";

            $payload = [
                "contents" => [
                    [
                        "parts" => [
                            [
                                "text" => $prompt
                            ],
                            [
                                "inlineData" => [
                                    "mimeType" => $mime,
                                    "data" => $base64_data
                                ]
                            ]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "responseMimeType" => "application/json"
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new Exception("Erreur de connexion à l'API Gemini : " . $err);
            }
            curl_close($ch);

            if ($http_code !== 200) {
                $err_data = json_decode($response, true);
                $err_msg = isset($err_data['error']['message']) ? $err_data['error']['message'] : "HTTP Code " . $http_code;
                throw new Exception("Erreur API Gemini: " . $err_msg);
            }

            $res_data = json_decode($response, true);
            $text_output = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (empty($text_output)) {
                throw new Exception("L'API Gemini n'a renvoyé aucun texte.");
            }

            $invoice_details = json_decode($text_output, true);
            if (!$invoice_details) {
                throw new Exception("Impossible de parser la réponse JSON de Gemini.");
            }

            echo json_encode(['data' => $invoice_details]);
            break;

        case 'save_scanned_invoice':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            if (!$data || empty($data['items'])) {
                echo json_encode(['error' => 'Données invalides reçues']);
                exit;
            }

            $pdo->beginTransaction();

            $supplier_id = $data['supplier_id'];
            if ($supplier_id === '__NEW__') {
                $new_supplier_name = trim($data['new_supplier_name'] ?? '');
                if (empty($new_supplier_name)) {
                    throw new Exception("Le nom du nouveau fournisseur est requis.");
                }
                $stmtSupplierInsert = $pdo->prepare("INSERT INTO suppliers (name) VALUES (?)");
                $stmtSupplierInsert->execute([$new_supplier_name]);
                $supplier_id = $pdo->lastInsertId();
            }

            $date = $data['date'] ?? date('Y-m-d');
            $status = $data['status'] ?? 'Received';
            $purchase_type = $data['purchase_type'] ?? 'BA';
            $paid_amount = isset($data['paid_amount']) ? (float)$data['paid_amount'] : 0;
            $account_id = !empty($data['account_id']) ? $data['account_id'] : null;
            $items = $data['items'];

            $total = 0;
            $items_clean = [];
            foreach ($items as $item) {
                $prod_id = $item['product_id'];
                $qty = (int)$item['qty'];
                $cost = (float)$item['unit_cost'];

                if ($qty <= 0) throw new Exception("Quantité invalide.");
                if ($cost < 0) throw new Exception("Coût unitaire invalide.");

                if ($prod_id === '__NEW__') {
                    $new_product_name = trim($item['new_product_name'] ?? '');
                    if (empty($new_product_name)) {
                        throw new Exception("Le nom du nouveau produit est requis.");
                    }
                    $selling_price = isset($item['selling_price']) ? (float)$item['selling_price'] : 0;
                    if ($selling_price <= 0) {
                        throw new Exception("Le prix de vente pour un nouveau produit doit être supérieur à zéro.");
                    }

                    $stmtProductInsert = $pdo->prepare("INSERT INTO products (name, purchase_price, selling_price, stock_qty) VALUES (?, ?, ?, 0)");
                    $stmtProductInsert->execute([$new_product_name, $cost, $selling_price]);
                    $prod_id = $pdo->lastInsertId();
                }

                $total += ($qty * $cost);

                $items_clean[] = [
                    'product_id' => $prod_id,
                    'qty' => $qty,
                    'unit_cost' => $cost,
                    'expiry_date' => $item['expiry_date'] ?? null
                ];
            }
            $items = $items_clean;

            $payment_status = 'Unpaid';
            if ($status === 'Received') {
                if ($paid_amount > $total) {
                    throw new Exception('Le montant payé ne peut pas dépasser le total de la commande.');
                }

                if ($account_id && $paid_amount > 0) {
                    $balStmt = $pdo->prepare("SELECT balance FROM vault_accounts WHERE id = ? FOR UPDATE");
                    $balStmt->execute([$account_id]);
                    $acctBalance = (float)($balStmt->fetchColumn() ?? 0);
                    if ($paid_amount > $acctBalance) {
                        throw new Exception('Solde de compte insuffisant. Disponible : ' . number_format($acctBalance, 2));
                    }
                }

                if ($paid_amount >= $total && $total > 0) {
                    $payment_status = 'Paid';
                } elseif ($paid_amount > 0) {
                    $payment_status = 'Partial';
                }
            } else {
                $paid_amount = 0;
            }

            $stmtInsertPO = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, date, status, total, paid_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsertPO->execute([$supplier_id, $date, $status, $total, $paid_amount, $payment_status]);
            $po_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, received_qty, unit_cost, old_unit_cost) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty, expiry_date, purchase_price) VALUES (?, ?, ?, ?, ?, ?)");

            // Pre-fetch old prices in bulk to avoid N+1 queries.
            $old_prices_map = [];
            $product_ids_for_query = array_unique(array_column($items, 'product_id'));
            if (!empty($product_ids_for_query)) {
                $chunks = array_chunk($product_ids_for_query, 500);
                foreach ($chunks as $chunk) {
                    $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                    $stmtGetOldPrices = $pdo->prepare("SELECT id, purchase_price FROM products WHERE id IN ($placeholders)");
                    $stmtGetOldPrices->execute($chunk);
                    while ($row = $stmtGetOldPrices->fetch(PDO::FETCH_ASSOC)) {
                        $old_prices_map[$row['id']] = $row['purchase_price'];
                    }
                }
            }

            foreach ($items as $item) {
                $received_qty = ($status === 'Received') ? $item['qty'] : 0;

                $old_price = isset($old_prices_map[$item['product_id']]) ? $old_prices_map[$item['product_id']] : 0;

                $stmtItem->execute([$po_id, $item['product_id'], $item['qty'], $received_qty, $item['unit_cost'], $old_price]);

                if ($status === 'Received') {
                    $stmtUpdateProduct->execute([$item['qty'], $item['unit_cost'], $item['product_id']]);
                    $stmtBatch->execute([$item['product_id'], $purchase_type, $item['qty'], $item['qty'], $item['expiry_date'] ?? null, $item['unit_cost']]);
                }
            }

            if ($status === 'Received') {
                $debt = $total - $paid_amount;
                if ($debt > 0) {
                    $stmtSupplier = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?");
                    $stmtSupplier->execute([$debt, $supplier_id]);
                }

                if ($account_id && $paid_amount > 0) {
                    $user_id = $_SESSION['user_id'] ?? 1;
                    $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'PurchaseOrder', ?, ?)");
                    $stmtTx->execute([
                        $account_id,
                        $paid_amount,
                        "Supplier Order Payment #$po_id (Direct Scanned Receipt)",
                        $po_id,
                        $user_id
                    ]);

                    $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$paid_amount, $account_id]);
                }
            }

            if ($status === 'Pending') {
                $stmtSuppName = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
                $stmtSuppName->execute([$supplier_id]);
                $supplier_name = $stmtSuppName->fetchColumn() ?: 'Unknown';

                $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
                $po_msg = "New Purchase Order #$po_id has been created for " . $supplier_name . " (via invoice scan). Status: Pending.";
                $ins_notif->execute(['Admin', 'New PO: #' . $po_id, $po_msg, 'info', '#purchase_orders']);
                $ins_notif->execute(['Stock Manager', 'New PO: #' . $po_id, $po_msg, 'info', '#purchase_orders']);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Scanned invoice purchase order saved successfully', 'id' => $po_id]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Purchase Orders API Error: " . $e->getMessage());
    // Bug #4 Fix: Don't expose SQL error details to client
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
// Removed closing tag
