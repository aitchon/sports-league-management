<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
require_once 'pickleball_sms_service.php';

$message = '';
$sms = new PickleballSMSService($conn);
$settings = pickleballSmsSettings($conn) ?: [];
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_twilio'])) {
    $accountSid = strtoupper(trim($_POST['account_sid'] ?? ''));
    $authToken = trim($_POST['auth_token'] ?? '');
    $fromNumber = pickleballNormalizePhone($_POST['from_number'] ?? '');
    if ($authToken === '' && $settings) {
        $authToken = (string)($settings['api_secret'] ?? '');
    }
    if (!preg_match('/^AC[0-9a-f]{32}$/i', $accountSid)) {
        $message = '<div class="alert alert-danger">The Twilio account SID should look like AC followed by 32 letters and numbers.</div>';
    } elseif (strlen($authToken) < 16) {
        $message = '<div class="alert alert-danger">Enter the Twilio auth token.</div>';
    } elseif ($fromNumber === '') {
        $message = '<div class="alert alert-danger">Enter the 10-digit Twilio phone number texts should come from.</div>';
    } elseif (!pickleballSaveTwilioSettings($conn, $accountSid, $authToken, $fromNumber)) {
        $message = '<div class="alert alert-danger">Could not save the Twilio account.</div>';
    } else {
        $message = '<div class="alert alert-success">Saved the Twilio account.</div>';
        $sms = new PickleballSMSService($conn);
        $settings = pickleballSmsSettings($conn) ?: [];
    }
}

$playDate = $current_season ? pickleballCurrentPlayDate($current_season) : '';
$players = $current_season ? pickleballSmsPlayers($conn, (int)$current_season['id']) : [];
$weekStatuses = ($current_season && $playDate) ? (pickleballLoadWeekStatuses($conn, (int)$current_season['id'])[$playDate] ?? []) : [];
$textable = [];
$missingPhone = 0;
$confirmedCount = 0;
$regularCount = 0;
foreach ($players as $player) {
    if ($player['sms_phone'] === '') {
        $missingPhone++;
        continue;
    }
    $player['confirmed'] = pickleballNormalizeWeekStatus($weekStatuses[$player['id']] ?? 'not confirmed') === 'confirmed';
    if ($player['confirmed']) {
        $confirmedCount++;
    }
    if (empty($player['is_substitute'])) {
        $regularCount++;
    }
    $textable[] = $player;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_weekly']) && $current_season) {
    $force = !empty($_POST['send_again']);
    $weekly = pickleballSendWeeklyAvailability($conn, $sms, $current_season, $force, true);
    $alert = !empty($weekly['ok']) ? 'success' : 'danger';
    $message = '<div class="alert alert-' . $alert . '">' . htmlspecialchars($weekly['summary']) . '</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms']) && $current_season) {
    $playerIds = array_map('intval', $_POST['player_ids'] ?? []);
    $custom = trim($_POST['custom_message'] ?? '');
    $byId = [];
    foreach ($textable as $player) {
        $byId[$player['id']] = $player;
    }
    if (!$sms->isConfigured()) {
        $message = '<div class="alert alert-danger">Add the Twilio account before sending texts.</div>';
    } elseif (!$playerIds) {
        $message = '<div class="alert alert-danger">Choose at least one player.</div>';
    } elseif ($custom === '') {
        $message = '<div class="alert alert-danger">Enter a message.</div>';
    } else {
        $sent = 0;
        $failed = 0;
        $errors = [];
        foreach ($playerIds as $playerId) {
            if (empty($byId[$playerId])) {
                $failed++;
                continue;
            }
            $player = $byId[$playerId];
            $body = str_replace('{player_name}', $player['name'], $custom);
            $result = $sms->sendSMS($player['sms_phone'], $body, $player['id'], null, (int)$current_season['id'], null, 'custom', $playDate ?: null);
            if (!empty($result['success'])) {
                $sent++;
            } else {
                $failed++;
                if (count($errors) < 3) {
                    $errors[] = $player['name'] . ': ' . ($result['error'] ?? 'could not send');
                }
            }
        }
        $alert = $failed === 0 ? 'success' : ($sent === 0 ? 'danger' : 'warning');
        $text = 'Sent ' . $sent . ' text' . ($sent === 1 ? '' : 's') . '.';
        if ($failed > 0) {
            $text .= ' ' . $failed . ' failed.';
            if ($errors) {
                $text .= ' ' . implode(' ', $errors);
            }
        }
        $message = '<div class="alert alert-' . $alert . '">' . htmlspecialchars($text) . '</div>';
    }
}

