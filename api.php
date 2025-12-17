<?php
/**
 * Memory Card Game - Game API
 * 
 * Handles all CRUD operations for the memory card game
 * 
 * @author [Nana Malika]
 * @version 1.2
 * @date November 12, 2025
 */

// Include shared configuration
require_once 'config.php';

// Set headers for CORS and JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// Player Model
// ============================================================================
class Player {
    private $conn;
    private $table = 'players';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new player
     */
    public function create($username, $email = null) {
        // Validate input
        $usernameValidation = Validator::validateUsername($username);
        if (!$usernameValidation['valid']) {
            return ['success' => false, 'message' => $usernameValidation['message']];
        }

        $emailValidation = Validator::validateEmail($email);
        if (!$emailValidation['valid']) {
            return ['success' => false, 'message' => $emailValidation['message']];
        }

        // Sanitize input
        $username = Validator::sanitize($username);
        $email = $email ? Validator::sanitize($email) : null;

        try {
            $query = "INSERT INTO {$this->table} (username, email) VALUES (:username, :email)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'player_id' => $this->conn->lastInsertId(),
                    'message' => 'Player created successfully'
                ];
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                return ['success' => false, 'message' => 'Username already exists'];
            }
            error_log("Create Player Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }

        return ['success' => false, 'message' => 'Failed to create player'];
    }

