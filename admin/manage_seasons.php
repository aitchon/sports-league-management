<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";

// Handle new season addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_season'])) {
    $name = $_POST['name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // If setting as active, deactivate all other seasons first
    if ($is_active) {
        $conn->query("UPDATE seasons SET is_active = 0");
    }

    $stmt = $conn->prepare("INSERT INTO seasons (name, start_date, end_date, is_active) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $name, $start_date, $end_date, $is_active);

    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Season added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Handle setting active season
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['set_active'])) {
    $season_id = $_POST['season_id'];
    
    // Deactivate all seasons first
    $conn->query("UPDATE seasons SET is_active = 0");
    
    // Activate selected season
    $stmt = $conn->prepare("UPDATE seasons SET is_active = 1 WHERE id = ?");
    $stmt->bind_param("i", $season_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Active season updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    
    $stmt->close();
}

// Handle season editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_season'])) {
    $season_id = $_POST['season_id'];
    $name = $_POST['name'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // If setting as active, deactivate all other seasons first
    if ($is_active) {
        $conn->query("UPDATE seasons SET is_active = 0");
    }

    $stmt = $conn->prepare("UPDATE seasons SET name = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("sssii", $name, $start_date, $end_date, $is_active, $season_id);

    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Season updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Handle season deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_season'])) {
    $season_id = $_POST['season_id'];
    
    // Check if season has any teams or games
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teams WHERE season_id = ?");
    $check_stmt->bind_param("i", $season_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $team_count = $result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($team_count > 0) {
        $message = '<div class="alert alert-danger">Cannot delete season: It has teams associated with it. Please delete teams first.</div>';
    } else {
        $stmt = $conn->prepare("DELETE FROM seasons WHERE id = ?");
        $stmt->bind_param("i", $season_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Season deleted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
        }
        
        $stmt->close();
    }
}

// Get season for editing
$edit_season = null;
if (isset($_GET['edit'])) {
    $edit_season = getSeasonById($conn, $_GET['edit']);
}

// Fetch all seasons
$seasons = getAllSeasons($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Seasons</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <a href="dashboard.php" class="btn btn-secondary mb-3">⬅ Back to Dashboard</a>

    <div class="card shadow-lg p-4">
        <h2 class="mb-4 text-center">Manage Seasons</h2>

        <!-- Success/Error Message -->
        <?php if ($message) echo $message; ?>

        <!-- Add/Edit Season Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $edit_season ? 'Edit Season' : 'Add New Season'; ?></h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <?php if ($edit_season): ?>
                        <input type="hidden" name="edit_season" value="1">
                        <input type="hidden" name="season_id" value="<?php echo $edit_season['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="add_season" value="1">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Season Name:</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo $edit_season ? htmlspecialchars($edit_season['name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                           <?php echo ($edit_season && $edit_season['is_active']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Set as Active Season
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date:</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" 
                                       value="<?php echo $edit_season ? $edit_season['start_date'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date:</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" 
                                       value="<?php echo $edit_season ? $edit_season['end_date'] : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_season ? 'Update Season' : 'Add Season'; ?>
                        </button>
                        <?php if ($edit_season): ?>
                            <a href="manage_seasons.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Seasons Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Current Seasons</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seasons as $season): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($season['name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($season['start_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($season['end_date'])); ?></td>
                                    <td>
                                        <?php if ($season['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="?edit=<?php echo $season['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                            
                                            <?php if (!$season['is_active']): ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="set_active" value="1">
                                                    <input type="hidden" name="season_id" value="<?php echo $season['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            onclick="return confirm('Set this season as active? This will deactivate the current active season.')">
                                                        Set Active
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this season? This action cannot be undone.')">
                                                <input type="hidden" name="delete_season" value="1">
                                                <input type="hidden" name="season_id" value="<?php echo $season['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
