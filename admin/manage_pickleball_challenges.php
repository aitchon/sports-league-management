<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";

// Handle challenge status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_challenge'])) {
    $challenge_id = (int)$_POST['challenge_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE pickleball_challenges SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $challenge_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Challenge status updated successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    
    $stmt->close();
}

// Handle challenge deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_challenge'])) {
    $challenge_id = $_POST['challenge_id'];
    
    $stmt = $conn->prepare("DELETE FROM pickleball_challenges WHERE id = ?");
    $stmt->bind_param("i", $challenge_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Challenge deleted successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    
    $stmt->close();
}

// Get current season
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

// Get all seasons for dropdown
$seasons = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");

// Get challenges for current season
$challenges = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT c.*, 
               p1.name as challenger_name, p1.current_rating as challenger_rating,
               p2.name as challenged_name, p2.current_rating as challenged_rating,
               d.name as division_name
        FROM pickleball_challenges c
        JOIN pickleball_players p1 ON c.challenger_id = p1.id
        JOIN pickleball_players p2 ON c.challenged_id = p2.id
        LEFT JOIN pickleball_divisions d ON c.division_id = d.id
        WHERE c.season_id = ?
        ORDER BY c.challenge_date DESC
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $challenges[] = $row;
    }
    $stmt->close();
}

// Group challenges by status
$pending_challenges = array_filter($challenges, function($c) { return $c['status'] == 'pending'; });
$accepted_challenges = array_filter($challenges, function($c) { return $c['status'] == 'accepted'; });
$completed_challenges = array_filter($challenges, function($c) { return $c['status'] == 'completed'; });
$declined_challenges = array_filter($challenges, function($c) { return $c['status'] == 'declined'; });
$expired_challenges = array_filter($challenges, function($c) { return $c['status'] == 'expired'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pickleball Challenges</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .challenge-card {
            transition: transform 0.2s;
        }
        .challenge-card:hover {
            transform: translateY(-2px);
        }
        .status-pending { border-left: 4px solid #ffc107; }
        .status-accepted { border-left: 4px solid #28a745; }
        .status-declined { border-left: 4px solid #dc3545; }
        .status-completed { border-left: 4px solid #17a2b8; }
        .status-expired { border-left: 4px solid #6c757d; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12 sport-header py-4 mb-4">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">⚔️ Manage Pickleball Challenges</h1>
                            <p class="mb-0">View and manage player challenges</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light me-2">← Back to Dashboard</a>
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php echo $message; ?>

            <!-- Season Selector -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">⚔️ Challenge Management</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="season_id" class="form-label">Select Season:</label>
                            <select name="season_id" id="season_id" class="form-select" onchange="this.form.submit()">
                                <?php 
                                $seasons->data_seek(0); // Reset result pointer
                                while ($season = $seasons->fetch_assoc()): ?>
                                    <option value="<?php echo $season['id']; ?>" <?php echo ($current_season && $current_season['id'] == $season['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($season['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <a href="pickleball_dashboard.php" class="btn btn-info">Back to Dashboard</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($current_season): ?>
                <!-- Challenge Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo count($pending_challenges); ?></h3>
                                <p class="card-text">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo count($accepted_challenges); ?></h3>
                                <p class="card-text">Accepted</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-info">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo count($completed_challenges); ?></h3>
                                <p class="card-text">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo count($declined_challenges); ?></h3>
                                <p class="card-text">Declined</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-secondary">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo count($expired_challenges); ?></h3>
                                <p class="card-text">Expired</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo count($challenges); ?></h3>
                                <p class="card-text">Total</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Challenges -->
                <?php if (!empty($pending_challenges)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">⏳ Pending Challenges (<?php echo count($pending_challenges); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($pending_challenges as $challenge): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card challenge-card status-pending">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <?php echo htmlspecialchars($challenge['challenger_name']); ?> 
                                                    <span class="text-muted">challenges</span>
                                                    <?php echo htmlspecialchars($challenge['challenged_name']); ?>
                                                </h6>
                                                <p class="card-text">
                                                    <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($challenge['challenge_date'])); ?><br>
                                                    <strong>Expires:</strong> <?php echo date('M j, Y g:i A', strtotime($challenge['expires_at'])); ?><br>
                                                    <strong>Division:</strong> <?php echo htmlspecialchars($challenge['division_name'] ?? 'Unassigned'); ?><br>
                                                    <?php if ($challenge['message']): ?>
                                                        <strong>Message:</strong> <?php echo htmlspecialchars($challenge['message']); ?><br>
                                                    <?php endif; ?>
                                                </p>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#acceptModal<?php echo $challenge['id']; ?>">
                                                        Accept
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#declineModal<?php echo $challenge['id']; ?>">
                                                        Decline
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- All Challenges -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">📋 All Challenges</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($challenges)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Challenger</th>
                                            <th>Challenged</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Division</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($challenges as $challenge): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($challenge['challenger_name']); ?></strong>
                                                    <br><small class="text-muted">Rating: <?php echo $challenge['challenger_rating']; ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($challenge['challenged_name']); ?></strong>
                                                    <br><small class="text-muted">Rating: <?php echo $challenge['challenged_rating']; ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y', strtotime($challenge['challenge_date'])); ?><br>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($challenge['challenge_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $challenge['status'] == 'pending' ? 'warning' : 
                                                            ($challenge['status'] == 'accepted' ? 'success' : 
                                                            ($challenge['status'] == 'declined' ? 'danger' : 
                                                            ($challenge['status'] == 'completed' ? 'info' : 'secondary'))); 
                                                    ?>">
                                                        <?php echo ucfirst($challenge['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($challenge['division_name'] ?? 'Unassigned'); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $challenge['id']; ?>">
                                                            Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $challenge['id']; ?>">
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No challenges found for this season.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <h4>No Active Pickleball Season</h4>
                    <p>Please select a season to view challenges.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Accept/Decline Modals -->
    <?php foreach ($challenges as $challenge): ?>
        <!-- Accept Modal -->
        <div class="modal fade" id="acceptModal<?php echo $challenge['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Accept Challenge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                            <input type="hidden" name="status" value="accepted">
                            
                            <p>Are you sure you want to accept this challenge?</p>
                            <p><strong><?php echo htmlspecialchars($challenge['challenger_name']); ?></strong> challenges <strong><?php echo htmlspecialchars($challenge['challenged_name']); ?></strong></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_challenge" class="btn btn-success">Accept Challenge</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Decline Modal -->
        <div class="modal fade" id="declineModal<?php echo $challenge['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Decline Challenge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                            <input type="hidden" name="status" value="declined">
                            
                            <p>Are you sure you want to decline this challenge?</p>
                            <p><strong><?php echo htmlspecialchars($challenge['challenger_name']); ?></strong> challenges <strong><?php echo htmlspecialchars($challenge['challenged_name']); ?></strong></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_challenge" class="btn btn-danger">Decline Challenge</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?php echo $challenge['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Challenge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Status:</label>
                                <select class="form-select" name="status">
                                    <option value="pending" <?php echo $challenge['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="accepted" <?php echo $challenge['status'] == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                    <option value="declined" <?php echo $challenge['status'] == 'declined' ? 'selected' : ''; ?>>Declined</option>
                                    <option value="completed" <?php echo $challenge['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="expired" <?php echo $challenge['status'] == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_challenge" class="btn btn-success">Update Challenge</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal<?php echo $challenge['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Challenge</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this challenge?</p>
                        <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                            <button type="submit" name="delete_challenge" class="btn btn-danger">Delete Challenge</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




