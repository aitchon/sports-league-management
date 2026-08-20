<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get selected season from session
$selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : ($current_season ? $current_season['id'] : null);

$message = "";

// Handle playoff bracket creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_bracket'])) {
    $division_id = $_POST['division_id'];
    $bracket_name = $_POST['bracket_name'];
    $teams = $_POST['teams'] ?? [];
    
    // Create playoff placeholder teams for the bracket
    $placeholder_count = 0;
    foreach ($teams as $team_name) {
        if (!empty(trim($team_name))) {
            $stmt = $conn->prepare("INSERT INTO teams (name, coach, division_id, season_id, is_playoff_placeholder) VALUES (?, '', ?, ?, 1)");
            $stmt->bind_param("sii", $team_name, $division_id, $selected_season_id);
            if ($stmt->execute()) {
                $placeholder_count++;
            }
            $stmt->close();
        }
    }
    
    if ($placeholder_count > 0) {
        $message = '<div class="alert alert-success">Created ' . $placeholder_count . ' playoff placeholder teams for ' . $bracket_name . '!</div>';
    } else {
        $message = '<div class="alert alert-warning">No teams were created. Please enter at least one team name.</div>';
    }
}

// Fetch playoff divisions for the selected season
if ($selected_season_id) {
    $playoff_divisions_stmt = $conn->prepare("SELECT id, name FROM divisions WHERE season_id = ? AND is_playoff = 1 ORDER BY name");
    $playoff_divisions_stmt->bind_param("i", $selected_season_id);
    $playoff_divisions_stmt->execute();
    $playoff_divisions = $playoff_divisions_stmt->get_result();
    $playoff_divisions_stmt->close();
} else {
    $playoff_divisions = $conn->query("SELECT id, name FROM divisions WHERE is_playoff = 1 ORDER BY name");
}

// Fetch all playoff placeholder teams for the selected season
if ($selected_season_id) {
    $placeholder_teams_stmt = $conn->prepare("
        SELECT t.id, t.name, d.name as division_name 
        FROM teams t 
        JOIN divisions d ON t.division_id = d.id 
        WHERE t.season_id = ? AND t.is_playoff_placeholder = 1 
        ORDER BY d.name, t.name
    ");
    $placeholder_teams_stmt->bind_param("i", $selected_season_id);
    $placeholder_teams_stmt->execute();
    $placeholder_teams = $placeholder_teams_stmt->get_result();
    $placeholder_teams_stmt->close();
} else {
    $placeholder_teams = $conn->query("
        SELECT t.id, t.name, d.name as division_name 
        FROM teams t 
        JOIN divisions d ON t.division_id = d.id 
        WHERE t.is_playoff_placeholder = 1 
        ORDER BY d.name, t.name
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Playoff Brackets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <a href="dashboard.php" class="btn btn-secondary mb-3">⬅ Back to Dashboard</a>

    <!-- Season Selector -->
    <?php include 'season_selector.php'; ?>

    <div class="card shadow-lg p-4">
        <h2 class="mb-4 text-center">Manage Playoff Brackets</h2>

        <!-- Success/Error Message -->
        <?php if ($message) echo $message; ?>

        <!-- Create Playoff Bracket Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Create Playoff Bracket</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="create_bracket" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bracket_name" class="form-label">Bracket Name:</label>
                                <input type="text" id="bracket_name" name="bracket_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="division_id" class="form-label">Playoff Division:</label>
                                <select id="division_id" name="division_id" class="form-select" required>
                                    <option value="">Select Playoff Division</option>
                                    <?php while ($division = $playoff_divisions->fetch_assoc()): ?>
                                        <option value="<?php echo $division['id']; ?>"><?php echo htmlspecialchars($division['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Teams in Bracket:</label>
                        <div id="teams-container">
                            <div class="input-group mb-2">
                                <input type="text" name="teams[]" class="form-control" placeholder="Team name (e.g., 'TBD', 'Bye', 'Winner of Game 1')">
                                <button type="button" class="btn btn-outline-danger" onclick="removeTeam(this)">Remove</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addTeam()">Add Team</button>
                        <small class="form-text text-muted d-block mt-2">
                            Add placeholder teams for your playoff bracket (e.g., "TBD", "Bye", "Winner of Game 1", etc.)
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Bracket</button>
                </form>
            </div>
        </div>

        <!-- Existing Playoff Placeholder Teams -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Existing Playoff Placeholder Teams</h5>
            </div>
            <div class="card-body">
                <?php if ($placeholder_teams->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Team Name</th>
                                    <th>Division</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($team = $placeholder_teams->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($team['name']); ?>
                                            <span class="badge bg-warning ms-2">Placeholder</span>
                                        </td>
                                        <td><?php echo htmlspecialchars($team['division_name']); ?></td>
                                        <td>
                                            <a href="delete_team.php?id=<?php echo $team['id']; ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Are you sure you want to delete this playoff placeholder team?')">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No playoff placeholder teams found for the selected season.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function addTeam() {
    const container = document.getElementById('teams-container');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="text" name="teams[]" class="form-control" placeholder="Team name (e.g., 'TBD', 'Bye', 'Winner of Game 1')">
        <button type="button" class="btn btn-outline-danger" onclick="removeTeam(this)">Remove</button>
    `;
    container.appendChild(div);
}

function removeTeam(button) {
    button.parentElement.remove();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
