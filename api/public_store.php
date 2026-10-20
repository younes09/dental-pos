<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'products';

try {
    switch ($action) {
        case 'filters':
            $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
            $brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
            
            $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('store_name', 'currency', 'store_phone', 'store_phone_2', 'store_email', 'store_logo', 'address')");
            $settings = [];
            while ($row = $settings_stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            if (empty($settings['store_name'])) {
                $settings['store_name'] = 'DentalPOS';
            }
            if (empty($settings['currency'])) {
                $settings['currency'] = 'DZD';
            }

            echo json_encode([
                'categories' => $categories,
                'brands' => $brands,
                'settings' => $settings
            ]);
            break;

        case 'products':
            $search = $_GET['search'] ?? '';
            $category_id = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
            $brand_id = isset($_GET['brand_id']) && $_GET['brand_id'] !== '' ? (int)$_GET['brand_id'] : null;
            $availability = $_GET['availability'] ?? 'all';
            $sort = $_GET['sort'] ?? 'name_asc';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 12)));

            $where = "";
            $params = [];

            if (!empty($search)) {
                $where .= " AND (p.name LIKE :search OR p.barcode LIKE :search OR c.name LIKE :search OR b.name LIKE :search)";
                $params['search'] = '%' . $search . '%';
            }

            if ($category_id !== null) {
                $where .= " AND p.category_id = :category_id";
                $params['category_id'] = $category_id;
            }

            if ($brand_id !== null) {
                $where .= " AND p.brand_id = :brand_id";
                $params['brand_id'] = $brand_id;
            }

            if ($availability === 'in_stock') {
                $where .= " AND p.stock_qty > 0";
            } elseif ($availability === 'out_of_stock') {
                $where .= " AND p.stock_qty = 0";
            }

            // Total count query
            $countQuery = "
                SELECT COUNT(*) as total
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.status = 'Active'" . $where;

            $countStmt = $pdo->prepare($countQuery);
            $countStmt->execute($params);
            $totalRecords = (int)$countStmt->fetchColumn();

            $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $limit;

            // Data query
            $query = "
                SELECT p.id, p.name, p.selling_price, p.stock_qty, p.min_stock, p.image, p.barcode, p.expiry_date, p.created_at,
                       c.name as category_name, b.name as brand_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.status = 'Active'" . $where;

            switch ($sort) {
                case 'name_desc':
                    $query .= " ORDER BY p.name DESC";
                    break;
                case 'price_asc':
                    $query .= " ORDER BY p.selling_price ASC";
                    break;
                case 'price_desc':
                    $query .= " ORDER BY p.selling_price DESC";
                    break;
                case 'stock_desc':
                    $query .= " ORDER BY p.stock_qty DESC, p.name ASC";
                    break;
                case 'newest':
                    $query .= " ORDER BY p.id DESC";
                    break;
                default:
                    $query .= " ORDER BY p.name ASC";
                    break;
            }

            $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll();

            echo json_encode([
                'data' => $products,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Public Store API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while fetching catalog data.']);
}
