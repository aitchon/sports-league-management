-- Reset Pickleball Stats and Ladder Positions
-- This script will clear all game scores and ladder positions
-- WARNING: This will permanently delete all game scores and ladder data!

-- 1. Reset all game scores to NULL
UPDATE pickleball_games 
SET 
    match1_team1_score = NULL,
    match1_team2_score = NULL,
    match2_team1_score = NULL,
    match2_team2_score = NULL,
    match3_team1_score = NULL,
    match3_team2_score = NULL,
    status = 'scheduled',
    updated_at = NOW()
WHERE status = 'completed';

-- 2. Clear all ladder positions
DELETE FROM pickleball_ladder_positions;

-- 3. Reset game statuses to scheduled (optional - uncomment if needed)
-- UPDATE pickleball_games SET status = 'scheduled' WHERE status = 'completed';

-- 4. Show summary of what was reset
SELECT 
    'Games with scores reset' as action,
    COUNT(*) as count
FROM pickleball_games 
WHERE match1_team1_score IS NULL 
AND match1_team2_score IS NULL 
AND match2_team1_score IS NULL 
AND match2_team2_score IS NULL 
AND match3_team1_score IS NULL 
AND match3_team2_score IS NULL;

SELECT 
    'Ladder positions cleared' as action,
    COUNT(*) as count
FROM pickleball_ladder_positions;

