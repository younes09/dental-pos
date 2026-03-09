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
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, ?, ?, ?)");
            $stmtGetOldPrice = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");

            foreach ($items as $item) {
                $received_qty = ($status === 'Received') ? $item['qty'] : 0;
                
                // Get old purchase price
                $stmtGetOldPrice->execute([$item['product_id']]);
                $old_price = $stmtGetOldPrice->fetchColumn() ?: 0;
                
                $stmtItem->execute([$po_id, $item['product_id'], $item['qty'], $received_qty, $item['unit_cost'], $old_price]);
                
                if ($status === 'Received') {
                    $stmtUpdateProduct->execute([$item['qty'], $item['unit_cost'], $item['product_id']]);
                    $stmtBatch->execute([$item['product_id'], $purchase_type, $item['qty'], $item['qty']]);
                }
            }

            // If received, update supplier balance based on unpaid amount
            if ($status === 'Received') {
                $debt = $total - $paid_amount;
                if ($debt > 0) {
                    $stmtSupplier = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?");
                    $stmtSupplier->execute([$debt, $supplier_id]);
                }
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

            // Check if PO exists and is not already fully received
            $stmt = $pdo->prepare("SELECT supplier_id, status, total, paid_amount FROM purchase_orders WHERE id = ?");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch();

            if (!$po) throw new Exception("Order not found");
            if ($po['status'] === 'Received') throw new Exception("Order already received");

            // Prepare statements
            $stmtUpdateItemRow = $pdo->prepare("UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE id = ? AND po_id = ?");
            $stmtGetItemCost = $pdo->prepare("SELECT unit_cost FROM purchase_order_items WHERE id = ?");
            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, ?, ?, ?)");

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
                $stmtBatch->execute([$product_id, $purchase_type, $recv_qty_now, $recv_qty_now]);

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
                $stmtTx = $pdo->prepare("INSERT INTO vault_transactions (account_id, type, amount, description, related_type, related_id, user_id) VALUES (?, 'Expense', ?, ?, 'PurchaseOrder', ?, ?)");
                $stmtTx->execute([
                    $account_id,
                    $paid_amount,
                    "Paiement Commande Fournisseur #$po_id",
                    $po_id,
                    $user_id
                ]);

                $pdo->prepare("UPDATE vault_accounts SET balance = balance - ? WHERE id = ?")->execute([$paid_amount, $account_id]);
            }

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

            $pdo->commit();
            echo json_encode(['success' => 'Supplier return processed successfully', 'return_id' => $return_id]);
            break;

        case 'get_details':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])) {
                throw new Exception('Access denied.');
            }
            $id = $_GET['id'];
            
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
            $id = $_GET['id'];
            
            // F2.3: Prevent deletion of POs that have received stock
            $status_stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
            $status_stmt->execute([$id]);
            $po_status = $status_stmt->fetchColumn();
            
            if ($po_status === 'Received' || $po_status === 'Partial') {
                echo json_encode(['error' => 'Cannot delete a purchase order that has received stock. Cancel it instead.']);
                exit;
            }
            
            // purchase_order_items has ON DELETE CASCADE in schema
            $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Purchase order deleted successfully']);
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

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
// Removed closing tag
