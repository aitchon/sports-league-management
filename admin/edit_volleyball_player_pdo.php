<?php
session_start();
include 'db_pdo.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $field = $_POST['field'];
    $value = $_POST['value'];

    // Validate field to prevent SQL injection
    $allowed_fields = ['name', 'position', 'jersey_number'];
    if (in_array($field, $allowed_fields)) {
        $stmt = $pdo->prepare("UPDATE volleyball_players SET $field = ? WHERE id = ?");
        $stmt->execute([$value, $id]);
    }
}
?>
