<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$message = "";
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = isset($_SESSION['name']) && !empty($_SESSION['name']) ? $_SESSION['name'] : 'Administrator';

/* ADMIN PROFILE PHOTO */
$default_admin_photo = "../assets/southern.png";
$admin_photo = $default_admin_photo;
$admin_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

if ($admin_id > 0) {
    $admin_stmt = $conn->prepare("SELECT profile_photo FROM admin WHERE id = ?");
    $admin_stmt->bind_param("i", $admin_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result()->fetch_assoc();

    if ($admin_result && !empty($admin_result['profile_photo']) && file_exists("../assets/uploads/admin/" . $admin_result['profile_photo'])) {
        $admin_photo = "../assets/uploads/admin/" . $admin_result['profile_photo'];
    }
}

$upload_dir = "../assets/uploads/teacher_album/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$edit_mode = false;
$edit_id = 0;
$edit_teacher = [
    'teacher_name' => '',
    'teacher_email' => '',
    'teacher_contact' => '',
    'teacher_department' => '',
    'teacher_photo' => ''
];

/* EDIT MODE LOAD */
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM teacher_album WHERE id = ?");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();

    if ($edit_result->num_rows > 0) {
        $edit_teacher = $edit_result->fetch_assoc();
        $edit_mode = true;
    }
}

/* ADD TEACHER */
if (isset($_POST['add_teacher_album'])) {
    $teacher_name = trim($_POST['teacher_name']);
    $teacher_email = trim($_POST['teacher_email']);
    $teacher_contact = trim($_POST['teacher_contact']);
    $teacher_department = trim($_POST['teacher_department']);
    $teacher_photo_name = "";

    if (!empty($teacher_name)) {
        if (isset($_FILES['teacher_photo']) && $_FILES['teacher_photo']['error'] === 0) {
            $file_tmp = $_FILES['teacher_photo']['tmp_name'];
            $file_name = $_FILES['teacher_photo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($file_ext, $allowed)) {
                $teacher_photo_name = time() . "_" . rand(1000, 9999) . "." . $file_ext;
                $destination = $upload_dir . $teacher_photo_name;
                move_uploaded_file($file_tmp, $destination);
            }
        }

        $insert = $conn->prepare("INSERT INTO teacher_album (teacher_name, teacher_photo, teacher_email, teacher_contact, teacher_department) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("sssss", $teacher_name, $teacher_photo_name, $teacher_email, $teacher_contact, $teacher_department);

        if ($insert->execute()) {
            header("Location: admin_teacher_album.php?added=1");
            exit;
        } else {
            $message = "Failed to add teacher profile.";
        }
    } else {
        $message = "Teacher name is required.";
    }
}

/* UPDATE TEACHER */
if (isset($_POST['update_teacher_album'])) {
    $update_id = intval($_POST['teacher_id']);
    $teacher_name = trim($_POST['teacher_name']);
    $teacher_email = trim($_POST['teacher_email']);
    $teacher_contact = trim($_POST['teacher_contact']);
    $teacher_department = trim($_POST['teacher_department']);
    $old_photo = trim($_POST['old_photo']);
    $teacher_photo_name = $old_photo;

    if (!empty($teacher_name)) {
        if (isset($_FILES['teacher_photo']) && $_FILES['teacher_photo']['error'] === 0) {
            $file_tmp = $_FILES['teacher_photo']['tmp_name'];
            $file_name = $_FILES['teacher_photo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($file_ext, $allowed)) {
                $new_photo_name = time() . "_" . rand(1000, 9999) . "." . $file_ext;
                $destination = $upload_dir . $new_photo_name;

                if (move_uploaded_file($file_tmp, $destination)) {
                    if (!empty($old_photo) && file_exists($upload_dir . $old_photo)) {
                        unlink($upload_dir . $old_photo);
                    }
                    $teacher_photo_name = $new_photo_name;
                }
            }
        }

        $update = $conn->prepare("UPDATE teacher_album SET teacher_name = ?, teacher_photo = ?, teacher_email = ?, teacher_contact = ?, teacher_department = ? WHERE id = ?");
        $update->bind_param("sssssi", $teacher_name, $teacher_photo_name, $teacher_email, $teacher_contact, $teacher_department, $update_id);

        if ($update->execute()) {
            header("Location: admin_teacher_album.php?updated=1");
            exit;
        } else {
            $message = "Failed to update teacher profile.";
        }
    } else {
        $message = "Teacher name is required.";
    }
}

/* DELETE TEACHER */
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    $photo_stmt = $conn->prepare("SELECT teacher_photo FROM teacher_album WHERE id = ?");
    $photo_stmt->bind_param("i", $delete_id);
    $photo_stmt->execute();
    $photo_result = $photo_stmt->get_result();

    if ($photo_result->num_rows > 0) {
        $photo_data = $photo_result->fetch_assoc();
        if (!empty($photo_data['teacher_photo']) && file_exists($upload_dir . $photo_data['teacher_photo'])) {
            unlink($upload_dir . $photo_data['teacher_photo']);
        }
    }

    $delete_stmt = $conn->prepare("DELETE FROM teacher_album WHERE id = ?");
    $delete_stmt->bind_param("i", $delete_id);

    if ($delete_stmt->execute()) {
        header("Location: admin_teacher_album.php?deleted=1");
        exit;
    } else {
        $message = "Failed to delete teacher profile.";
    }
}

