<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $division_id = $_POST['division_id'];
    $coach = isset($_POST['coach']) ? $_POST['coach'] : NULL; // Ensure coach is either from form or NULL

    $stmt = $conn->prepare("INSERT INTO teams (name, coach, division_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $name, $coach, $division_id);

    $message = "";
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Team added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Fetch divisions for dropdown
$divisions = $conn->query("SELECT id, name FROM divisions");

// Fetch existing teams for selected division
$selected_division_id = isset($_POST['division_id']) ? $_POST['division_id'] : (isset($_GET['division_id']) ? $_GET['division_id'] : '');
$teams = [];
if (!empty($selected_division_id)) {
    $teams_query = $conn->prepare("SELECT name FROM teams WHERE division_id = ?");
    $teams_query->bind_param("i", $selected_division_id);
    $teams_query->execute();
    $teams_result = $teams_query->get_result();
    while ($team = $teams_result->fetch_assoc()) {
        $teams[] = $team;
    }
    $teams_query->close();
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
                window.location.href = 'add_team.php?division_id=' + divisionId;
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

        <!-- Success/Error Message -->
        <?php if (isset($message)) echo $message; ?>

        <!-- Team Addition Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="division_id" class="form-label">Division:</label>
                        <select id="division_id" name="division_id" class="form-select" required onchange="updateTeams()">
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
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="coach" class="form-label">Coach:</label>
                        <input type="text" id="coach" name="coach" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Add Team</button>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($team['name']); ?></td>
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
