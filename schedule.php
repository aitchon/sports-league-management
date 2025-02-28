<?php
include 'admin/db.php';

// Fetch all unique divisions
$divisions_query = "SELECT DISTINCT d.id, d.name FROM divisions d JOIN teams t ON d.id = t.division_id WHERE d.is_playoff=0";
$divisions_result = $conn->query($divisions_query);
$divisions = [];
while ($row = $divisions_result->fetch_assoc()) {
    $divisions[] = $row;
}

// Fetch all unique game dates for the dropdown (including past dates), ordered ascending
$dates_query = "SELECT DISTINCT DATE(date) AS game_date FROM games ORDER BY game_date ASC"; // Ascending order
$dates_result = $conn->query($dates_query);
$dates = [];
while ($row = $dates_result->fetch_assoc()) {
    $dates[] = $row['game_date'];
}

// Fetch schedule data (only future games for the schedule table)
$selectedDate = isset($_GET['date']) ? $_GET['date'] : null;
$query = "
    SELECT g.id, DATE(g.date) AS game_date, TIME(g.date) AS start_time, 
           g.location, g.is_playoff,
           t1.name AS home_team, t2.name AS away_team, 
           g.status, s.home_team_score, s.away_team_score, 
           d.name AS division_name, d.id AS division_id,
           t1.id as home_team_id, t2.id as away_team_id
    FROM games g
    JOIN teams t1 ON g.home_team_id = t1.id
    JOIN teams t2 ON g.away_team_id = t2.id
    LEFT JOIN scores s ON g.id = s.game_id
    JOIN divisions d ON t1.division_id = d.id OR t2.division_id = d.id
    WHERE DATE(g.date) >= CURDATE() " . 
    ($selectedDate ? " OR DATE(g.date) = '$selectedDate'" : "") . "
    ORDER BY game_date, g.location, start_time
";

$schedule = $conn->query($query);

// Organize data by date and location
$grouped_schedule = [];
if ($schedule) {
    while ($game = $schedule->fetch_assoc()) {
        $date = $game['game_date'];
        $location = $game['location'];
        $grouped_schedule[$date][$location][] = $game;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule</title>
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
        <div class="mb-4">
            <a href="https://81a070c1-965e-4148-b43e-9fe7adcc5672.usrfiles.com/ugd/81a070_9bbd19b4a25043dd83fcf960f6f12b03.pdf" class="download-button" download>Download Schedule as PDF</a>
        </div>

        <h1 class="mb-3">2025 Spring Basketball League</h1>

        <!-- Filter Dropdowns -->
        <div class="filter-container mb-3">
            <!-- Add Date Filter Dropdown -->
            <div class="filter-container mb-3">
                <label for="dateFilter" class="fw-bold">Select Date:</label>
                <select id="dateFilter" class="form-select" style="max-width: 300px;">
                    <option value="all">All Dates</option>
                    <?php 
                    foreach ($dates as $date): // Loop through all dates for the dropdown
                    ?>
                        <option value="<?php echo htmlspecialchars($date); ?>" <?php echo ($selectedDate == $date ? 'selected' : ''); ?>>
                            <?php echo date('l, F j, Y', strtotime($date)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="divisionFilter" class="fw-bold">Select Division:</label>
                <select id="divisionFilter" class="form-select" style="max-width: 300px;">
                    <option value="all">All Divisions</option>
                    <?php foreach ($divisions as $division): ?>
                        <option value="<?php echo htmlspecialchars($division['id']); ?>">
                            <?php echo htmlspecialchars($division['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Schedule Table (Initially Empty) -->
        <div id="scheduleTable">
            <?php if (!empty($grouped_schedule)): ?>
                <!-- Schedule Display -->
                <?php foreach ($grouped_schedule as $date => $locations): ?>
                    <?php 
                        // Check if any games have scores for this date
                        $hasScores = false;
                        foreach ($locations as $location => $games) {
                            foreach ($games as $game) {
                                if (!is_null($game['home_team_score']) && !is_null($game['away_team_score'])) {
                                    $hasScores = true;
                                    break 2; // Exit both loops
                                }
                            }
                        }
                    ?>

                    <div class="schedule-group" data-date="<?php echo htmlspecialchars($date); ?>">
                        <h2 class="mt-4 text-primary"><?php echo date('l, F j, Y', strtotime($date)); ?></h2>
                        
                        <?php foreach ($locations as $location => $games): ?>
                            <h4 class="mt-3 text-secondary">Location: <?php echo htmlspecialchars($location); ?></h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Time</th>
                                            <th>Home Team</th>
                                            <?php if ($hasScores): ?> <th>Score</th> <?php endif; ?>
                                            <th>Division</th>
                                            <?php if ($hasScores): ?> <th>Score</th> <?php endif; ?>
                                            <th>Visiting Team</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($games as $game): ?>
                                            <tr class="game-row" 
                                                data-division="<?php echo htmlspecialchars($game['division_id']); ?>" 
                                                data-date="<?php echo htmlspecialchars($game['game_date']); ?>">
                                                <td><?php echo date('g:i A', strtotime($game['start_time'])); ?></td>
                                                <td>
                                                    <?php if (($game['division_name'] != 'PRACTICE') && ($game['is_playoff'] != 1)) : ?>
                                                        <a href="team_details.php?team_id=<?php echo $game['home_team_id']; ?>" class="btn btn-link">
                                                            <?php echo htmlspecialchars($game['home_team']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($game['home_team']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($hasScores): ?>
                                                    <td>
                                                        <?php if (!is_null($game['home_team_score']) && !is_null($game['away_team_score'])): ?>
                                                            <a href="game_details.php?game_id=<?php echo $game['id']; ?>">
                                                                <?php echo htmlspecialchars($game['home_team_score']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td><?php echo htmlspecialchars($game['division_name']); ?></td>
                                                <?php if ($hasScores): ?>
                                                    <td>
                                                        <?php if (!is_null($game['home_team_score']) && !is_null($game['away_team_score'])): ?>
                                                            <a href="game_details.php?game_id=<?php echo $game['id']; ?>">
                                                                <?php echo htmlspecialchars($game['away_team_score']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <?php if (($game['division_name'] != 'PRACTICE') && ($game['is_playoff'] != 1)) : ?>
                                                        <a href="team_details.php?team_id=<?php echo $game['away_team_id']; ?>" class="btn btn-link">
                                                            <?php echo htmlspecialchars($game['away_team']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($game['away_team']); ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-danger">No games scheduled.</p>
            <?php endif; ?>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // When the user changes the date filter
        $('#dateFilter').on('change', function() {
            var selectedDate = $(this).val();
            var selectedDivision = $('#divisionFilter').val();
            
            // Get the filtered schedule via AJAX
            $.ajax({
                url: 'fetch_schedule.php',  // A separate PHP file to fetch the filtered schedule
                method: 'GET',
                data: {date: selectedDate, division: selectedDivision},
                success: function(data) {
                    $('#scheduleTable').html(data);  // Update the table with the new data
                }
            });
        });

        // When the user changes the division filter
        $('#divisionFilter').on('change', function() {
            var selectedDate = $('#dateFilter').val();
            var selectedDivision = $(this).val();

            // Get the filtered schedule via AJAX
            $.ajax({
                url: 'fetch_schedule.php',
                method: 'GET',
                data: {date: selectedDate, division: selectedDivision},
                success: function(data) {
                    $('#scheduleTable').html(data);
                }
            });
        });
    });
</script>
</body>
</html>
