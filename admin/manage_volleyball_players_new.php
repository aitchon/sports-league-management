<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get selected volleyball season from session
$selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : null;

// Fetch volleyball divisions (filtered by selected season)
if ($selected_season_id) {
    $divisions_stmt = $conn->prepare("SELECT id, name FROM volleyball_divisions WHERE season_id = ? ORDER BY name");
    $divisions_stmt->bind_param("i", $selected_season_id);
    $divisions_stmt->execute();
    $divisions = $divisions_stmt->get_result();
    $divisions_stmt->close();
} else {
    $divisions = $conn->query("SELECT id, name FROM volleyball_divisions ORDER BY name");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Volleyball Players</title>
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
        <a href="volleyball_dashboard.php" class="btn btn-secondary">← Back to Volleyball Dashboard</a>
    </div>
    <div class="container py-5">
        <div class="sport-header p-4 rounded mb-4">
            <h1 class="display-4 text-center mb-0">🏐 Manage Volleyball Players</h1>
        </div>

        <!-- Season Selector -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏐 Volleyball Season Selection</h5>
            </div>
            <div class="card-body">
                <form method="post" class="d-flex align-items-center">
                    <div class="me-3">
                        <label for="season_id" class="form-label mb-0">Current Season:</label>
                    </div>
                    <div class="me-3">
                        <select name="season_id" id="season_id" class="form-select" onchange="this.form.submit()">
                            <?php
                            $seasons = $conn->query("SELECT * FROM volleyball_seasons ORDER BY start_date DESC");
                            while ($season = $seasons->fetch_assoc()): ?>
                                <option value="<?php echo $season['id']; ?>" 
                                        <?php echo ($selected_season_id == $season['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($season['name']); ?>
                                    <?php if ($season['is_active']): ?>
                                        (Active)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <input type="hidden" name="select_volleyball_season" value="1">
                    <button type="submit" class="btn btn-primary btn-sm">Select Season</button>
                </form>
            </div>
        </div>

        <?php if (!$selected_season_id): ?>
            <div class="alert alert-warning">
                <h4>No Season Selected</h4>
                <p>Please select a volleyball season from the dropdown above to manage players.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p><strong>Selected Season:</strong> 
                <?php 
                $stmt = $conn->prepare("SELECT name FROM volleyball_seasons WHERE id = ?");
                $stmt->bind_param("i", $selected_season_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $season_name = $result->fetch_assoc()['name'];
                echo htmlspecialchars($season_name);
                $stmt->close();
                ?> (ID: <?php echo $selected_season_id; ?>)</p>
            </div>

        <!-- Select Division -->
        <div class="mb-3">
            <label for="division_id" class="form-label">Select Division:</label>
            <select id="division_id" class="form-select" required onchange="loadVolleyballTeams(this.value)">
                <option value="">-- Select Division --</option>
                <?php while ($division = $divisions->fetch_assoc()): ?>
                    <option value="<?php echo $division['id']; ?>"><?php echo htmlspecialchars($division['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Select Team -->
        <div class="mb-3">
            <label for="team_id" class="form-label">Select Team:</label>
            <select id="team_id" class="form-select" required onchange="loadVolleyballPlayers(this.value)">
                <option value="">-- Select Team --</option>
            </select>
        </div>

        <!-- Add Player Form -->
        <form id="addVolleyballPlayerForm" class="mb-4" onsubmit="addVolleyballPlayer(event)">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="name" class="form-label">Player Name:</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="position" class="form-label">Position:</label>
                    <select id="position" name="position" class="form-select">
                        <option value="">-- Select Position --</option>
                        <option value="Setter">Setter</option>
                        <option value="Outside Hitter">Outside Hitter</option>
                        <option value="Middle Blocker">Middle Blocker</option>
                        <option value="Opposite Hitter">Opposite Hitter</option>
                        <option value="Libero">Libero</option>
                        <option value="Defensive Specialist">Defensive Specialist</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="jersey_number" class="form-label">Jersey Number:</label>
                    <input type="number" id="jersey_number" name="jersey_number" class="form-control" min="1" max="99">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="addVolleyballPlayerBtn" disabled>Add Player</button>
        </form>

        <!-- Player List -->
        <div id="volleyball-players-list" class="mt-4">
            <h3>Players</h3>
            <ul id="volleyball-players" class="list-group"></ul>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh divisions when season changes
        document.addEventListener('DOMContentLoaded', function() {
            const seasonSelect = document.getElementById('season_id');
            if (seasonSelect) {
                seasonSelect.addEventListener('change', function() {
                    // Clear division and team selections
                    document.getElementById('division_id').innerHTML = '<option value="">-- Select Division --</option>';
                    document.getElementById('team_id').innerHTML = '<option value="">-- Select Team --</option>';
                    document.getElementById('volleyball-players').innerHTML = '';
                    
                    // Reload divisions for the selected season
                    if (this.value) {
                        loadVolleyballDivisions(this.value);
                    }
                });
            }
        });

        function loadVolleyballDivisions(seasonId) {
            fetch(`get_volleyball_divisions.php?season_id=${seasonId}`)
                .then(response => response.json())
                .then(data => {
                    const divisionSelect = document.getElementById('division_id');
                    divisionSelect.innerHTML = '<option value="">-- Select Division --</option>';
                    data.forEach(division => {
                        const option = document.createElement('option');
                        option.value = division.id;
                        option.textContent = division.name;
                        divisionSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching divisions:', error));
        }

        function loadVolleyballTeams(divisionId) {
            if (divisionId) {
                fetch(`get_volleyball_teams.php?division_id=${divisionId}`)
                    .then(response => response.json())
                    .then(data => {
                        const teamSelect = document.getElementById('team_id');
                        teamSelect.innerHTML = '<option value="">-- Select Team --</option>';
                        data.forEach(team => {
                            const option = document.createElement('option');
                            option.value = team.id;
                            option.textContent = team.name;
                            teamSelect.appendChild(option);
                        });
                        // Disable add player button until team is selected
                        document.getElementById('addVolleyballPlayerBtn').disabled = true;
                    })
                    .catch(error => console.error('Error fetching teams:', error));
            } else {
                document.getElementById('team_id').innerHTML = '<option value="">-- Select Team --</option>';
                document.getElementById('addVolleyballPlayerBtn').disabled = true;
            }
        }

        function loadVolleyballPlayers(teamId) {
            if (teamId) {
                // Enable add player button when team is selected
                document.getElementById('addVolleyballPlayerBtn').disabled = false;
                
                fetch(`get_volleyball_players_new.php?team_id=${teamId}`)
                    .then(response => response.json())
                    .then(data => {
                        const playersList = document.getElementById('volleyball-players');
                        playersList.innerHTML = '';

                        if (data.error) {
                            playersList.innerHTML = '<li class="list-group-item text-danger">Error: ' + data.error + '</li>';
                        } else if (data.length > 0) {
                            data.forEach(player => {
                                const listItem = document.createElement('li');
                                listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                                listItem.innerHTML = `
                                    <div class="d-flex align-items-center">
                                        <input type="text" value="${player.name}" class="form-control form-control-sm me-2" style="width: 150px;" 
                                            onchange="editVolleyballPlayer(${player.id}, 'name', this.value)">
                                        <input type="text" value="${player.position || ''}" class="form-control form-control-sm me-2" style="width: 120px;" 
                                            onchange="editVolleyballPlayer(${player.id}, 'position', this.value)" placeholder="Position">
                                        <input type="number" value="${player.jersey_number || ''}" class="form-control form-control-sm me-2" style="width: 80px;" 
                                            onchange="editVolleyballPlayer(${player.id}, 'jersey_number', this.value)" placeholder="#" min="1" max="99">
                                    </div>
                                    <button class="btn btn-danger btn-sm" onclick="deleteVolleyballPlayer(${player.id})">Delete</button>
                                `;
                                playersList.appendChild(listItem);
                            });
                        } else {
                            playersList.innerHTML = '<li class="list-group-item text-muted">No players found.</li>';
                        }
                    })
                    .catch(error => console.error('Error fetching players:', error));
            } else {
                document.getElementById('volleyball-players').innerHTML = '';
                document.getElementById('addVolleyballPlayerBtn').disabled = true;
            }
        }

        function addVolleyballPlayer(event) {
            event.preventDefault();
            const name = document.getElementById('name').value;
            const teamId = document.getElementById('team_id').value;
            const position = document.getElementById('position').value;
            const jerseyNumber = document.getElementById('jersey_number').value;

            if (teamId && name) {
                fetch('add_volleyball_player_new.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `name=${name}&team_id=${teamId}&position=${position}&jersey_number=${jerseyNumber}`
                })
                .then(response => response.text())
                .then(() => {
                    document.getElementById('name').value = '';
                    document.getElementById('position').value = '';
                    document.getElementById('jersey_number').value = '';
                    loadVolleyballPlayers(teamId);
                })
                .catch(error => console.error('Error adding player:', error));
            }
        }

        function editVolleyballPlayer(playerId, field, newValue) {
            fetch('edit_volleyball_player_new.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${playerId}&field=${field}&value=${newValue}`
            }).catch(error => console.error('Error updating player:', error));
        }

        function deleteVolleyballPlayer(playerId) {
            if (confirm('Are you sure you want to delete this player?')) {
                fetch('delete_volleyball_player_new.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${playerId}`
                })
                .then(() => loadVolleyballPlayers(document.getElementById('team_id').value))
                .catch(error => console.error('Error deleting player:', error));
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Handle season selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_volleyball_season'])) {
    $_SESSION['selected_volleyball_season_id'] = $_POST['season_id'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>
