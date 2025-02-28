<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Fetch divisions
$divisions = $conn->query("SELECT id, name FROM divisions");

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
            <button type="submit" class="btn btn-primary">Add Player</button>
        </form>

        <!-- Player List -->
        <div id="players-list" class="mt-4">
            <h3>Players</h3>
            <ul id="players" class="list-group"></ul>
        </div>
    </div>

    <script>
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
                    })
                    .catch(error => console.error('Error fetching teams:', error));
            } else {
                document.getElementById('team_id').innerHTML = '<option value="">-- Select Team --</option>';
            }
        }

        function loadPlayers(teamId) {
            if (teamId) {
                fetch(`get_players.php?team_id=${teamId}`)
                    .then(response => response.json())
                    .then(data => {
                        const playersList = document.getElementById('players');
                        playersList.innerHTML = '';

                        if (data.length > 0) {
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
