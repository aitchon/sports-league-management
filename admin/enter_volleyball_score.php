<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : null;
$message = "";

// Fetch game details
$game = null;
if ($game_id) {
    $stmt = $conn->prepare("
        SELECT vg.id, vg.date, vg.location, vg.status,
               ht.name as home_team, at.name as away_team
        FROM volleyball_games vg
        JOIN volleyball_teams ht ON vg.home_team_id = ht.id
        JOIN volleyball_teams at ON vg.away_team_id = at.id
        WHERE vg.id = ?
    ");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
    $stmt->close();
}

// Handle score submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_score'])) {
    $set_number = intval($_POST['set_number']);
    $home_score = intval($_POST['home_score']);
    $away_score = intval($_POST['away_score']);
    
    if ($game_id && $set_number && $home_score >= 0 && $away_score >= 0) {
        // Check if score already exists for this set
        $check_stmt = $conn->prepare("SELECT id FROM volleyball_scores WHERE game_id = ? AND set_number = ?");
        $check_stmt->bind_param("ii", $game_id, $set_number);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            // Update existing score
            $stmt = $conn->prepare("UPDATE volleyball_scores SET home_team_score = ?, away_team_score = ? WHERE game_id = ? AND set_number = ?");
            $stmt->bind_param("iiii", $home_score, $away_score, $game_id, $set_number);
        } else {
            // Insert new score
            $stmt = $conn->prepare("INSERT INTO volleyball_scores (game_id, set_number, home_team_score, away_team_score) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiii", $game_id, $set_number, $home_score, $away_score);
        }
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Score saved successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="alert alert-danger">Please fill in all fields with valid values.</div>';
    }
}

// Fetch existing scores
$scores = [];
if ($game_id) {
    $scores_stmt = $conn->prepare("SELECT set_number, home_team_score, away_team_score FROM volleyball_scores WHERE game_id = ? ORDER BY set_number");
    $scores_stmt->bind_param("i", $game_id);
    $scores_stmt->execute();
    $scores_result = $scores_stmt->get_result();
    while ($row = $scores_result->fetch_assoc()) {
        $scores[$row['set_number']] = $row;
    }
    $scores_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Volleyball Score</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="mb-3">
        <a href="manage_volleyball_games.php" class="btn btn-secondary">← Back to Manage Games</a>
    </div>
    <div class="container py-5">
        <div class="sport-header p-4 rounded mb-4">
            <h1 class="display-4 text-center mb-0">🏐 Enter Volleyball Score</h1>
        </div>

        <?php echo $message; ?>

        <?php if ($game): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Game Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><?php echo htmlspecialchars($game['home_team']); ?> vs <?php echo htmlspecialchars($game['away_team']); ?></h4>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="mb-1"><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($game['date'])); ?></p>
                            <p class="mb-0"><strong>Location:</strong> <?php echo htmlspecialchars($game['location']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Scores -->
            <?php if (!empty($scores)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Current Scores</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Set</th>
                                        <th><?php echo htmlspecialchars($game['home_team']); ?></th>
                                        <th><?php echo htmlspecialchars($game['away_team']); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <tr>
                                            <td><strong>Set <?php echo $i; ?></strong></td>
                                            <td>
                                                <?php if (isset($scores[$i])): ?>
                                                    <span class="badge bg-primary"><?php echo $scores[$i]['home_team_score']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($scores[$i])): ?>
                                                    <span class="badge bg-primary"><?php echo $scores[$i]['away_team_score']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Score Entry Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Enter/Update Score</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="set_number" class="form-label">Set Number *</label>
                                <select class="form-select" id="set_number" name="set_number" required>
                                    <option value="">-- Select Set --</option>
                                    <option value="1">Set 1</option>
                                    <option value="2">Set 2</option>
                                    <option value="3">Set 3</option>
                                    <option value="4">Set 4</option>
                                    <option value="5">Set 5</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="home_score" class="form-label"><?php echo htmlspecialchars($game['home_team']); ?> Score *</label>
                                <input type="number" class="form-control" id="home_score" name="home_score" min="0" max="50" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="away_score" class="form-label"><?php echo htmlspecialchars($game['away_team']); ?> Score *</label>
                                <input type="number" class="form-control" id="away_score" name="away_score" min="0" max="50" required>
                            </div>
                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <button type="submit" name="submit_score" class="btn btn-primary w-100">Save Score</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h4>Game Not Found</h4>
                <p>The requested game could not be found.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
