<?php
include 'admin/db.php';

// Get current volleyball season
$current_season = null;
$volleyball_season_stmt = $conn->prepare("SELECT * FROM volleyball_seasons WHERE is_active = 1 LIMIT 1");
$volleyball_season_stmt->execute();
$volleyball_season_result = $volleyball_season_stmt->get_result();
if ($volleyball_season_result && $volleyball_season_result->num_rows > 0) {
    $current_season = $volleyball_season_result->fetch_assoc();
}
$volleyball_season_stmt->close();

// Fetch volleyball divisions for the dropdown (filtered by current season, excluding divisions with only playoff placeholders)
if ($current_season) {
    $divisions_stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name 
        FROM volleyball_divisions d 
        JOIN volleyball_teams t ON d.id = t.division_id 
        WHERE d.season_id = ? AND d.name <> 'PRACTICE' AND d.is_playoff = 0 
        AND t.is_playoff_placeholder = 0 
        ORDER BY d.name
    ");
    $divisions_stmt->bind_param("i", $current_season['id']);
    $divisions_stmt->execute();
    $divisions = $divisions_stmt->get_result();
    $divisions_stmt->close();
} else {
    $divisions = $conn->query("
        SELECT DISTINCT d.id, d.name 
        FROM volleyball_divisions d 
        JOIN volleyball_teams t ON d.id = t.division_id 
        WHERE d.name <> 'PRACTICE' AND d.is_playoff = 0 
        AND t.is_playoff_placeholder = 0 
        ORDER BY d.name
    ");
}

// Check if there's only one division and auto-select it
$selected_division = isset($_GET['division']) ? intval($_GET['division']) : 0;

// If no division is selected and there's only one division available, auto-select it
if (!$selected_division && $current_season) {
    // Reset the divisions result to count them
    $divisions_stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name 
        FROM volleyball_divisions d 
        JOIN volleyball_teams t ON d.id = t.division_id 
        WHERE d.season_id = ? AND d.name <> 'PRACTICE' AND d.is_playoff = 0 
        AND t.is_playoff_placeholder = 0 
        ORDER BY d.name
    ");
    $divisions_stmt->bind_param("i", $current_season['id']);
    $divisions_stmt->execute();
    $divisions_result = $divisions_stmt->get_result();
    $divisions_array = [];
    while ($row = $divisions_result->fetch_assoc()) {
        $divisions_array[] = $row;
    }
    $divisions_stmt->close();
    
    // If there's exactly one division, auto-select it
    if (count($divisions_array) == 1) {
        $selected_division = $divisions_array[0]['id'];
    }
    
    // Recreate the divisions result for the dropdown
    $divisions_stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name 
        FROM volleyball_divisions d 
        JOIN volleyball_teams t ON d.id = t.division_id 
        WHERE d.season_id = ? AND d.name <> 'PRACTICE' AND d.is_playoff = 0 
        AND t.is_playoff_placeholder = 0 
        ORDER BY d.name
    ");
    $divisions_stmt->bind_param("i", $current_season['id']);
    $divisions_stmt->execute();
    $divisions = $divisions_stmt->get_result();
    $divisions_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volleyball Stat Leaders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-size: 0.9rem; 
        }
        .table th, .table td {
            font-size: 0.85rem; 
        }
        h1, h2 {
            font-size: 1.4rem; 
        }
        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
                align-items: flex-start;
            }
        }
/* Default styles (for non-mobile) remain unchanged */

