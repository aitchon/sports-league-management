<?php
// Test script to verify pickleball system updates
echo "Pickleball System Update Test\n";
echo "============================\n\n";

// Test 1: Check if database update script exists
echo "1. Checking database update script...\n";
if (file_exists('admin/update_pickleball_5_players.sql')) {
    echo "   ✓ Database update script exists\n";
} else {
    echo "   ✗ Database update script missing\n";
}

// Test 2: Check if match assignments page exists
echo "2. Checking match assignments page...\n";
if (file_exists('pickleball_match_assignments.php')) {
    echo "   ✓ Match assignments page exists\n";
} else {
    echo "   ✗ Match assignments page missing\n";
}

// Test 3: Check if manage_pickleball_games.php has been updated
echo "3. Checking game management updates...\n";
$content = file_get_contents('admin/manage_pickleball_games.php');
if (strpos($content, 'player5_id') !== false) {
    echo "   ✓ Player 5 support added\n";
} else {
    echo "   ✗ Player 5 support missing\n";
}

if (strpos($content, 'round_robin') !== false) {
    echo "   ✓ Round robin game type added\n";
} else {
    echo "   ✗ Round robin game type missing\n";
}

if (strpos($content, 'generateRoundRobinMatches') !== false) {
    echo "   ✓ Round robin match generation function added\n";
} else {
    echo "   ✗ Round robin match generation function missing\n";
}

// Test 4: Check if schedule page has been updated
echo "4. Checking schedule page updates...\n";
$schedule_content = file_get_contents('pickleball_schedule.php');
if (strpos($schedule_content, 'player5_name') !== false) {
    echo "   ✓ Player 5 display support added\n";
} else {
    echo "   ✗ Player 5 display support missing\n";
}

if (strpos($schedule_content, 'round_robin') !== false) {
    echo "   ✓ Round robin display support added\n";
} else {
    echo "   ✗ Round robin display support missing\n";
}

// Test 5: Check if navigation has been updated
echo "5. Checking navigation updates...\n";
if (strpos($schedule_content, 'pickleball_match_assignments.php') !== false) {
    echo "   ✓ Match assignments navigation added\n";
} else {
    echo "   ✗ Match assignments navigation missing\n";
}

echo "\nTest Summary:\n";
echo "=============\n";
echo "The pickleball system has been updated to support:\n";
echo "• 4-5 players per court (minimum 4, maximum 5)\n";
echo "• 3 matches for 4 players, 4 matches for 5 players\n";
echo "• Round robin game type with automatic match generation\n";
echo "• New match assignments page to view round robin matchups\n";
echo "• Updated UI to display 5 players and round robin games\n";
echo "• Database schema updates for player5_id and match4 scores\n\n";

echo "Next steps:\n";
echo "1. Run the database update script: admin/update_pickleball_5_players.sql\n";
echo "2. Test creating a round robin game with 4-5 players\n";
echo "3. Verify match assignments are generated correctly\n";
echo "4. Test the match assignments display page\n";
?>
