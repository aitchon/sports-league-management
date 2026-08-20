<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get selected season from session
$selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : null;

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

// Handle team operations
$message = "";

// Add new team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_team'])) {
    $name = $_POST['name'];
    $coach = $_POST['coach'] ?? '';
    $division_id = intval($_POST['division_id']);
    $team_pic_url = $_POST['team_pic_url'] ?? '';
    $is_playoff_placeholder = isset($_POST['is_playoff_placeholder']) ? 1 : 0;
    
    if ($name && $division_id && $selected_season_id) {
        $stmt = $conn->prepare("INSERT INTO teams (name, coach, team_pic_url, division_id, season_id, is_playoff_placeholder) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiii", $name, $coach, $team_pic_url, $division_id, $selected_season_id, $is_playoff_placeholder);
        
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=added");
            exit();
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            $stmt->close();
        }
    } else {
        $message = '<div class="alert alert-danger">Please fill in all required fields and select a season.</div>';
    }
}

// Update team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_team'])) {
    $team_id = intval($_POST['team_id']);
    $name = $_POST['name'];
    $coach = $_POST['coach'] ?? '';
    $division_id = intval($_POST['division_id']);
    $team_pic_url = $_POST['team_pic_url'] ?? '';
    $is_playoff_placeholder = isset($_POST['is_playoff_placeholder']) ? 1 : 0;
    
    if ($name && $division_id) {
        $stmt = $conn->prepare("UPDATE teams SET name = ?, coach = ?, team_pic_url = ?, division_id = ?, is_playoff_placeholder = ? WHERE id = ?");
        $stmt->bind_param("sssiii", $name, $coach, $team_pic_url, $division_id, $is_playoff_placeholder, $team_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=updated");
            exit();
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            $stmt->close();
        }
    } else {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    }
}

// Delete team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_team'])) {
    $team_id = intval($_POST['team_id']);
    
    $stmt = $conn->prepare("DELETE FROM teams WHERE id = ?");
    $stmt->bind_param("i", $team_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=deleted");
        exit();
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        $stmt->close();
    }
}

