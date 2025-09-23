<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get selected season from session
$selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : ($current_season ? $current_season['id'] : null);
$selected_season = $selected_season_id ? getSeasonById($conn, $selected_season_id) : null;

// Fetch divisions (filtered by selected season)
if ($selected_season_id) {
    $divisions_stmt = $conn->prepare("SELECT id, name FROM divisions WHERE season_id = ? ORDER BY name");
    $divisions_stmt->bind_param("i", $selected_season_id);
    $divisions_stmt->execute();
    $divisions = $divisions_stmt->get_result();
    $divisions_stmt->close();
} else {
    $divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Players</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="mb-3">
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
    <div class="container py-5">
        <h1 class="display-4 text-center mb-4">Manage Players</h1>

        <!-- Season Selector -->
        <?php include 'season_selector.php'; ?>

        <?php if (!$selected_season_id): ?>
            <div class="alert alert-warning">
                <h4>No Season Selected</h4>
                <p>Please select a season from the dropdown above to manage players.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p><strong>Selected Season:</strong> <?php echo $selected_season['name'] ?? 'Unknown'; ?> (ID: <?php echo $selected_season_id; ?>)</p>
            </div>

        <!-- Select Division -->
        <div class="mb-3">
            <label for="division_id" class="form-label">Select Division:</label>
            <select id="division_id" class="form-select" required onchange="loadTeams(this.value)">
                <option value="">-- Select Division --</option>
                <?php while ($division = $divisions->fetch_assoc()): ?>
                    <option value="<?php echo $division['id']; ?>"><?php echo $division['name']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Select Team -->
        <div class="mb-3">
            <label for="team_id" class="form-label">Select Team:</label>
            <select id="team_id" class="form-select" required onchange="loadPlayers(this.value)">
                <option value="">-- Select Team --</option>
            </select>
        </div>

        <!-- Add Player Form -->
        <form id="addPlayerForm" class="mb-4" onsubmit="addPlayer(event)">
            <div class="mb-3">
                <label for="name" class="form-label">Player Name:</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" id="addPlayerBtn" disabled>Add Player</button>
        </form>

        <!-- Player List -->
        <div id="players-list" class="mt-4">
            <h3>Players</h3>
            <ul id="players" class="list-group"></ul>
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
                    document.getElementById('players').innerHTML = '';
                    
                    // Reload divisions for the selected season
                    if (this.value) {
                        loadDivisions(this.value);
                    }
                });
            }
        });

        function loadDivisions(seasonId) {
            fetch(`get_divisions.php?season_id=${seasonId}`)
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

        function loadTeams(divisionId) {
            if (divisionId) {
                fetch(`get_teams.php?division_id=${divisionId}`)
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
                        document.getElementById('addPlayerBtn').disabled = true;
                    })
                    .catch(error => console.error('Error fetching teams:', error));
            } else {
                document.getElementById('team_id').innerHTML = '<option value="">-- Select Team --</option>';
                document.getElementById('addPlayerBtn').disabled = true;
            }
        }

        function loadPlayers(teamId) {
            if (teamId) {
                // Enable add player button when team is selected
                document.getElementById('addPlayerBtn').disabled = false;
                
                fetch(`get_players.php?team_id=${teamId}`)
                    .then(response => response.json())
                    .then(data => {
                        const playersList = document.getElementById('players');
                        playersList.innerHTML = '';

                        if (data.error) {
                            playersList.innerHTML = '<li class="list-group-item text-danger">Error: ' + data.error + '</li>';
                        } else if (data.length > 0) {
                            data.forEach(player => {
                                const listItem = document.createElement('li');
                                listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                                listItem.innerHTML = `
                                    <input type="text" value="${player.name}" class="form-control form-control-sm w-50" 
                                        onchange="editPlayer(${player.id}, this.value)">
                                    <button class="btn btn-danger btn-sm" onclick="deletePlayer(${player.id})">Delete</button>
                                `;
                                playersList.appendChild(listItem);
                            });
                        } else {
                            playersList.innerHTML = '<li class="list-group-item text-muted">No players found.</li>';
                        }
                    })
                    .catch(error => console.error('Error fetching players:', error));
            } else {
                document.getElementById('players').innerHTML = '';
                document.getElementById('addPlayerBtn').disabled = true;
            }
        }

        function addPlayer(event) {
            event.preventDefault();
            const name = document.getElementById('name').value;
            const teamId = document.getElementById('team_id').value;

            if (teamId && name) {
                fetch('add_player.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `name=${name}&team_id=${teamId}`
                })
                .then(response => response.text())
                .then(() => {
                    document.getElementById('name').value = '';
                    loadPlayers(teamId);
                })
                .catch(error => console.error('Error adding player:', error));
            }
        }

        function editPlayer(playerId, newName) {
            fetch('edit_player.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${playerId}&name=${newName}`
            }).catch(error => console.error('Error updating player:', error));
        }

        function deletePlayer(playerId) {
            if (confirm('Are you sure you want to delete this player?')) {
                fetch('delete_player.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${playerId}`
                })
                .then(() => loadPlayers(document.getElementById('team_id').value))
                .catch(error => console.error('Error deleting player:', error));
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
