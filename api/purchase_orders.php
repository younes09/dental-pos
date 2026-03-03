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
            // Get raw POST data
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

            $stmt = $pdo->prepare("INSERT INTO purchase_orders (supplier_id, date, status, total) VALUES (?, ?, ?, ?)");
            $stmt->execute([$supplier_id, $date, $status, $total]);
            $po_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, unit_cost) VALUES (?, ?, ?, ?)");
            $stmtUpdateProduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");

            foreach ($items as $item) {
                $stmtItem->execute([$po_id, $item['product_id'], $item['qty'], $item['unit_cost']]);
                
                // If the status is received, update product stock and price
                if ($status === 'Received') {
                    $stmtUpdateProduct->execute([$item['qty'], $item['unit_cost'], $item['product_id']]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => 'Purchase order saved successfully', 'id' => $po_id]);
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
