<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = intval($_POST['game_id']);
    $home_team_score = intval($_POST['home_team_score']);
    $away_team_score = intval($_POST['away_team_score']);

    // Check if score already exists
    $score_check_stmt = $conn->prepare("SELECT id FROM scores WHERE game_id = ?");
    $score_check_stmt->bind_param("i", $game_id);
    $score_check_stmt->execute();
    $score_check_result = $score_check_stmt->get_result();

    if ($score_check_result->num_rows > 0) {
        // If score exists, update it
        $stmt = $conn->prepare("UPDATE scores SET home_team_score = ?, away_team_score = ? WHERE game_id = ?");
        $stmt->bind_param("iii", $home_team_score, $away_team_score, $game_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // If score doesn't exist, insert it
        $stmt = $conn->prepare("INSERT INTO scores (game_id, home_team_score, away_team_score) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $game_id, $home_team_score, $away_team_score);
        $stmt->execute();
        $stmt->close();
    }

    // Handle player statistics
    if (isset($_POST['player_stats'])) {
        foreach ($_POST['player_stats'] as $player_id => $stats) {
            $points = !empty($stats['points']) ? intval($stats['points']) : 0;
            $three_pointers_made = !empty($stats['three_pointers_made']) ? intval($stats['three_pointers_made']) : 0;
            $fouls = !empty($stats['fouls']) ? intval($stats['fouls']) : 0;

            // Check if player stats already exist
            $stats_check_stmt = $conn->prepare("SELECT id FROM player_stats WHERE player_id = ? AND game_id = ?");
            $stats_check_stmt->bind_param("ii", $player_id, $game_id);
            $stats_check_stmt->execute();
            $stats_check_result = $stats_check_stmt->get_result();

            if ($stats_check_result->num_rows > 0) {
                // If stats exist, update them
                $stmt = $conn->prepare("UPDATE player_stats SET points = ?, three_pointers_made = ?, fouls = ? WHERE player_id = ? AND game_id = ?");
                $stmt->bind_param("iiiii", $points, $three_pointers_made, $fouls, $player_id, $game_id);
                $stmt->execute();
                $stmt->close();
            } else {
                // If stats don't exist, insert them
                $stmt = $conn->prepare("INSERT INTO player_stats (player_id, game_id, points, three_pointers_made, fouls) 
                                        VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiii", $player_id, $game_id, $points, $three_pointers_made, $fouls);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Update game status
    $stmt = $conn->prepare("UPDATE games SET status = 'completed' WHERE id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $stmt->close();

    // Redirect after submission
    header("Location: manage_games.php"); // Return to dashboard
}

// Fetch games
$games = $conn->query("SELECT g.id, g.date, t1.name AS home_team, t2.name AS away_team 
                       FROM games g 
                       JOIN teams t1 ON g.home_team_id = t1.id 
                       JOIN teams t2 ON g.away_team_id = t2.id 
                       WHERE g.status = 'scheduled' OR g.status = 'completed' 
                       ORDER BY g.date");

// Fetch specific game if selected
$game = null;
$home_team_players = null;
$away_team_players = null;
$score = null;
$home_team_stats = [];
$away_team_stats = [];

if (isset($_GET['game_id'])) {
    $game_id = intval($_GET['game_id']);
    $sql = "SELECT g.id, g.date, t1.name AS home_team, t2.name AS away_team 
            FROM games g 
            JOIN teams t1 ON g.home_team_id = t1.id 
            JOIN teams t2 ON g.away_team_id = t2.id 
            WHERE g.id = $game_id";
    $game_query = $conn->query($sql);

    if ($game_query && $game_query->num_rows > 0) {
        $game = $game_query->fetch_assoc();

        // Fetch players
        $home_team_players = $conn->query("SELECT id, name FROM players WHERE team_id = (SELECT home_team_id FROM games WHERE id = $game_id)");
        $away_team_players = $conn->query("SELECT id, name FROM players WHERE team_id = (SELECT away_team_id FROM games WHERE id = $game_id)");

        // Fetch existing scores
        $score = $conn->query("SELECT home_team_score, away_team_score FROM scores WHERE game_id = $game_id")->fetch_assoc();

        // Fetch existing player stats
        $home_team_stats_query = $conn->query("SELECT player_id, points, three_pointers_made, fouls FROM player_stats WHERE game_id = $game_id AND player_id IN (SELECT id FROM players WHERE team_id = (SELECT home_team_id FROM games WHERE id = $game_id))");
        while ($row = $home_team_stats_query->fetch_assoc()) {
            $home_team_stats[$row['player_id']] = $row;
        }

        $away_team_stats_query = $conn->query("SELECT player_id, points, three_pointers_made, fouls FROM player_stats WHERE game_id = $game_id AND player_id IN (SELECT id FROM players WHERE team_id = (SELECT away_team_id FROM games WHERE id = $game_id))");
        while ($row = $away_team_stats_query->fetch_assoc()) {
            $away_team_stats[$row['player_id']] = $row;
        }
    } else {
        echo "<div class='alert alert-danger'>Game not found.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Final Score and Player Stats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">🏀 Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-3">Enter Final Score and Player Stats</h2>

        <!-- Game Selection Form -->
        <form method="get" action="enter_final_score.php" class="mb-4">
            <div class="mb-3">
                <label for="game_id" class="form-label">Select Game:</label>
                <select id="game_id" name="game_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">-- Select Game --</option>
                    <?php while ($game_row = $games->fetch_assoc()): ?>
                        <option value="<?php echo $game_row['id']; ?>" 
                            <?php echo (isset($_GET['game_id']) && $_GET['game_id'] == $game_row['id']) ? 'selected' : ''; ?>>
                            <?php echo $game_row['date']; ?>: <?php echo $game_row['home_team']; ?> vs <?php echo $game_row['away_team']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </form>

        <?php if ($game): ?>
            <!-- Final Score Form -->
            <div class="card p-4">
                <h3 class="text-center mb-3"><?php echo $game['home_team']; ?> vs <?php echo $game['away_team']; ?></h3>
                <form method="post">
                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <label for="home_team_score" class="form-label"><?php echo $game['home_team']; ?> Score:</label>
                            <input type="number" id="home_team_score" name="home_team_score" 
                                class="form-control" value="<?php echo $score['home_team_score'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="away_team_score" class="form-label"><?php echo $game['away_team']; ?> Score:</label>
                            <input type="number" id="away_team_score" name="away_team_score" 
                                class="form-control" value="<?php echo $score['away_team_score'] ?? ''; ?>" required>
                        </div>
                    </div>

                    <h4 class="mt-4"><?php echo $game['home_team']; ?> Player Stats</h4>
                    <?php if ($home_team_players): ?>
                        <?php while ($player = $home_team_players->fetch_assoc()): ?>
                            <div class="mb-3">
                                <label class="form-label"><?php echo $player['name']; ?></label>
                                <input type="number" name="player_stats[<?php echo $player['id']; ?>][points]" 
                                    class="form-control" placeholder="Points" value="<?php echo $home_team_stats[$player['id']]['points'] ?? ''; ?>">
                                <input type="number" name="player_stats[<?php echo $player['id']; ?>][three_pointers_made]" 
                                    class="form-control mt-2" placeholder="Three Pointers Made" value="<?php echo $home_team_stats[$player['id']]['three_pointers_made'] ?? ''; ?>">
                                <input type="number" name="player_stats[<?php echo $player['id']; ?>][fouls]" 
                                    class="form-control mt-2" placeholder="Fouls" value="<?php echo $home_team_stats[$player['id']]['fouls'] ?? ''; ?>">
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <h4 class="mt-4"><?php echo $game['away_team']; ?> Player Stats</h4>
                    <?php if ($away_team_players): ?>
                        <?php while ($player = $away_team_players->fetch_assoc()): ?>
                            <div class="mb-3">
                                <label class="form-label"><?php echo $player['name']; ?></label>
                                <input type="number" name="player_stats[<?php echo $player['id']; ?>][points]" 
                                    class="form-control" placeholder="Points" value="<?php echo $away_team_stats[$player['id']]['points'] ?? ''; ?>">
                                <input type="number" name="player_stats[<?php echo $player['id']; ?>][three_pointers_made]" 
                                    class="form-control mt-2" placeholder="Three Pointers Made" value="<?php echo $away_team_stats[$player['id']]['three_pointers_made'] ?? ''; ?>">
                                <input type="number" name="player_stats[<?php echo $player['id']; ?>][fouls]" 
                                    class="form-control mt-2" placeholder="Fouls" value="<?php echo $away_team_stats[$player['id']]['fouls'] ?? ''; ?>">
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <!-- Submit Buttons -->
                    <div class="mt-4 text-center">
                        <button type="submit" class="btn btn-success">Submit Scores</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
