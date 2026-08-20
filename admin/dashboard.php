<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
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

        <!-- Season Selector -->
        <?php include 'season_selector.php'; ?>

        <div class="card shadow-lg p-4">
            <h2 class="mb-4">Basketball League Dashboard</h2>
            <div class="list-group">
                <a href="manage_seasons.php" class="list-group-item list-group-item-action">🏀 Manage Seasons</a>
                <a href="add_division.php" class="list-group-item list-group-item-action">➕ Add Division</a>
                <a href="manage_teams.php" class="list-group-item list-group-item-action">🏆 Manage Teams</a>
                <a href="add_team.php" class="list-group-item list-group-item-action">➕ Add Team</a>
                <a href="manage_playoff_brackets.php" class="list-group-item list-group-item-action">🏆 Manage Playoff Brackets</a>
                <a href="manage_players.php" class="list-group-item list-group-item-action">👤 Manage Players</a>
                <a href="manage_games.php" class="list-group-item list-group-item-action">📅 Manage Games</a>
            </div>
            
            <hr class="my-4">
            
            <h3 class="mb-3">Other Leagues Admin</h3>
            <div class="list-group">
                <a href="volleyball_dashboard.php" class="list-group-item list-group-item-action list-group-item-danger">🏐 Volleyball League Admin</a>
                <a href="pickleball_dashboard.php" class="list-group-item list-group-item-action list-group-item-warning">🏓 Pickleball League Admin</a>
            </div>

            <hr class="my-4">

            <h3 class="mb-3">Sport Dashboards</h3>
            <div class="list-group">
                <a href="../standings.php" class="list-group-item list-group-item-action list-group-item-primary" target="_blank">🏀 Basketball Standings</a>
                <a href="../volleyball_standings.php" class="list-group-item list-group-item-action list-group-item-danger" target="_blank">🏐 Volleyball Standings</a>
                <a href="../pickleball_standings.php" class="list-group-item list-group-item-action list-group-item-warning" target="_blank">🏓 Pickleball Standings</a>
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
