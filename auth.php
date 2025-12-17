<?php
/**
 * Authentication API for Memory Card Game
 * Handles user registration, login, and session management
 * 
 * @author [Malika]
 * @version 1.1
 * @date December 10, 2025
 */

// Include shared configuration
require_once 'config.php';

// Start session
session_start();

// Set headers for CORS and JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// Authentication Class
// ============================================================================
class Auth {
    private $conn;
    private $playersTable = 'players';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Register new user
     */
    public function signup($username, $email, $password) {
        // Validate inputs
        $usernameValidation = Validator::validateUsername($username);
        if (!$usernameValidation['valid']) {
            return ['success' => false, 'message' => $usernameValidation['message']];
        }

        $emailValidation = Validator::validateEmail($email);
        if (!$emailValidation['valid']) {
            return ['success' => false, 'message' => $emailValidation['message']];
        }

        $passwordValidation = Validator::validatePassword($password);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'message' => $passwordValidation['message']];
        }

        // Sanitize inputs
        $username = Validator::sanitize($username);
        $email = $email ? Validator::sanitize($email) : null;

        // Check if username already exists
        if ($this->usernameExists($username)) {
            return ['success' => false, 'message' => 'Username already taken'];
        }

        // Check if email already exists (if provided)
        if ($email && $this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Hash password securely
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Add password_hash column to players table if not exists
            $query = "INSERT INTO {$this->playersTable} 
                      (username, email, password_hash) 
                      VALUES (:username, :email, :password_hash)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $passwordHash);

            if ($stmt->execute()) {
                $playerId = $this->conn->lastInsertId();
                
                // Auto-login: Create session
                $sessionToken = $this->createSession($playerId);
                
                // Update last login
                $this->updateLastLogin($playerId);

                return [
                    'success' => true,
                    'message' => 'Account created successfully',
                    'player_id' => $playerId,
                    'username' => $username,
                    'email' => $email,
                    'session_token' => $sessionToken
                ];
            }
        } catch (PDOException $e) {
            error_log("Signup Error: " . $e->getMessage());
            
            // Check if password_hash column doesn't exist
            if (strpos($e->getMessage(), 'password_hash') !== false) {
                return [
                    'success' => false, 
                    'message' => 'Database schema needs update. Please run SQL update script.'
                ];
            }
            
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }

        return ['success' => false, 'message' => 'Registration failed'];
    }

    /**
     * Login user
     */
    public function login($username, $password, $rememberMe = false) {
        // Validate inputs
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        $username = Validator::sanitize($username);

        try {
            // Get user from database
            $query = "SELECT player_id, username, email, password_hash 
                      FROM {$this->playersTable} 
                      WHERE username = :username";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            // Check if password_hash exists
            if (!isset($user['password_hash'])) {
                return [
                    'success' => false, 
                    'message' => 'Account needs password setup. Please contact administrator.'
                ];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }

            // Create session
            $sessionToken = $this->createSession($user['player_id'], $rememberMe);

            // Update last login time
            $this->updateLastLogin($user['player_id']);

            return [
                'success' => true,
                'message' => 'Login successful',
                'player_id' => $user['player_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'session_token' => $sessionToken
            ];

        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        // Destroy session
        session_unset();
        session_destroy();

        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Verify session
     */
    public function verifySession($sessionToken) {
        if (empty($sessionToken)) {
            return ['success' => false, 'message' => 'No session token provided'];
        }

        // In production, you'd verify against database
        // For now, check PHP session
        if (isset($_SESSION['player_id']) && isset($_SESSION['session_token'])) {
            if ($_SESSION['session_token'] === $sessionToken) {
                return [
                    'success' => true,
                    'player_id' => $_SESSION['player_id'],
                    'username' => $_SESSION['username']
                ];
            }
        }

        return ['success' => false, 'message' => 'Invalid session'];
    }

    /**
     * Check if username exists
     */
    private function usernameExists($username) {
        $query = "SELECT player_id FROM {$this->playersTable} WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch() !== false;
    }

    /**
     * Check if email exists
     */
    private function emailExists($email) {
        $query = "SELECT player_id FROM {$this->playersTable} WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch() !== false;
    }

    /**
     * Create session token
     */
    private function createSession($playerId, $rememberMe = false) {
        // Generate secure session token
        $sessionToken = bin2hex(random_bytes(32));

        // Store in PHP session
        $_SESSION['player_id'] = $playerId;
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['logged_in_at'] = time();

        // Get username for session
        $query = "SELECT username FROM {$this->playersTable} WHERE player_id = :player_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':player_id', $playerId);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['username'] = $user['username'];
        }

        // Set session lifetime
        if ($rememberMe) {
            // Remember for 30 days
            ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
            session_set_cookie_params(30 * 24 * 60 * 60);
        }

        return $sessionToken;
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin($playerId) {
        try {
            $query = "UPDATE {$this->playersTable} 
                      SET updated_at = CURRENT_TIMESTAMP 
                      WHERE player_id = :player_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':player_id', $playerId);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }

    /**
     * Change password
     */
    public function changePassword($playerId, $currentPassword, $newPassword) {
        // Get current password hash
        $query = "SELECT password_hash FROM {$this->playersTable} WHERE player_id = :player_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':player_id', $playerId);
        $stmt->execute();
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        // Validate new password
        $passwordValidation = Validator::validatePassword($newPassword);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'message' => $passwordValidation['message']];
        }

        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password
        try {
            $query = "UPDATE {$this->playersTable} 
                      SET password_hash = :password_hash 
                      WHERE player_id = :player_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':password_hash', $newPasswordHash);
            $stmt->bindParam(':player_id', $playerId);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Password updated successfully'];
            }
        } catch (PDOException $e) {
            error_log("Change password error: " . $e->getMessage());
        }

        return ['success' => false, 'message' => 'Failed to update password'];
    }
}

