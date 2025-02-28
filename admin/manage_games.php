<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Handle new game addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_game'])) {
    $datetime = $_POST['datetime'];
    $location = $_POST['location'];
    $home_team_id = $_POST['home_team_id'];
    $away_team_id = $_POST['away_team_id'];

    // You can convert the datetime to the proper format for MySQL if needed
    // MySQL expects the datetime format as 'YYYY-MM-DD HH:MM:SS', so we will use `strtotime` for conversion.
    $formatted_datetime = date('Y-m-d H:i:s', strtotime($datetime));

    $stmt = $conn->prepare("INSERT INTO games (date, location, home_team_id, away_team_id, status) VALUES (?, ?, ?, ?, 'scheduled')");
    $stmt->bind_param("ssii", $formatted_datetime, $location, $home_team_id, $away_team_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch games
$games = $conn->query("
    SELECT g.id, g.date as datetime, g.location, g.home_team_id, g.away_team_id, 
           t1.name AS home_team, t2.name AS away_team, d.id AS division_id
    FROM games g
    JOIN teams t1 ON g.home_team_id = t1.id
    JOIN teams t2 ON g.away_team_id = t2.id
    JOIN divisions d ON t1.division_id = d.id
    ORDER BY g.date
");

if (!$games) {
    die("Error fetching games: " . $conn->error); // Error handling
}

// Fetch divisions
$divisions = $conn->query("SELECT id, name FROM divisions");

if (!$divisions) {
    die("Error fetching divisions: " . $conn->error); // Error handling
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Games</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <a href="dashboard.php" class="btn btn-secondary mb-3">⬅ Back to Dashboard</a>

    <div class="card p-4 shadow-lg">
        <h2 class="mb-4 text-center">Manage Games</h2>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addGameModal">➕ Add New Game</button>

        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Date/Time</th>
                        <th>Location</th>
                        <th>Home Team</th>
                        <th>Away Team</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($game = $games->fetch_assoc()): ?>
<tr>
    <td><?php echo $game['datetime']; ?></td>
    <td><?php echo $game['location']; ?></td>
    <td><?php echo $game['home_team']; ?></td>
    <td><?php echo $game['away_team']; ?></td>
    <td>
        <button class="btn btn-warning btn-sm edit-btn" 
            data-bs-toggle="modal" data-bs-target="#editGameModal"
            data-id="<?php echo $game['id']; ?>"
            data-datetime="<?php echo $game['datetime']; ?>"
            data-location="<?php echo $game['location']; ?>"
            data-division-id="<?php echo $game['division_id']; ?>"
            data-home-id="<?php echo $game['home_team_id']; ?>"
            data-away-id="<?php echo $game['away_team_id']; ?>">
            ✏ Edit
        </button>
        <a href="delete_game.php?id=<?php echo $game['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this game?');">🗑 Delete</a>
        <a href="enter_final_score.php?game_id=<?php echo $game['id']; ?>" class="btn btn-info btn-sm">📊 Enter Final Score</a>
    </td>
</tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Game Modal -->
<div class="modal fade" id="addGameModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule a New Game</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="add_game" value="1">
                    
                    <div class="mb-3">
                        <label for="datetime" class="form-label">Date/Time:</label>
                        <input type="datetime-local" name="datetime" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label">Location:</label>
                        <input type="text" name="location" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="division_id" class="form-label">Division:</label>
                        <select id="division_id" name="division_id" class="form-control" required onchange="loadTeams(this.value)">
                            <option value="">Select Division</option>
                            <?php while ($division = $divisions->fetch_assoc()): ?>
                                <option value="<?php echo $division['id']; ?>"><?php echo $division['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="home_team_id" class="form-label">Home Team:</label>
                        <select id="home_team_id" name="home_team_id" class="form-control" required>
                            <option value="">Select Home Team</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="away_team_id" class="form-label">Away Team:</label>
                        <select id="away_team_id" name="away_team_id" class="form-control" required>
                            <option value="">Select Away Team</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">📅 Schedule Game</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadTeams(divisionId) {
    if (divisionId) {
        fetch(`get_teams.php?division_id=${divisionId}`)
            .then(response => response.json())
            .then(data => {
                let homeSelect = document.getElementById('home_team_id');
                let awaySelect = document.getElementById('away_team_id');
                homeSelect.innerHTML = '<option value="">Select Home Team</option>';
                awaySelect.innerHTML = '<option value="">Select Away Team</option>';
                
                data.forEach(team => {
                    let option = document.createElement('option');
                    option.value = team.id;
                    option.textContent = team.name;
                    homeSelect.appendChild(option.cloneNode(true));
                    awaySelect.appendChild(option);
                });
            });
    }
}

function deleteGame(gameId) {
    if (confirm("Are you sure you want to delete this game?")) {
        fetch('delete_game.php?id=' + gameId, { method: 'GET' })
            .then(response => response.text())
            .then(() => location.reload());
    }
}
</script>

<!-- Edit Game Modal -->
<div class="modal fade" id="editGameModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Game</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editGameForm" method="post" action="edit_game.php">
                    <input type="hidden" name="game_id" id="edit_game_id">

                    <div class="mb-3">
                        <label for="edit_datetime" class="form-label">Date/Time:</label>
                        <input type="datetime-local" name="datetime" id="edit_datetime" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_location" class="form-label">Location:</label>
                        <input type="text" name="location" id="edit_location" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_division_id" class="form-label">Division:</label>
                        <select name="division_id" id="edit_division_id" class="form-control" required onchange="loadTeamsForEdit(this.value)">
                            <option value="">Select Division</option>
                            <?php
                            $divisions = $conn->query("SELECT id, name FROM divisions");
                            while ($division = $divisions->fetch_assoc()):
                            ?>
                                <option value="<?php echo $division['id']; ?>"><?php echo $division['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_home_team" class="form-label">Home Team:</label>
                        <select name="home_team_id" id="edit_home_team" class="form-control" required>
                            <option value="">Select Home Team</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_away_team" class="form-label">Away Team:</label>
                        <select name="away_team_id" id="edit_away_team" class="form-control" required>
                            <option value="">Select Away Team</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">💾 Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".edit-btn").forEach(button => {
        button.addEventListener("click", function () {
            const gameId = this.getAttribute("data-id");
            const datetime = this.getAttribute("data-datetime");
            const location = this.getAttribute("data-location");
            const divisionId = this.getAttribute("data-division-id");
            const homeTeam = this.getAttribute("data-home-id");
            const awayTeam = this.getAttribute("data-away-id");

            document.getElementById("edit_game_id").value = gameId;
            document.getElementById("edit_datetime").value = datetime;
            document.getElementById("edit_location").value = location;
            document.getElementById("edit_division_id").value = divisionId;

            // Load teams for the selected division
            loadTeamsForEdit(divisionId, homeTeam, awayTeam);
        });
    });
});

function loadTeamsForEdit(divisionId, homeId = null, awayId = null) {
    if (!divisionId) return;

    fetch(`get_teams.php?division_id=${divisionId}`)
        .then(response => response.json())
        .then(data => {
            const homeSelect = document.getElementById("edit_home_team");
            const awaySelect = document.getElementById("edit_away_team");

            homeSelect.innerHTML = '<option value="">Select Home Team</option>';
            awaySelect.innerHTML = '<option value="">Select Away Team</option>';

            data.forEach(team => {
                let option = document.createElement("option");
                option.value = team.id;
                option.textContent = team.name;

                if (team.id == homeId) option.selected = true;
                homeSelect.appendChild(option);

                option = document.createElement("option");
                option.value = team.id;
                option.textContent = team.name;

                if (team.id == awayId) option.selected = true;
                awaySelect.appendChild(option);
            });
        });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
