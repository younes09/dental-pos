<?php
/**
 * Dental POS Database Backup Script
 * 
 * This script exports the database as a SQL dump, saves it to the backups folder,
 * and maintains a maximum of 5 backups, removing the oldest when the limit is exceeded.
 * It also logs all activities to backups/backup.log.
 * 
 * Scheduled to run daily.
 */

// Ensure this script is only run via the Command Line Interface (CLI)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Error: This script can only be run from the command line.\n");
}

// Set paths relative to this script's directory
$base_dir = dirname(__DIR__);
$backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
$log_file = $backup_dir . DIRECTORY_SEPARATOR . 'backup.log';

// Ensure the backups directory exists
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0777, true)) {
        die("Error: Failed to create backups directory: $backup_dir\n");
    }
}

/**
 * Log a message to the console and to the log file.
 * 
 * @param string $message
 */
function log_msg($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $formatted, FILE_APPEND);
    echo $formatted;
}

log_msg("Starting database backup process...");

// Include the database configuration to retrieve credentials
$db_config_file = $base_dir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
if (!file_exists($db_config_file)) {
    log_msg("ERROR: Database config file not found at: $db_config_file");
    exit(1);
}

require_once $db_config_file;

// Check if credentials are defined
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    log_msg("ERROR: Database configuration constants are not defined in db.php");
    exit(1);
}

// Generate the backup file name
$timestamp_file = date('Y-m-d_H-i-s');
$backup_filename = "dental_pos_backup_{$timestamp_file}.sql";
$backup_path = $backup_dir . DIRECTORY_SEPARATOR . $backup_filename;

// Locate mysqldump.exe (standard path in XAMPP on Windows, or fallback to system path)
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
if (!file_exists($mysqldump)) {
    $mysqldump = 'mysqldump'; // Fallback to system path environment variable
}

// Build the mysqldump command
// We use double quotes around the paths to handle spaces in folder names.
// Redirection '2>&1' captures any error output to help diagnose issues if the exit code is non-zero.
$password_arg = (DB_PASS !== '') ? '--password=' . escapeshellarg(DB_PASS) : '';
$cmd = sprintf(
    '"%s" --host=%s --user=%s %s %s > %s 2>&1',
    $mysqldump,
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_USER),
    $password_arg,
    escapeshellarg(DB_NAME),
    escapeshellarg($backup_path)
);

// Execute the backup command
$output = [];
$return_var = -1;
exec($cmd, $output, $return_var);

if ($return_var !== 0) {
    // If the command failed, find the error message
    $error_msg = implode("\n", $output);
    if (empty($error_msg) && file_exists($backup_path)) {
        $error_msg = file_get_contents($backup_path);
    }
    
    log_msg("ERROR: Backup command failed with exit code: $return_var");
    log_msg("DETAILS: " . trim($error_msg));
    
    // Clean up empty or corrupted backup file
    if (file_exists($backup_path)) {
        @unlink($backup_path);
    }
    exit(1);
}

// Fix #6: Validate the backup file is non-empty (guards against silent mysqldump failures)
$backupSize = file_exists($backup_path) ? filesize($backup_path) : 0;
if ($backupSize === 0) {
    log_msg("ERROR: Backup file was created but is empty. Possible mysqldump issue.");
    @unlink($backup_path);
    exit(1);
}

log_msg("SUCCESS: Backup created successfully at: $backup_filename (size: " . number_format($backupSize) . " bytes)");

// Perform backup rotation (keep maximum of 5 files)
$search_pattern = $backup_dir . DIRECTORY_SEPARATOR . 'dental_pos_backup_*.sql';
$files = glob($search_pattern);

if ($files !== false) {
    // Alphabetical sort naturally orders by date (oldest first) because of the YYYY-MM-DD_HH-mm-ss format
    sort($files);
    
    $max_backups = 5;
    $count = count($files);
    
    if ($count > $max_backups) {
        log_msg("INFO: Found $count backup files. Limit is $max_backups. Cleaning up oldest backups...");
        
        while (count($files) > $max_backups) {
            $oldest = array_shift($files);
            if (@unlink($oldest)) {
                log_msg("INFO: Deleted oldest backup file: " . basename($oldest));
            } else {
                log_msg("WARNING: Failed to delete old backup file: " . basename($oldest));
            }
        }
    } else {
        log_msg("INFO: Current backup count: $count. No cleanup required.");
    }
} else {
    log_msg("WARNING: Could not list files to verify rotation.");
}

log_msg("Database backup process completed successfully.");
exit(0);

// creating a task in windows to backup the database
// schtasks /create /tn "DentalPOS_Database_Backup" /tr "C:\xampp\php\php.exe -f C:\xampp\htdocs\dental-pos\database\backup.php" /sc daily /st 13:00 /ru "SYSTEM"
