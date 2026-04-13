<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_code = trim($_POST['class_code']);

    $stmt = $conn->prepare("SELECT * FROM teacher_classes WHERE class_code = ?");
    $stmt->bind_param("s", $class_code);
    $stmt->execute();
    $class = $stmt->get_result()->fetch_assoc();

    if (!$class) {
        die("Invalid class code.");
    }

    $class_id = $class['id'];

    $checkMember = $conn->prepare("SELECT id FROM class_members WHERE class_id = ? AND student_id = ?");
    $checkMember->bind_param("ii", $class_id, $student_id);
    $checkMember->execute();
    $memberRes = $checkMember->get_result();

    if ($memberRes->num_rows === 0) {
        $insertMember = $conn->prepare("INSERT INTO class_members (class_id, student_id) VALUES (?, ?)");
        $insertMember->bind_param("ii", $class_id, $student_id);
        $insertMember->execute();
    }

    $checkRequest = $conn->prepare("SELECT id FROM class_requests WHERE class_id = ? AND student_id = ?");
    $checkRequest->bind_param("ii", $class_id, $student_id);
    $checkRequest->execute();
    $requestRes = $checkRequest->get_result();

    if ($requestRes->num_rows === 0) {
        $insertRequest = $conn->prepare("INSERT INTO class_requests (class_id, student_id, status) VALUES (?, ?, 'Requesting')");
        $insertRequest->bind_param("ii", $class_id, $student_id);
        $insertRequest->execute();
    }

    header("Location: student.php");
    exit;
}
?>