-- Add score columns to pickleball_games table
ALTER TABLE `pickleball_games` 
ADD COLUMN `player1_score` int(11) DEFAULT NULL,
ADD COLUMN `player2_score` int(11) DEFAULT NULL,
ADD COLUMN `player3_score` int(11) DEFAULT NULL,
ADD COLUMN `player4_score` int(11) DEFAULT NULL,
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

