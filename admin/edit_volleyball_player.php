<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $field = $_POST['field'];
    $value = $_POST['value'];

    // Validate field to prevent SQL injection
    $allowed_fields = ['name', 'position', 'jersey_number'];
    if (in_array($field, $allowed_fields)) {
        $stmt = $conn->prepare("UPDATE volleyball_players SET $field = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
        $stmt->execute();
        $stmt->close();
    }
}
?>
