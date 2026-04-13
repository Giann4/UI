<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$folder = "../assets/uploads/profile/";

if (!file_exists($folder)) {
    mkdir($folder, 0777, true);
}

if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
    $file_name = $_FILES['profile_photo']['name'];
    $file_tmp = $_FILES['profile_photo']['tmp_name'];
    $file_size = $_FILES['profile_photo']['size'];

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($file_ext, $allowed)) {
        echo "<script>alert('Only JPG, JPEG, PNG, and GIF files are allowed.'); window.location.href='change_password.php';</script>";
        exit;
    }

    if ($file_size > 5 * 1024 * 1024) {
        echo "<script>alert('File is too large. Max 5MB only.'); window.location.href='change_password.php';</script>";
        exit;
    }

    $new_file_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
    $upload_path = $folder . $new_file_name;

    if (move_uploaded_file($file_tmp, $upload_path)) {
        if ($role === 'admin') {
            $update = $conn->prepare("UPDATE admin SET profile_photo = ? WHERE id = ?");
        } else {
            $update = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
        }

        $update->bind_param("si", $new_file_name, $user_id);
        $update->execute();
    }
}

header("Location: change_password.php");
exit;
?>