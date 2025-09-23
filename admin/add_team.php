<?php
session_start();
include 'db.php';

// Get selected season from session
$selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : ($current_season ? $current_season['id'] : null);

// Initialize message variable
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$selected_season_id) {
        $message = '<div class="alert alert-danger">Please select a season before adding a team.</div>';
    } else {
        $name = $_POST['name'];
        $division_id = $_POST['division_id'];
        $coach = isset($_POST['coach']) ? $_POST['coach'] : NULL; // Ensure coach is either from form or NULL
        $season_id = $selected_season_id; // Use selected season
        $is_playoff_placeholder = isset($_POST['is_playoff_placeholder']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO teams (name, coach, division_id, season_id, is_playoff_placeholder) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiii", $name, $coach, $division_id, $season_id, $is_playoff_placeholder);

        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Team added successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }

        $stmt->close();
    }
}

// Fetch divisions for dropdown (filtered by selected season)
if ($selected_season_id) {
    $divisions_stmt = $conn->prepare("SELECT id, name FROM divisions WHERE season_id = ? ORDER BY name");
    $divisions_stmt->bind_param("i", $selected_season_id);
    $divisions_stmt->execute();
    $divisions = $divisions_stmt->get_result();
    $divisions_stmt->close();
} else {
    $divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name");
}

// Fetch existing teams for selected division and season
$selected_division_id = isset($_POST['division_id']) ? $_POST['division_id'] : (isset($_GET['division_id']) ? $_GET['division_id'] : '');
$teams = [];

// Only fetch teams if we have both a division and a season selected
if (!empty($selected_division_id) && $selected_season_id) {
    $teams_query = $conn->prepare("SELECT name, is_playoff_placeholder FROM teams WHERE division_id = ? AND season_id = ? ORDER BY name");
    $teams_query->bind_param("ii", $selected_division_id, $selected_season_id);
    $teams_query->execute();
    $teams_result = $teams_query->get_result();
    while ($team = $teams_result->fetch_assoc()) {
        $teams[] = $team;
    }
    $teams_query->close();
} elseif (!empty($selected_division_id) && !$selected_season_id) {
    // If division is selected but no season, show a message
    $message = '<div class="alert alert-warning">Please select a season first to view teams in this division.</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Team</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function updateTeams() {
            let divisionId = document.getElementById('division_id').value;
            if (divisionId) {
                // Preserve any existing URL parameters and add division_id
                let url = new URL(window.location);
                url.searchParams.set('division_id', divisionId);
                window.location.href = url.toString();
            } else {
                // Remove division_id parameter if no division selected
                let url = new URL(window.location);
                url.searchParams.delete('division_id');
                window.location.href = url.toString();
            }
        }
    </script>
</head>
<body class="bg-light">

    <!-- Navigation Bar -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="mb-4">Add Team</h1>

        <!-- Season Selector -->
        <?php include 'season_selector.php'; ?>

        <!-- Success/Error Message -->
        <?php if (isset($message)) echo $message; ?>

        <!-- Team Addition Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <?php if (!$selected_season_id): ?>
                    <div class="alert alert-warning mb-3">
                        <strong>Note:</strong> Please select a season above before adding a team.
                    </div>
                <?php endif; ?>
                <form method="post" <?php echo !$selected_season_id ? 'onsubmit="alert(\'Please select a season first.\'); return false;"' : ''; ?>>
                    <div class="mb-3">
                        <label for="division_id" class="form-label">Division:</label>
                        <select id="division_id" name="division_id" class="form-select" required onchange="updateTeams()" <?php echo !$selected_season_id ? 'disabled' : ''; ?>>
                            <option value="">-- Select Division --</option>
                            <?php while ($division = $divisions->fetch_assoc()): ?>
                                <option value="<?php echo $division['id']; ?>" <?php echo ($selected_division_id == $division['id']) ? 'selected' : ''; ?>>
                                    <?php echo $division['name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Team Name:</label>
                        <input type="text" id="name" name="name" class="form-control" required <?php echo !$selected_season_id ? 'disabled' : ''; ?>>
                    </div>

                    <div class="mb-3">
                        <label for="coach" class="form-label">Coach:</label>
                        <input type="text" id="coach" name="coach" class="form-control" <?php echo !$selected_season_id ? 'disabled' : ''; ?>>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_playoff_placeholder" name="is_playoff_placeholder" <?php echo !$selected_season_id ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="is_playoff_placeholder">
                                This is a playoff placeholder team (for scheduling purposes)
                            </label>
                        </div>
                        <small class="form-text text-muted">Check this for teams that are used as placeholders in playoff brackets (e.g., "TBD", "Bye", etc.)</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" <?php echo !$selected_season_id ? 'disabled' : ''; ?>>Add Team</button>
                </form>
            </div>
        </div>

        <!-- Existing Teams Table -->
        <?php if (!empty($teams)): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="mb-3">Existing Teams in Selected Division</h2>
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Team Name</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($team['name']); ?></td>
                                    <td>
                                        <?php if ($team['is_playoff_placeholder']): ?>
                                            <span class="badge bg-warning">Playoff Placeholder</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Regular Team</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
