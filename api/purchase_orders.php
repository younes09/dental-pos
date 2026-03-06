<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
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

            $stmt = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, date, status, total) VALUES (?, ?, ?, ?)");
            $stmt->execute([$supplier_id, $date, $status, $total]);
            $po_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, received_qty, unit_cost) VALUES (?, ?, ?, ?, ?)");
            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, ?, ?, ?)");

            foreach ($items as $item) {
                $received_qty = ($status === 'Received') ? $item['qty'] : 0;
                $stmtItem->execute([$po_id, $item['product_id'], $item['qty'], $received_qty, $item['unit_cost']]);
                
                if ($status === 'Received') {
                    $stmtUpdateProduct->execute([$item['qty'], $item['unit_cost'], $item['product_id']]);
                    $stmtBatch->execute([$item['product_id'], $purchase_type, $item['qty'], $item['qty']]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => 'Purchase order saved successfully', 'id' => $po_id]);
            break;

        case 'receive_order':
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

            // Check if PO exists and is not already fully received
            $stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch();

            if (!$po) throw new Exception("Order not found");
            if ($po['status'] === 'Received') throw new Exception("Order already received");

            // Prepare statements
            $stmtUpdateItemRow = $pdo->prepare("UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE id = ? AND po_id = ?");
            $stmtGetItemCost = $pdo->prepare("SELECT unit_cost FROM purchase_order_items WHERE id = ?");
            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, ?, ?, ?)");

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

            // Update PO status
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $po_id]);

            $pdo->commit();
            $msg = ($new_status === 'Received') ? 'Order fully received' : 'Order partially received';
            echo json_encode(['success' => $msg]);
            break;

        case 'get_details':
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
            $stmt = $pdo->query("SELECT id, name, barcode, purchase_price, stock_qty FROM products ORDER BY name ASC");
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
