-- Weekly RSVP for each player. Missing rows mean not confirmed.
CREATE TABLE IF NOT EXISTS `pickleball_week_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `season_id` int NOT NULL,
  `play_date` date NOT NULL,
  `player_id` int NOT NULL,
  `status` enum('not confirmed','confirmed','not playing') NOT NULL DEFAULT 'not confirmed',
  PRIMARY KEY (`id`),
  UNIQUE KEY `season_date_player` (`season_id`, `play_date`, `player_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `fk_pickleball_week_status_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
  CONSTRAINT `fk_pickleball_week_status_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Who is playing a Friday. The court page uses this when the count changes or someone does not show.
CREATE TABLE IF NOT EXISTS `pickleball_session_players` (
  `id` int NOT NULL AUTO_INCREMENT,
  `season_id` int NOT NULL,
  `play_date` date NOT NULL,
  `player_id` int NOT NULL,
  `is_present` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `season_date_player` (`season_id`, `play_date`, `player_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `fk_pickleball_session_players_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
  CONSTRAINT `fk_pickleball_session_players_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Which court each regular belongs to after a Friday is assigned. Someone who misses still keeps the row.
CREATE TABLE IF NOT EXISTS `pickleball_court_rungs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `season_id` int NOT NULL,
  `play_date` date NOT NULL,
  `player_id` int NOT NULL,
  `court_number` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `season_date_player` (`season_id`, `play_date`, `player_id`),
  KEY `player_id` (`player_id`),
  CONSTRAINT `fk_pickleball_court_rungs_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
  CONSTRAINT `fk_pickleball_court_rungs_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Players who can fill in. They are not part of the season roster until checked in for a Friday.
-- Safe to re-run: skip this statement if the column is already there.
ALTER TABLE `pickleball_players`
  ADD COLUMN `is_substitute` tinyint NOT NULL DEFAULT 0 AFTER `is_active`;

-- November 20 uses courts 3-7. Only empty placeholder slots are renamed.
-- Safe to re-run: slots already named Court 3-7 are left alone.
UPDATE `pickleball_games` g
JOIN `pickleball_seasons` s ON s.`id` = g.`season_id`
SET g.`location` = CASE g.`location`
    WHEN 'Court 1' THEN 'Court 3'
    WHEN 'Court 2' THEN 'Court 4'
    WHEN 'Court 3' THEN 'Court 5'
    WHEN 'Court 4' THEN 'Court 6'
    WHEN 'Court 5' THEN 'Court 7'
    ELSE g.`location`
END
WHERE s.`name` = '2026 Pickleball'
  AND DATE(g.`date`) = '2026-11-20'
  AND g.`player1_id` IS NULL
  AND g.`status` = 'scheduled'
  AND g.`location` IN ('Court 1', 'Court 2', 'Court 3', 'Court 4', 'Court 5');