// ============================================================================
// API Router
// ============================================================================
class AuthRouter {
    private $db;
    private $auth;
    private $action;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new Auth($this->db);
        $this->action = $_GET['action'] ?? '';
    }

    public function route() {
        if (!$this->db) {
            return $this->response(['error' => 'Database connection failed'], 500);
        }

        switch ($this->action) {
            case 'signup':
                return $this->handleSignup();
            case 'login':
                return $this->handleLogin();
            case 'logout':
                return $this->handleLogout();
            case 'verify_session':
                return $this->handleVerifySession();
            case 'change_password':
                return $this->handleChangePassword();
            default:
                return $this->response([
                    'error' => 'Invalid action',
                    'available_actions' => ['signup', 'login', 'logout', 'verify_session', 'change_password']
                ], 400);
        }
    }

    private function handleSignup() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        $result = $this->auth->signup($username, $email, $password);
        return $this->response($result, $result['success'] ? 201 : 400);
    }

    private function handleLogin() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $rememberMe = $data['remember_me'] ?? false;

        $result = $this->auth->login($username, $password, $rememberMe);
        return $this->response($result, $result['success'] ? 200 : 401);
    }

    private function handleLogout() {
        $result = $this->auth->logout();
        return $this->response($result);
    }

    private function handleVerifySession() {
        $data = json_decode(file_get_contents('php://input'), true);
        $sessionToken = $data['session_token'] ?? null;

        $result = $this->auth->verifySession($sessionToken);
        return $this->response($result, $result['success'] ? 200 : 401);
    }

    private function handleChangePassword() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $playerId = $data['player_id'] ?? null;
        $currentPassword = $data['current_password'] ?? null;
        $newPassword = $data['new_password'] ?? null;

        $result = $this->auth->changePassword($playerId, $currentPassword, $newPassword);
        return $this->response($result, $result['success'] ? 200 : 400);
    }

    private function response($data, $status = 200) {
        // Clear any previous output (whitespace, warnings)
        ob_clean();
        
        http_response_code($status);
        echo json_encode($data);
        exit();
    }
}

// ============================================================================
// Initialize and route
// ============================================================================
$router = new AuthRouter();
$router->route();

