-- Minimal Update Pickleball System to Support 5 Players and 5 Matches
-- This script only adds what's missing, step by step

USE `aitchon_basketball_league`;

-- Step 1: Check current table structure
SELECT 'Current pickleball_games table structure:' as info;
DESCRIBE pickleball_games;

-- Step 2: Add player5_id column (skip if already exists)
-- If you get "Duplicate column name 'player5_id'" error, skip this line
ALTER TABLE `pickleball_games` ADD COLUMN `player5_id` int(11) DEFAULT NULL AFTER `player4_id`;

-- Step 3: Add match4_team1_score column (skip if already exists)
-- If you get "Duplicate column name 'match4_team1_score'" error, skip this line
ALTER TABLE `pickleball_games` ADD COLUMN `match4_team1_score` int(11) DEFAULT NULL AFTER `match3_team2_score`;

-- Step 4: Add match4_team2_score column (skip if already exists)
-- If you get "Duplicate column name 'match4_team2_score'" error, skip this line
ALTER TABLE `pickleball_games` ADD COLUMN `match4_team2_score` int(11) DEFAULT NULL AFTER `match4_team1_score`;

-- Step 5: Add match5_team1_score column (skip if already exists)
-- If you get "Duplicate column name 'match5_team1_score'" error, skip this line
ALTER TABLE `pickleball_games` ADD COLUMN `match5_team1_score` int(11) DEFAULT NULL AFTER `match4_team2_score`;

-- Step 6: Add match5_team2_score column (skip if already exists)
-- If you get "Duplicate column name 'match5_team2_score'" error, skip this line
ALTER TABLE `pickleball_games` ADD COLUMN `match5_team2_score` int(11) DEFAULT NULL AFTER `match5_team1_score`;

-- Step 7: Add key for player5_id (skip if already exists)
-- If you get "Duplicate key name 'player5_id'" error, skip this line
ALTER TABLE `pickleball_games` ADD KEY `player5_id` (`player5_id`);

-- Step 8: Add foreign key constraint for player5_id (skip if already exists)
-- If you get "Duplicate key name 'fk_pickleball_games_player5'" error, skip this line
ALTER TABLE `pickleball_games` ADD CONSTRAINT `fk_pickleball_games_player5` FOREIGN KEY (`player5_id`) REFERENCES `pickleball_players` (`id`) ON DELETE SET NULL;

-- Step 9: Update game_type enum to include 'round_robin'
-- If you get an error, the enum might already be updated
ALTER TABLE `pickleball_games` MODIFY COLUMN `game_type` enum('singles','doubles','mixed','round_robin') NOT NULL DEFAULT 'singles';

-- Step 10: Add player5_score to pickleball_scores table (if table exists)
-- If you get "Duplicate column name 'player5_score'" error, skip this line
ALTER TABLE `pickleball_scores` ADD COLUMN `player5_score` int(11) DEFAULT NULL AFTER `player4_score`;

-- Step 11: Create match assignments table if it doesn't exist
CREATE TABLE IF NOT EXISTS `pickleball_match_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `match_number` int(11) NOT NULL,
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

-- Step 12: Add match assignments for existing games (using INSERT IGNORE to avoid duplicates)
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

-- Step 13: Add indexes (skip if they already exist)
-- If you get "Duplicate key name" errors, skip these lines
CREATE INDEX `idx_pickleball_games_player5` ON `pickleball_games` (`player5_id`);
CREATE INDEX `idx_pickleball_match_assignments_game_match` ON `pickleball_match_assignments` (`game_id`, `match_number`);

-- Step 14: Show final result
SELECT 'Update completed successfully!' as status;
SELECT 'Final pickleball_games table structure:' as info;
DESCRIBE pickleball_games;

