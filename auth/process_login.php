<?php
session_start();
include("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$email = trim($_POST['email']);
$password = trim($_POST['password']);

if (empty($email) || empty($password)) {
    header("Location: login.php?error=" . urlencode("Please enter your email and password."));
    exit;
}

$hashed_password = md5($password);

/* ADMIN LOGIN */
$admin_stmt = $conn->prepare("SELECT id, name, email, role FROM admin WHERE email = ? AND password = ?");
$admin_stmt->bind_param("ss", $email, $hashed_password);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();

if ($admin_result->num_rows > 0) {
    $admin = $admin_result->fetch_assoc();

    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['name'] = $admin['name'];
    $_SESSION['email'] = $admin['email'];
    $_SESSION['role'] = 'admin';

    header("Location: ../dashboard/admin.php");
    exit;
}

/* STUDENT / TEACHER LOGIN */
$user_stmt = $conn->prepare("SELECT id, firstname, lastname, email, role, course FROM users WHERE email = ? AND password = ?");
$user_stmt->bind_param("ss", $email, $hashed_password);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows > 0) {
    $user = $user_result->fetch_assoc();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['firstname'] = $user['firstname'];
    $_SESSION['lastname'] = $user['lastname'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['course'] = $user['course'];

    if ($user['role'] === 'teacher') {
        header("Location: ../dashboard/teacher.php");
        exit;
    } elseif ($user['role'] === 'student') {
        header("Location: ../dashboard/student.php");
        exit;
    } else {
        header("Location: login.php?error=" . urlencode("Invalid user role found."));
        exit;
    }
}

header("Location: login.php?error=" . urlencode("Invalid email or password."));
exit;
?>