if (isset($_GET['added']) && $_GET['added'] == '1') {
    $message = "Teacher profile added successfully.";
}

if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $message = "Teacher profile deleted successfully.";
}

if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $message = "Teacher profile updated successfully.";
}

/* GET ALL TEACHERS */
$teachers = $conn->query("SELECT * FROM teacher_album ORDER BY id DESC");
$totalTeachersAlbum = $teachers ? $teachers->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Teacher Album</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: #0f172a;
    color: #e5e7eb;
}

body.light-mode {
    background: #f4f7fb;
    color: #102a33;
}

.admin-wrapper {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */
.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 285px;
    height: 100vh;
    padding: 16px;
    background:
        radial-gradient(circle at top left, rgba(32, 220, 126, 0.20), transparent 34%),
        linear-gradient(180deg, #063946 0%, #03313c 52%, #021f29 100%);
    color: #fff;
    z-index: 1000;
    overflow-y: auto;
    box-shadow: 18px 0 45px rgba(0,0,0,0.24);
    border-right: 1px solid rgba(255,255,255,0.12);
}

.sidebar-shell {
    min-height: calc(100vh - 32px);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 22px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background: rgba(255,255,255,0.035);
}

.brand-mini {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 8px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.12);
}

.brand-icon {
    width: 38px;
    height: 38px;
    border-radius: 13px;
    background: linear-gradient(135deg, #13cf74, #8fbc67);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    box-shadow: 0 10px 20px rgba(18,201,107,0.28);
}

.brand-text {
    font-size: 17px;
    font-weight: 900;
    letter-spacing: .4px;
}

.profile-box {
    margin-top: 14px;
    padding: 24px 16px 20px;
    border-radius: 20px;
    text-align: center;
    background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.05));
    border: 1px solid rgba(255,255,255,0.13);
    box-shadow: 0 18px 35px rgba(0,0,0,0.22);
}

.profile-icon-wrap {
    width: 96px;
    height: 96px;
    margin: 0 auto 12px;
    padding: 4px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ffffff, #18d675);
    position: relative;
}

.profile-icon {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 3px solid #ffffff;
    object-fit: cover;
    background: #fff;
}

.online-dot {
    position: absolute;
    width: 20px;
    height: 20px;
    right: 7px;
    bottom: 8px;
    background: #2edb79;
    border: 3px solid #ffffff;
    border-radius: 50%;
}

.profile-box h3 {
    font-size: 22px;
    font-weight: 900;
    margin-bottom: 5px;
}

.profile-box p {
    color: #23e986;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 14px;
}

