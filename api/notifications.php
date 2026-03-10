<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'Cashier';

try {
    switch ($action) {
        case 'list':
            // Generate expiry warnings proactively
            if (in_array($user_role, ['Admin', 'Stock Manager'])) {
                $expiry_stmt = $pdo->prepare("
                    SELECT id, name, expiry_date 
                    FROM products 
                    WHERE status = 'Active' 
                    AND expiry_date IS NOT NULL 
                    AND expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
                    AND expiry_date >= CURRENT_DATE
                ");
                $expiry_stmt->execute();
                $expiring_products = $expiry_stmt->fetchAll();

                $notif_exists = $pdo->prepare("SELECT id FROM notifications WHERE title LIKE ? AND is_read = 0");
                $ins_notif = $pdo->prepare("INSERT INTO notifications (role, title, message, type, link) VALUES (?, ?, ?, ?, ?)");

                foreach ($expiring_products as $product) {
                    $title = "Expiring soon: " . $product['name'];
                    $notif_exists->execute(["%" . $title . "%"]);
                    
                    if (!$notif_exists->fetch()) {
                        $msg = "Product expires on " . $product['expiry_date'] . ".";
                        $ins_notif->execute(['Admin', $title, $msg, 'danger', '#catalog']);
                        $ins_notif->execute(['Stock Manager', $title, $msg, 'danger', '#catalog']);
                    }
                }
            }

            // Fetch unread notifications for this user or their role
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE (user_id = ? OR role = ? OR (user_id IS NULL AND role IS NULL))
                AND is_read = 0
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stmt->execute([$user_id, $user_role]);
            $notifications = $stmt->fetchAll();
            echo json_encode(['data' => $notifications]);
            break;

        case 'history':
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE (user_id = ? OR role = ? OR (user_id IS NULL AND role IS NULL))
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id, $user_role]);
            $notifications = $stmt->fetchAll();
            echo json_encode(['data' => $notifications]);
            break;

        case 'mark_read':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Notification ID required');
            
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR role = ?)");
            $stmt->execute([$id, $user_id, $user_role]);
            echo json_encode(['success' => true]);
            break;

        case 'mark_all_read':
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR role = ?)");
            $stmt->execute([$user_id, $user_role]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
