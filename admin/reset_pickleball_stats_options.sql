-- Pickleball Stats Reset Options
-- Choose which reset option you need:

-- OPTION 1: Reset only game scores (keep ladder positions but recalculate them)
-- This resets all game scores to NULL and changes status back to 'scheduled'
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

-- OPTION 2: Clear all ladder positions (run this after Option 1)
-- This removes all ladder position data
DELETE FROM pickleball_ladder_positions;

-- OPTION 3: Reset specific season only (replace 1 with your season ID)
-- UPDATE pickleball_games 
-- SET 
--     match1_team1_score = NULL,
--     match1_team2_score = NULL,
--     match2_team1_score = NULL,
--     match2_team2_score = NULL,
--     match3_team1_score = NULL,
--     match3_team2_score = NULL,
--     status = 'scheduled',
--     updated_at = NOW()
-- WHERE season_id = 1 AND status = 'completed';

-- DELETE FROM pickleball_ladder_positions WHERE season_id = 1;

-- OPTION 4: Reset specific division only (replace 1 with your division ID)
-- UPDATE pickleball_games 
-- SET 
--     match1_team1_score = NULL,
--     match1_team2_score = NULL,
--     match2_team1_score = NULL,
--     match2_team2_score = NULL,
--     match3_team1_score = NULL,
--     match3_team2_score = NULL,
--     status = 'scheduled',
--     updated_at = NOW()
-- WHERE division_id = 1 AND status = 'completed';

-- OPTION 5: Complete reset (WARNING: This deletes ALL game data)
-- DELETE FROM pickleball_games;
-- DELETE FROM pickleball_ladder_positions;

-- OPTION 6: Reset availability data only
-- DELETE FROM pickleball_availability;

-- Verification queries (run these to see what was reset):
SELECT 
    'Games with scores' as type,
    COUNT(*) as count
FROM pickleball_games 
WHERE match1_team1_score IS NOT NULL 
OR match1_team2_score IS NOT NULL 
OR match2_team1_score IS NOT NULL 
OR match2_team2_score IS NOT NULL 
OR match3_team1_score IS NOT NULL 
OR match3_team2_score IS NOT NULL;

SELECT 
    'Games without scores' as type,
    COUNT(*) as count
FROM pickleball_games 
WHERE match1_team1_score IS NULL 
AND match1_team2_score IS NULL 
AND match2_team1_score IS NULL 
AND match2_team2_score IS NULL 
AND match3_team1_score IS NULL 
AND match3_team2_score IS NULL;

SELECT 
    'Ladder positions' as type,
    COUNT(*) as count
FROM pickleball_ladder_positions;

SELECT 
    'Availability records' as type,
    COUNT(*) as count
FROM pickleball_availability;

