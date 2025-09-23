-- Add season_id column to players table if it doesn't exist
-- Run this script to enable season-based player filtering

-- Check if the column exists first
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'players' 
  AND COLUMN_NAME = 'season_id';

-- Add the column if it doesn't exist
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE players ADD COLUMN season_id INT(11) DEFAULT NULL AFTER team_id', 
    'SELECT "Column season_id already exists" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if the column was added
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'players' 
  AND COLUMN_NAME = 'season_id';

SET @sql = IF(@col_exists > 0, 
    'ALTER TABLE players ADD CONSTRAINT fk_players_season FOREIGN KEY (season_id) REFERENCES seasons(id)', 
    'SELECT "Column season_id does not exist, skipping foreign key" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

