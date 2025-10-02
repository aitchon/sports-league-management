-- Pickleball Player Availability Tables
-- Add these tables to support player availability for match scheduling

-- Player Availability
CREATE TABLE `pickleball_player_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_available` tinyint(4) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `season_id` (`season_id`),
  KEY `date` (`date`),
  CONSTRAINT `fk_pickleball_availability_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_availability_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Availability Templates (for recurring availability patterns)
CREATE TABLE `pickleball_availability_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL, -- 0=Sunday, 1=Monday, etc.
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_available` tinyint(4) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `season_id` (`season_id`),
  KEY `day_of_week` (`day_of_week`),
  CONSTRAINT `fk_pickleball_availability_templates_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_availability_templates_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Match Scheduling Preferences
CREATE TABLE `pickleball_scheduling_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `preferred_days` varchar(50) DEFAULT NULL, -- JSON array of preferred days
  `preferred_times` varchar(100) DEFAULT NULL, -- JSON array of preferred time ranges
  `max_matches_per_week` int(11) DEFAULT 3,
  `min_advance_notice_hours` int(11) DEFAULT 24,
  `auto_accept_challenges` tinyint(4) DEFAULT 0,
  `notify_email` tinyint(4) DEFAULT 1,
  `notify_sms` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `season_id` (`season_id`),
  CONSTRAINT `fk_pickleball_scheduling_preferences_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_scheduling_preferences_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

