<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$subject = trim($_POST['subject']);
$course = trim($_POST['course']);

function generateClassCode($length = 7) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

$class_code = generateClassCode();

$check = $conn->prepare("SELECT id FROM teacher_classes WHERE class_code = ?");
$check->bind_param("s", $class_code);
$check->execute();
$res = $check->get_result();

while ($res->num_rows > 0) {
    $class_code = generateClassCode();
    $check = $conn->prepare("SELECT id FROM teacher_classes WHERE class_code = ?");
    $check->bind_param("s", $class_code);
    $check->execute();
    $res = $check->get_result();
}

$stmt = $conn->prepare("INSERT INTO teacher_classes (teacher_id, subject, course, class_code) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $teacher_id, $subject, $course, $class_code);

if ($stmt->execute()) {
    header("Location: teacher.php");
    exit;
} else {
    echo "Failed to create class.";
}
?>