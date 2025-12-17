<?php
// debug_db.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$response = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        $response['status'] = 'success';
        $response['message'] = 'Database connection successful!';
        
        // Test query
        $stmt = $conn->query("SELECT COUNT(*) as count FROM players");
        $row = $stmt->fetch();
        $response['player_count'] = $row['count'];
        
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Database connection returned NULL.';
    }
} catch (Exception $e) {
    $response['status'] = 'exception';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
