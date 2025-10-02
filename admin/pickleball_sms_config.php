<?php
// Pickleball SMS Configuration
// This file contains SMS provider configurations and settings

// SMS Provider Settings
$sms_providers = [
    'twilio' => [
        'name' => 'Twilio',
        'description' => 'Professional SMS service with global reach',
        'api_key_label' => 'Account SID',
        'api_secret_label' => 'Auth Token',
        'from_number_required' => true,
        'webhook_support' => true,
        'pricing' => 'Pay per message',
        'setup_url' => 'https://www.twilio.com/try-twilio'
    ],
    'nexmo' => [
        'name' => 'Nexmo (Vonage)',
        'description' => 'Reliable SMS API with good global coverage',
        'api_key_label' => 'API Key',
        'api_secret_label' => 'API Secret',
        'from_number_required' => true,
        'webhook_support' => true,
        'pricing' => 'Pay per message',
        'setup_url' => 'https://developer.nexmo.com/'
    ],
    'textbelt' => [
        'name' => 'Textbelt',
        'description' => 'Simple SMS service for US numbers',
        'api_key_label' => 'API Key',
        'api_secret_label' => 'API Secret (Optional)',
        'from_number_required' => false,
        'webhook_support' => false,
        'pricing' => 'Free tier available',
        'setup_url' => 'https://textbelt.com/'
    ]
];

// Default SMS templates
$default_templates = [
    'match_reminder' => [
        'name' => 'Match Reminder',
        'message' => 'Hi {player_name}! Your pickleball match is scheduled for {match_date} at {match_time} at {location}. Good luck! - {league_name}',
        'variables' => ['player_name', 'match_date', 'match_time', 'location', 'league_name']
    ],
    'schedule_update' => [
        'name' => 'Schedule Update',
        'message' => 'Hi {player_name}! There has been a schedule update for your upcoming match. New details: {match_date} at {match_time} at {location}. - {league_name}',
        'variables' => ['player_name', 'match_date', 'match_time', 'location', 'league_name']
    ],
    'challenge_notification' => [
        'name' => 'Challenge Notification',
        'message' => 'Hi {player_name}! {challenger_name} has challenged you to a match on {match_date} at {match_time}. Please respond to accept or decline. - {league_name}',
        'variables' => ['player_name', 'challenger_name', 'match_date', 'match_time', 'league_name']
    ],
    'general_announcement' => [
        'name' => 'General Announcement',
        'message' => 'Hi {player_name}! {announcement_message} - {league_name}',
        'variables' => ['player_name', 'announcement_message', 'league_name']
    ]
];

// SMS rate limits (messages per minute)
$sms_rate_limits = [
    'twilio' => 100,
    'nexmo' => 50,
    'textbelt' => 10
];

// Common phone number formats
$phone_formats = [
    'US' => '+1XXXXXXXXXX',
    'UK' => '+44XXXXXXXXX',
    'Canada' => '+1XXXXXXXXXX',
    'Australia' => '+61XXXXXXXXX'
];

// SMS character limits
$sms_limits = [
    'single_sms' => 160,
    'unicode_sms' => 70,
    'concatenated_sms' => 153
];

// Webhook endpoints for different providers
$webhook_endpoints = [
    'twilio' => '/admin/pickleball_sms_webhook.php',
    'nexmo' => '/admin/pickleball_sms_webhook.php',
    'textbelt' => null // Textbelt doesn't support webhooks
];

// Helper function to get provider info
function getSMSProviderInfo($provider) {
    global $sms_providers;
    return $sms_providers[$provider] ?? null;
}

// Helper function to validate phone number
function validatePhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid length (7-15 digits)
    if (strlen($phone) < 7 || strlen($phone) > 15) {
        return false;
    }
    
    return true;
}

// Helper function to format phone number
function formatPhoneNumber($phone, $country_code = 'US') {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) == 10 && $country_code == 'US') {
        return '+1' . $phone;
    }
    
    return '+' . $phone;
}

// Helper function to get message length info
function getMessageLengthInfo($message) {
    $length = strlen($message);
    $unicode_length = mb_strlen($message, 'UTF-8');
    
    $is_unicode = $length !== $unicode_length;
    $limit = $is_unicode ? 70 : 160;
    $sms_count = ceil($unicode_length / $limit);
    
    return [
        'length' => $unicode_length,
        'is_unicode' => $is_unicode,
        'limit' => $limit,
        'sms_count' => $sms_count,
        'remaining' => $limit - ($unicode_length % $limit)
    ];
}
?>

