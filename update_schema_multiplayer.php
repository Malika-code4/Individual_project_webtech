<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS multiplayer_rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_code VARCHAR(10) UNIQUE NOT NULL,
        player1_id INT NOT NULL,
        player2_id INT,
        curr_turn INT DEFAULT 0 COMMENT '0 for p1, 1 for p2',
        board_state JSON,
        game_state JSON,
        status ENUM('waiting', 'active', 'finished') DEFAULT 'waiting',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (player1_id) REFERENCES players(player_id)
    )";
    
    $pdo->exec($sql);
    echo "Multiplayer schema updated successfully.";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
