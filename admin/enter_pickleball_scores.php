<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Configuration for variable match calculations
$match_config = [
    'round_robin' => [
        'min_players' => 4,
        'max_players' => 7,
        'description' => 'Each player plays in multiple matches'
    ],
    'tournament' => [
        'min_players' => 4,
        'max_players' => 8,
        'description' => 'Elimination-style matches'
    ],
    'friendly' => [
        'min_players' => 4,
        'max_players' => 7,
        'description' => 'Flexible number of matches'
    ]
];

// Function to calculate matches dynamically
function calculateMatches($player_count, $game_type, $config) {
    if ($player_count < 4) {
        return 0; // Not enough players for doubles
    }
    
    switch ($game_type) {
        case 'round_robin':
            return $player_count; // Each player gets a match
        case 'tournament':
            return max(3, ceil($player_count / 2)); // Fewer matches, elimination style
        case 'friendly':
            return max(3, min($player_count, 5)); // 3-5 matches max
        default:
            return $player_count; // Default: one match per player
    }
}

// Function to update ladder positions for a season
function updateLadderPositions($conn, $season_id) {
    try {
        // Get all players for the season with their divisions
        $stmt = $conn->prepare("
            SELECT p.id, p.name, p.division_id
            FROM pickleball_players p
            WHERE p.season_id = ? AND p.is_active = 1
            ORDER BY p.division_id, p.name
        ");
        $stmt->bind_param("i", $season_id);
        $stmt->execute();
        $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($players)) {
            return "No players found for season $season_id";
        }
        
        // Group players by division
        $players_by_division = [];
        foreach ($players as $player) {
            $division_id = $player['division_id'] ?: 0; // Use 0 for players without division
            if (!isset($players_by_division[$division_id])) {
                $players_by_division[$division_id] = [];
            }
            $players_by_division[$division_id][] = $player;
        }
        
        // Calculate stats for each player within their division
        $all_player_stats = [];
        foreach ($players_by_division as $division_id => $division_players) {
            $player_stats = [];
            foreach ($division_players as $player) {
                $player_id = $player['id'];
                
                // Get all games this player participated in - use pg.* to get all match columns
                $stmt = $conn->prepare("
                    SELECT pg.*
                    FROM pickleball_games pg
                    WHERE pg.season_id = ? 
                    AND pg.status = 'completed'
                    AND (pg.player1_id = ? OR pg.player2_id = ? OR pg.player3_id = ? OR pg.player4_id = ? 
                         OR pg.player5_id = ? OR pg.player6_id = ? OR pg.player7_id = ?)
                    ORDER BY pg.date
                ");
                $stmt->bind_param("iiiiiiii", $season_id, $player_id, $player_id, $player_id, $player_id, $player_id, $player_id, $player_id);
                $stmt->execute();
                $games_result = $stmt->get_result();
                $games = [];
                while ($row = $games_result->fetch_assoc()) {
                    $games[] = $row;
                }
                $stmt->close();
                
                $wins = 0;
                $losses = 0;
                $points_scored = 0;
                $points_against = 0;
                
                // Process each game and all its matches
                foreach ($games as $game) {
                    $game_id = $game['id'];
                    
                    // Get all match assignments for this game
                    $assignments_stmt = $conn->prepare("
                        SELECT match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id
                        FROM pickleball_match_assignments
                        WHERE game_id = ?
                        ORDER BY match_number
                    ");
                    $assignments_stmt->bind_param("i", $game_id);
                    $assignments_stmt->execute();
                    $assignments_result = $assignments_stmt->get_result();
                    $assignments = [];
                    while ($assignment = $assignments_result->fetch_assoc()) {
                        $assignments[$assignment['match_number']] = $assignment;
                    }
                    $assignments_stmt->close();
                    
                    // Find all matches with scores (check all possible match columns dynamically)
                    $matches_with_scores = [];
                    foreach ($game as $key => $value) {
                        if (preg_match('/^match(\d+)_team[12]_score$/', $key, $matches)) {
                            $match_num = (int)$matches[1];
                            if (!isset($matches_with_scores[$match_num])) {
                                $matches_with_scores[$match_num] = [
                                    'team1_score' => $game["match{$match_num}_team1_score"] ?? null,
                                    'team2_score' => $game["match{$match_num}_team2_score"] ?? null
                                ];
                            }
                        }
                    }
                    
                    // Process matches where player was assigned AND has scores
                    foreach ($assignments as $match_num => $assignment) {
                        // Get scores for this match number
                        $team1_score = null;
                        $team2_score = null;
                        
                        if (isset($matches_with_scores[$match_num])) {
                            $team1_score = $matches_with_scores[$match_num]['team1_score'];
                            $team2_score = $matches_with_scores[$match_num]['team2_score'];
                        } else {
                            // Fallback: try direct access
                            $team1_score = $game["match{$match_num}_team1_score"] ?? null;
                            $team2_score = $game["match{$match_num}_team2_score"] ?? null;
                        }
                        
                        // Handle NULL values that might be 0 (legacy data issue)
                        // If one score is NULL and the other is > 0, treat NULL as 0
                        if ($team1_score === null && ($team2_score !== null && $team2_score > 0)) {
                            $team1_score = 0;
                        }
                        if ($team2_score === null && ($team1_score !== null && $team1_score > 0)) {
                            $team2_score = 0;
                        }
                        
                        // Skip if no final scores - both must be set (or inferred as 0) and not both zero
                        // Valid scores include: 11-0, 0-11, 11-9, etc. (one team can have 0)
                        if ($team1_score === null || $team2_score === null) {
                            continue; // Missing scores (both null or both can't be inferred)
                        }
                        if ($team1_score == 0 && $team2_score == 0) {
                            continue; // Both zero means not entered
                        }
                        // If we get here, at least one score is > 0, which is valid
                        
                        // Determine which team the player was on using match assignments
                        $player_on_team1 = false;
                        $player_on_team2 = false;
                        
                        if ($assignment['team1_player1_id'] == $player_id || $assignment['team1_player2_id'] == $player_id) {
                            $player_on_team1 = true;
                        } elseif ($assignment['team2_player1_id'] == $player_id || $assignment['team2_player2_id'] == $player_id) {
                            $player_on_team2 = true;
                        }
                        
                        // Only count if player was assigned to this match
                        if ($player_on_team1 || $player_on_team2) {
                            // Convert to integers to ensure proper comparison (0 is valid)
                            $team1_score_int = (int)$team1_score;
                            $team2_score_int = (int)$team2_score;
                            
                            if ($player_on_team1) {
                                $points_scored += $team1_score_int;
                                $points_against += $team2_score_int;
                                if ($team1_score_int > $team2_score_int) {
                                    $wins++;
                                } elseif ($team2_score_int > $team1_score_int) {
                                    $losses++;
                                }
                            } elseif ($player_on_team2) {
                                $points_scored += $team2_score_int;
                                $points_against += $team1_score_int;
                                if ($team2_score_int > $team1_score_int) {
                                    $wins++;
                                } elseif ($team1_score_int > $team2_score_int) {
                                    $losses++;
                                }
                            }
                        }
                    }
                }
                
                $total_matches = $wins + $losses;
                $win_percentage = $total_matches > 0 ? ($wins / $total_matches) * 100 : 0;
                $point_differential = $points_scored - $points_against;
                
                $player_stats[] = [
                    'player_id' => $player_id,
                    'name' => $player['name'],
                    'division_id' => $division_id,
                    'wins' => $wins,
                    'losses' => $losses,
                    'points_scored' => $points_scored,
                    'points_against' => $points_against,
                    'win_percentage' => $win_percentage,
                    'point_differential' => $point_differential
                ];
            }
            
            usort($player_stats, function ($a, $b) {
                $aQualified = ($a['wins'] + $a['losses']) >= 8;
                $bQualified = ($b['wins'] + $b['losses']) >= 8;
                if ($aQualified !== $bQualified) {
                    return $aQualified ? -1 : 1;
                }
                return pickleballRankCompare($a, $b);
            });
            
            // Assign positions within this division
            foreach ($player_stats as $index => $stats) {
                $stats['position'] = $index + 1;
                $all_player_stats[] = $stats;
            }
        }
        
        // Update ladder positions for all players
        foreach ($all_player_stats as $stats) {
            $stmt = $conn->prepare("
                INSERT INTO pickleball_ladder_positions 
                (player_id, season_id, position, wins, losses, points_scored, points_against, win_percentage, point_differential)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                position = VALUES(position),
                wins = VALUES(wins),
                losses = VALUES(losses),
                points_scored = VALUES(points_scored),
                points_against = VALUES(points_against),
                win_percentage = VALUES(win_percentage),
                point_differential = VALUES(point_differential),
                last_updated = NOW()
            ");
            $stmt->bind_param("iiiiiiidd", 
                $stats['player_id'], $season_id, $stats['position'], $stats['wins'], $stats['losses'],
                $stats['points_scored'], $stats['points_against'], $stats['win_percentage'], $stats['point_differential']
            );
            $stmt->execute();
            $stmt->close();
        }
        
        return "Ladder positions updated successfully for " . count($all_player_stats) . " players across " . count($players_by_division) . " divisions";
        
    } catch (Exception $e) {
        return "Error updating ladder positions: " . $e->getMessage();
    }
}

$message = '';

// Handle score submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_scores'])) {
    $game_id = (int)$_POST['game_id'];
    
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    try {
        // Get the game details (allow all statuses for editing)
        error_log("Looking for game ID: $game_id");
        $stmt = $conn->prepare("
            SELECT * FROM pickleball_games 
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare game query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $game_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute game query: " . $stmt->error);
        }
        
        $game = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$game) {
            throw new Exception("Game not found with ID: $game_id");
        }
        
        error_log("Found game: " . print_r($game, true));
        
        // Process match assignments if provided
        error_log("All POST data: " . print_r($_POST, true));
        if (isset($_POST['match_assignments'])) {
            $match_assignments = $_POST['match_assignments'];
            error_log("Match assignments from POST: " . print_r($match_assignments, true));
            
            // Clear existing match assignments for this game
            $stmt = $conn->prepare("DELETE FROM pickleball_match_assignments WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();
            
            // Insert new match assignments (only if all players are selected)
            foreach ($match_assignments as $match_num => $assignment) {
                // Only insert if all 4 players are selected (making assignments optional)
                if (!empty($assignment['team1_player1_id']) && !empty($assignment['team1_player2_id']) && 
                    !empty($assignment['team2_player1_id']) && !empty($assignment['team2_player2_id'])) {
                    
                    error_log("Inserting match assignment for match $match_num: " . print_r($assignment, true));
                    
                    $stmt = $conn->prepare("
                        INSERT INTO pickleball_match_assignments 
                        (game_id, match_number, team1_player1_id, team1_player2_id, team2_player1_id, team2_player2_id)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiiiii", 
                        $game_id, $match_num,
                        $assignment['team1_player1_id'], $assignment['team1_player2_id'],
                        $assignment['team2_player1_id'], $assignment['team2_player2_id']
                    );
                    $stmt->execute();
                    $stmt->close();
                } else {
                    error_log("Skipping match assignment for match $match_num - not all players selected");
                }
            }
        }
        
        // Process the match scores - store in the main games table (make scores optional)
        // Use isset to allow 0 as valid score (not empty() which treats 0 as empty)
        $match1_team1_score = isset($_POST['match1_team1_score']) && $_POST['match1_team1_score'] !== '' ? (int)$_POST['match1_team1_score'] : null;
        $match1_team2_score = isset($_POST['match1_team2_score']) && $_POST['match1_team2_score'] !== '' ? (int)$_POST['match1_team2_score'] : null;
        $match2_team1_score = isset($_POST['match2_team1_score']) && $_POST['match2_team1_score'] !== '' ? (int)$_POST['match2_team1_score'] : null;
        $match2_team2_score = isset($_POST['match2_team2_score']) && $_POST['match2_team2_score'] !== '' ? (int)$_POST['match2_team2_score'] : null;
        $match3_team1_score = isset($_POST['match3_team1_score']) && $_POST['match3_team1_score'] !== '' ? (int)$_POST['match3_team1_score'] : null;
        $match3_team2_score = isset($_POST['match3_team2_score']) && $_POST['match3_team2_score'] !== '' ? (int)$_POST['match3_team2_score'] : null;
        $match4_team1_score = isset($_POST['match4_team1_score']) && $_POST['match4_team1_score'] !== '' ? (int)$_POST['match4_team1_score'] : null;
        $match4_team2_score = isset($_POST['match4_team2_score']) && $_POST['match4_team2_score'] !== '' ? (int)$_POST['match4_team2_score'] : null;
        $match5_team1_score = isset($_POST['match5_team1_score']) && $_POST['match5_team1_score'] !== '' ? (int)$_POST['match5_team1_score'] : null;
        $match5_team2_score = isset($_POST['match5_team2_score']) && $_POST['match5_team2_score'] !== '' ? (int)$_POST['match5_team2_score'] : null;
        $match6_team1_score = isset($_POST['match6_team1_score']) && $_POST['match6_team1_score'] !== '' ? (int)$_POST['match6_team1_score'] : null;
        $match6_team2_score = isset($_POST['match6_team2_score']) && $_POST['match6_team2_score'] !== '' ? (int)$_POST['match6_team2_score'] : null;
        $match7_team1_score = isset($_POST['match7_team1_score']) && $_POST['match7_team1_score'] !== '' ? (int)$_POST['match7_team1_score'] : null;
        $match7_team2_score = isset($_POST['match7_team2_score']) && $_POST['match7_team2_score'] !== '' ? (int)$_POST['match7_team2_score'] : null;
        
        error_log("Scores: M1T1=$match1_team1_score, M1T2=$match1_team2_score, M2T1=$match2_team1_score, M2T2=$match2_team2_score, M3T1=$match3_team1_score, M3T2=$match3_team2_score, M4T1=$match4_team1_score, M4T2=$match4_team2_score, M5T1=$match5_team1_score, M5T2=$match5_team2_score");
        
        // Check if the new columns exist first
        $result = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match1_team1_score'");
        if ($result->num_rows == 0) {
            throw new Exception("Required columns don't exist. Please run admin/add_doubles_match_columns.sql first.");
        }
        
        // Check if match5 columns exist
        $result5 = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match5_team1_score'");
        $has_match5_columns = $result5->num_rows > 0;
        error_log("Match5 columns exist: " . ($has_match5_columns ? 'YES' : 'NO'));
        
        // Check if match6 and match7 columns exist
        $result6 = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match6_team1_score'");
        $has_match6_columns = $result6->num_rows > 0;
        $result7 = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match7_team1_score'");
        $has_match7_columns = $result7->num_rows > 0;
        error_log("Match6 columns exist: " . ($has_match6_columns ? 'YES' : 'NO'));
        error_log("Match7 columns exist: " . ($has_match7_columns ? 'YES' : 'NO'));
        
        // Update the game with all match scores - dynamic approach
        error_log("Preparing UPDATE statement...");
        
        // Build dynamic SQL based on available columns
        $update_fields = [];
        $update_values = [];
        $param_types = "";
        
        // Check which match columns exist and build the update statement
        // Support unlimited matches by checking up to 50 matches
        for ($i = 1; $i <= 50; $i++) {
            $team1_col = "match{$i}_team1_score";
            $team2_col = "match{$i}_team2_score";
            
            // Check if these columns exist. Only update a match that was submitted.
            $result = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE '$team1_col'");
            if ($result->num_rows > 0 && (isset($_POST[$team1_col]) || isset($_POST[$team2_col]))) {
                $update_fields[] = "$team1_col = ?, $team2_col = ?";
                
                // Get the score values from POST data - use isset to allow 0 as valid score
                $team1_score = isset($_POST["match{$i}_team1_score"]) && $_POST["match{$i}_team1_score"] !== '' 
                    ? (int)$_POST["match{$i}_team1_score"] : null;
                $team2_score = isset($_POST["match{$i}_team2_score"]) && $_POST["match{$i}_team2_score"] !== '' 
                    ? (int)$_POST["match{$i}_team2_score"] : null;
                
                $update_values[] = $team1_score;
                $update_values[] = $team2_score;
                $param_types .= "ii";
            }
        }
        
        if (empty($update_fields)) {
            throw new Exception("No match score columns found in database");
        }
        
        // Build the complete UPDATE statement
        $sql = "UPDATE pickleball_games SET " . implode(", ", $update_fields) . ", status = 'completed', updated_at = NOW() WHERE id = ?";
        $update_values[] = $game_id;
        $param_types .= "i";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare UPDATE statement: " . $conn->error);
        }
        
        error_log("Binding parameters for " . count($update_values) . " values...");
        $stmt->bind_param($param_types, ...$update_values);
        
        error_log("Executing UPDATE...");
        if ($stmt->execute()) {
            // Update ladder positions after scores are submitted
            try {
                $ladder_message = updateLadderPositions($conn, $game['season_id']);
                error_log("Ladder positions updated: " . $ladder_message);
            } catch (Exception $e) {
                error_log("Ladder update failed: " . $e->getMessage());
                // Don't fail the whole operation if ladder update fails
            }
            
            // Determine if this was an update or new submission
            $was_completed = $game['status'] === 'completed';
            $action = $was_completed ? 'updated' : 'submitted';
            
            $message = '<div class="alert alert-success">Score ' . $action . ' successfully.<br><small>Ladder positions updated.</small></div>';
            error_log("UPDATE successful!");
        } else {
            throw new Exception("Failed to execute UPDATE statement: " . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Exception in score submission: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Get current season
$current_season = null;
$stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}
$stmt->close();

// Get unique dates where games exist (for date filter dropdown)
$available_dates = [];
if ($current_season) {
    $dates_stmt = $conn->prepare("
        SELECT DISTINCT DATE(pg.date) as game_date
        FROM pickleball_games pg
        WHERE pg.season_id = ? 
        AND pg.player1_id IS NOT NULL AND pg.player2_id IS NOT NULL 
        AND pg.player3_id IS NOT NULL AND pg.player4_id IS NOT NULL
        AND pg.location LIKE 'Court %'
        AND EXISTS (
            SELECT 1 FROM pickleball_match_assignments pma
            WHERE pma.game_id = pg.id
            AND pma.match_number = 1
            AND pma.team1_player1_id IS NOT NULL
            AND pma.team1_player2_id IS NOT NULL
            AND pma.team2_player1_id IS NOT NULL
            AND pma.team2_player2_id IS NOT NULL
        )
        ORDER BY game_date DESC
    ");
    $dates_stmt->bind_param("i", $current_season['id']);
    $dates_stmt->execute();
    $dates_result = $dates_stmt->get_result();
    while ($date_row = $dates_result->fetch_assoc()) {
        $available_dates[] = $date_row['game_date'];
    }
    $dates_stmt->close();
}

// A blank score is both sides empty or both zero. A 0 on one side is a real result.
$missing_score_sql = "((pg.match1_team1_score IS NULL OR pg.match1_team1_score = 0) AND (pg.match1_team2_score IS NULL OR pg.match1_team2_score = 0))";
$entered_score_sql = "NOT $missing_score_sql";

$selected_date = '';
if (isset($_GET['date_filter']) && $_GET['date_filter'] !== 'all' && $_GET['date_filter'] !== '' && in_array($_GET['date_filter'], $available_dates, true)) {
    $selected_date = $_GET['date_filter'];
}

// Opening a day shows that night's scores so an earlier result can be corrected.
$filter = $_GET['filter'] ?? ($selected_date !== '' ? 'all' : 'needs_scores');
if (!in_array($filter, ['needs_scores', 'has_scores', 'scheduled', 'completed', 'all'], true)) {
    $filter = $selected_date !== '' ? 'all' : 'needs_scores';
}

$score_counts = ['needs_scores' => 0, 'has_scores' => 0];
if ($current_season) {
    $count_stmt = $conn->prepare("
        SELECT
            SUM($missing_score_sql) AS needs_scores,
            SUM($entered_score_sql) AS has_scores
        FROM pickleball_games pg
        WHERE pg.season_id = ?
        AND pg.status IN ('scheduled', 'in_progress', 'completed')
        AND pg.player1_id IS NOT NULL AND pg.player2_id IS NOT NULL
        AND pg.player3_id IS NOT NULL AND pg.player4_id IS NOT NULL
        AND pg.location LIKE 'Court %'
        AND EXISTS (
            SELECT 1 FROM pickleball_match_assignments pma
            WHERE pma.game_id = pg.id
            AND pma.match_number = 1
            AND pma.team1_player1_id IS NOT NULL
            AND pma.team1_player2_id IS NOT NULL
            AND pma.team2_player1_id IS NOT NULL
            AND pma.team2_player2_id IS NOT NULL
        )
    ");
    $count_stmt->bind_param("i", $current_season['id']);
    $count_stmt->execute();
    $count_row = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();
    if ($count_row) {
        $score_counts['needs_scores'] = (int)$count_row['needs_scores'];
        $score_counts['has_scores'] = (int)$count_row['has_scores'];
    }
}

// Get games that need scores or can be edited (games with 4-5 players)
$games = [];
if ($current_season) {
    // Determine status filter
    $status_filter = "('scheduled', 'in_progress', 'completed')";
    $score_filter = "";
    
    switch ($filter) {
        case 'needs_scores':
            $status_filter = "('scheduled', 'in_progress', 'completed')";
            $score_filter = "AND $missing_score_sql";
            break;
        case 'has_scores':
            $status_filter = "('scheduled', 'in_progress', 'completed')";
            $score_filter = "AND $entered_score_sql";
            break;
        case 'scheduled':
            $status_filter = "('scheduled', 'in_progress')";
            break;
        case 'completed':
            $status_filter = "('completed')";
            break;
        case 'all':
        default:
            $status_filter = "('scheduled', 'in_progress', 'completed')";
            break;
    }
    
    // Date filter - a chosen day includes scores that were already entered.
    $date_filter = "";
    if ($selected_date !== '') {
        $date_filter = "AND DATE(pg.date) = ?";
    }
    
    $stmt = $conn->prepare("
        SELECT pg.*, p1.name as player1_name, p2.name as player2_name,
               p3.name as player3_name, p4.name as player4_name, p5.name as player5_name,
               p6.name as player6_name, p7.name as player7_name,
               d.name as division_name,
               CASE 
                   WHEN $missing_score_sql THEN 'needs_scores'
                   ELSE 'has_scores'
               END as score_status
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
        LEFT JOIN pickleball_players p5 ON pg.player5_id = p5.id
        LEFT JOIN pickleball_players p6 ON pg.player6_id = p6.id
        LEFT JOIN pickleball_players p7 ON pg.player7_id = p7.id
        LEFT JOIN pickleball_divisions d ON pg.division_id = d.id
        WHERE pg.season_id = ? AND pg.status IN $status_filter
        AND pg.player1_id IS NOT NULL AND pg.player2_id IS NOT NULL 
        AND pg.player3_id IS NOT NULL AND pg.player4_id IS NOT NULL
        AND pg.location LIKE 'Court %'
        AND EXISTS (
            SELECT 1 FROM pickleball_match_assignments pma
            WHERE pma.game_id = pg.id
            AND pma.match_number = 1
            AND pma.team1_player1_id IS NOT NULL
            AND pma.team1_player2_id IS NOT NULL
            AND pma.team2_player1_id IS NOT NULL
            AND pma.team2_player2_id IS NOT NULL
        )
        $score_filter
        $date_filter
        ORDER BY DATE(pg.date) ASC,
                 IFNULL(CAST(REGEXP_SUBSTR(pg.location, '[0-9]+') AS UNSIGNED), 9999),
                 pg.location ASC,
                 pg.date ASC,
                 pg.id ASC
    ");
    
    // Bind parameters based on date filter
    if ($selected_date !== '') {
        $stmt->bind_param("is", $current_season['id'], $selected_date);
    } else {
        $stmt->bind_param("i", $current_season['id']);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $games[] = $row;
    }
    $stmt->close();
}

// Get match assignments for each game
$game_assignments = [];
foreach ($games as $game) {
    $stmt = $conn->prepare("
        SELECT 
            pma.match_number,
            pma.team1_player1_id, pma.team1_player2_id,
            pma.team2_player1_id, pma.team2_player2_id,
            p1.name as team1_p1_name, p2.name as team1_p2_name,
            p3.name as team2_p1_name, p4.name as team2_p2_name
        FROM pickleball_match_assignments pma
        LEFT JOIN pickleball_players p1 ON pma.team1_player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pma.team1_player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pma.team2_player1_id = p3.id
        LEFT JOIN pickleball_players p4 ON pma.team2_player2_id = p4.id
        WHERE pma.game_id = ?
        ORDER BY pma.match_number
    ");
    $stmt->bind_param("i", $game['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $game_assignments[$game['id']] = $assignments;
    $stmt->close();
}

// No need for separate stats table - scores are stored in the main games table
$score_form_action = 'enter_pickleball_scores.php?filter=' . urlencode($filter);
if ($selected_date !== '') {
    $score_form_action .= '&date_filter=' . urlencode($selected_date);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter/Edit Pickleball Scores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .game-card {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        .match-section {
            background-color: white;
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            margin-bottom: 1rem;
        }
        .team-score {
            background-color: #e9ecef;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }
        .player-name {
            font-weight: bold;
            color: #495057;
        }
        .score-input {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
        }
        .existing-scores {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }
        .matches-container {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
        }
        .match-section {
            border: 1px solid #e9ecef;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: white;
        }
        .match-section:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">🏓 Enter/Edit Pickleball Scores</h1>
                            <p class="lead mb-0">One match for each court and time</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light btn-lg">
                                ← Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php echo $message; ?>
        
        <!-- Filter Interface -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📊 Filter Games</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" id="filterForm">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            
                            <!-- Status Filter -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Status Filter:</label>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn <?php echo $filter === 'needs_scores' ? 'btn-primary' : 'btn-outline-primary'; ?>" onclick="setFilter('needs_scores')">
                                            🎯 Need Scores (<?php echo $score_counts['needs_scores']; ?>)
                                        </button>
                                        <button type="button" class="btn <?php echo $filter === 'has_scores' ? 'btn-success' : 'btn-outline-success'; ?>" onclick="setFilter('has_scores')">
                                            ✅ Entered Scores (<?php echo $score_counts['has_scores']; ?>)
                                        </button>
                                        <button type="button" class="btn <?php echo $filter === 'scheduled' ? 'btn-warning' : 'btn-outline-warning'; ?>" onclick="setFilter('scheduled')">
                                            📅 Scheduled
                                        </button>
                                        <button type="button" class="btn <?php echo $filter === 'all' ? 'btn-secondary' : 'btn-outline-secondary'; ?>" onclick="setFilter('all')">
                                            📋 All Games
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Date Filter -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Open a day to correct a score:</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <select name="date_filter" id="dateFilterSelect" class="form-select" onchange="submitDate()">
                                                <option value="all" <?php echo $selected_date === '' ? 'selected' : ''; ?>>
                                                    📅 All Dates (<?php echo count($available_dates); ?> days with matches)
                                                </option>
                                                <?php foreach ($available_dates as $date): ?>
                                                    <?php
                                                    $date_formatted = date('l, M j, Y', strtotime($date));
                                                    $is_today = $date == date('Y-m-d');
                                                    $is_selected = ($selected_date === $date);
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($date); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                                        <?php echo $date_formatted; ?><?php echo $is_today ? ' (Today)' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block mt-2">
                                                <?php if ($selected_date !== ''): ?>
                                                    Showing every match from <strong><?php echo date('l, M j, Y', strtotime($selected_date)); ?></strong>. Change a score and choose Update Score.
                                                <?php else: ?>
                                                    Games that still need a score are listed below. Choose an earlier day to fix a score that was entered wrong.
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        Showing <?php echo count($games); ?> game<?php echo count($games) != 1 ? 's' : ''; ?>
                                    </small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">Clear All Filters</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            function setFilter(filter) {
                document.querySelector('input[name="filter"]').value = filter;
                submitForm();
            }
            
            function clearFilters() {
                window.location.href = window.location.pathname;
            }
            
            function submitDate() {
                const date = document.getElementById('dateFilterSelect').value;
                document.querySelector('input[name="filter"]').value = date === 'all' ? 'needs_scores' : 'all';
                submitForm();
            }
            
            function submitForm() {
                document.getElementById('filterForm').submit();
            }
        </script>
        

        <!-- Quick Actions -->
        <?php if (!empty($games)): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-light border">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Quick Actions:</strong>
                                <a href="#game-<?php echo $games[0]['id']; ?>" class="btn btn-sm btn-primary ms-2">
                                    Jump to First Game
                                </a>
                                <?php if (count($games) > 1): ?>
                                    <a href="#game-<?php echo $games[count($games)-1]['id']; ?>" class="btn btn-sm btn-outline-primary ms-1">
                                        Jump to Last Game
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <small class="text-muted">
                                    Use the filters above to focus on specific games
                                    <br>
                                    <strong>Keyboard Shortcuts:</strong> Ctrl+1 (Jump to first), Ctrl+2 (Need scores), Ctrl+3 (Has scores)
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($games)): ?>
            <div class="alert alert-info">
                <h4>No Games Found</h4>
                <p>There are no court assignments to score. Assign courts first, then enter the one match for each court and time.</p>
                <?php if ($current_season): ?>
                    <p><strong>Debug Info:</strong></p>
                    <ul>
                        <li>Current Season ID: <?php echo $current_season['id']; ?></li>
                        <li>Season Name: <?php echo htmlspecialchars($current_season['name']); ?></li>
                    </ul>
                <?php else: ?>
                    <p><strong>No active season found.</strong></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($games as $game): ?>
                <?php
                $assignment = null;
                foreach ($game_assignments[$game['id']] as $existing_assignment) {
                    if ((int)$existing_assignment['match_number'] === 1
                        && $existing_assignment['team1_player1_id']
                        && $existing_assignment['team1_player2_id']
                        && $existing_assignment['team2_player1_id']
                        && $existing_assignment['team2_player2_id']) {
                        $assignment = $existing_assignment;
                        break;
                    }
                }
                if (!$assignment) {
                    continue;
                }
                $team1_score = $game['match1_team1_score'] ?? null;
                $team2_score = $game['match1_team2_score'] ?? null;
                $has_existing_scores = (int)$team1_score > 0 || (int)$team2_score > 0;
                $team1_won = (int)$team1_score > (int)$team2_score;
                ?>
                <div class="card mb-4" id="game-<?php echo $game['id']; ?>">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <?php echo date('l, M j, Y', strtotime($game['date'])); ?> at 
                                    <?php echo date('g:i A', strtotime($game['date'])); ?>
                                </h5>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($game['location']); ?> • 
                                    <?php echo $game['division_name'] ? htmlspecialchars($game['division_name']) : 'No Division'; ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($game['status'] === 'completed'): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php elseif ($game['status'] === 'in_progress'): ?>
                                    <span class="badge bg-warning">In Progress</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Scheduled</span>
                                <?php endif; ?>
                                
                                <?php if ($game['score_status'] == 'needs_scores'): ?>
                                    <span class="badge bg-danger ms-1">🎯 Needs Scores</span>
                                <?php elseif ($game['score_status'] == 'has_scores'): ?>
                                    <span class="badge bg-info ms-1">✅ Has Scores</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($score_form_action); ?>" class="score-form">
                            <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="mb-2">Team 1</h6>
                                    <div class="player-name"><?php echo htmlspecialchars($assignment['team1_p1_name'] ?: 'Player'); ?></div>
                                    <div class="player-name"><?php echo htmlspecialchars($assignment['team1_p2_name'] ?: 'Player'); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-2">Team 2</h6>
                                    <div class="player-name"><?php echo htmlspecialchars($assignment['team2_p1_name'] ?: 'Player'); ?></div>
                                    <div class="player-name"><?php echo htmlspecialchars($assignment['team2_p2_name'] ?: 'Player'); ?></div>
                                </div>
                            </div>
                            
                            <?php if ($has_existing_scores): ?>
                                <div class="existing-scores">
                                    <strong>Current Scores:</strong>
                                    Team 1: <span class="badge bg-primary"><?php echo (int)$team1_score; ?></span> |
                                    Team 2: <span class="badge bg-primary"><?php echo (int)$team2_score; ?></span>
                                    <?php if ($team1_won): ?>
                                        <span class="badge bg-success ms-2">Team 1 Won</span>
                                    <?php elseif ((int)$team2_score > (int)$team1_score): ?>
                                        <span class="badge bg-success ms-2">Team 2 Won</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary ms-2">Tie</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="team-score">
                                        <h6 class="mb-2">Team 1 Score:</h6>
                                        <input type="number" class="form-control score-input"
                                               name="match1_team1_score"
                                               value="<?php echo $has_existing_scores ? (int)$team1_score : ''; ?>"
                                               min="0" max="21" placeholder="Score" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="team-score">
                                        <h6 class="mb-2">Team 2 Score:</h6>
                                        <input type="number" class="form-control score-input"
                                               name="match1_team2_score"
                                               value="<?php echo $has_existing_scores ? (int)$team2_score : ''; ?>"
                                               min="0" max="21" placeholder="Score" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" name="submit_scores" class="btn btn-success btn-lg">
                                        <?php echo $has_existing_scores ? 'Update Score' : 'Save Score'; ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Navigation Links -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="pickleball_dashboard.php" class="btn btn-primary me-2">← Back to Dashboard</a>
                <a href="view_player_stats.php" class="btn btn-info me-2">View Player Stats</a>
                <a href="update_ladder_positions.php" class="btn btn-warning">Update Ladder Positions</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and user experience enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Add validation to prevent duplicate player selections within a match
            const forms = document.querySelectorAll('.score-form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const matchSections = form.querySelectorAll('.match-section');
                    let isValid = true;
                    
                    matchSections.forEach(section => {
                        const selects = section.querySelectorAll('select[name*="match_assignments"]');
                        const selectedPlayers = new Set();
                        
                        selects.forEach(select => {
                            if (select.value) {
                                if (selectedPlayers.has(select.value)) {
                                    alert('Error: Each player can only be selected once per match. Please check your team assignments.');
                                    isValid = false;
                                    return false;
                                }
                                selectedPlayers.add(select.value);
                            }
                        });
                        
                        // Check if all 4 players are selected for each match
                        if (selectedPlayers.size > 0 && selectedPlayers.size < 4) {
                            alert('Error: Please select all 4 players for each match.');
                            isValid = false;
                            return false;
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                    }
                });
                
                // Real-time validation for player selection
                const selects = form.querySelectorAll('select[name*="match_assignments"]');
                selects.forEach(select => {
                    select.addEventListener('change', function() {
                        const matchSection = this.closest('.match-section');
                        const matchSelects = matchSection.querySelectorAll('select[name*="match_assignments"]');
                        const selectedValues = Array.from(matchSelects).map(s => s.value).filter(v => v);
                        
                        // Check for duplicates
                        const duplicates = selectedValues.filter((item, index) => selectedValues.indexOf(item) !== index);
                        if (duplicates.length > 0) {
                            this.style.borderColor = 'red';
                            this.title = 'Duplicate player selection';
                        } else {
                            this.style.borderColor = '';
                            this.title = '';
                        }
                    });
                });
            });
            
            // Auto-populate default match assignments if none exist
            forms.forEach(form => {
                const matchSections = form.querySelectorAll('.match-section');
                matchSections.forEach((section, index) => {
                    const selects = section.querySelectorAll('select[name*="match_assignments"]');
                    const hasSelections = Array.from(selects).some(select => select.value);
                    
                    if (!hasSelections) {
                        // Set default assignments based on match number
                        const matchNum = index + 1;
                        const defaultAssignments = getDefaultAssignments(matchNum);
                        
                        selects.forEach((select, selectIndex) => {
                            if (defaultAssignments[selectIndex]) {
                                select.value = defaultAssignments[selectIndex];
                            }
                        });
                    }
                });
            });
        });
        
        function getDefaultAssignments(matchNum) {
            // Get the actual player IDs from hidden inputs
            const player1Id = document.getElementById('player1_id_' + matchNum)?.value;
            const player2Id = document.getElementById('player2_id_' + matchNum)?.value;
            const player3Id = document.getElementById('player3_id_' + matchNum)?.value;
            const player4Id = document.getElementById('player4_id_' + matchNum)?.value;
            const player5Id = document.getElementById('player5_id_' + matchNum)?.value;
            const player6Id = document.getElementById('player6_id_' + matchNum)?.value;
            const player7Id = document.getElementById('player7_id_' + matchNum)?.value;
            
            console.log('Player IDs from hidden inputs:', {player1Id, player2Id, player3Id, player4Id, player5Id, player6Id, player7Id});
            
            // Default assignments using actual player IDs
            const defaults = {
                1: [player1Id, player2Id, player3Id, player4Id], // P1&P2 vs P3&P4
                2: [player1Id, player3Id, player2Id, player4Id], // P1&P3 vs P2&P4
                3: [player1Id, player4Id, player2Id, player3Id], // P1&P4 vs P2&P3
                4: [player1Id, player5Id, player2Id, player3Id], // P1&P5 vs P2&P3 (for 5-player games)
                5: [player2Id, player4Id, player3Id, player5Id], // P2&P4 vs P3&P5 (for 5-player games)
                6: [player1Id, player6Id, player2Id, player4Id], // P1&P6 vs P2&P4 (for 6-player games)
                7: [player1Id, player7Id, player2Id, player5Id]  // P1&P7 vs P2&P5 (for 7-player games)
            };
            
            const result = defaults[matchNum] || [];
            console.log('Default assignments for match', matchNum, ':', result);
            console.log('Individual values:', result[0], result[1], result[2], result[3]);
            return result;
        }
        
        function setDefaultAssignments(matchNum) {
            console.log('Setting default assignments for match', matchNum);
            const defaultAssignments = getDefaultAssignments(matchNum);
            
            // Find the match section for this match number
            const matchSections = document.querySelectorAll('.match-section');
            const targetSection = matchSections[matchNum - 1]; // matchNum is 1-based, array is 0-based
            
            if (targetSection) {
                const selects = targetSection.querySelectorAll('select[name*="match_assignments"]');
                console.log('Found', selects.length, 'selects in match', matchNum);
                console.log('Default assignments array:', defaultAssignments);
                
                selects.forEach((select, selectIndex) => {
                    console.log('Processing select', selectIndex, 'with value', defaultAssignments[selectIndex]);
                    console.log('Select options:', Array.from(select.options).map(opt => opt.value + ':' + opt.text));
                    
                    if (defaultAssignments[selectIndex] && defaultAssignments[selectIndex] !== 'null' && defaultAssignments[selectIndex] !== '') {
                        // Try to set the value
                        select.value = defaultAssignments[selectIndex];
                        
                        // Check if it was actually set
                        if (select.value === defaultAssignments[selectIndex]) {
                            console.log('✓ Set select', selectIndex, 'to value', defaultAssignments[selectIndex]);
                        } else {
                            console.log('✗ Failed to set select', selectIndex, 'to value', defaultAssignments[selectIndex], 'current value is:', select.value);
                            
                            // Try to find the option manually
                            const option = Array.from(select.options).find(opt => opt.value === defaultAssignments[selectIndex]);
                            if (option) {
                                option.selected = true;
                                console.log('✓ Manually selected option:', option.text);
                            } else {
                                console.log('✗ Option with value', defaultAssignments[selectIndex], 'not found in dropdown');
                            }
                        }
                    } else {
                        console.log('✗ Skipped select', selectIndex, 'because value is:', defaultAssignments[selectIndex]);
                    }
                });
            } else {
                console.error('Could not find match section for match', matchNum);
            }
        }
        
        // Dynamic match management functions
        function addMatch(gameId) {
            const container = document.getElementById('matches-container-' + gameId);
            const existingMatches = container.querySelectorAll('.match-section').length;
            const newMatchNum = existingMatches + 1;
            
            // Create new match HTML
            const matchHtml = `
                <div class="match-section" id="match-${gameId}-${newMatchNum}">
                    <h6 class="text-primary mb-3">Match ${newMatchNum}:</h6>
                    
                    <!-- Match Assignment Selection -->
                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <small><strong>Note:</strong> Player assignments and scores are optional. You can leave fields empty if not needed.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setDefaultAssignments(${newMatchNum})">
                                Set Default Assignments for Match ${newMatchNum}
                            </button>
                            <!-- Hidden inputs with actual player IDs -->
                            <input type="hidden" id="player1_id_${newMatchNum}" value="">
                            <input type="hidden" id="player2_id_${newMatchNum}" value="">
                            <input type="hidden" id="player3_id_${newMatchNum}" value="">
                            <input type="hidden" id="player4_id_${newMatchNum}" value="">
                            <input type="hidden" id="player5_id_${newMatchNum}" value="">
                            <input type="hidden" id="player6_id_${newMatchNum}" value="">
                            <input type="hidden" id="player7_id_${newMatchNum}" value="">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Team 1:</strong></label>
                            <div class="row">
                                <div class="col-6">
                                    <select class="form-select" name="match_assignments[${newMatchNum}][team1_player1_id]">
                                        <option value="">Select Player</option>
                                        <!-- Players will be populated by JavaScript -->
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select" name="match_assignments[${newMatchNum}][team1_player2_id]">
                                        <option value="">Select Player</option>
                                        <!-- Players will be populated by JavaScript -->
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Team 2:</strong></label>
                            <div class="row">
                                <div class="col-6">
                                    <select class="form-select" name="match_assignments[${newMatchNum}][team2_player1_id]">
                                        <option value="">Select Player</option>
                                        <!-- Players will be populated by JavaScript -->
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select class="form-select" name="match_assignments[${newMatchNum}][team2_player2_id]">
                                        <option value="">Select Player</option>
                                        <!-- Players will be populated by JavaScript -->
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Score Input -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="team-score">
                                <h6 class="mb-2">Team 1 Score:</h6>
                                <input type="number" class="form-control score-input" 
                                       name="match${newMatchNum}_team1_score" 
                                       min="0" max="21" placeholder="Score">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="team-score">
                                <h6 class="mb-2">Team 2 Score:</h6>
                                <input type="number" class="form-control score-input" 
                                       name="match${newMatchNum}_team2_score" 
                                       min="0" max="21" placeholder="Score">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add the new match to the container
            container.insertAdjacentHTML('beforeend', matchHtml);
            
            // Populate player options for the new match
            populatePlayerOptions(gameId, newMatchNum);
            
            // Update match count
            updateMatchCount(gameId);
            
            // Scroll to the new match
            const newMatch = document.getElementById('match-' + gameId + '-' + newMatchNum);
            newMatch.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function removeMatch(gameId) {
            const container = document.getElementById('matches-container-' + gameId);
            const matches = container.querySelectorAll('.match-section');
            
            if (matches.length > 1) {
                const lastMatch = matches[matches.length - 1];
                lastMatch.remove();
                updateMatchCount(gameId);
            } else {
                alert('You need at least one match!');
            }
        }
        
        function updateMatchCount(gameId) {
            const container = document.getElementById('matches-container-' + gameId);
            const matchCount = container.querySelectorAll('.match-section').length;
            document.getElementById('match-count-' + gameId).textContent = matchCount;
        }
        
        function populatePlayerOptions(gameId, matchNum) {
            // Get player options from the first match
            const firstMatch = document.querySelector('#matches-container-' + gameId + ' .match-section');
            if (firstMatch) {
                const playerOptions = firstMatch.querySelectorAll('select option');
                const newMatch = document.getElementById('match-' + gameId + '-' + matchNum);
                const newSelects = newMatch.querySelectorAll('select');
                
                newSelects.forEach(select => {
                    // Clear existing options except the first one
                    while (select.children.length > 1) {
                        select.removeChild(select.lastChild);
                    }
                    
                    // Add player options
                    playerOptions.forEach(option => {
                        if (option.value !== '') {
                            const newOption = option.cloneNode(true);
                            select.appendChild(newOption);
                        }
                    });
                });
            }
        }
        
        // Add smooth scrolling for jump links
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + 1 = Jump to first game
                if ((e.ctrlKey || e.metaKey) && e.key === '1') {
                    e.preventDefault();
                    const firstGame = document.querySelector('.card[id^="game-"]');
                    if (firstGame) {
                        firstGame.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
                
                // Ctrl/Cmd + 2 = Filter to games that need scores
                if ((e.ctrlKey || e.metaKey) && e.key === '2') {
                    e.preventDefault();
                    window.location.href = '?filter=needs_scores';
                }
                
                // Ctrl/Cmd + 3 = Filter to games with scores
                if ((e.ctrlKey || e.metaKey) && e.key === '3') {
                    e.preventDefault();
                    window.location.href = '?filter=has_scores';
                }
            });
            
            // Add focus indicators for better accessibility
            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#007bff';
                    this.style.boxShadow = '0 4px 8px rgba(0,123,255,0.2)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                });
            });
        });
    </script>
</body>
</html>