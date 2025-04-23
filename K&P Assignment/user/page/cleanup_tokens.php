<?php
// Script to clean up expired tokens
// This can be run as a cron job every day

// Define the path to _base.php - adjust as needed
$base_path = __DIR__ . '/../../_base.php';

// Check if the file exists
if (!file_exists($base_path)) {
    echo "Error: Base file not found at: $base_path\n";
    exit(1);
}

// Include the base file
require_once $base_path;

try {
    // Delete tokens that have expired
    $stm = $_db->prepare("DELETE FROM tokens WHERE expires_at < NOW()");
    $stm->execute();
    
    $count = $stm->rowCount();
    echo "Success: Deleted $count expired tokens.\n";
    error_log("Token cleanup: Deleted $count expired tokens");
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Token cleanup error: " . $e->getMessage());
    exit(1);
}

exit(0);