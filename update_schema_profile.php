<?php
// Include configuration
require_once 'config.php';

// Disable error reporting to output, use log instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'schema_errors.log');

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if column exists
    $stmt = $conn->prepare("SHOW COLUMNS FROM players LIKE 'profile_picture'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Add column
        $sql = "ALTER TABLE players ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER email";
        $conn->exec($sql);
        echo json_encode(['success' => true, 'message' => 'Column profile_picture added successfully.']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Column profile_picture already exists.']);
    }
    
    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/uploads';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
