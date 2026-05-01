<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$returnUrl = isset($_GET['return']) && !empty($_GET['return'])
    ? $_GET['return']
    : 'admin.php?view=students';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: " . $returnUrl);
    exit;
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $separator = (strpos($returnUrl, '?') !== false) ? '&' : '?';
    header("Location: " . $returnUrl . $separator . "msg=archived");
    exit;
} else {
    $separator = (strpos($returnUrl, '?') !== false) ? '&' : '?';
    header("Location: " . $returnUrl . $separator . "msg=error");
    exit;
}
?>