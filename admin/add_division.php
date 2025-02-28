<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];

    $stmt = $conn->prepare("INSERT INTO divisions (name) VALUES (?)");
    $stmt->bind_param("s", $name);

    $message = "";
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Division added successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
}

// Fetch existing divisions
$divisions = $conn->query("SELECT id, name FROM divisions");
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

        <!-- Success/Error Message -->
        <?php if (isset($message)) echo $message; ?>

        <!-- Division Addition Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="name" class="form-label">Division Name:</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add Division</button>
                </form>
            </div>
        </div>

        <!-- Existing Divisions Table -->
        <?php if ($divisions->num_rows > 0): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="mb-3">Existing Divisions</h2>
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($division = $divisions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($division['id']); ?></td>
                                    <td><?php echo htmlspecialchars($division['name']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
