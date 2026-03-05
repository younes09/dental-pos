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

            if (!$data) {
                echo json_encode(['error' => 'Invalid data received']);
                exit;
            }

            $pdo->beginTransaction();

            $supplier_id = $data['supplier_id'];
            $date = $data['date'] ?? date('Y-m-d');
            $status = $data['status'] ?? 'Pending';
            $total = $data['total'];
            $items = $data['items'];
            $purchase_type = $data['purchase_type'] ?? 'BA'; // Default to BA if not provided

            $stmt = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, date, status, total) VALUES (?, ?, ?, ?)");
            $stmt->execute([$supplier_id, $date, $status, $total]);
            $po_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, unit_cost) VALUES (?, ?, ?, ?)");
            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, ?, ?, ?)");

            foreach ($items as $item) {
                $stmtItem->execute([$po_id, $item['product_id'], $item['qty'], $item['unit_cost']]);
                
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

            if (!$data || !isset($data['po_id']) || !isset($data['purchase_type'])) {
                echo json_encode(['error' => 'Invalid data']);
                exit;
            }

            $pdo->beginTransaction();

            $po_id = $data['po_id'];
            $purchase_type = $data['purchase_type'];

            // Check if PO exists and is not already received
            $stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch();

            if (!$po) throw new Exception("Order not found");
            if ($po['status'] === 'Received') throw new Exception("Order already received");

            // Update PO status
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'Received' WHERE id = ?");
            $stmt->execute([$po_id]);

            // Get items to update stock and batches
            $stmt = $pdo->prepare("SELECT product_id, qty, unit_cost FROM purchase_order_items WHERE po_id = ?");
            $stmt->execute([$po_id]);
            $items = $stmt->fetchAll();

            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");
            $stmtBatch = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, ?, ?, ?)");

            foreach ($items as $item) {
                $stmtUpdateProduct->execute([$item['qty'], $item['unit_cost'], $item['product_id']]);
                $stmtBatch->execute([$item['product_id'], $purchase_type, $item['qty'], $item['qty']]);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Order received successfully']);
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
            $id = $_GET['id'];
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
?>
