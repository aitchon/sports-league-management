-- Pickleball Availability Table
CREATE TABLE IF NOT EXISTS `pickleball_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `match_date` date NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_player_date` (`player_id`, `match_date`),
  KEY `player_id` (`player_id`),
  KEY `match_date` (`match_date`),
  CONSTRAINT `fk_pickleball_availability_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

