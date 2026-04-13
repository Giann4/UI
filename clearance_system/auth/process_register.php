<?php
session_start();
include("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit;
}

/* KUHANIN ANG MGA INPUT */
$firstname        = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$lastname         = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$email            = isset($_POST['email']) ? trim($_POST['email']) : '';
$contact_number   = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
$password         = isset($_POST['password']) ? trim($_POST['password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
$role             = isset($_POST['role']) ? trim($_POST['role']) : '';
$course           = isset($_POST['course']) ? trim($_POST['course']) : '';

/* BASIC VALIDATION */
if (
    empty($firstname) ||
    empty($lastname) ||
    empty($email) ||
    empty($contact_number) ||
    empty($password) ||
    empty($confirm_password) ||
    empty($role)
) {
    header("Location: register.php?error=" . urlencode("Please fill in all required fields."));
    exit;
}

/* EMAIL VALIDATION */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.php?error=" . urlencode("Please enter a valid email address."));
    exit;
}

/* PASSWORD MATCH CHECK */
if ($password !== $confirm_password) {
    header("Location: register.php?error=" . urlencode("Password and Confirm Password do not match."));
    exit;
}

/* ROLE VALIDATION */
if ($role !== 'student' && $role !== 'teacher') {
    header("Location: register.php?error=" . urlencode("Invalid role selected."));
    exit;
}

/* COURSE REQUIRED LANG SA STUDENT */
if ($role === 'student') {
    if (empty($course)) {
        header("Location: register.php?error=" . urlencode("Please select a course for student account."));
        exit;
    }
} else {
    $course = null;
}

/* CHECK KUNG MAY KAPAREHONG EMAIL NA */
$check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$check_stmt) {
    header("Location: register.php?error=" . urlencode("Database error: failed to prepare email check."));
    exit;
}

$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    $check_stmt->close();
    header("Location: register.php?error=" . urlencode("Email is already registered."));
    exit;
}
$check_stmt->close();

/*
    IMPORTANT:
    Ito ay naka-md5 para tugma sa current login system mo.
    Kung md5 din ang gamit ng process_login.php mo, ito ang tama.
*/
$hashed_password = md5($password);

/* INSERT USER */
$insert_stmt = $conn->prepare("
    INSERT INTO users (firstname, lastname, email, contact_number, password, role, course)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$insert_stmt) {
    header("Location: register.php?error=" . urlencode("Database error: failed to prepare insert."));
    exit;
}

$insert_stmt->bind_param(
    "sssssss",
    $firstname,
    $lastname,
    $email,
    $contact_number,
    $hashed_password,
    $role,
    $course
);

if ($insert_stmt->execute()) {
    $insert_stmt->close();
    header("Location: login.php?registered=1");
    exit;
} else {
    $insert_stmt->close();
    header("Location: register.php?error=" . urlencode("Registration failed. Please try again."));
    exit;
}
?>