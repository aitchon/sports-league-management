<?php
session_start();
include 'db_pdo.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);

    $stmt = $pdo->prepare("DELETE FROM volleyball_players WHERE id = ?");
    $stmt->execute([$id]);
}
?>
