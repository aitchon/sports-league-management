<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include '../db.php';

// Handle form submission to schedule a new game
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'];
    $location = $_POST['location'];
    $home_team_id = $_POST['home_team_id'];
    $away_team_id = $_POST['away_team_id'];

    $stmt = $conn->prepare("INSERT INTO games (date, location, home_team_id, away_team_id, status) VALUES (?, ?, ?, ?, 'scheduled')");
    $stmt->bind_param("ssii", $date, $location, $home_team_id, $away_team_id);

    if ($stmt->execute()) {
        echo "<div class='alert alert-success text-center'>Game scheduled successfully!</div>";
    } else {
        echo "<div class='alert alert-danger text-center'>Error: " . $stmt->error . "</div>";
    }

    $stmt->close();
}

// Fetch all scheduled games
$scheduled_games = $conn->query("
    SELECT g.id, g.date, g.location, t1.name AS home_team, t2.name AS away_team
    FROM games g
    JOIN teams t1 ON g.home_team_id = t1.id
    JOIN teams t2 ON g.away_team_id = t2.id
    WHERE g.status = 'scheduled'
    ORDER BY g.date
");

// Fetch divisions for the form
$divisions = $conn->query("SELECT id, name FROM divisions");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Game</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container py-5">
        <div class="mb-3">
            <a href="dashboard.php" class="btn btn-secondary">⬅ Back to Dashboard</a>
        </div>

        <div class="card shadow-lg p-4">
            <h2 class="mb-4 text-center">Schedule a New Game</h2>
            <form method="post">
                <div class="mb-3">
                    <label for="date" class="form-label">Date:</label>
                    <input type="date" id="date" name="date" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="location" class="form-label">Location:</label>
                    <input type="text" id="location" name="location" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="division_id" class="form-label">Division:</label>
                    <select id="division_id" name="division_id" class="form-select" required onchange="loadTeams(this.value)">
                        <option value="">Select Division</option>
                        <?php while ($division = $divisions->fetch_assoc()): ?>
                            <option value="<?php echo $division['id']; ?>"><?php echo $division['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="home_team_id" class="form-label">Home Team:</label>
                    <select id="home_team_id" name="home_team_id" class="form-select" required>
                        <option value="">Select Home Team</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="away_team_id" class="form-label">Away Team:</label>
                    <select id="away_team_id" name="away_team_id" class="form-select" required>
                        <option value="">Select Away Team</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">📅 Schedule Game</button>
            </form>
        </div>

        <div class="mt-5">
            <h2 class="mb-3">Scheduled Games</h2>
            <?php if ($scheduled_games->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Location</th>
                                <th>Home Team</th>
                                <th>Away Team</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($game = $scheduled_games->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $game['date']; ?></td>
                                    <td><?php echo $game['location']; ?></td>
                                    <td><?php echo $game['home_team']; ?></td>
                                    <td><?php echo $game['away_team']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="alert alert-info text-center">No scheduled games found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    function loadTeams(divisionId) {
        if (divisionId) {
            fetch(`get_teams.php?division_id=${divisionId}`)
                .then(response => response.json())
                .then(data => {
                    const homeTeamSelect = document.getElementById('home_team_id');
                    const awayTeamSelect = document.getElementById('away_team_id');
                    homeTeamSelect.innerHTML = '<option value="">Select Home Team</option>';
                    awayTeamSelect.innerHTML = '<option value="">Select Away Team</option>';
                    data.forEach(team => {
                        const option = document.createElement('option');
                        option.value = team.id;
                        option.textContent = team.name;
                        homeTeamSelect.appendChild(option.cloneNode(true));
                        awayTeamSelect.appendChild(option);
                    });
                });
        }
    }
    </script>
</body>
</html>
