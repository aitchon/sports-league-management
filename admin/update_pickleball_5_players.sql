-- Update Pickleball System to Support 5 Players and 4 Matches
-- This script updates the existing pickleball system to support 4-5 players per court
-- with 3-4 matches accordingly (3 matches for 4 players, 4 matches for 5 players)

USE `aitchon_basketball_league`;

-- 1. Add player5_id column to pickleball_games table (if it doesn't exist)
-- Check if column exists and add it if it doesn't
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'pickleball_games' 
AND COLUMN_NAME = 'player5_id';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `pickleball_games` ADD COLUMN `player5_id` int(11) DEFAULT NULL AFTER `player4_id`',
    'SELECT "Column player5_id already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add key and constraint for player5_id (if column exists)
SELECT COUNT(*) INTO @col_exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'pickleball_games' 
AND COLUMN_NAME = 'player5_id';

SET @sql = IF(@col_exists > 0, 
    'ALTER TABLE `pickleball_games` ADD KEY `player5_id` (`player5_id`), ADD CONSTRAINT `fk_pickleball_games_player5` FOREIGN KEY (`player5_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL',
    'SELECT "Column player5_id does not exist, skipping key/constraint" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add match4 and match5 columns to pickleball_games table for 4th and 5th matches (if they don't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pickleball_games' 
     AND COLUMN_NAME = 'match4_team1_score') = 0,
    'ALTER TABLE `pickleball_games` ADD COLUMN `match4_team1_score` int(11) DEFAULT NULL AFTER `match3_team2_score`',
    'SELECT "Column match4_team1_score already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pickleball_games' 
     AND COLUMN_NAME = 'match4_team2_score') = 0,
    'ALTER TABLE `pickleball_games` ADD COLUMN `match4_team2_score` int(11) DEFAULT NULL AFTER `match4_team1_score`',
    'SELECT "Column match4_team2_score already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pickleball_games' 
     AND COLUMN_NAME = 'match5_team1_score') = 0,
    'ALTER TABLE `pickleball_games` ADD COLUMN `match5_team1_score` int(11) DEFAULT NULL AFTER `match4_team2_score`',
    'SELECT "Column match5_team1_score already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pickleball_games' 
     AND COLUMN_NAME = 'match5_team2_score') = 0,
    'ALTER TABLE `pickleball_games` ADD COLUMN `match5_team2_score` int(11) DEFAULT NULL AFTER `match5_team1_score`',
    'SELECT "Column match5_team2_score already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Update game_type enum to include 'round_robin' for 5-player games (if not already updated)
SET @sql = (SELECT IF(
    (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pickleball_games' 
     AND COLUMN_NAME = 'game_type' 
     AND COLUMN_TYPE LIKE '%round_robin%') IS NULL,
    'ALTER TABLE `pickleball_games` MODIFY COLUMN `game_type` enum(\'singles\',\'doubles\',\'mixed\',\'round_robin\') NOT NULL DEFAULT \'singles\'',
    'SELECT "game_type enum already includes round_robin" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add player5_score column to pickleball_scores table (if it doesn't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pickleball_scores' 
     AND COLUMN_NAME = 'player5_score') = 0,
    'ALTER TABLE `pickleball_scores` ADD COLUMN `player5_score` int(11) DEFAULT NULL AFTER `player4_score`',
    'SELECT "Column player5_score already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Add new table for round-robin match assignments (which players play in which matches)
CREATE TABLE IF NOT EXISTS `pickleball_match_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `match_number` int(11) NOT NULL, -- 1, 2, 3, or 4
  `team1_player1_id` int(11) DEFAULT NULL,
  `team1_player2_id` int(11) DEFAULT NULL,
  `team2_player1_id` int(11) DEFAULT NULL,
  `team2_player2_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `match_number` (`match_number`),
  CONSTRAINT `fk_pickleball_match_assignments_game` FOREIGN KEY (`game_id`) REFERENCES `pickleball_games` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pickleball_match_assignments_team1_p1` FOREIGN KEY (`team1_player1_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_match_assignments_team1_p2` FOREIGN KEY (`team1_player2_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_match_assignments_team2_p1` FOREIGN KEY (`team2_player1_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pickleball_match_assignments_team2_p2` FOREIGN KEY (`team2_player2_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Add validation constraints
-- Add check constraint to ensure we have either 4 or 5 players for round_robin games
-- (Note: MySQL doesn't support CHECK constraints in older versions, so we'll handle this in application logic)

-- 7. Update existing games to have proper match assignments if they don't exist
-- This will create match assignments for existing games
INSERT INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
SELECT 
    pg.id as game_id,
    1 as match_number,
    pg.player1_id as team1_player1_id,
    pg.player2_id as team1_player2_id,
    pg.player3_id as team2_player1_id,
    pg.player4_id as team2_player2_id
FROM pickleball_games pg
WHERE pg.game_type IN ('doubles', 'mixed') 
AND pg.player1_id IS NOT NULL 
AND pg.player2_id IS NOT NULL 
AND pg.player3_id IS NOT NULL 
AND pg.player4_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM pickleball_match_assignments pma 
    WHERE pma.game_id = pg.id AND pma.match_number = 1
);

