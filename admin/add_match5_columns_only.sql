-- Add only the match5 columns for 5-player pickleball support
-- Since most columns already exist, we only need the match5 columns

USE `aitchon_basketball_league`;

-- Check current table structure first
SELECT 'Current pickleball_games table structure:' as info;
DESCRIBE pickleball_games;

-- Add match5_team1_score column (skip if you get duplicate error)
ALTER TABLE `pickleball_games` ADD COLUMN `match5_team1_score` int(11) DEFAULT NULL AFTER `match4_team2_score`;

-- Add match5_team2_score column (skip if you get duplicate error)
ALTER TABLE `pickleball_games` ADD COLUMN `match5_team2_score` int(11) DEFAULT NULL AFTER `match5_team1_score`;

-- Update game_type enum to include 'round_robin' (skip if you get error)
ALTER TABLE `pickleball_games` MODIFY COLUMN `game_type` enum('singles','doubles','mixed','round_robin') NOT NULL DEFAULT 'singles';

-- Create match assignments table if it doesn't exist
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

-- Show final result
SELECT 'Update completed! Final table structure:' as status;
DESCRIBE pickleball_games;

