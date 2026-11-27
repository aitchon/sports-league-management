<?php
include 'db.php';
require_once 'pickleball_sms_service.php';

header('Content-Type: text/xml; charset=UTF-8');

function pickleballInboundReply($text) {
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response><Message>' . htmlspecialchars($text, ENT_XML1) . '</Message></Response>';
    exit();
}

$sms = new PickleballSMSService($conn);
$settings = $sms->isConfigured() ? pickleballSmsSettings($conn) : null;
$authToken = '';
if ($sms->usesEnvironmentCredentials()) {
    $authToken = trim((string)getenv('TWILIO_AUTH_TOKEN'));
} elseif ($settings) {
    $authToken = (string)($settings['api_secret'] ?? '');
}
$signature = (string)($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');
$webhookUrl = pickleballPublicBaseUrl() . '/admin/pickleball_sms_inbound.php';
if (!$sms->isConfigured() || !pickleballTwilioSignatureValid($authToken, $webhookUrl, $_POST, $signature)) {
    http_response_code(403);
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit();
}

$status = pickleballParseAvailabilityReply($_POST['Body'] ?? '');
if ($status === '') {
    pickleballInboundReply('Reply YES if you can play or NO if you can\'t.');
}

$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
$season = $result ? $result->fetch_assoc() : null;
if (!$season) {
    pickleballInboundReply('Pickleball is between seasons, so this reply was not saved.');
}

$player = pickleballPlayerByPhone($conn, (int)$season['id'], $_POST['From'] ?? '');
if (!$player) {
    pickleballInboundReply('This number is not on the pickleball list.');
}

$playDate = pickleballReplyPlayDate($conn, $player['id'], pickleballCurrentPlayDate($season));
if ($playDate === '' || !pickleballSaveWeekStatuses($conn, (int)$season['id'], $playDate, [$player['id']], [$player['id'] => $status])) {
    pickleballInboundReply('Sorry, that reply could not be saved. Please try the link in the text.');
}

$when = date('l, F j', strtotime($playDate));
if ($status === 'confirmed') {
    pickleballInboundReply('Thanks ' . pickleballFirstName($player['name']) . '. You\'re confirmed for ' . $when . '.');
}
pickleballInboundReply('Thanks ' . pickleballFirstName($player['name']) . '. You\'re marked not playing ' . $when . '.');
