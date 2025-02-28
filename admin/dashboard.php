<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container py-5">
        <div class="text-center mb-4">
            <h1 class="display-4">Welcome, <?php echo $_SESSION['username']; ?>!</h1>
        </div>

        <div class="card shadow-lg p-4">
            <h2 class="mb-4">Dashboard</h2>
            <div class="list-group">
                <a href="add_division.php" class="list-group-item list-group-item-action">➕ Add Division</a>
                <a href="add_team.php" class="list-group-item list-group-item-action">🏆 Add Team</a>
                <a href="manage_players.php" class="list-group-item list-group-item-action">👤 Manage Players</a>
                <a href="manage_games.php" class="list-group-item list-group-item-action">📅 Manage Games</a>
            </div>

            <div class="mt-4 text-center">
                <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
