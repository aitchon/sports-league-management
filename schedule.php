<?php
include 'admin/db.php';

// Fetch all unique divisions
$divisions_query = "SELECT DISTINCT d.id, d.name FROM divisions d JOIN teams t ON d.id = t.division_id";
$divisions_result = $conn->query($divisions_query);
$divisions = [];
while ($row = $divisions_result->fetch_assoc()) {
    $divisions[] = $row;
}

// Fetch all unique game dates
$dates_query = "SELECT DISTINCT DATE(date) AS game_date FROM games ORDER BY game_date";
$dates_result = $conn->query($dates_query);
$dates = [];
while ($row = $dates_result->fetch_assoc()) {
    $dates[] = $row['game_date'];
}

// Fetch schedule data
$query = "
    SELECT g.id, DATE(g.date) AS game_date, TIME(g.date) AS start_time, 
           g.location, 
           t1.name AS home_team, t2.name AS away_team, 
           g.status, s.home_team_score, s.away_team_score, 
           d.name AS division_name, d.id AS division_id,
           t1.id as home_team_id, t2.id as away_team_id
    FROM games g
    JOIN teams t1 ON g.home_team_id = t1.id
    JOIN teams t2 ON g.away_team_id = t2.id
    LEFT JOIN scores s ON g.id = s.game_id
    JOIN divisions d ON t1.division_id = d.id OR t2.division_id = d.id
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
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="mb-4">
            <a href="https://81a070c1-965e-4148-b43e-9fe7adcc5672.usrfiles.com/ugd/81a070_9bbd19b4a25043dd83fcf960f6f12b03.pdf" class="btn btn-primary" download>Download Schedule as PDF</a>
        </div>

        <h1 class="mb-3">2025 Spring Basketball League</h1>

        <!-- Filter Dropdowns -->
        <div class="filter-container mb-3">
            <!-- Add Date Filter Dropdown -->
            <div class="filter-container mb-3">
                <label for="dateFilter" class="fw-bold">Select Date:</label>
                <select id="dateFilter" class="form-select" style="max-width: 300px;">
                    <option value="all">All Dates</option>
                    <?php foreach (array_keys($grouped_schedule) as $date): ?>
                        <option value="<?php echo htmlspecialchars($date); ?>">
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

        <?php if (!empty($grouped_schedule)): ?>
            <!-- Schedule Display -->
            <?php foreach ($grouped_schedule as $date => $locations): ?>
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
                                        <th>Score</th>
                                        <th>Division</th>
                                        <th>Score</th>
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
                                                <?php if ($game['division_name'] != 'PRACTICE'): ?>
                                                    <a href="team_details.php?team_id=<?php echo $game['home_team_id']; ?>" class="btn btn-link">
                                                        <?php echo htmlspecialchars($game['home_team']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($game['home_team']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo isset($game['home_team_score']) ? $game['home_team_score'] : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($game['division_name']); ?></td>
                                            <td><?php echo isset($game['away_team_score']) ? $game['away_team_score'] : '-'; ?></td>
                                            <td>
                                                <?php if ($game['division_name'] != 'PRACTICE'): ?>
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

<script>
    document.getElementById('divisionFilter').addEventListener('change', filterGames);
    document.getElementById('dateFilter').addEventListener('change', filterGames);

    function filterGames() {
        let selectedDivision = document.getElementById('divisionFilter').value;
        let selectedDate = document.getElementById('dateFilter').value;

        // Loop through each date section
        document.querySelectorAll('h2.text-primary').forEach(dateHeader => {
            let dateText = dateHeader.textContent.trim();
            let formattedDate = new Date(dateText).toISOString().split('T')[0]; // Convert text to YYYY-MM-DD

            let shouldShowDate = selectedDate === 'all' || formattedDate === selectedDate;
            let locationSection = dateHeader.nextElementSibling; // First location after the date header
            let hasVisibleGames = false;

            // Loop through location sections under this date
            while (locationSection && locationSection.tagName !== 'H2') {
                if (locationSection.tagName === 'H4') { // Location header
                    let tableContainer = locationSection.nextElementSibling; // The table container
                    if (tableContainer && tableContainer.classList.contains('table-responsive')) {
                        let table = tableContainer.querySelector('table');
                        let rows = table.querySelectorAll('.game-row');
                        let hasMatchingGames = false;

                        // Filter games by division
                        rows.forEach(row => {
                            let gameDivision = row.getAttribute('data-division');
                            if ((selectedDivision === 'all' || gameDivision === selectedDivision) && shouldShowDate) {
                                row.style.display = '';
                                hasMatchingGames = true;
                            } else {
                                row.style.display = 'none';
                            }
                        });

                        // Show or hide table and location header
                        tableContainer.style.display = hasMatchingGames ? '' : 'none';
                        locationSection.style.display = hasMatchingGames ? '' : 'none';
                        hasVisibleGames = hasVisibleGames || hasMatchingGames;
                    }
                }
                locationSection = locationSection.nextElementSibling;
            }

            // Show or hide the entire date section
            dateHeader.style.display = hasVisibleGames ? '' : 'none';
        });
    }
</script>
</body>
</html>