/* Mobile-specific styles */
@media (max-width: 768px) {
    body, p, span, div, td, th {
        font-size: 2px !important;
    }

    a {
        font-size: 2px !important;
        color: blue !important;  /* Change to your preferred color */
        text-decoration: underline !important;
    }

    a:hover {
        color: red !important;  /* Change hover color */
        text-decoration: none !important;
    }

    a:visited {
        color: purple !important;  /* Change visited link color */
    }
.download-button a {
    color: white !important;
    text-decoration: none !important;
}

.download-button a:hover {
    color: lightgray !important; /* Optional: Slightly different color on hover */
}
    
}

    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h1 class="text-danger">🏐 Volleyball Stat Leaders by Division</h1>
        
        <?php if ($current_season): ?>
            <div class="alert alert-info">
                <p><strong>Current Season:</strong> <?php echo htmlspecialchars($current_season['name']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="mb-4 text-center">
            <a href="volleyball_standings.php" class="btn btn-outline-danger me-2">Volleyball Standings</a>
            <a href="volleyball_schedule.php" class="btn btn-outline-danger me-2">Volleyball Schedule</a>
        </div>

        <form method="GET" action="">
            <label for="division">Select Division:</label>
            <select name="division" id="division" onchange="this.form.submit()">
                <option value="">--- Select a division ---</option>
                <?php while ($division = $divisions->fetch_assoc()): ?>
                    <option value="<?php echo $division['id']; ?>" <?= ($selected_division == $division['id']) ? 'selected' : '' ?>>
                        <?php echo htmlspecialchars($division['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>

        <?php 
        // Reset divisions result for display
        if ($current_season) {
            $divisions_stmt = $conn->prepare("
                SELECT DISTINCT d.id, d.name 
                FROM volleyball_divisions d 
                JOIN volleyball_teams t ON d.id = t.division_id 
                WHERE d.season_id = ? AND d.name <> 'PRACTICE' AND d.is_playoff = 0 
                AND t.is_playoff_placeholder = 0 
                ORDER BY d.name
            ");
            $divisions_stmt->bind_param("i", $current_season['id']);
            $divisions_stmt->execute();
            $divisions = $divisions_stmt->get_result();
            $divisions_stmt->close();
        } else {
            $divisions = $conn->query("
                SELECT DISTINCT d.id, d.name 
                FROM volleyball_divisions d 
                JOIN volleyball_teams t ON d.id = t.division_id 
                WHERE d.name <> 'PRACTICE' AND d.is_playoff = 0 
                AND t.is_playoff_placeholder = 0 
                ORDER BY d.name
            ");
        }
        
        while ($division = $divisions->fetch_assoc()): 
            $division_id = $division['id'];
            $division_name = htmlspecialchars($division['name']);
            
            if ($selected_division != $division_id) {
                continue; // Skip divisions that don't match the filter
            }
        ?>

        <h2><?php echo $division_name; ?></h2>

        <?php
        function displayVolleyballLeaderboard($conn, $result, $headers, $fields, $orderField) {
            $rows = [];

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            if (empty($rows)) {
                echo "<p>No data available.</p>";
                return;
            }

            echo '<table class="table table-bordered table-striped">';
            echo '<thead class="thead-dark"><tr>';
            foreach ($headers as $header) {
                echo "<th>$header</th>";
            }
            echo '</tr></thead><tbody>';

            $rank = 1;
            $previous_value = null;
            $tie_rank = 1;
            $last_included_score = null;
            $max_displayed = 10;
            $included_players = 0;

            foreach ($rows as $index => $row) {
                // If we have displayed 10 players and the next player isn't tied, stop.
                if ($included_players >= $max_displayed && $row[$orderField] != $last_included_score) {
                    break;
                }

                // Update rank if the value changes
                if ($previous_value !== null && $row[$orderField] != $previous_value) {
                    $rank = $tie_rank;
                }

                echo '<tr><td>' . $rank . '</td>';
                foreach ($fields as $field) {
                    if ($field == 'kill_percentage' || $field == 'service_percentage') {
                        echo '<td>' . number_format($row[$field], 1) . '%</td>';
                    } else {
                        echo '<td>' . htmlspecialchars($row[$field]) . '</td>';
                    }
                }
                echo '</tr>';

                $previous_value = $row[$orderField];
                $tie_rank++;
                $included_players++;

                // Track the last included score for tie-breaking
                if ($included_players <= $max_displayed) {
                    $last_included_score = $row[$orderField];
                }
            }
            echo '</tbody></table>';
        }
        ?>

        <h3>Top Kill Leaders</h3>
        <?php
        if ($current_season) {
            $sql_top_kills = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.kills) AS total_kills
                              FROM volleyball_player_stats vps
                              JOIN volleyball_players p ON vps.player_id = p.id
                              JOIN volleyball_teams t ON p.team_id = t.id
                              JOIN volleyball_games g ON vps.game_id = g.id
                              WHERE t.division_id = ? AND g.season_id = ?
                              GROUP BY p.id, p.name, t.name
                              ORDER BY total_kills DESC";
            $stmt = $conn->prepare($sql_top_kills);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_top_kills = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.kills) AS total_kills
                              FROM volleyball_player_stats vps
                              JOIN volleyball_players p ON vps.player_id = p.id
                              JOIN volleyball_teams t ON p.team_id = t.id
                              WHERE t.division_id = $division_id
                              GROUP BY p.id, p.name, t.name
                              ORDER BY total_kills DESC";
            $result = $conn->query($sql_top_kills);
        }
        displayVolleyballLeaderboard($conn, $result, ["Rank", "Player", "Team", "Total Kills"], ["name", "team_name", "total_kills"], "total_kills");
        ?>

        <h3>Top Assist Leaders</h3>
        <?php
        if ($current_season) {
            $sql_top_assists = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.assists) AS total_assists
                                FROM volleyball_player_stats vps
                                JOIN volleyball_players p ON vps.player_id = p.id
                                JOIN volleyball_teams t ON p.team_id = t.id
                                JOIN volleyball_games g ON vps.game_id = g.id
                                WHERE t.division_id = ? AND g.season_id = ?
                                GROUP BY p.id, p.name, t.name
                                ORDER BY total_assists DESC";
            $stmt = $conn->prepare($sql_top_assists);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_top_assists = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.assists) AS total_assists
                                FROM volleyball_player_stats vps
                                JOIN volleyball_players p ON vps.player_id = p.id
                                JOIN volleyball_teams t ON p.team_id = t.id
                                WHERE t.division_id = $division_id
                                GROUP BY p.id, p.name, t.name
                                ORDER BY total_assists DESC";
            $result = $conn->query($sql_top_assists);
        }
        displayVolleyballLeaderboard($conn, $result, ["Rank", "Player", "Team", "Total Assists"], ["name", "team_name", "total_assists"], "total_assists");
        ?>

        <h3>Top Dig Leaders</h3>
        <?php
        if ($current_season) {
            $sql_top_digs = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.digs) AS total_digs
                             FROM volleyball_player_stats vps
                             JOIN volleyball_players p ON vps.player_id = p.id
                             JOIN volleyball_teams t ON p.team_id = t.id
                             JOIN volleyball_games g ON vps.game_id = g.id
                             WHERE t.division_id = ? AND g.season_id = ?
                             GROUP BY p.id, p.name, t.name
                             ORDER BY total_digs DESC";
            $stmt = $conn->prepare($sql_top_digs);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_top_digs = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.digs) AS total_digs
                             FROM volleyball_player_stats vps
                             JOIN volleyball_players p ON vps.player_id = p.id
                             JOIN volleyball_teams t ON p.team_id = t.id
                             WHERE t.division_id = $division_id
                             GROUP BY p.id, p.name, t.name
                             ORDER BY total_digs DESC";
            $result = $conn->query($sql_top_digs);
        }
        displayVolleyballLeaderboard($conn, $result, ["Rank", "Player", "Team", "Total Digs"], ["name", "team_name", "total_digs"], "total_digs");
        ?>

        <h3>Top Block Leaders</h3>
        <?php
        if ($current_season) {
            $sql_top_blocks = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.blocks) AS total_blocks
                               FROM volleyball_player_stats vps
                               JOIN volleyball_players p ON vps.player_id = p.id
                               JOIN volleyball_teams t ON p.team_id = t.id
                               JOIN volleyball_games g ON vps.game_id = g.id
                               WHERE t.division_id = ? AND g.season_id = ?
                               GROUP BY p.id, p.name, t.name
                               ORDER BY total_blocks DESC";
            $stmt = $conn->prepare($sql_top_blocks);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_top_blocks = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.blocks) AS total_blocks
                               FROM volleyball_player_stats vps
                               JOIN volleyball_players p ON vps.player_id = p.id
                               JOIN volleyball_teams t ON p.team_id = t.id
                               WHERE t.division_id = $division_id
                               GROUP BY p.id, p.name, t.name
                               ORDER BY total_blocks DESC";
            $result = $conn->query($sql_top_blocks);
        }
        displayVolleyballLeaderboard($conn, $result, ["Rank", "Player", "Team", "Total Blocks"], ["name", "team_name", "total_blocks"], "total_blocks");
        ?>

        <h3>Top Ace Leaders</h3>
        <?php
        if ($current_season) {
            $sql_top_aces = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.aces) AS total_aces
                             FROM volleyball_player_stats vps
                             JOIN volleyball_players p ON vps.player_id = p.id
                             JOIN volleyball_teams t ON p.team_id = t.id
                             JOIN volleyball_games g ON vps.game_id = g.id
                             WHERE t.division_id = ? AND g.season_id = ?
                             GROUP BY p.id, p.name, t.name
                             ORDER BY total_aces DESC";
            $stmt = $conn->prepare($sql_top_aces);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_top_aces = "SELECT p.id, p.name, t.name AS team_name, SUM(vps.aces) AS total_aces
                             FROM volleyball_player_stats vps
                             JOIN volleyball_players p ON vps.player_id = p.id
                             JOIN volleyball_teams t ON p.team_id = t.id
                             WHERE t.division_id = $division_id
                             GROUP BY p.id, p.name, t.name
                             ORDER BY total_aces DESC";
            $result = $conn->query($sql_top_aces);
        }
        displayVolleyballLeaderboard($conn, $result, ["Rank", "Player", "Team", "Total Aces"], ["name", "team_name", "total_aces"], "total_aces");
        ?>

        <h3>Best Kill Percentage (Min. 20 Attempts)</h3>
        <?php
        if ($current_season) {
            $sql_kill_percentage = "SELECT p.id, p.name, t.name AS team_name, 
                                          SUM(vps.kills) AS total_kills, 
                                          SUM(vps.kills + vps.attack_errors) AS total_attempts, 
                                          (SUM(vps.kills) / SUM(vps.kills + vps.attack_errors)) * 100 AS kill_percentage
                                   FROM volleyball_player_stats vps
                                   JOIN volleyball_players p ON vps.player_id = p.id
                                   JOIN volleyball_teams t ON p.team_id = t.id
                                   JOIN volleyball_games g ON vps.game_id = g.id
                                   WHERE t.division_id = ? AND g.season_id = ?
                                   GROUP BY p.id, p.name, t.name
                                   HAVING total_attempts >= 20
                                   ORDER BY kill_percentage DESC";
            $stmt = $conn->prepare($sql_kill_percentage);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_kill_percentage = "SELECT p.id, p.name, t.name AS team_name, 
                                          SUM(vps.kills) AS total_kills, 
                                          SUM(vps.kills + vps.attack_errors) AS total_attempts, 
                                          (SUM(vps.kills) / SUM(vps.kills + vps.attack_errors)) * 100 AS kill_percentage
                                   FROM volleyball_player_stats vps
                                   JOIN volleyball_players p ON vps.player_id = p.id
                                   JOIN volleyball_teams t ON p.team_id = t.id
                                   WHERE t.division_id = $division_id
                                   GROUP BY p.id, p.name, t.name
                                   HAVING total_attempts >= 20
                                   ORDER BY kill_percentage DESC";
            $result = $conn->query($sql_kill_percentage);
        }
        displayVolleyballLeaderboard($conn, $result, ["Rank", "Player", "Team", "Kills", "Attempts", "Kill %"], ["name", "team_name", "total_kills", "total_attempts", "kill_percentage"], "kill_percentage");
        ?>

        <h3>Best Service Percentage (Min. 10 Serves)</h3>
        <?php
        if ($current_season) {
            $sql_service_percentage = "SELECT p.id, p.name, t.name AS team_name, 
                                             SUM(vps.aces) AS total_aces, 
                                             SUM(vps.aces + vps.service_errors) AS total_serves, 
                                             (SUM(vps.aces) / SUM(vps.aces + vps.service_errors)) * 100 AS service_percentage
                                      FROM volleyball_player_stats vps
                                      JOIN volleyball_players p ON vps.player_id = p.id
                                      JOIN volleyball_teams t ON p.team_id = t.id
                                      JOIN volleyball_games g ON vps.game_id = g.id
                                      WHERE t.division_id = ? AND g.season_id = ?
                                      GROUP BY p.id, p.name, t.name
                                      HAVING total_serves >= 10
                                      ORDER BY service_percentage DESC";
            $stmt = $conn->prepare($sql_service_percentage);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_service_percentage = "SELECT p.id, p.name, t.name AS team_name, 
                                             SUM(vps.aces) AS total_aces, 
                                             SUM(vps.aces + vps.service_errors) AS total_serves, 
                                             (SUM(vps.aces) / SUM(vps.aces + vps.service_errors)) * 100 AS service_percentage
                                      FROM volleyball_player_stats vps
                                      JOIN volleyball_players p ON vps.player_id = p.id
                                      JOIN volleyball_teams t ON p.team_id = t.id
                                      WHERE t.division_id = $division_id
                                      GROUP BY p.id, p.name, t.name
                                      HAVING total_serves >= 10
                                      ORDER BY service_percentage DESC";
            $result = $conn->query($sql_service_percentage);
        }
        displayVolleyballLeaderboard($conn, $result, ["Rank", "Player", "Team", "Aces", "Total Serves", "Service %"], ["name", "team_name", "total_aces", "total_serves", "service_percentage"], "service_percentage");
        ?>

        <hr>
        <?php endwhile; ?>
    </div>
</body>
</html>