-- Add match 2 assignments for existing doubles games
INSERT INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
SELECT 
    pg.id as game_id,
    2 as match_number,
    pg.player1_id as team1_player1_id,
    pg.player3_id as team1_player2_id,
    pg.player2_id as team2_player1_id,
    pg.player4_id as team2_player2_id
FROM pickleball_games pg
WHERE pg.game_type IN ('doubles', 'mixed') 
AND pg.player1_id IS NOT NULL 
AND pg.player2_id IS NOT NULL 
AND pg.player3_id IS NOT NULL 
AND pg.player4_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM pickleball_match_assignments pma 
    WHERE pma.game_id = pg.id AND pma.match_number = 2
);

-- Add match 3 assignments for existing doubles games
INSERT INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
SELECT 
    pg.id as game_id,
    3 as match_number,
    pg.player1_id as team1_player1_id,
    pg.player4_id as team1_player2_id,
    pg.player2_id as team2_player1_id,
    pg.player3_id as team2_player2_id
FROM pickleball_games pg
WHERE pg.game_type IN ('doubles', 'mixed') 
AND pg.player1_id IS NOT NULL 
AND pg.player2_id IS NOT NULL 
AND pg.player3_id IS NOT NULL 
AND pg.player4_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM pickleball_match_assignments pma 
    WHERE pma.game_id = pg.id AND pma.match_number = 3
);

-- Add match 4 assignments for existing 5-player games
INSERT INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
SELECT 
    pg.id as game_id,
    4 as match_number,
    pg.player1_id as team1_player1_id,
    pg.player5_id as team1_player2_id,
    pg.player2_id as team2_player1_id,
    pg.player3_id as team2_player2_id
FROM pickleball_games pg
WHERE pg.game_type = 'round_robin' 
AND pg.player1_id IS NOT NULL 
AND pg.player2_id IS NOT NULL 
AND pg.player3_id IS NOT NULL 
AND pg.player4_id IS NOT NULL
AND pg.player5_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM pickleball_match_assignments pma 
    WHERE pma.game_id = pg.id AND pma.match_number = 4
);

-- Add match 5 assignments for existing 5-player games
INSERT INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
SELECT 
    pg.id as game_id,
    5 as match_number,
    pg.player2_id as team1_player1_id,
    pg.player4_id as team1_player2_id,
    pg.player3_id as team2_player1_id,
    pg.player5_id as team2_player2_id
FROM pickleball_games pg
WHERE pg.game_type = 'round_robin' 
AND pg.player1_id IS NOT NULL 
AND pg.player2_id IS NOT NULL 
AND pg.player3_id IS NOT NULL 
AND pg.player4_id IS NOT NULL
AND pg.player5_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM pickleball_match_assignments pma 
    WHERE pma.game_id = pg.id AND pma.match_number = 5
);

-- 8. Add indexes for better performance (if they don't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pickleball_games' 
     AND INDEX_NAME = 'idx_pickleball_games_player5') = 0,
    'CREATE INDEX `idx_pickleball_games_player5` ON `pickleball_games` (`player5_id`)',
    'SELECT "Index idx_pickleball_games_player5 already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'pickleball_match_assignments' 
     AND INDEX_NAME = 'idx_pickleball_match_assignments_game_match') = 0,
    'CREATE INDEX `idx_pickleball_match_assignments_game_match` ON `pickleball_match_assignments` (`game_id`, `match_number`)',
    'SELECT "Index idx_pickleball_match_assignments_game_match already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 9. Show summary of changes
SELECT 'Database schema updated successfully' as status;
SELECT 'Added player5_id column to pickleball_games' as change_1;
SELECT 'Added match4 and match5 score columns to pickleball_games' as change_2;
SELECT 'Added round_robin to game_type enum' as change_3;
SELECT 'Added player5_score to pickleball_scores' as change_4;
SELECT 'Created pickleball_match_assignments table' as change_5;
SELECT 'Added match assignments for existing games (3 matches for 4 players, 5 matches for 5 players)' as change_6;
