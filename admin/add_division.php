<?php
session_start();
include 'db.php';

// Get selected season from session
$selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : ($current_season ? $current_season['id'] : null);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $is_playoff = isset($_POST['is_playoff']) ? 1 : 0;
    $season_id = $selected_season_id; // Use selected season

    $stmt = $conn->prepare("INSERT INTO divisions (name, is_playoff, season_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $name, $is_playoff, $season_id);

    $message = "";
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Division added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Fetch existing divisions for selected season
if ($selected_season_id) {
    $divisions_stmt = $conn->prepare("SELECT id, name, is_playoff FROM divisions WHERE season_id = ? ORDER BY name");
    $divisions_stmt->bind_param("i", $selected_season_id);
    $divisions_stmt->execute();
    $divisions = $divisions_stmt->get_result();
    $divisions_stmt->close();
} else {
    $divisions = $conn->query("SELECT id, name, is_playoff FROM divisions ORDER BY name");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Division</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navigation Bar -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="mb-4">Add Division</h1>

        <!-- Season Selector -->
        <?php include 'season_selector.php'; ?>

        <!-- Success/Error Message -->
        <?php if (isset($message)) echo $message; ?>

        <!-- Division Addition Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New Division</h5>
                <small class="text-muted">Divisions are season-specific</small>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="name" class="form-label">Division Name:</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_playoff" name="is_playoff">
                            <label class="form-check-label" for="is_playoff">
                                This is a playoff division
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add Division</button>
                </form>
            </div>
        </div>

        <!-- Existing Divisions Table -->
        <?php if ($divisions->num_rows > 0): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="mb-3">Existing Divisions for Selected Season</h2>
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($division = $divisions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($division['id']); ?></td>
                                    <td><?php echo htmlspecialchars($division['name']); ?></td>
                                    <td>
                                        <?php if ($division['is_playoff']): ?>
                                            <span class="badge bg-warning">Playoff</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Regular</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="mb-3">Existing Divisions for Selected Season</h2>
                    <p class="text-muted">No divisions found for the selected season.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