// Fetch teams for display
$teams = null;
if ($selected_season_id) {
    $teams_stmt = $conn->prepare("
        SELECT t.id, t.name, t.coach, t.team_pic_url, t.division_id, t.is_playoff_placeholder, d.name as division_name 
        FROM teams t 
        JOIN divisions d ON t.division_id = d.id 
        WHERE t.season_id = ?
        ORDER BY t.is_playoff_placeholder, d.name, t.name
    ");
    $teams_stmt->bind_param("i", $selected_season_id);
    $teams_stmt->execute();
    $teams = $teams_stmt->get_result();
    $teams_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Basketball Teams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="mb-3">
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
    <div class="container py-5">
        <div class="sport-header p-4 rounded mb-4">
            <h1 class="display-4 text-center mb-0">🏀 Manage Basketball Teams</h1>
        </div>

        <?php 
        // Show success message if redirected after operation
        if (isset($_GET['success'])) {
            $success_msg = '';
            switch ($_GET['success']) {
                case 'added':
                    $success_msg = '<div class="alert alert-success">Team added successfully!</div>';
                    break;
                case 'updated':
                    $success_msg = '<div class="alert alert-success">Team updated successfully!</div>';
                    break;
                case 'deleted':
                    $success_msg = '<div class="alert alert-success">Team deleted successfully!</div>';
                    break;
            }
            echo $success_msg;
        }
        echo $message; 
        ?>

        <!-- Season Selector -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏀 Basketball Season Selection</h5>
            </div>
            <div class="card-body">
                <?php include 'season_selector.php'; ?>
            </div>
        </div>

        <?php if (!$selected_season_id): ?>
            <div class="alert alert-warning">
                <h4>No Season Selected</h4>
                <p>Please select a season from the dropdown above to manage teams.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p><strong>Selected Season:</strong> 
                <?php 
                $stmt = $conn->prepare("SELECT name FROM seasons WHERE id = ?");
                $stmt->bind_param("i", $selected_season_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $season_name = $result->fetch_assoc()['name'];
                echo htmlspecialchars($season_name);
                $stmt->close();
                ?> (ID: <?php echo $selected_season_id; ?>)</p>
            </div>

            <!-- Add Team Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Add New Team</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="name" class="form-label">Team Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="coach" class="form-label">Coach Name</label>
                                <input type="text" class="form-control" id="coach" name="coach">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="division_id" class="form-label">Division *</label>
                                <select class="form-select" id="division_id" name="division_id" required>
                                    <option value="">-- Select Division --</option>
                                    <?php 
                                    // Reset divisions result
                                    if ($selected_season_id) {
                                        $divisions_stmt = $conn->prepare("SELECT id, name FROM divisions WHERE season_id = ? ORDER BY name");
                                        $divisions_stmt->bind_param("i", $selected_season_id);
                                        $divisions_stmt->execute();
                                        $divisions = $divisions_stmt->get_result();
                                        $divisions_stmt->close();
                                    }
                                    while ($division = $divisions->fetch_assoc()): ?>
                                        <option value="<?php echo $division['id']; ?>"><?php echo $division['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="team_pic_url" class="form-label">Team Picture URL (Optional)</label>
                                <input type="url" class="form-control" id="team_pic_url" name="team_pic_url" placeholder="https://example.com/team-photo.jpg">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_playoff_placeholder" name="is_playoff_placeholder">
                                    <label class="form-check-label" for="is_playoff_placeholder">
                                        This is a playoff placeholder team (for scheduling purposes)
                                    </label>
                                </div>
                                <small class="form-text text-muted">Check this for teams that are used as placeholders in playoff brackets (e.g., "TBD", "Bye", etc.). These teams will not appear in standings.</small>
                            </div>
                        </div>
                        <button type="submit" name="add_team" class="btn btn-primary">Add Team</button>
                    </form>
                </div>
            </div>

            <!-- Teams List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Current Teams</h5>
                </div>
                <div class="card-body">
                    <?php if ($teams && $teams->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Team Name</th>
                                        <th>Coach</th>
                                        <th>Division</th>
                                        <th>Type</th>
                                        <th>Picture</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($team = $teams->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($team['name']); ?></td>
                                            <td><?php echo htmlspecialchars($team['coach'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($team['division_name']); ?></td>
                                            <td>
                                                <?php if ($team['is_playoff_placeholder']): ?>
                                                    <span class="badge bg-warning">Playoff Placeholder</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Regular Team</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($team['team_pic_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($team['team_pic_url']); ?>" 
                                                         alt="Team Photo" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                <?php else: ?>
                                                    <span class="text-muted">No image</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editTeam(<?php echo htmlspecialchars(json_encode($team)); ?>)">
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteTeam(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['name']); ?>')">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No teams found for the selected season.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Team Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" id="edit_team_id" name="team_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Team Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_coach" class="form-label">Coach Name</label>
                            <input type="text" class="form-control" id="edit_coach" name="coach">
                        </div>
                        <div class="mb-3">
                            <label for="edit_division_id" class="form-label">Division *</label>
                            <select class="form-select" id="edit_division_id" name="division_id" required>
                                <option value="">-- Select Division --</option>
                                <?php 
                                if ($selected_season_id) {
                                    $divisions_stmt = $conn->prepare("SELECT id, name FROM divisions WHERE season_id = ? ORDER BY name");
                                    $divisions_stmt->bind_param("i", $selected_season_id);
                                    $divisions_stmt->execute();
                                    $divisions = $divisions_stmt->get_result();
                                    $divisions_stmt->close();
                                }
                                while ($division = $divisions->fetch_assoc()): ?>
                                    <option value="<?php echo $division['id']; ?>"><?php echo $division['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_team_pic_url" class="form-label">Team Picture URL (Optional)</label>
                            <input type="url" class="form-control" id="edit_team_pic_url" name="team_pic_url" placeholder="https://example.com/team-photo.jpg">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_playoff_placeholder" name="is_playoff_placeholder">
                                <label class="form-check-label" for="edit_is_playoff_placeholder">
                                    This is a playoff placeholder team (for scheduling purposes)
                                </label>
                            </div>
                            <small class="form-text text-muted">Check this for teams that are used as placeholders in playoff brackets (e.g., "TBD", "Bye", etc.). These teams will not appear in standings.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_team" class="btn btn-primary">Update Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Team Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the team "<span id="teamName"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This will also delete all players associated with this team!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" id="deleteTeamId" name="team_id">
                        <button type="submit" name="delete_team" class="btn btn-danger">Delete Team</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editTeam(team) {
            document.getElementById('edit_team_id').value = team.id;
            document.getElementById('edit_name').value = team.name;
            document.getElementById('edit_coach').value = team.coach || '';
            document.getElementById('edit_division_id').value = team.division_id || '';
            document.getElementById('edit_team_pic_url').value = team.team_pic_url || '';
            document.getElementById('edit_is_playoff_placeholder').checked = team.is_playoff_placeholder == 1;
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteTeam(teamId, teamName) {
            document.getElementById('deleteTeamId').value = teamId;
            document.getElementById('teamName').textContent = teamName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

