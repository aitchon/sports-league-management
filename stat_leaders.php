<?php
include 'admin/db.php';

// Get current season
$current_season = getCurrentSeason($conn);

// Fetch divisions for the dropdown (filtered by current season, excluding divisions with only playoff placeholders)
if ($current_season) {
    $divisions_stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name 
        FROM divisions d 
        JOIN teams t ON d.id = t.division_id 
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
        FROM divisions d 
        JOIN teams t ON d.id = t.division_id 
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
        FROM divisions d 
        JOIN teams t ON d.id = t.division_id 
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
        FROM divisions d 
        JOIN teams t ON d.id = t.division_id 
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
    <title>Stat Leaders</title>
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
        <h1 class="text-primary">Stat Leaders by Division</h1>
        
        <?php if ($current_season): ?>
            <div class="alert alert-info">
                <p><strong>Current Season:</strong> <?php echo htmlspecialchars($current_season['name']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="mb-4 text-center">
            <a href="standings.php" class="btn btn-outline-primary me-2">Current Standings</a>
            <a href="schedule.php" class="btn btn-outline-primary me-2">Current Schedule</a>
            <a href="history.php" class="btn btn-outline-secondary me-2">League History</a>
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
                FROM divisions d 
                JOIN teams t ON d.id = t.division_id 
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
                FROM divisions d 
                JOIN teams t ON d.id = t.division_id 
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
        function displayLeaderboard($conn, $result, $headers, $fields, $orderField) {
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
                    echo '<td>' . htmlspecialchars($row[$field]) . '</td>';
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

        <h3>Top Scorers</h3>
        <?php
        if ($current_season) {
            $sql_top_scorers = "SELECT p.id, p.name, t.name AS team_name, SUM(ps.points) AS total_points
                                FROM player_stats ps
                                JOIN players p ON ps.player_id = p.id
                                JOIN teams t ON p.team_id = t.id
                                JOIN games g ON ps.game_id = g.id
                                WHERE t.division_id = ? AND g.season_id = ?
                                GROUP BY p.id, p.name, t.name
                                ORDER BY total_points DESC";
            $stmt = $conn->prepare($sql_top_scorers);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_top_scorers = "SELECT p.id, p.name, t.name AS team_name, SUM(ps.points) AS total_points
                                FROM player_stats ps
                                JOIN players p ON ps.player_id = p.id
                                JOIN teams t ON p.team_id = t.id
                                WHERE t.division_id = $division_id
                                GROUP BY p.id, p.name, t.name
                                ORDER BY total_points DESC";
            $result = $conn->query($sql_top_scorers);
        }
        displayLeaderboard($conn, $result, ["Rank", "Player", "Team", "Total Points"], ["name", "team_name", "total_points"], "total_points");
        ?>

        <h3>Top Free Throw Percentage (Min. 5 Attempts)</h3>
        <?php
        if ($current_season) {
            $sql_top_ft = "SELECT p.id, p.name, t.name AS team_name, 
                                  SUM(ps.free_throws_made) AS total_made, 
                                  SUM(ps.free_throws_attempted) AS total_attempted, 
                                  (SUM(ps.free_throws_made) / SUM(ps.free_throws_attempted)) * 100 AS ft_percentage
                           FROM player_stats ps
                           JOIN players p ON ps.player_id = p.id
                           JOIN teams t ON p.team_id = t.id
                           JOIN games g ON ps.game_id = g.id
                           WHERE t.division_id = ? AND g.season_id = ?
                           GROUP BY p.id, p.name, t.name
                           HAVING total_attempted >= 5
                           ORDER BY ft_percentage DESC";
            $stmt = $conn->prepare($sql_top_ft);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_top_ft = "SELECT p.id, p.name, t.name AS team_name, 
                                  SUM(ps.free_throws_made) AS total_made, 
                                  SUM(ps.free_throws_attempted) AS total_attempted, 
                                  (SUM(ps.free_throws_made) / SUM(ps.free_throws_attempted)) * 100 AS ft_percentage
                           FROM player_stats ps
                           JOIN players p ON ps.player_id = p.id
                           JOIN teams t ON p.team_id = t.id
                           WHERE t.division_id = $division_id
                           GROUP BY p.id, p.name, t.name
                           HAVING total_attempted >= 5
                           ORDER BY ft_percentage DESC";
            $result = $conn->query($sql_top_ft);
        }
        displayLeaderboard($conn, $result, ["Rank", "Player", "Team", "FT Made", "FT Attempted", "FT%"], ["name", "team_name", "total_made", "total_attempted", "ft_percentage"], "ft_percentage");
        ?>

        <h3>Top Three-Pointers Made</h3>
        <?php
        if ($current_season) {
            $sql_top_threes = "SELECT p.id, p.name, t.name AS team_name, SUM(ps.three_pointers_made) AS total_threes
                               FROM player_stats ps
                               JOIN players p ON ps.player_id = p.id
                               JOIN teams t ON p.team_id = t.id
                               JOIN games g ON ps.game_id = g.id
                               WHERE t.division_id = ? AND g.season_id = ?
                               GROUP BY p.id, p.name, t.name
                               ORDER BY total_threes DESC";
            $stmt = $conn->prepare($sql_top_threes);
            $stmt->bind_param("ii", $division_id, $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
        } else {
            $sql_top_threes = "SELECT p.id, p.name, t.name AS team_name, SUM(ps.three_pointers_made) AS total_threes
                               FROM player_stats ps
                               JOIN players p ON ps.player_id = p.id
                               JOIN teams t ON p.team_id = t.id
                               WHERE t.division_id = $division_id
                               GROUP BY p.id, p.name, t.name
                               ORDER BY total_threes DESC";
            $result = $conn->query($sql_top_threes);
        }
        displayLeaderboard($conn, $result, ["Rank", "Player", "Team", "Three-Pointers Made"], ["name", "team_name", "total_threes"], "total_threes");
        ?>

        <hr>
        <?php endwhile; ?>
    </div>
</body>
</html>
