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

// Get selected game
$selected_game_id = isset($_GET['game']) ? (int)$_GET['game'] : null;
$selected_game = null;

if ($selected_game_id && $current_season) {
    try {
        $stmt = $conn->prepare("
            SELECT pg.*, p1.name as player1_name, p2.name as player2_name,
                   p3.name as player3_name, p4.name as player4_name,
                   d.name as division_name
            FROM pickleball_games pg
            JOIN pickleball_players p1 ON pg.player1_id = p1.id
            JOIN pickleball_players p2 ON pg.player2_id = p2.id
            LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
            LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
            LEFT JOIN pickleball_divisions d ON p1.division_id = d.id
            WHERE pg.id = ? AND pg.season_id = ?
        ");
        $stmt->bind_param("ii", $selected_game_id, $current_season['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_game = $result->fetch_assoc();
        $stmt->close();
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error loading selected game: ' . $e->getMessage() . '</div>';
    }
}

// Get upcoming games
$upcoming_games = [];
if ($current_season) {
    try {
        $stmt = $conn->prepare("
            SELECT pg.id, pg.date, pg.location, pg.game_type, pg.is_challenge,
                   p1.name as player1_name, p2.name as player2_name,
                   p3.name as player3_name, p4.name as player4_name,
                   d.name as division_name
            FROM pickleball_games pg
            JOIN pickleball_players p1 ON pg.player1_id = p1.id
            JOIN pickleball_players p2 ON pg.player2_id = p2.id
            LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
            LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
            LEFT JOIN pickleball_divisions d ON p1.division_id = d.id
            WHERE pg.season_id = ? AND pg.status = 'scheduled' AND pg.date >= NOW()
            ORDER BY pg.date
            LIMIT 20
        ");
        $stmt->bind_param("i", $current_season['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $upcoming_games[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error loading games: ' . $e->getMessage() . '</div>';
    }
}

// Handle sending match SMS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_match_sms'])) {
    $game_id = (int)$_POST['game_id'];
    $sms_type = $_POST['sms_type'];
    $custom_message = $_POST['custom_message'] ?? '';
    
    // Get game details
    try {
        $stmt = $conn->prepare("
            SELECT pg.*, p1.name as player1_name, p1.phone as player1_phone,
                   p2.name as player2_name, p2.phone as player2_phone,
                   p3.name as player3_name, p3.phone as player3_phone,
                   p4.name as player4_name, p4.phone as player4_phone,
                   d.name as division_name
            FROM pickleball_games pg
            JOIN pickleball_players p1 ON pg.player1_id = p1.id
            JOIN pickleball_players p2 ON pg.player2_id = p2.id
            LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
            LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
            LEFT JOIN pickleball_divisions d ON p1.division_id = d.id
            WHERE pg.id = ?
        ");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $game = $result->fetch_assoc();
        $stmt->close();
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error loading game details: ' . $e->getMessage() . '</div>';
        $game = null;
    }
    
    if (!$game) {
        $message = '<div class="alert alert-danger">Game not found.</div>';
    } else {
        $players = [
            ['id' => $game['player1_id'], 'name' => $game['player1_name'], 'phone' => $game['player1_phone']],
            ['id' => $game['player2_id'], 'name' => $game['player2_name'], 'phone' => $game['player2_phone']]
        ];
        
        if ($game['player3_id']) {
            $players[] = ['id' => $game['player3_id'], 'name' => $game['player3_name'], 'phone' => $game['player3_phone']];
        }
        if ($game['player4_id']) {
            $players[] = ['id' => $game['player4_id'], 'name' => $game['player4_name'], 'phone' => $game['player4_phone']];
        }
        
        $success_count = 0;
        $failure_count = 0;
        
        foreach ($players as $player) {
            if (!$player['phone']) {
                $failure_count++;
                continue;
            }
            
            $message_text = '';
            
            switch ($sms_type) {
                case 'reminder':
                    $message_text = "Hi {$player['name']}! Your pickleball match is scheduled for " . 
                                  date('l, F j', strtotime($game['date'])) . " at " . 
                                  date('g:i A', strtotime($game['date'])) . " at {$game['location']}. Good luck! - Pickleball League";
                    break;
                case 'schedule_change':
                    $message_text = "Hi {$player['name']}! There has been a schedule change for your match. New details: " . 
                                  date('l, F j', strtotime($game['date'])) . " at " . 
                                  date('g:i A', strtotime($game['date'])) . " at {$game['location']}. - Pickleball League";
                    break;
                case 'cancellation':
                    $message_text = "Hi {$player['name']}! Your match scheduled for " . 
                                  date('l, F j', strtotime($game['date'])) . " at " . 
                                  date('g:i A', strtotime($game['date'])) . " has been cancelled. - Pickleball League";
                    break;
                case 'custom':
                    $message_text = str_replace('{player_name}', $player['name'], $custom_message);
                    break;
            }
            
            $result = sendSimpleSMS($player['phone'], $message_text);
            
            if ($result['success']) {
                $success_count++;
            } else {
                $failure_count++;
            }
        }
        
        $message = '<div class="alert alert-success">SMS sent! ' . $success_count . ' sent, ' . $failure_count . ' failed.</div>';
    }
}

// SMS templates not needed for this simplified version
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Match SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .game-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .game-card:hover {
            transform: translateY(-2px);
        }
        .game-card.selected {
            border-color: #007bff;
            background-color: #f8f9fa;
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
                            <h1 class="display-4 mb-0">📱 Match SMS Notifications</h1>
                            <p class="mb-0">Send text messages about specific matches</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light me-2">← Back to Dashboard</a>
                            <a href="pickleball_sms_manager.php" class="btn btn-light me-2">SMS Manager</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php echo $message; ?>

            <?php if ($current_season): ?>
                <!-- Game Selection -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🎾 Select Match</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($upcoming_games)): ?>
                            <div class="row">
                                <?php foreach ($upcoming_games as $game): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card game-card <?php echo $selected_game_id == $game['id'] ? 'selected' : ''; ?>" 
                                             onclick="selectGame(<?php echo $game['id']; ?>)">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <?php 
                                                    $players = [];
                                                    if ($game['player1_name']) $players[] = htmlspecialchars($game['player1_name']);
                                                    if ($game['player2_name']) $players[] = htmlspecialchars($game['player2_name']);
                                                    if ($game['player3_name']) $players[] = htmlspecialchars($game['player3_name']);
                                                    if ($game['player4_name']) $players[] = htmlspecialchars($game['player4_name']);
                                                    if ($game['player5_name']) $players[] = htmlspecialchars($game['player5_name']);
                                                    echo implode(', ', $players);
                                                    if (empty($players)) echo 'TBD';
                                                    ?>
                                                </h6>
                                                <p class="card-text">
                                                    <strong>Date:</strong> <?php echo date('M j, Y', strtotime($game['date'])); ?><br>
                                                    <strong>Time:</strong> <?php echo date('g:i A', strtotime($game['date'])); ?><br>
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($game['location']); ?><br>
                                                    <strong>Division:</strong> <?php echo htmlspecialchars($game['division_name'] ?? 'Unassigned'); ?><br>
                                                    <?php if ($game['is_challenge']): ?>
                                                        <span class="badge bg-danger">Challenge</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Ladder</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No upcoming matches found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selected_game): ?>
                    <!-- SMS Form -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">📤 Send SMS for Selected Match</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6>Match Details:</h6>
                                    <p>
                                        <strong>Players:</strong> 
                                        <?php if ($selected_game['game_type'] == 'singles'): ?>
                                            <?php 
                                            $players = [];
                                            if ($selected_game['player1_name']) $players[] = htmlspecialchars($selected_game['player1_name']);
                                            if ($selected_game['player2_name']) $players[] = htmlspecialchars($selected_game['player2_name']);
                                            if ($selected_game['player3_name']) $players[] = htmlspecialchars($selected_game['player3_name']);
                                            if ($selected_game['player4_name']) $players[] = htmlspecialchars($selected_game['player4_name']);
                                            if ($selected_game['player5_name']) $players[] = htmlspecialchars($selected_game['player5_name']);
                                            echo implode(', ', $players);
                                            if (empty($players)) echo 'TBD';
                                            ?>
                                        <?php else: ?>
                                            <?php 
                                            $players = [];
                                            if ($selected_game['player1_name']) $players[] = htmlspecialchars($selected_game['player1_name']);
                                            if ($selected_game['player2_name']) $players[] = htmlspecialchars($selected_game['player2_name']);
                                            if ($selected_game['player3_name']) $players[] = htmlspecialchars($selected_game['player3_name']);
                                            if ($selected_game['player4_name']) $players[] = htmlspecialchars($selected_game['player4_name']);
                                            if ($selected_game['player5_name']) $players[] = htmlspecialchars($selected_game['player5_name']);
                                            echo implode(', ', $players);
                                            if (empty($players)) echo 'TBD';
                                            ?>
                                        <?php endif; ?><br>
                                        <strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($selected_game['date'])); ?><br>
                                        <strong>Time:</strong> <?php echo date('g:i A', strtotime($selected_game['date'])); ?><br>
                                        <strong>Location:</strong> <?php echo htmlspecialchars($selected_game['location']); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Players to Notify:</h6>
                                    <ul class="list-unstyled">
                                        <li>✅ <?php echo htmlspecialchars($selected_game['player1_name']); ?> 
                                            <?php if ($selected_game['player1_phone']): ?>
                                                (<?php echo htmlspecialchars($selected_game['player1_phone']); ?>)
                                            <?php else: ?>
                                                <span class="text-danger">(No phone)</span>
                                            <?php endif; ?>
                                        </li>
                                        <li>✅ <?php echo htmlspecialchars($selected_game['player2_name']); ?> 
                                            <?php if ($selected_game['player2_phone']): ?>
                                                (<?php echo htmlspecialchars($selected_game['player2_phone']); ?>)
                                            <?php else: ?>
                                                <span class="text-danger">(No phone)</span>
                                            <?php endif; ?>
                                        </li>
                                        <?php if ($selected_game['player3_name']): ?>
                                            <li>✅ <?php echo htmlspecialchars($selected_game['player3_name']); ?> 
                                                <?php if ($selected_game['player3_phone']): ?>
                                                    (<?php echo htmlspecialchars($selected_game['player3_phone']); ?>)
                                                <?php else: ?>
                                                    <span class="text-danger">(No phone)</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ($selected_game['player4_name']): ?>
                                            <li>✅ <?php echo htmlspecialchars($selected_game['player4_name']); ?> 
                                                <?php if ($selected_game['player4_phone']): ?>
                                                    (<?php echo htmlspecialchars($selected_game['player4_phone']); ?>)
                                                <?php else: ?>
                                                    <span class="text-danger">(No phone)</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="game_id" value="<?php echo $selected_game['id']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sms_type" class="form-label">Message Type</label>
                                        <select class="form-select" id="sms_type" name="sms_type" onchange="toggleCustomMessage()">
                                            <option value="reminder">Match Reminder</option>
                                            <option value="schedule_change">Schedule Change</option>
                                            <option value="cancellation">Match Cancellation</option>
                                            <option value="custom">Custom Message</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Preview</label>
                                        <div id="message_preview" class="form-control" style="height: 38px; background-color: #f8f9fa;">
                                            Select a message type to see preview...
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3" id="custom_message_div" style="display: none;">
                                    <label for="custom_message" class="form-label">Custom Message</label>
                                    <textarea class="form-control" id="custom_message" name="custom_message" rows="3" 
                                              placeholder="Enter your custom message. Use {player_name} to personalize."></textarea>
                                </div>
                                
                                <button type="submit" name="send_match_sms" class="btn btn-success">Send SMS to All Players</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h4>Select a Match</h4>
                        <p>Please select a match from the list above to send SMS notifications.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h4>No Active Pickleball Season</h4>
                    <p>There is currently no active pickleball season.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectGame(gameId) {
            window.location.href = '?game=' + gameId;
        }
        
        function toggleCustomMessage() {
            const smsType = document.getElementById('sms_type').value;
            const customDiv = document.getElementById('custom_message_div');
            const preview = document.getElementById('message_preview');
            
            if (smsType === 'custom') {
                customDiv.style.display = 'block';
                preview.textContent = 'Enter custom message below...';
            } else {
                customDiv.style.display = 'none';
                updatePreview();
            }
        }
        
        function updatePreview() {
            const smsType = document.getElementById('sms_type').value;
            const preview = document.getElementById('message_preview');
            
            const messages = {
                'reminder': 'Hi {player_name}! Your pickleball match is scheduled for [date] at [time] at [location]. Good luck! - Pickleball League',
                'schedule_change': 'Hi {player_name}! There has been a schedule change for your match. New details: [date] at [time] at [location]. - Pickleball League',
                'cancellation': 'Hi {player_name}! Your match scheduled for [date] at [time] has been cancelled. - Pickleball League'
            };
            
            preview.textContent = messages[smsType] || 'Select a message type...';
        }
        
        // Initialize
        document.getElementById('sms_type').addEventListener('change', updatePreview);
        updatePreview();
    </script>
</body>
</html>
