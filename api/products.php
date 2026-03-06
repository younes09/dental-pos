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
            $purchase_type = $_POST['purchase_type'] ?? 'BA';
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
                    purchase_price=?, selling_price=?, min_stock=?, 
                    expiry_date=?, image=?
                    WHERE id=?
                ");
                $stmt->execute([$name, $category_id, $brand_id, $barcode, $purchase_price, $selling_price, $min_stock, $expiry_date, $image, $id]);
                echo json_encode(['success' => 'Product updated successfully']);
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO products 
                    (name, category_id, brand_id, barcode, purchase_price, selling_price, stock_qty, min_stock, expiry_date, image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $category_id, $brand_id, $barcode, $purchase_price, $selling_price, $stock_qty, $min_stock, $expiry_date, $image]);
                
                $product_id = $pdo->lastInsertId();
                if ($stock_qty > 0) {
                    $batch_stmt = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, ?, ?, ?)");
                    $batch_stmt->execute([$product_id, $purchase_type, $stock_qty, $stock_qty]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => 'Product added successfully']);
            }
            break;

        case 'delete':
            $id = $_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Product deleted successfully']);
            break;

        case 'adjust_stock':
            $id = $_GET['id'];
            $type = $_GET['type'];
            $qty = (int)$_GET['qty'];
            $purchase_type = $_GET['purchase_type'] ?? 'BA'; // Get purchase type from request
            
            $pdo->beginTransaction();
            
            try {
                if ($type === 'add') {
                    // 1. Add to products table stock_qty
                    $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                    $stmt->execute([$qty, $id]);
                    
                    // 2. Create new stock batch
                    $batch_stmt = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, ?, ?, ?)");
                    $batch_stmt->execute([$id, $purchase_type, $qty, $qty]);
                    
                } else if ($type === 'subtract') {
                    // 1. Subtract from products table
                    $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                    $stmt->execute([$qty, $id]);
                    
                    // 2. Subtract from oldest active batches (FIFO)
                    $remaining_to_deduct = $qty;
                    // Get batches sorted by oldest first
                    $batches_stmt = $pdo->prepare("SELECT id, remaining_qty FROM stock_batches WHERE product_id = ? AND remaining_qty > 0 ORDER BY created_at ASC");
                    $batches_stmt->execute([$id]);
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
                
                $pdo->commit();
                echo json_encode(['success' => 'Stock adjusted successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['error' => 'Failed to adjust stock: ' . $e->getMessage()]);
            }
            break;

        case 'download_template':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="product_template.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Name', 'Category', 'Brand', 'Barcode', 'Purchase Price', 'Selling Price', 'Stock Qty', 'Min Stock', 'Expiry Date (YYYY-MM-DD)']);
            fputcsv($output, ['Composite Resin A2', 'Consumables', '3M', '123456789', '45.00', '65.00', '10', '5', '2025-12-31']);
            fclose($output);
            exit;

        case 'import_csv':
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'No file uploaded or upload error']);
                exit;
            }

            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            // Skip header
            fgetcsv($handle);
            
            $success_count = 0;
            $error_count = 0;
            
            $pdo->beginTransaction();
            
            try {
                // Get all categories and brands for mapping
                $categories = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
                $brands = $pdo->query("SELECT id, name FROM brands")->fetchAll(PDO::FETCH_KEY_PAIR);
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) < 6) continue; // Basic validation
                    
                    $name = trim($data[0]);
                    $cat_name = trim($data[1]);
                    $brand_name = trim($data[2]);
                    $barcode = trim($data[3]);
                    $purchase_price = (float)$data[4];
                    $selling_price = (float)$data[5];
                    $stock_qty = (int)($data[6] ?? 0);
                    $min_stock = (int)($data[7] ?? 5);
                    $expiry_date = !empty($data[8]) ? trim($data[8]) : null;

                    if (empty($name)) {
                        $error_count++;
                        continue;
                    }

                    // Resolve or create category
                    $category_id = null;
                    if (!empty($cat_name)) {
                        $cat_id = array_search($cat_name, $categories);
                        if ($cat_id === false) {
                            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                            $stmt->execute([$cat_name]);
                            $cat_id = $pdo->lastInsertId();
                            $categories[$cat_id] = $cat_name;
                        }
                        $category_id = $cat_id;
                    }

                    // Resolve or create brand
                    $brand_id = null;
                    if (!empty($brand_name)) {
                        $b_id = array_search($brand_name, $brands);
                        if ($b_id === false) {
                            $stmt = $pdo->prepare("INSERT INTO brands (name) VALUES (?)");
                            $stmt->execute([$brand_name]);
                            $b_id = $pdo->lastInsertId();
                            $brands[$b_id] = $brand_name;
                        }
                        $brand_id = $b_id;
                    }

                    // Insert product
                    $stmt = $pdo->prepare("
                        INSERT INTO products 
                        (name, category_id, brand_id, barcode, purchase_price, selling_price, stock_qty, min_stock, expiry_date, image) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $category_id, $brand_id, $barcode, $purchase_price, $selling_price, $stock_qty, $min_stock, $expiry_date, 'default.png']);
                    
                    $product_id = $pdo->lastInsertId();
                    if ($stock_qty > 0) {
                        $batch_stmt = $pdo->prepare("INSERT INTO stock_batches (product_id, purchase_type, initial_qty, remaining_qty) VALUES (?, 'BA', ?, ?)");
                        $batch_stmt->execute([$product_id, $stock_qty, $stock_qty]);
                    }
                    
                    $success_count++;
                }
                
                $pdo->commit();
                fclose($handle);
                echo json_encode(['success' => "Imported $success_count products successfully. Errors: $error_count"]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                fclose($handle);
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
// Removed closing tag
