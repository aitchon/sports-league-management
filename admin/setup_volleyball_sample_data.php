<?php
// Quick setup script to add sample volleyball data
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'basketball_league';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Setting up Volleyball Sample Data</h2>";

// Set session for volleyball season
$_SESSION['selected_volleyball_season_id'] = 1;
echo "<p style='color: green;'>✅ Set volleyball season ID to 1</p>";

// Add sample divisions
$divisions = [
    ['name' => 'Men\'s Division A', 'season_id' => 1, 'is_playoff' => 0],
    ['name' => 'Women\'s Division A', 'season_id' => 1, 'is_playoff' => 0],
    ['name' => 'Co-ed Division', 'season_id' => 1, 'is_playoff' => 0]
];

foreach ($divisions as $division) {
    $stmt = $conn->prepare("INSERT INTO volleyball_divisions (name, season_id, is_playoff) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $division['name'], $division['season_id'], $division['is_playoff']);
    $stmt->execute();
    $division_id = $conn->insert_id;
    echo "<p style='color: green;'>✅ Added division: {$division['name']} (ID: $division_id)</p>";
    
    // Add sample teams for this division
    $teams = [
        ['name' => 'Team Alpha', 'coach' => 'Coach Smith'],
        ['name' => 'Team Beta', 'coach' => 'Coach Johnson'],
        ['name' => 'Team Gamma', 'coach' => 'Coach Williams']
    ];
    
    foreach ($teams as $team) {
        $stmt = $conn->prepare("INSERT INTO volleyball_teams (name, coach, division_id, season_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $team['name'], $team['coach'], $division_id, $division['season_id']);
        $stmt->execute();
        $team_id = $conn->insert_id;
        echo "<p style='color: blue;'>  → Added team: {$team['name']} (ID: $team_id)</p>";
    }
    $stmt->close();
}

echo "<h3>✅ Setup Complete!</h3>";
echo "<p>Now you can:</p>";
echo "<ol>";
echo "<li>Go to <strong>manage_volleyball_players.php</strong></li>";
echo "<li>Select the volleyball season</li>";
echo "<li>Choose a division</li>";
echo "<li>Choose a team</li>";
echo "<li>Add players!</li>";
echo "</ol>";

$conn->close();
?>
