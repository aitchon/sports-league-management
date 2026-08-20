<?php
// Production fix: Add sample volleyball teams
// Run this once on production to populate teams

session_start();
include 'db.php';

echo "<h2>Adding Sample Volleyball Teams</h2>";

// Set session for volleyball season
$_SESSION['selected_volleyball_season_id'] = 1;
echo "<p>✅ Set volleyball season ID to 1</p>";

// Check if we already have teams
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM volleyball_teams WHERE season_id = 1");
$check_stmt->execute();
$result = $check_stmt->get_result();
$count = $result->fetch_assoc()['count'];
$check_stmt->close();

if ($count > 0) {
    echo "<p style='color: orange;'>⚠️ Teams already exist ($count teams found)</p>";
    echo "<p>No need to add sample data.</p>";
} else {
    echo "<p style='color: blue;'>📝 Adding sample teams...</p>";
    
    // Add sample divisions and teams
    $divisions = [
        ['name' => "Men's Division A", 'teams' => ['Team Alpha', 'Team Beta', 'Team Gamma']],
        ['name' => "Women's Division A", 'teams' => ['Team Alpha', 'Team Beta', 'Team Gamma']],
        ['name' => "Co-ed Division", 'teams' => ['Team Alpha', 'Team Beta', 'Team Gamma']]
    ];
    
    foreach ($divisions as $div) {
        // Check if division exists
        $div_stmt = $conn->prepare("SELECT id FROM volleyball_divisions WHERE name = ? AND season_id = 1");
        $div_stmt->bind_param("s", $div['name']);
        $div_stmt->execute();
        $div_result = $div_stmt->get_result();
        
        if ($div_result->num_rows > 0) {
            $division_id = $div_result->fetch_assoc()['id'];
            echo "<p>✅ Using existing division: {$div['name']} (ID: $division_id)</p>";
        } else {
            // Create division
            $div_stmt = $conn->prepare("INSERT INTO volleyball_divisions (name, season_id) VALUES (?, 1)");
            $div_stmt->bind_param("s", $div['name']);
            $div_stmt->execute();
            $division_id = $conn->insert_id;
            echo "<p>✅ Created division: {$div['name']} (ID: $division_id)</p>";
        }
        $div_stmt->close();
        
        // Add teams for this division
        foreach ($div['teams'] as $team_name) {
            $team_stmt = $conn->prepare("INSERT INTO volleyball_teams (name, division_id, season_id, is_playoff_placeholder) VALUES (?, ?, 1, 0)");
            $team_stmt->bind_param("si", $team_name, $division_id);
            $team_stmt->execute();
            $team_id = $conn->insert_id;
            echo "<p style='color: blue;'>  → Added team: $team_name (ID: $team_id)</p>";
            $team_stmt->close();
        }
    }
    
    echo "<h3>✅ Setup Complete!</h3>";
}

echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Go to <strong>manage_volleyball_players.php</strong></li>";
echo "<li>Select the volleyball season</li>";
echo "<li>Choose a division</li>";
echo "<li>Choose a team</li>";
echo "<li>Add players!</li>";
echo "</ol>";

$conn->close();
?>
