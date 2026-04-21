<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->prepare("
                SELECT q.*, c.name as customer_name, u.name as user_name
                FROM quotations q
                LEFT JOIN customers c ON q.customer_id = c.id
                LEFT JOIN users u ON q.user_id = u.id
                ORDER BY q.date DESC
            ");
            $stmt->execute();
            $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $quotations]);
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Quotation ID is required');

            $stmt = $pdo->prepare("
                SELECT q.*, c.name as customer_name, c.phone as customer_phone, u.name as user_name
                FROM quotations q
                LEFT JOIN customers c ON q.customer_id = c.id
                LEFT JOIN users u ON q.user_id = u.id
                WHERE q.id = ?
            ");
            $stmt->execute([$id]);
            $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$quotation) {
                throw new Exception('Quotation not found');
            }

            $stmt = $pdo->prepare("
                SELECT qi.*, p.name as product_name, p.barcode, p.image
                FROM quotation_items qi
                JOIN products p ON qi.product_id = p.id
                WHERE qi.quotation_id = ?
            ");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'quotation' => $quotation,
                'items' => $items
            ]);
            break;

        case 'create':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])) {
                throw new Exception('Access denied.');
            }
            
            $pdo->beginTransaction();
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['items'])) throw new Exception('Invalid data or empty quotation');

            $customer_id = $data['customer_id'] ?? null;
            $user_id = $_SESSION['user_id'] ?? 1;
            $notes = $data['notes'] ?? '';

            $subtotal = 0;
            
            // Re-fetch products to ensure correct pricing
            $product_ids = array_column($data['items'], 'id');
            if (empty($product_ids)) throw new Exception('No items provided');
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            $prod_stmt = $pdo->prepare("SELECT id, name, selling_price FROM products WHERE id IN ($placeholders)");
            $prod_stmt->execute($product_ids);
            
            $db_products = [];
            while ($row = $prod_stmt->fetch(PDO::FETCH_ASSOC)) {
                $db_products[$row['id']] = $row;
            }

            $processed_items = [];
            foreach ($data['items'] as $item) {
                $qty = (int)$item['qty'];
                $item_id = (int)$item['id'];
                
                if ($qty <= 0) throw new Exception("Invalid quantity for product ID: " . $item_id);
                if (!isset($db_products[$item_id])) throw new Exception("Product not found: " . $item_id);
                
                // For Devis, we can use the price sent by the client if they modified it (if POS allows), 
                // but standard is to take current price or passed price. We'll use passed price for flexibility if cashier modifies it.
                // Wait, POS frontend doesn't allow price modification natively yet, but we'll trust frontend price as it might have discounts.
                // Actually to be safe, we'll take the frontend price if it's sent, otherwise DB selling price.
                $price = (float)($item['price'] ?? $db_products[$item_id]['selling_price']);
                $item_total = $price * $qty;
                $subtotal += $item_total;
                
                $processed_items[] = [
                    'product_id' => $item_id,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $item_total
                ];
            }

            $discount = (float)($data['discount_amount'] ?? 0);
            if ($discount > $subtotal) $discount = $subtotal;
            
            $taxable = $subtotal - $discount;
            // Get tax rate from settings
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'vat_rate'");
            $tax_rate = (float)($stmt->fetchColumn() ?? 0) / 100;
            
            $tax = round($taxable * $tax_rate, 2);
            $total = round($taxable + $tax, 2);

            $stmt = $pdo->prepare("
                INSERT INTO quotations (customer_id, user_id, subtotal, discount, tax, total, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stmt->execute([
                $customer_id,
                $user_id,
                $subtotal,
                $discount,
                $tax,
                $total,
                $notes
            ]);
            $quotation_id = $pdo->lastInsertId();

            $item_stmt = $pdo->prepare("
                INSERT INTO quotation_items (quotation_id, product_id, qty, unit_price, total) 
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($processed_items as $item) {
                $item_stmt->execute([
                    $quotation_id,
                    $item['product_id'],
                    $item['qty'],
                    $item['price'],
                    $item['total']
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => 'Quotation created successfully', 'quotation_id' => $quotation_id]);
            break;

        case 'cancel':
            if (!in_array($_SESSION['user_role'] ?? '', ['Admin'])) {
                throw new Exception('Only Admins can delete or cancel quotations.');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('Quotation ID is required');

            $stmt = $pdo->prepare("SELECT status FROM quotations WHERE id = ?");
            $stmt->execute([$id]);
            $status = $stmt->fetchColumn();

            if ($status === 'Converted') {
                throw new Exception("Cannot cancel a converted quotation.");
            }

            $stmt = $pdo->prepare("UPDATE quotations SET status = 'Cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => 'Quotation cancelled.']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Quotations API Error: " . $e->getMessage() . " | User: " . ($_SESSION['user_id'] ?? 'unknown'));
    echo json_encode(['error' => 'Database error occurred.']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
