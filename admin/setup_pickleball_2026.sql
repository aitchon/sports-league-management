-- Open the 2026 Pickleball season and keep 2025 Fall as archive data.
-- Safe to re-run: existing 2026 season, divisions, and court slots are left in place.

UPDATE `pickleball_seasons`
SET `is_active` = 0
WHERE `name` = '2025 Fall Pickleball Ladder';

INSERT INTO `pickleball_seasons`
    (`name`, `start_date`, `end_date`, `is_active`, `ladder_type`, `max_players`, `challenge_window_days`, `min_games_per_week`)
SELECT '2026 Pickleball', '2026-11-20', '2027-01-08', 1, 'doubles', 40, 7, 1
WHERE NOT EXISTS (
    SELECT 1 FROM `pickleball_seasons` WHERE `name` = '2026 Pickleball'
);

UPDATE `pickleball_seasons` SET `is_active` = 0;
UPDATE `pickleball_seasons`
SET `is_active` = 1,
    `start_date` = '2026-11-20',
    `end_date` = '2027-01-08',
    `ladder_type` = 'doubles',
    `max_players` = 40,
    `challenge_window_days` = 7,
    `min_games_per_week` = 1
WHERE `name` = '2026 Pickleball';

INSERT INTO `pickleball_divisions`
    (`name`, `skill_level`, `min_rating`, `max_rating`, `season_id`, `is_active`)
SELECT d.`name`, d.`skill_level`, d.`min_rating`, d.`max_rating`, s2026.`id`, 1
FROM `pickleball_divisions` d
JOIN `pickleball_seasons` s2025 ON s2025.`id` = d.`season_id` AND s2025.`name` = '2025 Fall Pickleball Ladder'
JOIN `pickleball_seasons` s2026 ON s2026.`name` = '2026 Pickleball'
WHERE d.`name` IN ('Beginner', 'Intermediate')
  AND NOT EXISTS (
      SELECT 1 FROM `pickleball_divisions` existing
      WHERE existing.`season_id` = s2026.`id` AND existing.`name` = d.`name`
  );

-- Fridays from November 20 through January 8.
-- Christmas (Friday 2026-12-25) and New Year's Day (Friday 2027-01-01) are skipped.
-- Drop placeholder slots that were dated on those Saturdays and have no players yet.
DELETE g FROM `pickleball_games` g
JOIN `pickleball_seasons` s ON s.`id` = g.`season_id`
WHERE s.`name` = '2026 Pickleball'
  AND g.`player1_id` IS NULL
  AND g.`location` IN ('Court 1', 'Court 2', 'Court 3', 'Court 4', 'Court 5')
  AND DATE(g.`date`) IN ('2026-11-21', '2026-11-28');

-- January 15 is no longer a play date. Remove only the empty placeholder slots.
DELETE g FROM `pickleball_games` g
JOIN `pickleball_seasons` s ON s.`id` = g.`season_id`
WHERE s.`name` = '2026 Pickleball'
  AND g.`player1_id` IS NULL
  AND DATE(g.`date`) = '2027-01-15';

-- Six Fridays, 7:00 PM, five courts.
INSERT INTO `pickleball_games`
    (`date`, `location`, `status`, `game_type`, `is_ladder_match`, `is_challenge`, `season_id`, `notes`)
SELECT CONCAT(nights.`play_date`, ' 19:00:00'),
       courts.`court`,
       'scheduled',
       'doubles',
       1,
       0,
       s.`id`,
       'Bash, 7:00 PM – 9:00 PM'
FROM `pickleball_seasons` s
JOIN (
    SELECT '2026-11-20' AS `play_date` UNION ALL
    SELECT '2026-11-27' UNION ALL
    SELECT '2026-12-04' UNION ALL
    SELECT '2026-12-11' UNION ALL
    SELECT '2026-12-18' UNION ALL
    SELECT '2027-01-08'
) nights
JOIN (
    SELECT 'Court 1' AS `court` UNION ALL
    SELECT 'Court 2' UNION ALL
    SELECT 'Court 3' UNION ALL
    SELECT 'Court 4' UNION ALL
    SELECT 'Court 5'
) courts
WHERE s.`name` = '2026 Pickleball'
  AND NOT EXISTS (
      SELECT 1 FROM `pickleball_games` g
      WHERE g.`season_id` = s.`id`
        AND g.`location` = courts.`court`
        AND DATE(g.`date`) = nights.`play_date`
  );

-- The first run stored the old venue in the court notes. Later runs correct it.
UPDATE `pickleball_games` g
JOIN `pickleball_seasons` s ON s.`id` = g.`season_id`
SET g.`notes` = 'Bash, 7:00 PM – 9:00 PM'
WHERE s.`name` = '2026 Pickleball'
  AND g.`notes` = 'UWM, 7:00 PM – 9:00 PM';
