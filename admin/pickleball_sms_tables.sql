-- Pickleball SMS Notification Tables
-- Add these tables to support SMS notifications for the pickleball league

-- SMS Templates
CREATE TABLE `pickleball_sms_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `template_type` enum('match_reminder','schedule_update','challenge_notification','general_announcement') NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message_template` text NOT NULL,
  `variables` text DEFAULT NULL, -- JSON array of available variables
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMS Notifications Log
CREATE TABLE `pickleball_sms_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed','delivered') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `season_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `player_id` (`player_id`),
  KEY `season_id` (`season_id`),
  KEY `game_id` (`game_id`),
  CONSTRAINT `fk_pickleball_sms_notifications_template` FOREIGN KEY (`template_id`) REFERENCES `pickleball_sms_templates` (`id`),
  CONSTRAINT `fk_pickleball_sms_notifications_player` FOREIGN KEY (`player_id`) REFERENCES `pickleball_players` (`id`),
  CONSTRAINT `fk_pickleball_sms_notifications_season` FOREIGN KEY (`season_id`) REFERENCES `pickleball_seasons` (`id`),
  CONSTRAINT `fk_pickleball_sms_notifications_game` FOREIGN KEY (`game_id`) REFERENCES `pickleball_games` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMS Settings
CREATE TABLE `pickleball_sms_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` enum('twilio','nexmo','textbelt','custom') NOT NULL DEFAULT 'twilio',
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `from_number` varchar(20) DEFAULT NULL,
  `webhook_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default SMS templates
INSERT INTO `pickleball_sms_templates` (`name`, `template_type`, `subject`, `message_template`, `variables`) VALUES
('Match Reminder', 'match_reminder', 'Pickleball Match Reminder', 'Hi {player_name}! Your pickleball match is scheduled for {match_date} at {match_time} at {location}. Good luck! - {league_name}', '["player_name", "match_date", "match_time", "location", "league_name"]'),
('Schedule Update', 'schedule_update', 'Schedule Update', 'Hi {player_name}! There has been a schedule update for your upcoming match. New details: {match_date} at {match_time} at {location}. - {league_name}', '["player_name", "match_date", "match_time", "location", "league_name"]'),
('Challenge Notification', 'challenge_notification', 'Pickleball Challenge', 'Hi {player_name}! {challenger_name} has challenged you to a match on {match_date} at {match_time}. Please respond to accept or decline. - {league_name}', '["player_name", "challenger_name", "match_date", "match_time", "league_name"]'),
('General Announcement', 'general_announcement', 'League Announcement', 'Hi {player_name}! {announcement_message} - {league_name}', '["player_name", "announcement_message", "league_name"]');

-- Insert default SMS settings (inactive by default)
INSERT INTO `pickleball_sms_settings` (`provider`, `is_active`) VALUES ('twilio', 0);

