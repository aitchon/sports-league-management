<?php
include 'db.php';
require_once 'pickleball_sms_service.php';

$settings = pickleballSmsSettings($conn);
$provided = (string)($_GET['key'] ?? '');
$expected = (string)($settings['cron_key'] ?? '');
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden\n";
    exit();
}

$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
$season = $result ? $result->fetch_assoc() : null;
header('Content-Type: text/plain; charset=UTF-8');
if (!$season) {
    echo "No active pickleball season.\n";
    exit();
}

$sms = new PickleballSMSService($conn);
$weekly = pickleballSendWeeklyAvailability($conn, $sms, $season, false, false);
echo $weekly['summary'] . "\n";
