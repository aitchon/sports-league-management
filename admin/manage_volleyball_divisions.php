<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";

// Handle new division addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_division'])) {
    $name = $_POST['name'];
    $is_playoff = isset($_POST['is_playoff']) ? 1 : 0;
    $season_id = (int)$_POST['season_id'];

    $stmt = $conn->prepare("INSERT INTO volleyball_divisions (name, is_playoff, season_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $name, $is_playoff, $season_id);

    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Volleyball division added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Handle division editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_division'])) {
    $division_id = $_POST['division_id'];
    $name = $_POST['name'];
    $is_playoff = isset($_POST['is_playoff']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE volleyball_divisions SET name = ?, is_playoff = ? WHERE id = ?");
    $stmt->bind_param("sii", $name, $is_playoff, $division_id);

    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Volleyball division updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Handle division deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_division'])) {
    $division_id = $_POST['division_id'];
    
    // Check if division has teams
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM volleyball_teams WHERE division_id = ?");
    $check_stmt->bind_param("i", $division_id);
    $check_stmt->execute();
    $team_count = $check_stmt->get_result()->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($team_count > 0) {
        $message = '<div class="alert alert-danger">Cannot delete division with existing teams. Please move or delete teams first.</div>';
    } else {
        $stmt = $conn->prepare("DELETE FROM volleyball_divisions WHERE id = ?");
        $stmt->bind_param("i", $division_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Volleyball division deleted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        
        $stmt->close();
    }
}

// Get current season
$current_season = null;
$result = $conn->query("SELECT * FROM volleyball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

// Get all seasons for dropdown
$seasons = $conn->query("SELECT * FROM volleyball_seasons ORDER BY start_date DESC");

// Get divisions for current season
$divisions = [];
if ($current_season) {
    $stmt = $conn->prepare("SELECT d.*, COUNT(t.id) as team_count FROM volleyball_divisions d LEFT JOIN volleyball_teams t ON d.id = t.division_id WHERE d.season_id = ? GROUP BY d.id ORDER BY d.name ASC");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Volleyball Divisions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #FF6B6B, #FF8E8E);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12 sport-header py-4 mb-4">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">🏐 Manage Volleyball Divisions</h1>
                            <p class="mb-0">Create and manage volleyball divisions</p>
                        </div>
                        <div>
                            <a href="volleyball_dashboard.php" class="btn btn-light me-2">← Back to Dashboard</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php echo $message; ?>

            <!-- Add New Division -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New Volleyball Division</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Division Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="season_id" class="form-label">Season</label>
                                <select class="form-select" id="season_id" name="season_id" required>
                                    <?php while ($season = $seasons->fetch_assoc()): ?>
                                        <option value="<?php echo $season['id']; ?>" <?php echo ($current_season && $current_season['id'] == $season['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($season['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_playoff" name="is_playoff">
                                    <label class="form-check-label" for="is_playoff">
                                        Playoff Division
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_division" class="btn btn-primary">Add Division</button>
                    </form>
                </div>
            </div>

            <!-- Existing Divisions -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Existing Volleyball Divisions</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($divisions)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Teams</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($divisions as $division): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($division['name']); ?></strong></td>
                                            <td>
                                                <?php if ($division['is_playoff']): ?>
                                                    <span class="badge bg-warning">Playoff</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Regular</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $division['team_count']; ?> teams</span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $division['id']; ?>">
                                                        Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $division['id']; ?>">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No volleyball divisions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modals -->
    <?php foreach ($divisions as $division): ?>
        <div class="modal fade" id="editModal<?php echo $division['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Volleyball Division</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="division_id" value="<?php echo $division['id']; ?>">
                            
                            <div class="mb-3">
                                <label for="edit_name_<?php echo $division['id']; ?>" class="form-label">Division Name</label>
                                <input type="text" class="form-control" id="edit_name_<?php echo $division['id']; ?>" name="name" value="<?php echo htmlspecialchars($division['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_playoff_<?php echo $division['id']; ?>" name="is_playoff" <?php echo $division['is_playoff'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_is_playoff_<?php echo $division['id']; ?>">
                                        Playoff Division
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_division" class="btn btn-primary">Update Division</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal<?php echo $division['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Volleyball Division</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the division "<?php echo htmlspecialchars($division['name']); ?>"?</p>
                        <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                        <?php if ($division['team_count'] > 0): ?>
                            <p class="text-warning"><strong>Note:</strong> This division has <?php echo $division['team_count']; ?> teams. You may need to reassign them first.</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="division_id" value="<?php echo $division['id']; ?>">
                            <button type="submit" name="delete_division" class="btn btn-danger">Delete Division</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
