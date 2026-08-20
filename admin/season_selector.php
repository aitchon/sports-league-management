<?php
// Season selector component for admin pages
if (!isset($conn)) {
    include 'db.php';
}

// Get selected season from session or default to current season
$selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : ($current_season ? $current_season['id'] : null);

// Handle season selection
if (isset($_POST['select_season'])) {
    $_SESSION['selected_season_id'] = $_POST['season_id'];
    $selected_season_id = $_POST['season_id'];
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get all seasons
$seasons = getAllSeasons($conn);
$selected_season = $selected_season_id ? getSeasonById($conn, $selected_season_id) : $current_season;
?>

<!-- Season Selector -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">🏀 Season Selection</h5>
    </div>
    <div class="card-body">
        <form method="post" class="d-flex align-items-center">
            <div class="me-3">
                <label for="season_id" class="form-label mb-0">Current Season:</label>
            </div>
            <div class="me-3">
                <select name="season_id" id="season_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?php echo $season['id']; ?>" 
                                <?php echo ($selected_season_id == $season['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($season['name']); ?>
                            <?php if ($season['is_active']): ?>
                                (Active)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="select_season" value="1">
            <button type="submit" class="btn btn-primary btn-sm">Select Season</button>
        </form>
        <?php if ($selected_season): ?>
            <div class="mt-2">
                <small class="text-muted">
                    <strong>Selected:</strong> <?php echo htmlspecialchars($selected_season['name']); ?> 
                    (<?php echo date('M j, Y', strtotime($selected_season['start_date'])); ?> - 
                    <?php echo date('M j, Y', strtotime($selected_season['end_date'])); ?>)
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>
