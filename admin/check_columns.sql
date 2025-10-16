-- Check what columns exist in pickleball_games table
USE `aitchon_basketball_league`;

SELECT 'Current columns in pickleball_games table:' as info;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'pickleball_games' 
AND COLUMN_NAME LIKE '%match%'
ORDER BY ORDINAL_POSITION;

SELECT 'Player columns:' as info;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'pickleball_games' 
AND COLUMN_NAME LIKE '%player%'
ORDER BY ORDINAL_POSITION;

