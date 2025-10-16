-- Safe Update Pickleball System to Support 5 Players and 5 Matches
-- This script safely updates the existing pickleball system to support 4-5 players per court
-- with 3-5 matches accordingly (3 matches for 4 players, 5 matches for 5 players)

USE `aitchon_basketball_league`;

-- 1. Add player5_id column to pickleball_games table (safe method)
-- First, let's check what columns exist
SELECT 'Checking existing columns in pickleball_games table...' as status;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'pickleball_games' 
ORDER BY ORDINAL_POSITION;

-- Add player5_id column only if it doesn't exist
-- Note: If you get an error here, the column already exists and you can skip this step
ALTER TABLE `pickleball_games` 
ADD COLUMN `player5_id` int(11) DEFAULT NULL AFTER `player4_id`;

-- Add key and constraint for player5_id
ALTER TABLE `pickleball_games` 
ADD KEY `player5_id` (`player5_id`);

ALTER TABLE `pickleball_games` 
ADD CONSTRAINT `fk_pickleball_games_player5` 
FOREIGN KEY (`player5_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL;

-- 2. Add match score columns (safe method)
-- Add match4 columns
ALTER TABLE `pickleball_games` 
ADD COLUMN `match4_team1_score` int(11) DEFAULT NULL AFTER `match3_team2_score`;

ALTER TABLE `pickleball_games` 
ADD COLUMN `match4_team2_score` int(11) DEFAULT NULL AFTER `match4_team1_score`;

-- Add match5 columns
ALTER TABLE `pickleball_games` 
ADD COLUMN `match5_team1_score` int(11) DEFAULT NULL AFTER `match4_team2_score`;

ALTER TABLE `pickleball_games` 
ADD COLUMN `match5_team2_score` int(11) DEFAULT NULL AFTER `match5_team1_score`;

-- 3. Update game_type enum to include 'round_robin' for 5-player games
ALTER TABLE `pickleball_games` 
MODIFY COLUMN `game_type` enum('singles','doubles','mixed','round_robin') NOT NULL DEFAULT 'singles';

-- 4. Add player5_score column to pickleball_scores table (if table exists)
ALTER TABLE `pickleball_scores` 
ADD COLUMN `player5_score` int(11) DEFAULT NULL AFTER `player4_score`;

-- 5. Create match assignments table if it doesn't exist
CREATE TABLE IF NOT EXISTS `pickleball_match_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `match_number` int(11) NOT NULL, -- 1, 2, 3, 4, or 5
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

-- 6. Add match assignments for existing games
-- Add match 1 assignments for existing doubles games
INSERT IGNORE INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
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
AND pg.player4_id IS NOT NULL;

-- Add match 2 assignments for existing doubles games
INSERT IGNORE INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
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
AND pg.player4_id IS NOT NULL;

-- Add match 3 assignments for existing doubles games
INSERT IGNORE INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
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
AND pg.player4_id IS NOT NULL;

-- Add match 4 assignments for existing 5-player games
INSERT IGNORE INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
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
AND pg.player5_id IS NOT NULL;

-- Add match 5 assignments for existing 5-player games
INSERT IGNORE INTO `pickleball_match_assignments` (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
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
AND pg.player5_id IS NOT NULL;

-- 7. Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_pickleball_games_player5` ON `pickleball_games` (`player5_id`);
CREATE INDEX IF NOT EXISTS `idx_pickleball_match_assignments_game_match` ON `pickleball_match_assignments` (`game_id`, `match_number`);

-- 8. Show summary of changes
SELECT 'Database schema updated successfully' as status;
SELECT 'Added player5_id column to pickleball_games' as change_1;
SELECT 'Added match4 and match5 score columns to pickleball_games' as change_2;
SELECT 'Added round_robin to game_type enum' as change_3;
SELECT 'Added player5_score to pickleball_scores' as change_4;
SELECT 'Created pickleball_match_assignments table' as change_5;
SELECT 'Added match assignments for existing games (3 matches for 4 players, 5 matches for 5 players)' as change_6;

-- 9. Show final table structure
SELECT 'Final pickleball_games table structure:' as info;
DESCRIBE pickleball_games;

