<?php
include 'admin/db.php';
require_once 'admin/pickleball_sms_lib.php';

$settings = pickleballSmsSettings($conn);
$token = $_POST['t'] ?? ($_GET['t'] ?? '');
$parsed = ($settings && !empty($settings['cron_key'])) ? pickleballParseConfirmToken($token, $settings['cron_key']) : null;
$player = null;
$playDate = '';
$notice = '';
$savedStatus = '';

if ($parsed) {
    $playDate = $parsed['play_date'];
    $stmt = $conn->prepare("SELECT id, name, season_id, is_active FROM pickleball_players WHERE id = ?");
    $playerId = (int)$parsed['player_id'];
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $player = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$player || !(int)$player['is_active']) {
        $player = null;
    } else {
        $seasonStmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE id = ?");
        $seasonId = (int)$player['season_id'];
        $seasonStmt->bind_param('i', $seasonId);
        $seasonStmt->execute();
        $season = $seasonStmt->get_result()->fetch_assoc();
        $seasonStmt->close();
        if (!$season || !in_array($playDate, pickleballSeasonFridays($season), true)) {
            $player = null;
        }
    }
}

if ($player && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $choice = $_POST['status'] ?? '';
    if ($choice !== 'confirmed' && $choice !== 'not playing') {
        $notice = '<div class="alert alert-danger">Choose whether you can play.</div>';
    } elseif (pickleballSaveWeekStatuses($conn, (int)$player['season_id'], $playDate, [(int)$player['id']], [(int)$player['id'] => $choice])) {
        $savedStatus = $choice;
    } else {
        $notice = '<div class="alert alert-danger">That choice could not be saved. Please try again.</div>';
    }
} elseif ($player) {
    $statuses = pickleballLoadWeekStatuses($conn, (int)$player['season_id']);
    $savedStatus = pickleballNormalizeWeekStatus($statuses[$playDate][(int)$player['id']] ?? '');
    if ($savedStatus === 'not confirmed') {
        $savedStatus = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm pickleball</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header { background: linear-gradient(135deg, #4CAF50, #66BB6A); color: white; }
    </style>
</head>
<body class="bg-light">
    <div class="sport-header py-4 mb-4">
        <div class="container">
            <h1 class="h2 mb-0">Friday pickleball</h1>
        </div>
    </div>
    <div class="container pb-5" style="max-width: 36rem;">
        <?php echo $notice; ?>
        <?php if (!$player): ?>
            <div class="alert alert-warning">This confirmation link is not valid.</div>
        <?php else: ?>
            <p class="lead">Hi <?php echo htmlspecialchars(pickleballFirstName($player['name'])); ?>. Can you play <?php echo date('l, F j', strtotime($playDate)); ?>, 7:00–9:00 PM?</p>
            <?php if ($savedStatus === 'confirmed'): ?>
                <div class="alert alert-success">You're confirmed for this Friday.</div>
            <?php elseif ($savedStatus === 'not playing'): ?>
                <div class="alert alert-secondary">You're marked not playing this Friday.</div>
            <?php endif; ?>
            <form method="post" class="d-grid gap-2">
                <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
                <button type="submit" name="status" value="confirmed" class="btn btn-success btn-lg">Yes, I can play</button>
                <button type="submit" name="status" value="not playing" class="btn btn-outline-secondary btn-lg">No, I can't play</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
