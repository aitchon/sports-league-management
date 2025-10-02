<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = '';

// Simple SMS function for now
function sendSimpleSMS($phone, $message) {
    // This is a placeholder - you'll need to implement actual SMS sending
    // For now, just log the message
    error_log("SMS to {$phone}: {$message}");
    return ['success' => true, 'message' => 'SMS logged (not actually sent)'];
}

// SMS settings functionality removed for now - requires database setup

// Handle sending SMS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_sms'])) {
    $player_ids = $_POST['player_ids'] ?? [];
    $custom_message = $_POST['custom_message'] ?? '';
    
    if (empty($player_ids)) {
        $message = '<div class="alert alert-danger">Please select at least one player.</div>';
    } elseif (empty($custom_message)) {
        $message = '<div class="alert alert-danger">Please enter a message.</div>';
    } else {
        $success_count = 0;
        $failure_count = 0;
        
        foreach ($player_ids as $player_id) {
            try {
                $stmt = $conn->prepare("SELECT name, phone FROM pickleball_players WHERE id = ?");
                $stmt->bind_param("i", $player_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $player = $result->fetch_assoc();
                $stmt->close();
                
                if ($player && $player['phone']) {
                    // Replace {player_name} with actual name
                    $personalized_message = str_replace('{player_name}', $player['name'], $custom_message);
                    
                    $result = sendSimpleSMS($player['phone'], $personalized_message);
                    if ($result['success']) {
                        $success_count++;
                    } else {
                        $failure_count++;
                    }
                } else {
                    $failure_count++;
                }
            } catch (Exception $e) {
                $failure_count++;
            }
        }
        
        $message = '<div class="alert alert-success">SMS sent successfully! ' . $success_count . ' sent, ' . $failure_count . ' failed.</div>';
    }
}

// Get current season
$current_season = null;
try {
    $result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $current_season = $result->fetch_assoc();
    }
} catch (Exception $e) {
    $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '. Please run the SQL setup first.</div>';
}

// Get players for current season
$players = [];
if ($current_season) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, d.name as division_name
            FROM pickleball_players p
            LEFT JOIN pickleball_divisions d ON p.division_id = d.id
            WHERE p.season_id = ? AND p.is_active = 1 AND p.phone IS NOT NULL AND p.phone != ''
            ORDER BY p.name
        ");
        $stmt->bind_param("i", $current_season['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $players[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error loading players: ' . $e->getMessage() . '</div>';
    }
}

// Simplified settings for demo
$settings = [
    'provider' => 'twilio',
    'api_key' => '',
    'api_secret' => '',
    'from_number' => '',
    'is_active' => 0
];

// No templates or history for now
$templates = [];
$notification_history = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball SMS Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .sms-card {
            transition: transform 0.2s;
        }
        .sms-card:hover {
            transform: translateY(-2px);
        }
        .status-sent {
            color: #28a745;
        }
        .status-failed {
            color: #dc3545;
        }
        .status-pending {
            color: #ffc107;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12 sport-header py-4 mb-4">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">📱 Pickleball SMS Manager</h1>
                            <p class="mb-0">Send text messages to players about schedules and matches</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light me-2">← Back to Dashboard</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php echo $message; ?>

            <!-- SMS Info -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">ℹ️ SMS Service Status</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>Demo Mode</h6>
                        <p>This is a simplified version of the SMS manager. Messages will be logged to the server error log instead of being sent via SMS.</p>
                        <p><strong>To enable real SMS sending:</strong></p>
                        <ol>
                            <li>Run the SQL setup to create the required database tables</li>
                            <li>Configure your SMS provider settings</li>
                            <li>Update the code to use a real SMS service</li>
                        </ol>
                    </div>
                </div>
            </div>

            <?php if ($current_season): ?>
                <!-- Send SMS -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">📤 Send SMS Messages</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="smsForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="player_selection" class="form-label">Select Players</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select_all" onchange="toggleAllPlayers()">
                                        <label class="form-check-label" for="select_all">
                                            Select All Players
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Players with Phone Numbers</label>
                                    <p class="text-muted"><?php echo count($players); ?> players have phone numbers</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Players (<?php echo count($players); ?> with phone numbers):</label>
                                <div class="row" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($players as $player): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input player-checkbox" type="checkbox" name="player_ids[]" value="<?php echo $player['id']; ?>" id="player_<?php echo $player['id']; ?>">
                                                <label class="form-check-label" for="player_<?php echo $player['id']; ?>">
                                                    <?php echo htmlspecialchars($player['name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($player['phone']); ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="custom_message" class="form-label">Message</label>
                                <textarea class="form-control" id="custom_message" name="custom_message" rows="4" placeholder="Enter your message here..." required></textarea>
                                <div class="form-text">Use {player_name} to personalize messages. Messages will be logged to server error log in demo mode.</div>
                            </div>
                            
                            <button type="submit" name="send_sms" class="btn btn-success">Send SMS Messages</button>
                        </form>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">⚡ Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-primary w-100" onclick="selectAllPlayers()">Select All Players</button>
                            </div>
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-warning w-100" onclick="clearSelection()">Clear Selection</button>
                            </div>
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-secondary w-100" onclick="testSMS()">Test SMS</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h4>SMS Service Not Configured</h4>
                    <p>Please configure your SMS settings above to start sending messages to players.</p>
                </div>
            <?php endif; ?>

            <!-- Notification History -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">📋 Recent SMS History</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($notification_history)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Player</th>
                                        <th>Template</th>
                                        <th>Status</th>
                                        <th>Message Preview</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notification_history as $notification): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($notification['player_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($notification['template_name'] ?? 'Custom'); ?></td>
                                            <td>
                                                <span class="status-<?php echo $notification['status']; ?>">
                                                    <?php echo ucfirst($notification['status']); ?>
                                                </span>
                                                <?php if ($notification['error_message']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($notification['error_message']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars(substr($notification['message'], 0, 50)); ?>...</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No SMS notifications sent yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAllPlayers() {
            const selectAll = document.getElementById('select_all');
            const checkboxes = document.querySelectorAll('.player-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
        
        function selectAllPlayers() {
            document.querySelectorAll('.player-checkbox').forEach(cb => cb.checked = true);
            document.getElementById('select_all').checked = true;
        }
        
        function clearSelection() {
            document.querySelectorAll('.player-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('select_all').checked = false;
        }
        
        function testSMS() {
            if (confirm('This will send a test SMS to the first player. Continue?')) {
                const firstPlayer = document.querySelector('.player-checkbox');
                if (firstPlayer) {
                    firstPlayer.checked = true;
                    document.getElementById('custom_message').value = 'Test message from Pickleball League Admin - {player_name}';
                }
            }
        }
    </script>
</body>
</html>