.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 999px;
    border: 1px solid rgba(46,219,121,0.75);
    color: #ffffff;
    font-size: 12px;
    font-weight: 900;
    background: rgba(18,201,107,0.16);
}

.menu-label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 20px 6px 12px;
    color: #9fbfc5;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.menu-label::before,
.menu-label::after {
    content: "";
    height: 1px;
    background: rgba(255,255,255,0.13);
    flex: 1;
}

.nav-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.side-btn {
    width: 100%;
    border: none;
    outline: none;
    text-decoration: none;
    color: #f5ffff;
    background: transparent;
    padding: 13px 14px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14.5px;
    font-weight: 900;
    cursor: pointer;
    transition: .22s ease;
}

.side-btn:hover {
    background: rgba(255,255,255,0.08);
    transform: translateX(4px);
}

.side-btn.active {
    background: linear-gradient(135deg, #18cf74, #8fbc67);
    box-shadow: 0 12px 24px rgba(18,201,107,0.28);
}

.side-icon {
    width: 26px;
    text-align: center;
    font-size: 18px;
}

.side-label {
    flex: 1;
    text-align: left;
}

.logout-btn {
    margin-top: 20px;
    background: rgba(255, 93, 87, 0.13);
    color: #ff7474;
    border: 1px solid rgba(255, 93, 87, 0.22);
}

.logout-btn:hover {
    background: rgba(255, 93, 87, 0.24);
    color: #ffffff;
}

/* MAIN */
.main-content {
    flex: 1;
    margin-left: 285px;
    min-width: 0;
}

.top-hero {
    background: linear-gradient(135deg, #063946 0%, #8fbc67 100%);
    padding: 30px 34px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
}

.school-brand {
    display: flex;
    align-items: center;
    gap: 18px;
}

.school-logo {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    border: 3px solid rgba(255,255,255,0.78);
    box-shadow: 0 10px 22px rgba(0,0,0,0.16);
}

.school-brand h1 {
    font-size: 22px;
    line-height: 1.2;
    font-weight: 900;
    letter-spacing: .3px;
}

.school-brand p {
    margin-top: 6px;
    font-size: 15px;
    opacity: .95;
    font-weight: 700;
}

.darkmode-toggle {
    height: 52px;
    padding: 0 24px;
    border: none;
    border-radius: 14px;
    color: #fff;
    font-weight: 900;
    cursor: pointer;
    background: #063946;
    box-shadow: 0 10px 20px rgba(0,0,0,0.20);
    transition: .22s ease;
}

.darkmode-toggle:hover {
    transform: translateY(-2px);
}

.content-area {
    padding: 28px;
}

.page-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}

.page-title h2 {
    font-size: 24px;
    color: #eafff7;
    font-weight: 900;
    margin-bottom: 6px;
}

.page-title p {
    color: #9fbfc5;
    font-size: 14px;
    font-weight: 700;
}

.stat-card {
    min-width: 210px;
    min-height: 96px;
    border-radius: 20px;
    background: #111827;
    border: 1px solid #243244;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-icon {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #13cf74, #079564);
    font-size: 25px;
}

.stat-card span {
    color: #94a3b8;
    font-size: 12px;
    font-weight: 900;
}

.stat-card h3 {
    font-size: 28px;
    color: #fff;
    line-height: 1;
    margin-top: 6px;
}

.form-card,
.album-card {
    background: #111827;
    border-radius: 22px;
    padding: 24px;
    box-shadow: 0 18px 42px rgba(0,0,0,0.22);
    border: 1px solid #243244;
    margin-bottom: 24px;
}

.card-title {
    font-size: 24px;
    font-weight: 900;
    color: #ffffff;
    margin-bottom: 18px;
}

.message {
    margin-bottom: 18px;
    padding: 14px 18px;
    border-radius: 14px;
    background: rgba(18,201,107,0.12);
    color: #6ee7b7;
    border: 1px solid rgba(18,201,107,0.25);
    font-weight: 800;
}

.teacher-form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.input-wrap {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.input-label {
    font-size: 13px;
    font-weight: 900;
    color: #dbeafe;
}

.teacher-form input {
    width: 100%;
    height: 50px;
    border-radius: 13px;
    border: 1px solid #334155;
    background: #0f172a;
    color: #e5e7eb;
    padding: 0 16px;
    font-size: 14px;
    outline: none;
}

.teacher-form input::placeholder {
    color: #94a3b8;
}

.teacher-form input:focus {
    border-color: #13cf74;
    box-shadow: 0 0 0 4px rgba(18,201,107,0.12);
}

.full-width {
    grid-column: 1 / -1;
}

.upload-box {
    border: 2px dashed #334155;
    border-radius: 16px;
    padding: 18px;
    background: #0f172a;
}

.upload-box input[type="file"] {
    height: auto;
    border: none;
    padding: 0;
    background: transparent;
}

.upload-note {
    margin-top: 10px;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 800;
}

.current-preview {
    width: 86px;
    height: 86px;
    border-radius: 18px;
    overflow: hidden;
    border: 2px solid #334155;
    margin-bottom: 12px;
}

.current-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.form-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.save-btn,
.cancel-btn {
    min-width: 175px;
    height: 50px;
    border-radius: 14px;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    cursor: pointer;
    transition: .22s ease;
}

.save-btn {
    background: linear-gradient(135deg, #13cf74, #079564);
    color: #fff;
    box-shadow: 0 10px 20px rgba(18,201,107,0.22);
}

.cancel-btn {
    background: #243244;
    color: #e5e7eb;
}

.save-btn:hover,
.cancel-btn:hover {
    transform: translateY(-2px);
}

/* SAME UI AS STUDENT TEACHER CARDS - ADMIN VERSION */
.album-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 32px;
}

.teacher-card {
    position: relative;
    min-height: 450px;
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid #edf3f1;
    box-shadow: 0 14px 32px rgba(15,23,42,.13);
    text-align: center;
    transition: .22s ease;
    padding: 0 18px 28px;
}

.teacher-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 38px rgba(15,23,42,.18);
}

.teacher-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 125px;
    background: linear-gradient(135deg, #e8f7de 0%, #e4f4e8 55%, #dff1ff 100%);
    clip-path: polygon(0 0,100% 0,100% 70%,0 92%);
}

.teacher-card:nth-child(2)::before {
    background: linear-gradient(135deg, #eef9e9 0%, #e5f1ff 100%);
}

.teacher-card:nth-child(3)::before {
    background: linear-gradient(135deg, #e8fff5 0%, #e6f4ff 100%);
}

.teacher-card:nth-child(4)::before {
    background: linear-gradient(135deg, #f1e9ff 0%, #eef7ff 100%);
}

.teacher-card:nth-child(5)::before {
    background: linear-gradient(135deg, #fff0e7 0%, #ffe4dc 100%);
}

.teacher-photo-wrap {
    width: 135px;
    height: 135px;
    border-radius: 50%;
    padding: 5px;
    background: #ffffff;
    border: 4px solid #cceec3;
    position: relative;
    z-index: 2;
    margin: 30px 0 22px 52px;
    box-shadow: 0 12px 26px rgba(0,0,0,.20);
}

.teacher-photo {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    display: block;
}

.teacher-dept {
    position: absolute;
    top: 20px;
    right: 18px;
    z-index: 3;
    padding: 9px 16px;
    border-radius: 999px;
    background: #e4f9df;
    border: 1px solid #bce8b2;
    color: #18b56a;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.teacher-name {
    position: relative;
    z-index: 2;
    min-height: 75px;
    margin: 20px 18px 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0d2b42;
    font-size: 27px;
    line-height: 1.12;
    letter-spacing: .4px;
    font-weight: 900;
    text-transform: uppercase;
}

.teacher-info {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    color: #486171;
    font-size: 16px;
    margin: 14px 16px;
    word-break: break-word;
}

.teacher-info.email::before {
    content: "✉";
    color: #2991c8;
    font-size: 16px;
}

.teacher-info.contact::before {
    content: "☎";
    color: #2991c8;
    font-size: 16px;
}

.card-actions {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: center;
    gap: 24px;
    margin-top: 28px;
    flex-wrap: wrap;
}

.edit-btn,
.delete-btn {
    width: 96px;
    height: 38px;
    border-radius: 9px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 900;
    color: #fff;
    transition: .22s ease;
}

.edit-btn {
    background: linear-gradient(135deg,#5b91ff,#176ff0);
}

.delete-btn {
    background: linear-gradient(135deg,#ff7777,#ff3434);
}

.edit-btn:hover,
.delete-btn:hover {
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 34px 16px;
    border-radius: 18px;
    background: #0f172a;
    border: 1px dashed #334155;
}

.empty-state h3 {
    color: #ffffff;
    font-size: 22px;
    margin-bottom: 8px;
}

.empty-state p {
    color: #94a3b8;
    font-weight: 700;
}

/* LIGHT MODE */
body.light-mode .content-area {
    background: #f4f7fb;
}

body.light-mode .page-title h2 {
    color: #102a33;
}

body.light-mode .page-title p {
    color: #516574;
}

body.light-mode .stat-card,
body.light-mode .form-card,
body.light-mode .album-card {
    background: #fff;
    border-color: #e5eef3;
    box-shadow: 0 18px 42px rgba(21, 48, 66, 0.10);
}

body.light-mode .stat-card h3,
body.light-mode .card-title {
    color: #102a33;
}

body.light-mode .teacher-form input,
body.light-mode .upload-box,
body.light-mode .empty-state {
    background: #ffffff;
    border-color: #e5eef3;
    color: #425768;
}

body.light-mode .input-label,
body.light-mode .empty-state h3 {
    color: #102a33;
}

body.light-mode .teacher-card {
    background: #ffffff;
    border-color: #e5eef3;
    box-shadow: 0 14px 30px rgba(21,48,66,0.10);
}

body.light-mode .teacher-name {
    color: #102a33;
}

body.light-mode .teacher-info {
    color: #425768;
}

body.light-mode .upload-note,
body.light-mode .stat-card span,
body.light-mode .empty-state p {
    color: #516574;
}

body.light-mode .darkmode-toggle {
    background: #fff;
    color: #063946;
}

body.light-mode .cancel-btn {
    background: #e5eef3;
    color: #102a33;
}

body.light-mode .message {
    background: #eafaf1;
    color: #047857;
    border-color: #bbf7d0;
}

/* DARK CARD COLORS */
body:not(.light-mode) .teacher-card {
    background: #111827;
    border-color: #243244;
}

body:not(.light-mode) .teacher-name {
    color: #f8fafc;
}

body:not(.light-mode) .teacher-info {
    color: #cbd5e1;
}

@media (max-width: 1400px) {
    .album-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1100px) {
    .album-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .teacher-form {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 800px) {
    .admin-wrapper {
        flex-direction: column;
    }

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .sidebar-shell {
        min-height: auto;
    }

    .main-content {
        margin-left: 0;
    }

    .top-hero {
        flex-direction: column;
        align-items: stretch;
    }

    .school-brand {
        flex-direction: column;
        text-align: center;
    }

    .page-title-row {
        flex-direction: column;
    }

    .stat-card {
        width: 100%;
    }

    .album-grid {
        grid-template-columns: 1fr;
    }

    .teacher-photo-wrap {
        margin-left: auto;
        margin-right: auto;
    }
}

@media (max-width: 540px) {
    .content-area {
        padding: 16px;
    }

    .form-card,
    .album-card {
        padding: 18px;
    }

    .save-btn,
    .cancel-btn {
        width: 100%;
    }

    .form-actions {
        flex-direction: column;
    }
}
</style>
</head>

<body>

<div class="admin-wrapper">
    <aside class="sidebar">
        <div class="sidebar-shell">
            <div>
                <div class="brand-mini">
                    <div class="brand-icon">🎓</div>
                    <div class="brand-text">ADMIN PANEL</div>
                </div>

                <div class="profile-box">
                    <div class="profile-icon-wrap">
                        <img src="<?php echo htmlspecialchars($admin_photo); ?>" alt="Admin Photo" class="profile-icon" onerror="this.src='../assets/southern.png';">
                        <span class="online-dot"></span>
                    </div>
                    <h3>Admin</h3>
                    <p><?php echo htmlspecialchars($admin_name); ?></p>
                    <div class="admin-badge">🛡 ADMIN PANEL</div>
                </div>

                <div class="menu-label">Main Navigation</div>

                <div class="nav-group">
                    <a class="side-btn" href="admin.php?view=teachers">
                        <span class="side-icon">👨‍🏫</span>
                        <span class="side-label">List of Teachers</span>
                    </a>

                    <a class="side-btn" href="admin.php?view=students">
                        <span class="side-icon">👥</span>
                        <span class="side-label">List of Students</span>
                    </a>

                    <a class="side-btn" href="recently_deleted.php">
                        <span class="side-icon">🗑</span>
                        <span class="side-label">Recently Deleted</span>
                    </a>

                    <a class="side-btn active" href="admin_teacher_album.php">
                        <span class="side-icon">🖼</span>
                        <span class="side-label">Teacher Album</span>
                    </a>

                    <a class="side-btn" href="admin_change_password.php">
                        <span class="side-icon">🔑</span>
                        <span class="side-label">Change Password</span>
                    </a>
                </div>
            </div>

            <a class="side-btn logout-btn" href="../auth/logout.php">
                <span class="side-icon">🚪</span>
                <span class="side-label">Log Out</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-hero">
            <div class="school-brand">
                <img src="../assets/logo2.png" class="school-logo" alt="Logo" onerror="this.style.display='none';">
                <div>
                    <h1>SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</h1>
                    <p>CLEARANCE COLLEGE DEPARTMENT</p>
                </div>
            </div>

            <button type="button" class="darkmode-toggle" id="darkModeToggle" onclick="toggleDarkMode()">☀️ LIGHT MODE</button>
        </div>

        <div class="content-area">
            <div class="page-title-row">
                <div class="page-title">
                    <h2>ADMIN TEACHER ALBUM</h2>
                    <p>Add, update, and manage teacher profile cards displayed in the system.</p>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">🖼</div>
                    <div>
                        <span>Total Teacher Profiles</span>
                        <h3><?php echo $totalTeachersAlbum; ?></h3>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <div class="card-title"><?php echo $edit_mode ? 'Edit Teacher Album' : 'Add Teacher Album'; ?></div>

                <?php if (!empty($message)): ?>
                    <div class="message"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="teacher-form">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="teacher_id" value="<?php echo $edit_teacher['id']; ?>">
                        <input type="hidden" name="old_photo" value="<?php echo htmlspecialchars($edit_teacher['teacher_photo']); ?>">
                    <?php endif; ?>

                    <div class="input-wrap">
                        <label class="input-label">Teacher Name</label>
                        <input type="text" name="teacher_name" placeholder="Enter teacher full name" required value="<?php echo htmlspecialchars($edit_teacher['teacher_name']); ?>">
                    </div>

                    <div class="input-wrap">
                        <label class="input-label">Teacher Email</label>
                        <input type="email" name="teacher_email" placeholder="Enter teacher email" value="<?php echo htmlspecialchars($edit_teacher['teacher_email']); ?>">
                    </div>

                    <div class="input-wrap">
                        <label class="input-label">Contact Number</label>
                        <input type="text" name="teacher_contact" placeholder="Enter teacher contact number" value="<?php echo htmlspecialchars($edit_teacher['teacher_contact']); ?>">
                    </div>

                    <div class="input-wrap">
                        <label class="input-label">Department / Position</label>
                        <input type="text" name="teacher_department" placeholder="Enter department or position" value="<?php echo htmlspecialchars($edit_teacher['teacher_department']); ?>">
                    </div>

                    <div class="input-wrap full-width">
                        <label class="input-label">Teacher Photo <?php echo $edit_mode ? '(Optional - leave blank if no change)' : ''; ?></label>

                        <div class="upload-box">
                            <?php if ($edit_mode && !empty($edit_teacher['teacher_photo']) && file_exists($upload_dir . $edit_teacher['teacher_photo'])): ?>
                                <div class="current-preview">
                                    <img src="<?php echo $upload_dir . htmlspecialchars($edit_teacher['teacher_photo']); ?>" alt="Current Photo">
                                </div>
                            <?php endif; ?>

                            <input type="file" name="teacher_photo" accept=".jpg,.jpeg,.png,.webp">
                            <div class="upload-note">Supported files: JPG, JPEG, PNG, WEBP</div>
                        </div>
                    </div>

                    <div class="full-width form-actions">
                        <?php if ($edit_mode): ?>
                            <button type="submit" name="update_teacher_album" class="save-btn">Update Teacher</button>
                            <a href="admin_teacher_album.php" class="cancel-btn">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_teacher_album" class="save-btn">Add Teacher</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="album-card">
                <div class="card-title">Teacher Album List</div>

                <?php if ($teachers && $teachers->num_rows > 0): ?>
                    <div class="album-grid">
                        <?php while ($teacher = $teachers->fetch_assoc()): ?>
                            <?php
                                $teacher_photo = (!empty($teacher['teacher_photo']) && file_exists($upload_dir . $teacher['teacher_photo']))
                                    ? $upload_dir . $teacher['teacher_photo']
                                    : "../assets/southern.png";
                            ?>

                            <div class="teacher-card">
                                <div class="teacher-dept">
                                    <?php echo !empty($teacher['teacher_department']) ? htmlspecialchars($teacher['teacher_department']) : 'Teacher'; ?>
                                </div>

                                <div class="teacher-photo-wrap">
                                    <img src="<?php echo htmlspecialchars($teacher_photo); ?>" alt="Teacher Photo" class="teacher-photo" onerror="this.src='../assets/southern.png';">
                                </div>

                                <div class="teacher-name">
                                    <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                                </div>

                                <div class="teacher-info email">
                                    <?php echo !empty($teacher['teacher_email']) ? htmlspecialchars($teacher['teacher_email']) : 'No email'; ?>
                                </div>

                                <div class="teacher-info contact">
                                    <?php echo !empty($teacher['teacher_contact']) ? htmlspecialchars($teacher['teacher_contact']) : 'No contact number'; ?>
                                </div>

                                <div class="card-actions">
                                    <a href="admin_teacher_album.php?edit=<?php echo $teacher['id']; ?>" class="edit-btn">Edit</a>
                                    <a href="admin_teacher_album.php?delete=<?php echo $teacher['id']; ?>" class="delete-btn" onclick="return confirm('Delete this teacher profile?')">Delete</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Teacher Album Yet</h3>
                        <p>Start by adding a teacher profile above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function applyDarkModeState() {
    const isLight = localStorage.getItem('site_darkmode') === 'disabled';
    const btn = document.getElementById('darkModeToggle');

    if (isLight) {
        document.body.classList.add('light-mode');
        if (btn) btn.innerHTML = '🌙 DARK MODE';
    } else {
        document.body.classList.remove('light-mode');
        if (btn) btn.innerHTML = '☀️ LIGHT MODE';
    }
}

function toggleDarkMode() {
    const isLight = document.body.classList.contains('light-mode');

    if (isLight) {
        document.body.classList.remove('light-mode');
        localStorage.setItem('site_darkmode', 'enabled');
    } else {
        document.body.classList.add('light-mode');
        localStorage.setItem('site_darkmode', 'disabled');
    }

    applyDarkModeState();
}

document.addEventListener('DOMContentLoaded', function() {
    applyDarkModeState();
});
</script>

</body>
</html>