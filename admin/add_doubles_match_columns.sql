-- Add columns for 3 doubles matches per game
ALTER TABLE `pickleball_games` 
ADD COLUMN `match1_team1_score` int(11) DEFAULT NULL,
ADD COLUMN `match1_team2_score` int(11) DEFAULT NULL,
ADD COLUMN `match2_team1_score` int(11) DEFAULT NULL,
ADD COLUMN `match2_team2_score` int(11) DEFAULT NULL,
ADD COLUMN `match3_team1_score` int(11) DEFAULT NULL,
ADD COLUMN `match3_team2_score` int(11) DEFAULT NULL;



