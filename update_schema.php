<?php
/**
 * Database Schema Updater
 * Adds password_hash column to players table if missing.
 */

require_once 'config.php';

echo "Checking database schema...\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        die("Could not connect to database.\n");
    }

    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM players LIKE 'password_hash'");
    
    if ($stmt->rowCount() == 0) {
        echo "Column 'password_hash' missing. Adding it now...\n";
        
        // Add Column
        $sql = "ALTER TABLE players ADD COLUMN password_hash VARCHAR(255) NULL AFTER email";
        $conn->exec($sql);
        echo "✓ Column 'password_hash' added successfully.\n";
        
        // Update existing users with a default password 'password123'
        // Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
        $defaultHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        
        $count = $conn->exec("UPDATE players SET password_hash = '$defaultHash' WHERE password_hash IS NULL AND username != 'guest_user'");
        echo "✓ Updated $count existing players with default password: 'password'\n";
        
    } else {
        echo "✓ Schema is already up to date.\n";
    }
    
    echo "\nUpdate complete! You can now login.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