    /**
     * Get player by ID
     */
    public function getById($playerId) {
        $query = "SELECT * FROM {$this->table} WHERE player_id = :player_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get player by username
     */
    public function getByUsername($username) {
        $query = "SELECT * FROM {$this->table} WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get all players
     */
    public function getAll() {
        $query = "SELECT * FROM {$this->table} ORDER BY total_games_played DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Update player
     */
    public function update($playerId, $data) {
        $updates = [];
        $params = [':player_id' => $playerId];

        if (isset($data['username'])) {
            $validation = Validator::validateUsername($data['username']);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            $updates[] = "username = :username";
            $params[':username'] = Validator::sanitize($data['username']);
        }

        if (isset($data['email'])) {
            $validation = Validator::validateEmail($data['email']);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            $updates[] = "email = :email";
            $params[':email'] = Validator::sanitize($data['email']);
        }

        if (isset($data['profile_picture'])) {
            $updates[] = "profile_picture = :profile_picture";
            $params[':profile_picture'] = Validator::sanitize($data['profile_picture']);
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        try {
            $query = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE player_id = :player_id";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute($params)) {
                return ['success' => true, 'message' => 'Player updated successfully'];
            }
        } catch (PDOException $e) {
            error_log("Update Player Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }

        return ['success' => false, 'message' => 'Failed to update player'];
    }

    /**
     * Delete player
     */
    public function delete($playerId) {
        try {
            $query = "DELETE FROM {$this->table} WHERE player_id = :player_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Player deleted successfully'];
            }
        } catch (PDOException $e) {
            error_log("Delete Player Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }

        return ['success' => false, 'message' => 'Failed to delete player'];
    }
}

// ============================================================================
// Game Model
// ============================================================================
class Game {
    private $conn;
    private $table = 'games';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new game
     */
    public function create($playerId, $gridSize = 16, $difficultyLevel = 'medium') {
        // Validate grid size
        $validation = Validator::validateGridSize($gridSize);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        try {
            $query = "INSERT INTO {$this->table} (player_id, grid_size, difficulty_level) 
                      VALUES (:player_id, :grid_size, :difficulty_level)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':grid_size', $gridSize, PDO::PARAM_INT);
            $stmt->bindParam(':difficulty_level', $difficultyLevel);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'game_id' => $this->conn->lastInsertId(),
                    'message' => 'Game created successfully'
                ];
            }
        } catch (PDOException $e) {
            error_log("Create Game Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }

        return ['success' => false, 'message' => 'Failed to create game'];
    }

    /**
     * Update game (for moves, time, completion)
     */
    public function update($gameId, $data) {
        $updates = [];
        $params = [':game_id' => $gameId];

        if (isset($data['total_moves'])) {
            $validation = Validator::validateInteger($data['total_moves'], 0);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            $updates[] = "total_moves = :total_moves";
            $params[':total_moves'] = (int)$data['total_moves'];
        }

        if (isset($data['time_elapsed'])) {
            $validation = Validator::validateInteger($data['time_elapsed'], 0);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            $updates[] = "time_elapsed = :time_elapsed";
            $params[':time_elapsed'] = (int)$data['time_elapsed'];
        }

        if (isset($data['completed'])) {
            $updates[] = "completed = :completed";
            $params[':completed'] = (bool)$data['completed'];
            
            if ($data['completed']) {
                $updates[] = "completed_at = NOW()";
                
                // Calculate score (higher is better)
                // Score = 1000 - (moves * 5) - (time / 10)
                if (isset($data['total_moves']) && isset($data['time_elapsed'])) {
                    $score = max(0, 1000 - ($data['total_moves'] * 5) - ($data['time_elapsed'] / 10));
                    $updates[] = "score = :score";
                    $params[':score'] = (int)$score;
                }
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        try {
            $query = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE game_id = :game_id";
            $stmt = $this->conn->prepare($query);
            
            if ($stmt->execute($params)) {
                return ['success' => true, 'message' => 'Game updated successfully'];
            }
        } catch (PDOException $e) {
            error_log("Update Game Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }

        return ['success' => false, 'message' => 'Failed to update game'];
    }

    /**
     * Get game by ID
     */
    public function getById($gameId) {
        $query = "SELECT * FROM {$this->table} WHERE game_id = :game_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get games by player
     */
    public function getByPlayer($playerId, $limit = 10) {
        $query = "SELECT * FROM {$this->table} 
                  WHERE player_id = :player_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard($gridSize = null, $limit = 10) {
        $query = "SELECT g.*, p.username 
                  FROM {$this->table} g
                  INNER JOIN players p ON g.player_id = p.player_id
                  WHERE g.completed = TRUE";
        
        if ($gridSize !== null) {
            $query .= " AND g.grid_size = :grid_size";
        }
        
        $query .= " ORDER BY g.score DESC, g.time_elapsed ASC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        
        if ($gridSize !== null) {
            $stmt->bindParam(':grid_size', $gridSize, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Delete game
     */
    public function delete($gameId) {
        try {
            $query = "DELETE FROM {$this->table} WHERE game_id = :game_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Game deleted successfully'];
            }
        } catch (PDOException $e) {
            error_log("Delete Game Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }

        return ['success' => false, 'message' => 'Failed to delete game'];
    }
}

// ============================================================================
// Statistics Model
// ============================================================================
class Statistics {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get player statistics
     */
    public function getPlayerStats($playerId) {
        $query = "SELECT * FROM statistics WHERE player_id = :player_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get overall game statistics
     */
    public function getOverallStats() {
        $query = "SELECT * FROM vw_game_statistics";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get player leaderboard (Replacing View)
     */
    public function getPlayerLeaderboard($limit = 10) {
        $query = "SELECT 
                    p.player_id,
                    p.username,
                    s.total_games,
                    s.total_wins,
                    s.best_score,
                    s.best_time,
                    s.average_moves,
                    s.average_time
                FROM players p
                INNER JOIN statistics s 
                ON p.player_id = s.player_id
                ORDER BY s.best_score DESC, s.best_time ASC
                LIMIT :limit";
                
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

// ============================================================================
// Multiplayer Model
// ============================================================================


// ============================================================================
// API Router (Improved - Works on All Hosting)
// ============================================================================
class APIRouter {
    private $db;
    private $method;
    private $action;
    private $id;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->method = $_SERVER['REQUEST_METHOD'];
        
        // Use query parameters for routing (works on all hosting)
        $this->action = $_GET['action'] ?? '';
        $this->id = $_GET['id'] ?? null;
    }

    /**
     * Route the request
     */
    public function route() {
        if (!$this->db) {
            return $this->response(['error' => 'Database connection failed'], 500);
        }

        // Route based on action parameter
        switch ($this->action) {
            // Player actions
            case 'create_player':
                return $this->createPlayer();
            case 'get_player':
                return $this->getPlayer();
            case 'get_all_players':
                return $this->getAllPlayers();
            case 'update_player':
                return $this->updatePlayer();
            case 'delete_player':
                return $this->deletePlayer();
            
            // Game actions
            case 'create_game':
                return $this->createGame();
            case 'get_game':
                return $this->getGame();
            case 'get_player_games':
                return $this->getPlayerGames();
            case 'update_game':
                return $this->updateGame();
            case 'delete_game':
                return $this->deleteGame();
            
            // Leaderboard
            case 'get_leaderboard':
                return $this->getLeaderboard();
            
            // Statistics
            case 'get_player_stats':
                return $this->getPlayerStats();
            case 'get_overall_stats':
                return $this->getOverallStats();
            case 'get_player_leaderboard':
                return $this->getPlayerLeaderboard();
            
            // Multiplayer
            case 'create_room':
                return $this->createRoom();
            case 'join_room':
                return $this->joinRoom();
            case 'poll_room':
                return $this->pollRoom();
            case 'submit_move':
                return $this->submitMove();
            
            // Profile actions
            case 'update_profile':
                return $this->updateProfile();
            
            default:
                return $this->response([
                    'error' => 'Invalid action',
                    'available_actions' => [
                        'create_player', 'get_player', 'get_all_players', 'update_player', 'delete_player',
                        'create_game', 'get_game', 'get_player_games', 'update_game', 'delete_game',
                        'get_leaderboard', 'get_player_stats', 'get_overall_stats', 'get_player_leaderboard'
                    ]
                ], 400);
        }
    }

    private function updateProfile() {
        // Handle FormData (Multipart)
        $playerId = $_POST['player_id'] ?? null;
        $username = $_POST['username'] ?? null;
        
        if (!$playerId) return $this->response(['success' => false, 'message' => 'Player ID required'], 400);

        $updates = [];
        if ($username) $updates['username'] = $username;

        // Handle File Upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            
            // Validate image
            $check = getimagesize($file['tmp_name']);
            if ($check === false) return $this->response(['success' => false, 'message' => 'File is not an image'], 400);

            // Generate filename
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $newFilename = 'profile_' . $playerId . '_' . uniqid() . '.' . $ext;
            $targetPath = 'uploads/' . $newFilename;

            // Ensure uploads dir exists
            if (!file_exists('uploads')) mkdir('uploads', 0777, true);

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $updates['profile_picture'] = $newFilename;
            } else {
                return $this->response(['success' => false, 'message' => 'Failed to upload file'], 500);
            }
        }

        if (empty($updates)) {
            return $this->response(['success' => false, 'message' => 'No changes provided'], 400);
        }

        // Update DB (Reusing Player Update Logic but allowing extra fields if I extend Player class)
        // Since Player::update only handles username/email, I might need to extend it or do a direct query here for speed.
        // Let's modify Player::update to support generic data or add a specific method.
        // Actually, let's just use the Player class but I will need to update Player::update to handle profile_picture.
        
        // BETTER: Extend Player::update to support profile_picture
        $player = new Player($this->db);
        // I need to patch Player::update first. For now, let's do a direct update logic here or assuming I'll fix Player::update next.
        // Let's assume I will fix Player::update to handle 'profile_picture' key.
        $result = $player->update($playerId, $updates);
        
        // Return updated player data
        if ($result['success']) {
             $updatedPlayer = $player->getById($playerId);
             // Remove password if it existed
             unset($updatedPlayer['password_hash']); 
             $result['player'] = $updatedPlayer; // Return full player object for frontend state
        }

        return $this->response($result, $result['success'] ? 200 : 400);
    }

    // ========================================================================
    // Player Handlers
    // ========================================================================
    
    private function createPlayer() {
        $data = json_decode(file_get_contents('php://input'), true);
        $player = new Player($this->db);
        
        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        
        $result = $player->create($username, $email);
        return $this->response($result, $result['success'] ? 201 : 400);
    }

    private function getPlayer() {
        $player = new Player($this->db);
        
        if ($this->id) {
            $result = $player->getById($this->id);
        } else if (isset($_GET['username'])) {
            $result = $player->getByUsername($_GET['username']);
        } else {
            return $this->response(['error' => 'Player ID or username required'], 400);
        }
        
        return $this->response($result ? $result : ['error' => 'Player not found'], $result ? 200 : 404);
    }

    private function getAllPlayers() {
        $player = new Player($this->db);
        $result = $player->getAll();
        return $this->response($result);
    }

    private function updatePlayer() {
        if (!$this->id) {
            return $this->response(['error' => 'Player ID required'], 400);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $player = new Player($this->db);
        $result = $player->update($this->id, $data);
        
        return $this->response($result, $result['success'] ? 200 : 400);
    }

    private function deletePlayer() {
        if (!$this->id) {
            return $this->response(['error' => 'Player ID required'], 400);
        }
        
        $player = new Player($this->db);
        $result = $player->delete($this->id);
        
        return $this->response($result, $result['success'] ? 200 : 400);
    }

    // ========================================================================
    // Game Handlers
    // ========================================================================
    
    private function createGame() {
        $data = json_decode(file_get_contents('php://input'), true);
        $game = new Game($this->db);
        
        $playerId = $data['player_id'] ?? null;
        $gridSize = $data['grid_size'] ?? 16;
        $difficulty = $data['difficulty_level'] ?? 'medium';
        
        $result = $game->create($playerId, $gridSize, $difficulty);
        return $this->response($result, $result['success'] ? 201 : 400);
    }

    private function getGame() {
        if (!$this->id) {
            return $this->response(['error' => 'Game ID required'], 400);
        }
        
        $game = new Game($this->db);
        $result = $game->getById($this->id);
        
        return $this->response($result ? $result : ['error' => 'Game not found'], $result ? 200 : 404);
    }

    private function getPlayerGames() {
        $playerId = $_GET['player_id'] ?? null;
        if (!$playerId) {
            return $this->response(['error' => 'Player ID required'], 400);
        }
        
        $game = new Game($this->db);
        $limit = $_GET['limit'] ?? 10;
        $result = $game->getByPlayer($playerId, $limit);
        
        return $this->response($result);
    }

    private function updateGame() {
        if (!$this->id) {
            return $this->response(['error' => 'Game ID required'], 400);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $game = new Game($this->db);
        $result = $game->update($this->id, $data);
        
        return $this->response($result, $result['success'] ? 200 : 400);
    }

    private function deleteGame() {
        if (!$this->id) {
            return $this->response(['error' => 'Game ID required'], 400);
        }
        
        $game = new Game($this->db);
        $result = $game->delete($this->id);
        
        return $this->response($result, $result['success'] ? 200 : 400);
    }

    // ========================================================================
    // Leaderboard & Statistics Handlers
    // ========================================================================
    
    private function getLeaderboard() {
        $game = new Game($this->db);
        $gridSize = isset($_GET['grid_size']) ? (int)$_GET['grid_size'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $result = $game->getLeaderboard($gridSize, $limit);
        return $this->response($result);
    }

    private function getPlayerStats() {
        $playerId = $_GET['player_id'] ?? null;
        if (!$playerId) {
            return $this->response(['error' => 'Player ID required'], 400);
        }
        
        $stats = new Statistics($this->db);
        $result = $stats->getPlayerStats($playerId);
        
        return $this->response($result ? $result : ['error' => 'Statistics not found'], $result ? 200 : 404);
    }

    private function getOverallStats() {
        $stats = new Statistics($this->db);
        $result = $stats->getOverallStats();
        return $this->response($result);
    }

    private function getPlayerLeaderboard() {
        $stats = new Statistics($this->db);
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $result = $stats->getPlayerLeaderboard($limit);
        return $this->response($result);
    }

    // ========================================================================
    // Response Helper
    // ========================================================================
    
    private function response($data, $status = 200) {
        // Clear any previous output (whitespace, warnings)
        ob_clean();
        
        http_response_code($status);
        echo json_encode($data);
        exit();
    }
}

// ============================================================================
// Initialize and route the request
// ============================================================================
$router = new APIRouter();
$router->route();


