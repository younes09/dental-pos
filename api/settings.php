<?php
header('Content-Type: application/json');
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['action']) && $_GET['action'] === 'backup') {
            // Only Admin can backup
            if (($_SESSION['user_role'] ?? '') !== 'Admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Only Admins can perform database backup.']);
                exit;
            }

            try {
                $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
                // Fix #4: Use a constant so the path is configurable, not hard-coded
                $mysqldumpPath = defined('MYSQLDUMP_PATH') ? MYSQLDUMP_PATH : 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

                if (!file_exists($mysqldumpPath)) {
                    throw new Exception('mysqldump binary not found at: ' . $mysqldumpPath);
                }

                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');

                $command = sprintf(
                    '"%s" --user=%s --password=%s --host=%s %s',
                    $mysqldumpPath,
                    escapeshellarg(DB_USER),
                    escapeshellarg(DB_PASS),
                    escapeshellarg(DB_HOST),
                    escapeshellarg(DB_NAME)
                );

                passthru($command);
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
        }

        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            echo json_encode($settings);
        } catch (PDOException $e) {
            http_response_code(500);
            error_log("Settings GET Error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load settings.']);
        }
        break;

    case 'POST':
        // F4.1: Only Admin can modify settings / restore
        if (($_SESSION['user_role'] ?? '') !== 'Admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Only Admins can modify settings or restore database.']);
            exit;
        }

        // Handle Logo Upload
        if (isset($_GET['action']) && $_GET['action'] === 'upload_logo') {
            if (!isset($_FILES['logo_file'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No logo file uploaded']);
                exit;
            }

            $file = $_FILES['logo_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'Upload error code: ' . $file['error']]);
                exit;
            }

            // Validate file extension and MIME type
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid image format. Allowed: jpg, png, webp']);
                exit;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!str_starts_with($mime, 'image/')) {
                http_response_code(400);
                echo json_encode(['error' => 'Uploaded file is not a valid image']);
                exit;
            }

            $target_dir = "../assets/img/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $file_name = "store_logo_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            $target_path = $target_dir . $file_name;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Delete old logo file if it exists in settings
                try {
                    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'store_logo'");
                    $stmt->execute();
                    $old_logo = $stmt->fetchColumn();
                    if ($old_logo && str_starts_with($old_logo, 'assets/img/')) {
                        $old_logo_file = "../" . $old_logo;
                        if (file_exists($old_logo_file)) {
                            @unlink($old_logo_file);
                        }
                    }
                } catch (Exception $e) {
                    // Ignore deletion error
                }

                $logo_path = 'assets/img/' . $file_name;

                // Save setting
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('store_logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$logo_path]);

                echo json_encode(['success' => true, 'logo_path' => $logo_path]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save uploaded file']);
            }
            exit;
        }

        // Handle Database Restore
        if (isset($_GET['action']) && $_GET['action'] === 'restore') {
            if (!isset($_FILES['backup_file'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No backup file uploaded']);
                exit;
            }

            $file = $_FILES['backup_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                echo json_encode(['error' => 'File upload error: ' . $file['error']]);
                exit;
            }

            try {
                $mysqlPath = defined('MYSQL_PATH') ? MYSQL_PATH : 'C:\\xampp\\mysql\\bin\\mysql.exe';
                $tmpFile = $file['tmp_name'];

                // Fix #5: Validate the uploaded file is actually a .sql file
                $uploadedName = $file['name'] ?? '';
                $ext = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));
                if ($ext !== 'sql') {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid file type. Only .sql backup files are accepted.']);
                    exit;
                }

                if (!file_exists($mysqlPath)) {
                    throw new Exception('mysql binary not found at: ' . $mysqlPath);
                }

                $command = sprintf(
                    '"%s" --user=%s --password=%s --host=%s %s < "%s"',
                    $mysqlPath,
                    escapeshellarg(DB_USER),
                    escapeshellarg(DB_PASS),
                    escapeshellarg(DB_HOST),
                    escapeshellarg(DB_NAME),
                    escapeshellarg($tmpFile)
                );

                $winCommand = 'cmd /c "' . $command . '"';

                exec($winCommand, $output, $returnVar);

                if ($returnVar === 0) {
                    echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
                } else {
                    error_log("DB Restore failed with exit code $returnVar. Command: $winCommand");
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Restore failed with exit code ' . $returnVar,
                        'details' => 'Check server logs for more information.'
                    ]);
                }
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }

        try {
            // Fix #15: Whitelist allowed setting keys to prevent arbitrary key injection
            // Bug #3 Fix: Include all settings keys used across the application
            $allowed_keys = [
                'store_name', 'store_phone', 'store_phone_2', 'clinic_name', 'clinic_phone', 'clinic_email', 'clinic_address',
                'currency', 'tax_rate', 'vat_rate', 'tax_number', 'nif', 'nis', 'rc', 'address',
                'low_stock_threshold', 'receipt_footer',
                'language', 'timezone', 'date_format', 'theme',
                'loyalty_earning_rate', 'loyalty_point_value',
                'store_logo'
            ];

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            foreach ($data as $key => $value) {
                if (!in_array($key, $allowed_keys)) continue; // Skip unknown keys

                if ($key === 'vat_rate') {
                    if (!is_numeric($value) || (float)$value < 0 || (float)$value > 100) {
                        throw new Exception('VAT rate must be a number between 0 and 100.');
                    }
                }
                if (in_array($key, ['loyalty_earning_rate', 'loyalty_point_value'])) {
                    if (!is_numeric($value) || (float)$value < 0) {
                        throw new Exception(ucfirst(str_replace('_', ' ', $key)) . ' must be a non-negative number.');
                    }
                }
                if ($key === 'low_stock_threshold') {
                    if (!is_numeric($value) || (int)$value < 0) {
                        throw new Exception('Low stock threshold must be a non-negative integer.');
                    }
                }

                $stmt->execute([$key, $value]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            error_log("Settings POST Error: " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