$weeklySummary = ($current_season && $playDate) ? pickleballWeeklySentSummary($conn, (int)$current_season['id'], $playDate) : ['sent_at' => null, 'count' => 0];
$history = $sms->getNotificationHistory(40);
$inboundUrl = pickleballPublicBaseUrl() . '/admin/pickleball_sms_inbound.php';
$cronKey = $settings['cron_key'] ?? '';
$cronUrl = pickleballPublicBaseUrl() . '/admin/pickleball_weekly_sms.php?key=' . rawurlencode($cronKey);
$sampleWeekly = $playDate ? pickleballWeeklyMessage('Alex', $playDate, pickleballPublicBaseUrl() . '/pickleball_confirm.php?t=…') : '';
$savedFrom = pickleballNormalizePhone($settings['from_number'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Text players</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header { background: linear-gradient(135deg, #4CAF50, #66BB6A); color: white; }
        .player-list { max-height: 22rem; overflow-y: auto; }
        .status-sent, .status-delivered { color: #198754; }
        .status-failed { color: #dc3545; }
        .status-pending { color: #997404; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 sport-header py-4 mb-4">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">Text players</h1>
                            <p class="mb-0">Send a text now, or send the weekly ask to every regular.</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light me-2">Back to Dashboard</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container pb-5">
            <?php echo $message; ?>

            <div class="card mb-4">
                <div class="card-header bg-success text-white"><strong>Twilio account</strong></div>
                <div class="card-body">
                    <?php if ($sms->usesEnvironmentCredentials()): ?>
                        <p class="mb-3">Texts are using the Twilio account saved on the server. The form below is kept as a backup.</p>
                    <?php elseif ($sms->isConfigured()): ?>
                        <p class="mb-3">Twilio is ready. Texts come from <?php echo htmlspecialchars($savedFrom); ?>.</p>
                    <?php else: ?>
                        <p class="mb-3">Add the Twilio account SID, auth token, and the phone number Twilio gave you. Nothing sends until those are saved.</p>
                    <?php endif; ?>
                    <form method="post" class="row g-3">
                        <div class="col-md-4">
                            <label for="account_sid" class="form-label">Account SID</label>
                            <input class="form-control" id="account_sid" name="account_sid" value="<?php echo htmlspecialchars($settings['api_key'] ?? ''); ?>" autocomplete="off" required>
                        </div>
                        <div class="col-md-4">
                            <label for="auth_token" class="form-label">Auth token</label>
                            <input class="form-control" id="auth_token" name="auth_token" type="password" value="" placeholder="<?php echo !empty($settings['api_secret']) ? 'Saved. Leave blank to keep it.' : ''; ?>" autocomplete="new-password">
                        </div>
                        <div class="col-md-4">
                            <label for="from_number" class="form-label">Twilio phone number</label>
                            <input class="form-control" id="from_number" name="from_number" value="<?php echo htmlspecialchars($savedFrom); ?>" placeholder="5865550100" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="save_twilio" class="btn btn-success">Save Twilio account</button>
                        </div>
                    </form>
                    <hr>
                    <p class="mb-1">In Twilio, set this number's "A message comes in" webhook to this address so a YES or NO reply updates that player's week:</p>
                    <p class="mb-3"><code><?php echo htmlspecialchars($inboundUrl); ?></code></p>
                    <p class="mb-1">A daily cron at 9:00 AM sends the weekly availability text once, Monday through Friday, to every regular who has not already received it:</p>
                    <p class="mb-0"><code><?php echo htmlspecialchars($cronUrl); ?></code></p>
                </div>
            </div>

            <?php if (!$current_season): ?>
                <div class="alert alert-warning">No active pickleball season.</div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white"><strong>Weekly availability text</strong></div>
                    <div class="card-body">
                        <p>This goes to all <?php echo (int)$regularCount; ?> regular<?php echo $regularCount === 1 ? '' : 's'; ?> with a phone number. Substitutes are left off.<?php if ($missingPhone > 0): ?> <?php echo (int)$missingPhone; ?> active <?php echo $missingPhone === 1 ? 'player has' : 'players have'; ?> no usable phone number.<?php endif; ?></p>
                        <?php if ($playDate): ?>
                            <p class="mb-2">Asking about <?php echo date('l, F j, Y', strtotime($playDate)); ?>, 7:00–9:00 PM.</p>
                            <?php if ($weeklySummary['count'] > 0): ?>
                                <p class="mb-2">Already sent to <?php echo (int)$weeklySummary['count']; ?> regular<?php echo $weeklySummary['count'] === 1 ? '' : 's'; ?><?php echo $weeklySummary['sent_at'] ? ' on ' . date('M j, g:i A', strtotime($weeklySummary['sent_at'])) : ''; ?>.</p>
                            <?php else: ?>
                                <p class="mb-2">Not sent yet for this Friday.</p>
                            <?php endif; ?>
                            <p class="text-muted"><?php echo htmlspecialchars($sampleWeekly); ?></p>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm(this.send_again && this.send_again.checked ? 'Send the availability text again to every regular?' : 'Send the availability text to every regular who has not already received it?');">
                            <?php if ($weeklySummary['count'] > 0): ?>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="send_again" id="send_again" value="1">
                                    <label class="form-check-label" for="send_again">Send again, including people who already got this week's text</label>
                                </div>
                            <?php endif; ?>
                            <button type="submit" name="send_weekly" class="btn btn-success" <?php echo $sms->isConfigured() ? '' : 'disabled'; ?>>Send weekly availability text</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-success text-white"><strong>Send a text</strong></div>
                    <div class="card-body">
                        <form method="post" id="sms-form">
                            <fieldset class="mb-3">
                                <legend class="form-label">Who receives it</legend>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="audience" id="audience_all" value="all">
                                    <label class="form-check-label" for="audience_all">All players (<?php echo count($textable); ?> with a phone number)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="audience" id="audience_confirmed" value="confirmed">
                                    <label class="form-check-label" for="audience_confirmed">Players confirmed for <?php echo $playDate ? date('l, F j', strtotime($playDate)) : 'this week'; ?> (<?php echo (int)$confirmedCount; ?>)</label>
                                </div>
                            </fieldset>
                            <div class="player-list border rounded p-3 mb-3">
                                <div class="row">
                                    <?php foreach ($textable as $player): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input player-checkbox" type="checkbox" name="player_ids[]" value="<?php echo (int)$player['id']; ?>" id="player_<?php echo (int)$player['id']; ?>" data-confirmed="<?php echo $player['confirmed'] ? '1' : '0'; ?>">
                                                <label class="form-check-label" for="player_<?php echo (int)$player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                    <?php if (!empty($player['is_substitute'])): ?><span class="badge bg-secondary">Sub</span><?php endif; ?>
                                                    <?php if ($player['confirmed']): ?><span class="badge bg-success">Confirmed</span><?php endif; ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($player['sms_phone']); ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="custom_message" class="form-label">Message</label>
                                <textarea class="form-control" id="custom_message" name="custom_message" rows="4" required><?php echo htmlspecialchars($_POST['custom_message'] ?? ''); ?></textarea>
                                <div class="form-text">Use {player_name} for the player's full name. <span id="recipient-count">No players selected.</span></div>
                            </div>
                            <button type="submit" name="send_sms" class="btn btn-success" <?php echo $sms->isConfigured() ? '' : 'disabled'; ?>>Send text</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-success text-white"><strong>Recent texts</strong></div>
                    <div class="card-body">
                        <?php if ($history): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>When</th>
                                            <th>Player</th>
                                            <th>Kind</th>
                                            <th>Status</th>
                                            <th>Message</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history as $notification): ?>
                                            <tr>
                                                <td><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($notification['player_name'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($notification['kind'] ?? ($notification['template_name'] ?? 'Custom')); ?></td>
                                                <td class="status-<?php echo htmlspecialchars($notification['status']); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($notification['status'])); ?>
                                                    <?php if (!empty($notification['error_message'])): ?>
                                                        <br><small><?php echo htmlspecialchars($notification['error_message']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><small><?php echo htmlspecialchars($notification['message']); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No texts sent yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function selectedCount() {
            return document.querySelectorAll('.player-checkbox:checked').length;
        }
        function refreshCount() {
            const count = selectedCount();
            const label = document.getElementById('recipient-count');
            if (!label) return;
            label.textContent = count === 0 ? 'No players selected.' : (count === 1 ? '1 player selected.' : count + ' players selected.');
        }
        function applyAudience(mode) {
            document.querySelectorAll('.player-checkbox').forEach(function (box) {
                box.checked = mode === 'all' || (mode === 'confirmed' && box.dataset.confirmed === '1');
            });
            refreshCount();
        }
        document.querySelectorAll('input[name="audience"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                applyAudience(radio.value);
            });
        });
        document.querySelectorAll('.player-checkbox').forEach(function (box) {
            box.addEventListener('change', refreshCount);
        });
        const form = document.getElementById('sms-form');
        if (form) {
            form.addEventListener('submit', function (event) {
                const count = selectedCount();
                if (count === 0) {
                    event.preventDefault();
                    window.alert('Choose at least one player.');
                    return;
                }
                const noun = count === 1 ? '1 player' : count + ' players';
                if (!window.confirm('Send this text to ' + noun + '?')) {
                    event.preventDefault();
                }
            });
        }
        refreshCount();
    </script>
</body>
</html>
