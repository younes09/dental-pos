<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, b.name as brand_name 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                ORDER BY p.id DESC
            ");
            $stmt->execute();
            $products = $stmt->fetchAll();
            echo json_encode(['data' => $products]);
            break;

        case 'get_stats':
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
            $total = $stmt->fetch()['total'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as low FROM products WHERE stock_qty <= min_stock");
            $low = $stmt->fetch()['low'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as expired FROM products WHERE expiry_date <= CURDATE() AND expiry_date IS NOT NULL");
            $expired = $stmt->fetch()['expired'];
            
            $stmt = $pdo->query("SELECT SUM(stock_qty * purchase_price) as value FROM products");
            $value = $stmt->fetch()['value'] ?? 0;
            
            echo json_encode([
                'total' => $total,
                'low' => $low,
                'expired' => $expired,
                'value' => number_format($value, 2)
            ]);
            break;

        case 'get_meta':
            $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
            $brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
            echo json_encode(['categories' => $categories, 'brands' => $brands]);
            break;

        case 'save':
            $id = $_POST['id'] ?? null;
            $name = $_POST['name'];
            $category_id = $_POST['category_id'];
            $brand_id = $_POST['brand_id'];
            $barcode = $_POST['barcode'];
            $purchase_price = $_POST['purchase_price'];
            $selling_price = $_POST['selling_price'];
            $stock_qty = $_POST['stock_qty'];
            $min_stock = $_POST['min_stock'];
            $expiry_date = $_POST['expiry_date'] ?: null;
            
            // Image handling (simplified)
            $image = $_POST['existing_image'] ?? 'default.jpg';
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "../assets/img/products/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $file_name = time() . "_" . basename($_FILES["image"]["name"]);
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $file_name)) {
                    $image = $file_name;
                }
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE products SET 
                    name=?, category_id=?, brand_id=?, barcode=?, 
                    purchase_price=?, selling_price=?, stock_qty=?, 
                    min_stock=?, expiry_date=?, image=?
                    WHERE id=?
                ");
                $stmt->execute([$name, $category_id, $brand_id, $barcode, $purchase_price, $selling_price, $stock_qty, $min_stock, $expiry_date, $image, $id]);
                echo json_encode(['success' => 'Product updated successfully']);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO products 
                    (name, category_id, brand_id, barcode, purchase_price, selling_price, stock_qty, min_stock, expiry_date, image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $category_id, $brand_id, $barcode, $purchase_price, $selling_price, $stock_qty, $min_stock, $expiry_date, $image]);
                echo json_encode(['success' => 'Product added successfully']);
            }
            break;

        case 'delete':
            $id = $_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Product deleted successfully']);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
