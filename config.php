<?php
/**
 * Shared Configuration & Utilities
 * 
 * Contains Database connection details, common constants, and
 * shared utility classes like Validator.
 */

// Define constants
define('DB_HOST', '169.239.251.102:34');
define('DB_NAME', 'webtech_2025A_fannareme_abdou');
define('DB_USER', 'fannareme.abdou');
define('DB_PASS', 'fa889033');

// Disable error reporting to browser (prevent breaking JSON)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Start output buffering to catch unwanted whitespace/output
ob_start();

// ============================================================================
// Database Class
// ============================================================================
class Database {
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            // In a real app, you might want to show a generic error page
            // die("Connection failed"); 
        }

        return $this->conn;
    }
}

// ============================================================================
// Validator Class
// ============================================================================
class Validator {
    /**
     * Validate username
     * Must be alphanumeric, 3-50 characters
     */
    public static function validateUsername($username) {
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            return [
                'valid' => false,
                'message' => 'Username must be 3-50 alphanumeric characters'
            ];
        }
        return ['valid' => true];
    }

    /**
     * Validate email
     */
    public static function validateEmail($email) {
        if (empty($email)) {
            return ['valid' => true]; // Email is optional
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Invalid email format'
            ];
        }
        return ['valid' => true];
    }

    /**
     * Validate password
     */
    public static function validatePassword($password) {
        if (strlen($password) < 6) {
            return [
                'valid' => false,
                'message' => 'Password must be at least 6 characters long'
            ];
        }
        return ['valid' => true];
    }

    /**
     * Validate integer field
     */
    public static function validateInteger($value, $min = 0, $max = PHP_INT_MAX) {
        if (!is_numeric($value) || $value < $min || $value > $max) {
            return [
                'valid' => false,
                'message' => "Value must be between {$min} and {$max}"
            ];
        }
        return ['valid' => true];
    }

    /**
     * Validate grid size
     */
    public static function validateGridSize($gridSize) {
        $validSizes = [16, 24, 36];
        if (!in_array((int)$gridSize, $validSizes)) {
            return [
                'valid' => false,
                'message' => 'Grid size must be 16, 24, or 36'
            ];
        }
        return ['valid' => true];
    }

    /**
     * Sanitize input
     */
    public static function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}
