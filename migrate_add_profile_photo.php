<?php
// migrate_add_profile_photo.php
// Run this once via browser (http://localhost/Sit-in-Monitoring-System/migrate_add_profile_photo.php)
// It will add `profile_photo` column to `users` if missing and preserve existing data.
ini_set('display_errors', 1); error_reporting(E_ALL);

require_once 'config.php';
$conn = db_connect();

try {
    $res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_photo'");
    if ($res && $res->num_rows > 0) {
        echo "Column `profile_photo` already exists.\n";
    } else {
        $sql = "ALTER TABLE `users` ADD COLUMN `profile_photo` VARCHAR(255) DEFAULT NULL";
        if ($conn->query($sql) === TRUE) {
            echo "Added column `profile_photo` to users table.\n";
        } else {
            echo "Failed to add column: " . $conn->error . "\n";
        }
    }

    // Create uploads dir if missing
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) echo "Created uploads directory.\n";
        else echo "Failed to create uploads directory: check permissions.\n";
    } else {
        echo "uploads directory already exists.\n";
    }

    echo "Done. Delete this file after running for security.\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
} finally {
    $conn->close();
}
