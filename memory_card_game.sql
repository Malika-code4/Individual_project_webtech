-- ============================================================================
-- Memory Card Game - Database Schema
-- ============================================================================
-- Description: Complete database schema for Memory Card Game application
-- Author: [Your Name]
-- Date: November 12, 2025
-- Version: 1.0
-- ============================================================================

-- Drop database if exists (for clean installation)
-- DROP DATABASE IF EXISTS memory_card_game;


-- Use the database
USE webtech_2025A_fannareme_abdou;

-- ============================================================================
-- Table: players
-- Description: Stores player information
-- ============================================================================
CREATE TABLE players (
    player_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NULL,
    password_hash VARCHAR(255) NULL,
    total_games_played INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================================
-- Table: games
-- Description: Stores individual game session data
-- ============================================================================
CREATE TABLE games (
    game_id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NULL,
    grid_size INT NOT NULL DEFAULT 16,
    total_moves INT NOT NULL DEFAULT 0,
    time_elapsed INT NOT NULL DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    score INT NULL,
    difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_player_id (player_id),
    INDEX idx_completed (completed),
    INDEX idx_score (score),
    INDEX idx_created_at (created_at),
    CONSTRAINT chk_grid_size CHECK (grid_size IN (16, 24, 36)),
    CONSTRAINT chk_moves CHECK (total_moves >= 0),
    CONSTRAINT chk_time CHECK (time_elapsed >= 0)
) ENGINE=InnoDB;

-- ============================================================================
-- Table: high_scores
-- Description: Tracks top player performances
-- ============================================================================
CREATE TABLE high_scores (
    score_id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    game_id INT NOT NULL,
    score INT NOT NULL,
    grid_size INT NOT NULL,
    total_moves INT NOT NULL,
    time_elapsed INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_score (score DESC),
    INDEX idx_player_id (player_id),
    INDEX idx_grid_size (grid_size),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================================
-- Table: statistics
-- Description: Stores aggregated player statistics
-- ============================================================================
CREATE TABLE statistics (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL UNIQUE,
    total_games INT DEFAULT 0,
    total_wins INT DEFAULT 0,
    average_moves DECIMAL(10, 2) NULL,
    average_time DECIMAL(10, 2) NULL,
    best_score INT NULL,
    best_time INT NULL,
    fastest_completion INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_best_score (best_score DESC),
    INDEX idx_total_wins (total_wins DESC)
) ENGINE=InnoDB;

-- ============================================================================
-- Table: game_settings
-- Description: Stores configurable game settings
-- ============================================================================
CREATE TABLE game_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================================
-- Insert Default Game Settings
-- ============================================================================
INSERT INTO game_settings (setting_name, setting_value, description) VALUES
('default_grid_size', '16', 'Default grid size (4x4 = 16 cards)'),
('easy_grid_size', '16', 'Easy difficulty grid size'),
('medium_grid_size', '24', 'Medium difficulty grid size'),
('hard_grid_size', '36', 'Hard difficulty grid size'),
('flip_animation_duration', '600', 'Card flip animation duration in ms'),
('mismatch_delay', '1000', 'Delay before flipping mismatched cards back (ms)');

-- ============================================================================
-- Insert Sample Data for Testing
-- ============================================================================

-- Sample players
INSERT INTO players (username, email, password_hash, total_games_played) VALUES
('player1', 'player1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0), -- password
('player2', 'player2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0),
('player3', 'player3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0),
('guest_user', NULL, NULL, 0);

-- Sample games
INSERT INTO games (player_id, grid_size, total_moves, time_elapsed, completed, score, difficulty_level, completed_at) VALUES
(1, 16, 20, 45, TRUE, 850, 'easy', NOW()),
(1, 24, 35, 78, TRUE, 720, 'medium', NOW()),
(2, 16, 18, 40, TRUE, 880, 'easy', NOW()),
(2, 36, 50, 120, TRUE, 650, 'hard', NOW()),
(3, 16, 25, 60, TRUE, 780, 'easy', NOW());

-- Sample high scores
INSERT INTO high_scores (player_id, game_id, score, grid_size, total_moves, time_elapsed) VALUES
(2, 3, 880, 16, 18, 40),
(1, 1, 850, 16, 20, 45),
(3, 5, 780, 16, 25, 60),
(1, 2, 720, 24, 35, 78),
(2, 4, 650, 36, 50, 120);

-- Sample statistics
INSERT INTO statistics (player_id, total_games, total_wins, average_moves, average_time, best_score, best_time, fastest_completion) VALUES
(1, 2, 2, 27.50, 61.50, 850, 45, 45),
(2, 2, 2, 34.00, 80.00, 880, 40, 40),
(3, 1, 1, 25.00, 60.00, 780, 60, 60);

-- ============================================================================
-- Useful Views
-- ============================================================================



-- View: Recent High Scores
CREATE VIEW vw_recent_high_scores AS
SELECT 
    hs.score_id,
    p.username,
    hs.score,
    hs.grid_size,
    hs.total_moves,
    hs.time_elapsed,
    hs.created_at
FROM high_scores hs
INNER JOIN players p ON hs.player_id = p.player_id
ORDER BY hs.created_at DESC
LIMIT 10;

-- View: Game Statistics Dashboard
CREATE VIEW vw_game_statistics AS
SELECT 
    COUNT(*) as total_games,
    COUNT(DISTINCT player_id) as total_players,
    AVG(total_moves) as avg_moves,
    AVG(time_elapsed) as avg_time,
    MIN(time_elapsed) as fastest_time,
    MAX(score) as highest_score
FROM games
WHERE completed = TRUE;

-- ============================================================================
-- Stored Procedures
-- ============================================================================

-- Procedure: Create or Update Player Statistics
DELIMITER //

CREATE PROCEDURE update_player_statistics(IN p_player_id INT)
BEGIN
    DECLARE v_total_games INT;
    DECLARE v_total_wins INT;
    DECLARE v_avg_moves DECIMAL(10, 2);
    DECLARE v_avg_time DECIMAL(10, 2);
    DECLARE v_best_score INT;
    DECLARE v_best_time INT;
    DECLARE v_fastest_completion INT;
    
    -- Calculate statistics
    SELECT 
        COUNT(*),
        SUM(CASE WHEN completed = TRUE THEN 1 ELSE 0 END),
        AVG(total_moves),
        AVG(time_elapsed),
        MAX(score),
        MIN(time_elapsed),
        MIN(time_elapsed)
    INTO 
        v_total_games,
        v_total_wins,
        v_avg_moves,
        v_avg_time,
        v_best_score,
        v_best_time,
        v_fastest_completion
    FROM games
    WHERE player_id = p_player_id AND completed = TRUE;
    
    -- Insert or update statistics
    INSERT INTO statistics (
        player_id,
        total_games,
        total_wins,
        average_moves,
        average_time,
        best_score,
        best_time,
        fastest_completion
    ) VALUES (
        p_player_id,
        v_total_games,
        v_total_wins,
        v_avg_moves,
        v_avg_time,
        v_best_score,
        v_best_time,
        v_fastest_completion
    )
    ON DUPLICATE KEY UPDATE
        total_games = v_total_games,
        total_wins = v_total_wins,
        average_moves = v_avg_moves,
        average_time = v_avg_time,
        best_score = v_best_score,
        best_time = v_best_time,
        fastest_completion = v_fastest_completion;
END //

-- Procedure: Add High Score if Qualified
CREATE PROCEDURE add_high_score(
    IN p_player_id INT,
    IN p_game_id INT,
    IN p_score INT,
    IN p_grid_size INT,
    IN p_moves INT,
    IN p_time INT
)
BEGIN
    -- Insert the high score
    INSERT INTO high_scores (
        player_id,
        game_id,
        score,
        grid_size,
        total_moves,
        time_elapsed
    ) VALUES (
        p_player_id,
        p_game_id,
        p_score,
        p_grid_size,
        p_moves,
        p_time
    );
    
    -- Update player statistics
    CALL update_player_statistics(p_player_id);
END //

DELIMITER ;

-- ============================================================================
-- Triggers
-- ============================================================================

-- Trigger: Update player's total games count after new game
DELIMITER //

CREATE TRIGGER after_game_insert
AFTER INSERT ON games
FOR EACH ROW
BEGIN
    IF NEW.player_id IS NOT NULL THEN
        UPDATE players 
        SET total_games_played = total_games_played + 1
        WHERE player_id = NEW.player_id;
    END IF;
END //

-- Trigger: Update statistics when game is completed
CREATE TRIGGER after_game_complete
AFTER UPDATE ON games
FOR EACH ROW
BEGIN
    IF NEW.completed = TRUE AND OLD.completed = FALSE AND NEW.player_id IS NOT NULL THEN
        CALL update_player_statistics(NEW.player_id);
        
        -- Check if score qualifies for high scores
        IF NEW.score IS NOT NULL THEN
            CALL add_high_score(
                NEW.player_id,
                NEW.game_id,
                NEW.score,
                NEW.grid_size,
                NEW.total_moves,
                NEW.time_elapsed
            );
        END IF;
    END IF;
END //

DELIMITER ;

