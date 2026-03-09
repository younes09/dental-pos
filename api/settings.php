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
                $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
                
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');

                // Using direct path to mysqldump in XAMPP
                // -u {user} -p{pass} {db}
                // Note: No space between -p and password
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
                echo json_encode(['error' => 'Backup failed: ' . $e->getMessage()]);
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
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'POST':
        // F4.1: Only Admin can modify settings / restore
        if (($_SESSION['user_role'] ?? '') !== 'Admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Only Admins can modify settings or restore database.']);
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
                $mysqlPath = 'C:\\xampp\\mysql\\bin\\mysql.exe';
                $tmpFile = $file['tmp_name'];

                // Command to restore: mysql -u {user} -p{pass} {db} < {file}
                // Wrap EVERYTHING in double quotes for cmd /c on Windows
                $command = sprintf(
                    '"%s" --user=%s --password=%s --host=%s %s < "%s"',
                    $mysqlPath,
                    escapeshellarg(DB_USER),
                    escapeshellarg(DB_PASS),
                    escapeshellarg(DB_HOST),
                    escapeshellarg(DB_NAME),
                    $tmpFile
                );

                $winCommand = 'cmd /c "' . $command . '"'; // Extra quotes around the whole command
                
                exec($winCommand, $output, $returnVar);

                if ($returnVar === 0) {
                    echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Restore failed with exit code ' . $returnVar,
                        'details' => $output,
                        'command_used' => $winCommand // Helpful for debugging if it still fails
                    ]);
                }
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Restore failed: ' . $e->getMessage()]);
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
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            foreach ($data as $key => $value) {
                // Sanitize key to prevent potential issues, though it's coming from our own JS
                $stmt->execute([$key, $value]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
