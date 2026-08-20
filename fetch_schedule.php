<?php
include 'admin/db.php';

// Get parameters from AJAX request
$selectedDate = isset($_GET['date']) ? $_GET['date'] : null;
$selectedDivision = isset($_GET['division']) ? $_GET['division'] : null;

// Get current season and check for auto-selection
$current_season = getCurrentSeason($conn);

// If no division is selected and there's only one division available, auto-select it
if (!$selectedDivision && $current_season) {
    $divisions_query = "SELECT DISTINCT d.id, d.name FROM divisions d 
                       JOIN teams t ON d.id = t.division_id 
                       WHERE d.is_playoff=0 AND d.season_id = ? AND t.is_playoff_placeholder = 0";
    $divisions_stmt = $conn->prepare($divisions_query);
    $divisions_stmt->bind_param("i", $current_season['id']);
    $divisions_stmt->execute();
    $divisions_result = $divisions_stmt->get_result();
    $divisions = [];
    while ($row = $divisions_result->fetch_assoc()) {
        $divisions[] = $row;
    }
    $divisions_stmt->close();
    
    // If there's exactly one division, auto-select it
    if (count($divisions) == 1) {
        $selectedDivision = $divisions[0]['id'];
    }
}

// Fetch the filtered schedule based on date and division
if ($current_season) {
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
        WHERE g.season_id = ?" . 
        ($selectedDate && $selectedDate != 'all' ? " AND DATE(g.date) = '$selectedDate'" : "") . 
        ($selectedDivision && $selectedDivision != 'all' ? " AND (t1.division_id = '$selectedDivision' OR t2.division_id = '$selectedDivision')" : "") . "
        ORDER BY game_date, g.location, start_time
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $schedule = $stmt->get_result();
    $stmt->close();
} else {
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
        WHERE 1=1" . 
        ($selectedDate && $selectedDate != 'all' ? " AND DATE(g.date) = '$selectedDate'" : "") . 
        ($selectedDivision && $selectedDivision != 'all' ? " AND (t1.division_id = '$selectedDivision' OR t2.division_id = '$selectedDivision')" : "") . "
        ORDER BY game_date, g.location, start_time
    ";
    $schedule = $conn->query($query);
}

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

<?php if (!empty($grouped_schedule)): ?>
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
