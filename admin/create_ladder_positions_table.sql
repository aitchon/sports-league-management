-- Create the pickleball_ladder_positions table if it doesn't exist
USE `aitchon_basketball_league`;

-- Check if table exists
SELECT 'Checking if pickleball_ladder_positions table exists...' as status;

-- Create table if it doesn't exist
CREATE TABLE IF NOT EXISTS `pickleball_ladder_positions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `player_id` INT NOT NULL,
  `season_id` INT NOT NULL,
  `position` INT NOT NULL,
  `wins` INT NOT NULL DEFAULT 0,
  `losses` INT NOT NULL DEFAULT 0,
  `points_scored` INT NOT NULL DEFAULT 0,
  `points_against` INT NOT NULL DEFAULT 0,
  `win_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `point_differential` INT NOT NULL DEFAULT 0,
  `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `player_season_unique` (`player_id`, `season_id`),
  KEY `season_id` (`season_id`),
  KEY `position` (`position`),
  CONSTRAINT `fk_ladder_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ladder_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Show table structure
SELECT 'Table created/verified. Structure:' as status;
DESCRIBE pickleball_ladder_positions;

-- Check if there are any existing records
SELECT 'Current records in table:' as status;
SELECT COUNT(*) as record_count FROM pickleball_ladder_positions;