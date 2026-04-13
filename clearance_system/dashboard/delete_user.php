<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("User ID not found.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: admin.php");
    exit;
} else {
    echo "Delete failed.";
}
?>