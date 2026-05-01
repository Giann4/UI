<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'student' && $_SESSION['role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$message = "";
$message_type = "";

$user_stmt = $conn->prepare("SELECT id, firstname, lastname, email, contact_number, profile_photo, password, role FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

$upload_dir = "../assets/uploads/profile/";
$default_photo = "../assets/logo2.png";

$top_header_logo = "../assets/logo2.png";
if (!file_exists($top_header_logo)) {
    $top_header_logo = "../assets/southern.png";
}

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* UPLOAD / SAVE CROPPED PROFILE PHOTO */
if (isset($_POST['upload_photo'])) {

    $new_file_name = "";
    $target_file = "";

    if (isset($_POST['cropped_image']) && !empty($_POST['cropped_image'])) {

        $cropped_image = trim($_POST['cropped_image']);

        if (preg_match('/^data:image\/([a-zA-Z0-9]+);base64,/', $cropped_image, $matches)) {
            $file_ext = strtolower($matches[1]);

            if ($file_ext === 'jpeg') {
                $file_ext = 'jpg';
            }

            $allowed = ['jpg', 'png', 'gif', 'webp'];

            if (!in_array($file_ext, $allowed)) {
                $message = "Invalid cropped image format.";
                $message_type = "error";
            } else {
                $image_data = substr($cropped_image, strpos($cropped_image, ',') + 1);
                $image_data = str_replace(' ', '+', $image_data);
                $decoded_image = base64_decode($image_data);

                if ($decoded_image === false) {
                    $message = "Invalid cropped image data.";
                    $message_type = "error";
                } elseif (strlen($decoded_image) > 8 * 1024 * 1024) {
                    $message = "Cropped image is too large. Max 8MB only.";
                    $message_type = "error";
                } else {
                    $new_file_name = $user_role . "_" . $user_id . "_" . time() . "." . $file_ext;
                    $target_file = $upload_dir . $new_file_name;

                    if (file_put_contents($target_file, $decoded_image) !== false) {

                        if (!empty($user['profile_photo'])) {
                            $old_file = $upload_dir . $user['profile_photo'];
                            if (file_exists($old_file)) {
                                @unlink($old_file);
                            }
                        }

                        $update_photo = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                        $update_photo->bind_param("si", $new_file_name, $user_id);

                        if ($update_photo->execute()) {
                            $message = "Profile photo updated successfully.";
                            $message_type = "success";
                            $user['profile_photo'] = $new_file_name;
                        } else {
                            if (file_exists($target_file)) {
                                @unlink($target_file);
                            }
                            $message = "Photo saved, but database update failed.";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Failed to save cropped photo.";
                        $message_type = "error";
                    }
                }
            }
        } else {
            $message = "Invalid cropped image format.";
            $message_type = "error";
        }
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $file_name = $_FILES['profile_photo']['name'];
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_size = $_FILES['profile_photo']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_ext, $allowed)) {
            $message = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
            $message_type = "error";
        } elseif ($file_size > 5 * 1024 * 1024) {
            $message = "File size must not exceed 5MB.";
            $message_type = "error";
        } else {
            $mime = mime_content_type($file_tmp);
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array($mime, $allowed_mime)) {
                $message = "Invalid image file.";
                $message_type = "error";
            } else {
                if ($file_ext === 'jpeg') {
                    $file_ext = 'jpg';
                }

                $new_file_name = $user_role . "_" . $user_id . "_" . time() . "." . $file_ext;
                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $target_file)) {

                    if (!empty($user['profile_photo'])) {
                        $old_file = $upload_dir . $user['profile_photo'];
                        if (file_exists($old_file)) {
                            @unlink($old_file);
                        }
                    }

                    $update_photo = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $update_photo->bind_param("si", $new_file_name, $user_id);

                    if ($update_photo->execute()) {
                        $message = "Profile photo uploaded successfully.";
                        $message_type = "success";
                        $user['profile_photo'] = $new_file_name;
                    } else {
                        if (file_exists($target_file)) {
                            @unlink($target_file);
                        }
                        $message = "Photo uploaded, but database update failed.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Failed to upload photo.";
                    $message_type = "error";
                }
            }
        }
    } else {
        $message = "Please choose an image file first.";
        $message_type = "error";
    }
}

/* CHANGE PASSWORD */
if (isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all password fields.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirm password do not match.";
        $message_type = "error";
    } elseif (strlen($new_password) < 12) {
        $message = "Password must be at least 12 characters long.";
        $message_type = "error";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $message = "Password must contain at least 1 uppercase letter.";
        $message_type = "error";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $message = "Password must contain at least 1 number.";
        $message_type = "error";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $message = "Password must contain at least 1 special character.";
        $message_type = "error";
    } else {
        $stored_password = $user['password'];
        $password_matched = false;
        $new_hashed_password = "";

        if ($stored_password === md5($current_password)) {
            $password_matched = true;
            $new_hashed_password = md5($new_password);
        } elseif (password_verify($current_password, $stored_password)) {
            $password_matched = true;
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        } elseif ($stored_password === $current_password) {
            $password_matched = true;
            $new_hashed_password = md5($new_password);
        }

        if (!$password_matched) {
            $message = "Current password is incorrect.";
            $message_type = "error";
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_hashed_password, $user_id);

            if ($update_stmt->execute()) {
                $message = "Password changed successfully.";
                $message_type = "success";
                $user['password'] = $new_hashed_password;
            } else {
                $message = "Failed to update password.";
                $message_type = "error";
            }
        }
    }
}

/* PROFILE PHOTO PATH */
if (!empty($user['profile_photo']) && file_exists($upload_dir . $user['profile_photo'])) {
    $photo = $upload_dir . $user['profile_photo'];
} else {
    $photo = $default_photo;
}

/* DYNAMIC LINKS */
$dashboard_link = ($user_role === 'teacher') ? 'teacher.php' : 'student.php';
$page_title = ($user_role === 'teacher') ? 'TEACHER CHANGE PASSWORD' : 'CHANGE PASSWORD';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password</title>

<script>
(function () {
    const savedTheme = localStorage.getItem("site_theme");
    if (savedTheme === "dark") {
        document.documentElement.classList.add("dark-mode");
    }
})();
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}

:root{
    --sidebar-width:285px;
    --page-bg:#f3f7f6;
    --panel-bg:#ffffff;
    --panel-border:#dfece7;
    --text-main:#11353c;
    --text-soft:#4c6570;
    --text-muted:#718891;
    --green:#18cf74;
    --green2:#8fbc67;
    --dark-green:#063946;
    --shadow:0 18px 42px rgba(15, 23, 42, 0.09);
}

html.dark-mode{
    --page-bg:#0f172a;
    --panel-bg:#111827;
    --panel-border:#243244;
    --text-main:#f8fafc;
    --text-soft:#cbd5e1;
    --text-muted:#94a3b8;
    --shadow:0 18px 42px rgba(0,0,0,0.22);
}

body{
    min-height:100vh;
    background:var(--page-bg);
    color:var(--text-main);
}

.wrapper{
    display:flex;
    min-height:100vh;
}

.sidebar{
    position:fixed;
    inset:0 auto 0 0;
    width:var(--sidebar-width);
    height:100vh;
    padding:16px;
    background:
        radial-gradient(circle at top left, rgba(32,220,126,0.20), transparent 34%),
        linear-gradient(180deg, #063946 0%, #03313c 52%, #021f29 100%);
    color:#fff;
    z-index:1000;
    overflow-y:auto;
    box-shadow:18px 0 45px rgba(0,0,0,0.24);
    border-right:1px solid rgba(255,255,255,0.12);
}

.sidebar-top{
    min-height:calc(100vh - 32px);
    border:1px solid rgba(255,255,255,0.18);
    border-radius:22px;
    padding:14px;
    display:flex;
    flex-direction:column;
    background:rgba(255,255,255,0.035);
}

.brand-mini{
    display:flex;
    align-items:center;
    gap:12px;
    padding:8px 8px 16px;
    border-bottom:1px solid rgba(255,255,255,0.12);
}

.brand-dot{
    width:38px;
    height:38px;
    border-radius:13px;
    background:linear-gradient(135deg, #13cf74, #8fbc67);
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 10px 20px rgba(18,201,107,0.28);
}

.brand-dot::before{
    content:"🎓";
    font-size:20px;
}

.brand-text{
    font-size:17px;
    font-weight:900;
    letter-spacing:.4px;
    text-transform:uppercase;
}

.profile-card{
    margin-top:14px;
    padding:24px 16px 20px;
    border-radius:20px;
    text-align:center;
    background:linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.05));
    border:1px solid rgba(255,255,255,0.13);
    box-shadow:0 18px 35px rgba(0,0,0,0.22);
    overflow:hidden;
    position:relative;
}

.profile-card::before{
    content:"";
    position:absolute;
    left:0;
    right:0;
    top:0;
    height:78px;
    background:linear-gradient(135deg, rgba(143,188,103,0.28), rgba(81,184,255,0.14));
}

.profile-ring{
    width:98px;
    height:98px;
    margin:0 auto 12px;
    padding:4px;
    border-radius:50%;
    background:linear-gradient(135deg, #ffffff, #18d675);
    position:relative;
    z-index:2;
}

.profile-ring::after{
    content:"";
    position:absolute;
    width:20px;
    height:20px;
    right:7px;
    bottom:8px;
    background:#2edb79;
    border:3px solid #ffffff;
    border-radius:50%;
}

.profile-img{
    width:100%;
    height:100%;
    border-radius:50%;
    border:3px solid #ffffff;
    object-fit:cover;
    background:#fff;
    display:block;
}

.profile-card h3{
    position:relative;
    z-index:2;
    font-size:24px;
    font-weight:900;
    line-height:1.05;
    margin-bottom:7px;
    text-transform:uppercase;
}

.profile-card p{
    position:relative;
    z-index:2;
    font-size:13px;
    color:#d9eef2;
    margin-bottom:12px;
    word-break:break-word;
}

.role-badge{
    position:relative;
    z-index:2;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:9px 18px;
    border-radius:999px;
    background:linear-gradient(135deg, #a3cd76, #c5ec8f);
    color:#12341b;
    font-size:12px;
    font-weight:900;
}

.nav-title{
    display:flex;
    align-items:center;
    gap:10px;
    margin:20px 6px 12px;
    color:#9fbfc5;
    font-size:11px;
    font-weight:900;
    letter-spacing:1px;
    text-transform:uppercase;
}

.nav-title::before,
.nav-title::after{
    content:"";
    height:1px;
    background:rgba(255,255,255,0.13);
    flex:1;
}

.nav-group{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.sidebar a{
    width:100%;
    text-decoration:none;
    color:#f5ffff;
    background:transparent;
    padding:13px 14px;
    border-radius:14px;
    display:flex;
    align-items:center;
    gap:12px;
    font-size:14.5px;
    font-weight:900;
    transition:.22s ease;
}

.sidebar a:hover{
    background:rgba(255,255,255,0.08);
    transform:translateX(4px);
}

.sidebar a.active{
    background:linear-gradient(135deg, #aee0ff, #d4f1ff);
    color:#062d38;
    box-shadow:0 12px 24px rgba(18,201,107,0.18);
}

.nav-icon{
    width:26px;
    text-align:center;
    font-size:18px;
    flex-shrink:0;
}

.nav-text{
    flex:1;
    line-height:1.25;
}

.logout-link{
    margin-top:auto;
    background:rgba(255,93,87,0.13) !important;
    color:#ff7474 !important;
    border:1px solid rgba(255,93,87,0.22) !important;
}

.logout-link:hover{
    background:rgba(255,93,87,0.24) !important;
    color:#ffffff !important;
}

.main-content{
    margin-left:var(--sidebar-width);
    width:calc(100% - var(--sidebar-width));
    min-height:100vh;
    background:var(--page-bg);
}

.top-header{
    min-height:118px;
    background:
        radial-gradient(circle at 8% 30%, rgba(255,255,255,0.22), transparent 18%),
        linear-gradient(135deg, #063946 0%, #8fbc67 100%);
    color:#fff;
    padding:28px 34px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    font-size:22px;
    font-weight:900;
    letter-spacing:.4px;
    text-transform:uppercase;
    position:relative;
    overflow:hidden;
}

.top-header-brand{
    display:flex;
    align-items:center;
    gap:16px;
}

.top-header-logo{
    width:62px;
    height:62px;
    border-radius:50%;
    object-fit:cover;
    background:#fff;
    border:3px solid rgba(255,255,255,0.78);
    box-shadow:0 10px 22px rgba(0,0,0,0.16);
    flex-shrink:0;
}

.top-header span{
    display:block;
}

.top-header small{
    display:block;
    margin-top:6px;
    font-size:14px;
    color:#ecfff6;
    letter-spacing:.7px;
}

.theme-toggle-btn{
    height:50px;
    padding:0 24px;
    border:none;
    border-radius:14px;
    color:#063946;
    font-weight:900;
    cursor:pointer;
    background:#ffffff;
    box-shadow:0 10px 20px rgba(0,0,0,0.16);
    transition:.22s ease;
    white-space:nowrap;
}

.theme-toggle-btn:hover,
.submit-btn:hover,
.upload-submit:hover,
.upload-btn:hover{
    transform:translateY(-2px);
}

.content{
    padding:30px 34px 40px;
}

.welcome-box,
.card,
.profile-panel-card{
    background:var(--panel-bg);
    border:1px solid var(--panel-border);
    box-shadow:var(--shadow);
}

.welcome-box{
    border-radius:22px;
    padding:24px 26px;
    margin-bottom:22px;
    border-left:7px solid var(--green2);
}

.welcome-box h2{
    font-size:30px;
    color:var(--text-main);
    margin-bottom:8px;
}

.welcome-box p{
    color:var(--text-soft);
    font-size:15px;
    line-height:1.6;
}

.message{
    padding:14px 16px;
    border-radius:14px;
    margin-bottom:20px;
    font-weight:900;
    border-left:5px solid;
}

.message.success{
    background:#e9fff1;
    color:#0d7f40;
    border-color:#18cf74;
}

.message.error{
    background:#ffe8e8;
    color:#c62828;
    border-color:#ff4d4f;
}

.dark-mode .message.success{
    background:rgba(24,207,116,0.12);
    color:#d1fae5;
}

.dark-mode .message.error{
    background:rgba(255,77,79,0.12);
    color:#fecaca;
}

.page-grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:22px;
    align-items:start;
}

.card,
.profile-panel-card{
    border-radius:22px;
    padding:24px;
}

.card-title{
    font-size:28px;
    font-weight:900;
    margin-bottom:8px;
    color:var(--text-main);
}

.card-subtitle{
    color:var(--text-muted);
    font-size:14px;
    margin-bottom:20px;
    line-height:1.5;
}

.required{
    color:#ff4d4f;
}

.form-group{
    margin-bottom:16px;
}

.form-group label{
    display:block;
    font-size:14px;
    color:var(--text-main);
    font-weight:900;
    margin-bottom:8px;
}

.input-wrap{
    position:relative;
}

.form-group input{
    width:100%;
    height:54px;
    border-radius:15px;
    border:1px solid var(--panel-border);
    background:var(--panel-bg);
    color:var(--text-main);
    padding:0 78px 0 16px;
    font-size:15px;
    outline:none;
    font-weight:800;
}

.form-group input:focus{
    border-color:#18cf74;
    box-shadow:0 0 0 4px rgba(24,207,116,0.12);
}

.toggle-password{
    position:absolute;
    right:9px;
    top:50%;
    transform:translateY(-50%);
    background:#063946;
    color:#fff;
    border:none;
    border-radius:10px;
    padding:8px 12px;
    font-size:12px;
    font-weight:900;
    cursor:pointer;
    min-width:58px;
}

.submit-btn{
    margin-top:8px;
    width:100%;
    height:54px;
    border:none;
    border-radius:15px;
    background:linear-gradient(135deg, #13cf74, #079564);
    color:#ffffff;
    font-weight:900;
    cursor:pointer;
    transition:.22s ease;
}

.profile-panel-card{
    text-align:center;
}

.big-photo{
    width:150px;
    height:150px;
    border-radius:50%;
    object-fit:cover;
    border:5px solid #063946;
    margin:0 auto 18px;
    display:block;
    background:#0f3c43;
}

.dark-mode .big-photo{
    border-color:#8fbc67;
}

.upload-row{
    display:flex;
    justify-content:center;
    margin-bottom:12px;
    gap:8px;
    flex-wrap:wrap;
}

.upload-btn{
    display:inline-block;
    background:#063946;
    color:#ffffff;
    padding:10px 16px;
    border-radius:12px;
    font-size:13px;
    font-weight:900;
    cursor:pointer;
    transition:.22s ease;
}

.upload-submit{
    margin-top:12px;
    background:linear-gradient(135deg, #13cf74, #079564);
    color:#ffffff;
    border:none;
    padding:12px 18px;
    border-radius:12px;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
    transition:.22s ease;
}

.hidden-file{
    display:none;
}

.selected-file{
    margin-top:8px;
    font-size:13px;
    color:var(--text-muted);
    word-break:break-word;
    min-height:20px;
}

.preview-note{
    margin-top:8px;
    font-size:12px;
    color:var(--text-muted);
}

.profile-info{
    margin-top:20px;
}

.info-block{
    margin-bottom:16px;
}

.info-label{
    color:var(--text-muted);
    font-size:12px;
    font-weight:900;
    margin-bottom:6px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}

.info-value{
    font-size:16px;
    font-weight:900;
    color:var(--text-main);
    word-break:break-word;
}

.password-requirements-box{
    margin-top:18px;
    padding:18px;
    border-radius:20px;
    text-align:left;
    background:linear-gradient(135deg, #0c8c4e, #2ca568);
    border:1px solid rgba(255,255,255,0.25);
    box-shadow:0 14px 30px rgba(0,0,0,0.14);
}

.password-requirements-box h4{
    color:#ffffff;
    font-size:15px;
    font-weight:900;
    margin-bottom:13px;
    text-transform:uppercase;
    letter-spacing:.4px;
}

.password-requirements-box p{
    color:#ffffff;
    font-size:14px;
    margin:9px 0;
    display:flex;
    align-items:center;
    gap:8px;
}

.password-requirements-box p::before{
    content:"○";
    font-weight:900;
    color:#eafff2;
}

.password-requirements-box p.valid{
    color:#d9ff9d;
    font-weight:900;
}

.password-requirements-box p.valid::before{
    content:"✓";
    color:#d9ff9d;
}

.crop-modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.75);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:20px;
}

.crop-modal.show{
    display:flex;
}

.crop-modal-box{
    width:100%;
    max-width:820px;
    background:#0c3f45;
    border:1px solid rgba(255,255,255,0.12);
    border-radius:24px;
    padding:20px;
    box-shadow:0 20px 60px rgba(0,0,0,0.30);
}

.crop-modal-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:14px;
}

.crop-modal-title{
    color:#fff;
    font-size:22px;
    font-weight:900;
}

.crop-close{
    background:transparent;
    border:none;
    color:#fff;
    font-size:32px;
    line-height:1;
    cursor:pointer;
}

.crop-container{
    width:100%;
    max-height:500px;
    overflow:hidden;
    border-radius:18px;
    background:#102f34;
}

.crop-container img{
    display:block;
    max-width:100%;
}

.crop-modal-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    margin-top:16px;
    flex-wrap:wrap;
}

.crop-cancel-btn,
.crop-apply-btn{
    border:none;
    border-radius:12px;
    padding:11px 16px;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
}

.crop-cancel-btn{
    background:rgba(255,255,255,0.10);
    color:#fff;
}

.crop-apply-btn{
    background:#a9d466;
    color:#163328;
}

@media (max-width:950px){
    .page-grid{
        grid-template-columns:1fr;
    }
}

@media (max-width:850px){
    .wrapper{
        display:block;
    }

    .sidebar{
        position:relative;
        width:100%;
        height:auto;
    }

    .sidebar-top{
        min-height:auto;
    }

    .main-content{
        margin-left:0;
        width:100%;
    }

    .top-header{
        font-size:18px;
        padding:24px 18px;
        flex-direction:column;
        text-align:center;
        justify-content:center;
    }

    .top-header-brand{
        flex-direction:column;
    }

    .theme-toggle-btn{
        width:100%;
    }

    .content{
        padding:20px 14px;
    }

    .welcome-box h2,
    .card-title{
        font-size:24px;
    }

    .crop-modal-box{
        padding:14px;
    }

    .crop-container{
        max-height:380px;
    }
}
</style>
</head>

<body>

<div class="wrapper">
    <div class="sidebar">
        <div class="sidebar-top">
            <div>
                <div class="brand-mini">
                    <span class="brand-dot"></span>
                    <span class="brand-text"><?php echo $user_role === 'teacher' ? 'Teacher Panel' : 'Student Panel'; ?></span>
                </div>

                <div class="profile-card">
                    <div class="profile-ring">
                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Profile" class="profile-img" onerror="this.src='../assets/logo2.png';">
                    </div>

                    <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>

                    <div class="role-badge">
                        <?php echo strtoupper(htmlspecialchars($user_role)); ?>
                    </div>
                </div>

                <div class="nav-title">Navigation</div>

                <div class="nav-group">
                    <a href="<?php echo $dashboard_link; ?>" class="<?php echo ($current_page == basename($dashboard_link)) ? 'active' : ''; ?>">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Dashboard</span>
                    </a>

                    <?php if ($user_role === 'student'): ?>
                        <a href="student_result.php" class="<?php echo ($current_page == 'student_result.php') ? 'active' : ''; ?>">
                            <span class="nav-icon">📄</span>
                            <span class="nav-text">Result</span>
                        </a>
                    <?php endif; ?>

                    <a href="change_password.php" class="<?php echo ($current_page == 'change_password.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🔒</span>
                        <span class="nav-text">Change Password</span>
                    </a>

                    <?php if ($user_role === 'student'): ?>
                        <a href="all_teachers.php" class="<?php echo ($current_page == 'all_teachers.php') ? 'active' : ''; ?>">
                            <span class="nav-icon">👨‍🏫</span>
                            <span class="nav-text">List of All Teacher's in Southern</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <a href="../auth/logout.php" class="logout-link">
                <span class="nav-icon">↩</span>
                <span class="nav-text">Log Out</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="top-header-brand">
                <img 
                    src="<?php echo htmlspecialchars($top_header_logo); ?>" 
                    alt="School Logo" 
                    class="top-header-logo"
                    onerror="this.src='../assets/southern.png';"
                >

                <div>
                    <span>SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</span>
                    <small><?php echo $page_title; ?></small>
                </div>
            </div>

            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">🌙 DARK MODE</button>
        </div>

        <div class="content">

            <div class="welcome-box">
                <h2>Hi, <?php echo htmlspecialchars($user['firstname']); ?> 👋</h2>
                <p>
                    Keep your account secure by updating your password regularly and uploading a profile photo if needed.
                </p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="page-grid">

                <div class="card">
                    <div class="card-title">Update Your Password</div>
                    <div class="card-subtitle">Enter your current password and choose a new one.</div>

                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password <span class="required">*</span></label>
                            <div class="input-wrap">
                                <input type="password" name="current_password" id="current_password" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('current_password', this)">Show</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>New Password <span class="required">*</span></label>
                            <div class="input-wrap">
                                <input type="password" name="new_password" id="new_password" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('new_password', this)">Show</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirm Password <span class="required">*</span></label>
                            <div class="input-wrap">
                                <input type="password" name="confirm_password" id="confirm_password" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">Show</button>
                            </div>
                        </div>

                        <button type="submit" name="change_password" class="submit-btn">Save New Password</button>
                    </form>
                </div>

                <div class="profile-panel-card">
                    <form method="POST" enctype="multipart/form-data" id="photoForm">
                        <div class="upload-row">
                            <label for="fileInput" class="upload-btn">UPLOAD PHOTO</label>
                        </div>

                        <input type="file" id="fileInput" name="profile_photo" class="hidden-file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                        <input type="hidden" name="cropped_image" id="croppedImageInput">

                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Profile" id="mainPreviewPhoto" class="big-photo" onerror="this.src='../assets/logo2.png';">

                        <div id="selectedFile" class="selected-file">No file selected</div>
                        <div class="preview-note">After choosing a photo, crop it first before saving.</div>

                        <button type="submit" name="upload_photo" class="upload-submit">Save Photo</button>
                    </form>

                    <div class="profile-info">
                        <div class="info-block">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></div>
                        </div>

                        <div class="info-block">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>

                        <div class="info-block">
                            <div class="info-label">Contact</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['contact_number']); ?></div>
                        </div>
                    </div>

                    <div class="password-requirements-box">
                        <h4>🛡 Password Requirements</h4>
                        <p id="reqLength">At least 12 characters long</p>
                        <p id="reqUpper">At least 1 uppercase letter</p>
                        <p id="reqNumber">At least 1 number</p>
                        <p id="reqSpecial">At least 1 special character</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="crop-modal" id="cropModal">
    <div class="crop-modal-box">
        <div class="crop-modal-header">
            <div class="crop-modal-title">Crop Profile Photo</div>
            <button type="button" class="crop-close" id="closeCropModal">&times;</button>
        </div>

        <div class="crop-container">
            <img id="cropImage" src="" alt="Crop Preview">
        </div>

        <div class="crop-modal-actions">
            <button type="button" class="crop-cancel-btn" id="cancelCropBtn">Cancel</button>
            <button type="button" class="crop-apply-btn" id="applyCropBtn">Crop & Preview</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);

    if (input.type === "password") {
        input.type = "text";
        btn.textContent = "Hide";
    } else {
        input.type = "password";
        btn.textContent = "Show";
    }
}

function applyThemeButton() {
    const btn = document.getElementById("themeToggleBtn");
    const isDark = document.documentElement.classList.contains("dark-mode");

    if (!btn) return;

    btn.textContent = isDark ? "☀️ LIGHT MODE" : "🌙 DARK MODE";
}

function toggleTheme() {
    document.documentElement.classList.toggle("dark-mode");

    if (document.documentElement.classList.contains("dark-mode")) {
        localStorage.setItem("site_theme", "dark");
    } else {
        localStorage.setItem("site_theme", "light");
    }

    applyThemeButton();
}

document.addEventListener("DOMContentLoaded", function () {
    applyThemeButton();

    const newPasswordInput = document.getElementById("new_password");

    if (newPasswordInput) {
        newPasswordInput.addEventListener("input", function () {
            const password = this.value;

            document.getElementById("reqLength").classList.toggle("valid", password.length >= 12);
            document.getElementById("reqUpper").classList.toggle("valid", /[A-Z]/.test(password));
            document.getElementById("reqNumber").classList.toggle("valid", /[0-9]/.test(password));
            document.getElementById("reqSpecial").classList.toggle("valid", /[^A-Za-z0-9]/.test(password));
        });
    }

    const fileInput = document.getElementById("fileInput");
    const selectedFile = document.getElementById("selectedFile");
    const cropModal = document.getElementById("cropModal");
    const cropImage = document.getElementById("cropImage");
    const closeCropModal = document.getElementById("closeCropModal");
    const cancelCropBtn = document.getElementById("cancelCropBtn");
    const applyCropBtn = document.getElementById("applyCropBtn");
    const mainPreviewPhoto = document.getElementById("mainPreviewPhoto");
    const croppedImageInput = document.getElementById("croppedImageInput");
    const sidebarPhoto = document.querySelector(".profile-img");

    let cropper = null;
    let originalPreview = mainPreviewPhoto.getAttribute("src");

    fileInput.addEventListener("change", function () {
        const file = this.files[0];

        if (!file) {
            selectedFile.textContent = "No file selected";
            return;
        }

        selectedFile.textContent = file.name;

        const allowedTypes = ["image/jpeg", "image/png", "image/gif", "image/webp"];
        if (!allowedTypes.includes(file.type)) {
            alert("Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.");
            this.value = "";
            croppedImageInput.value = "";
            selectedFile.textContent = "No file selected";
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            cropImage.src = e.target.result;
            cropModal.classList.add("show");

            if (cropper) {
                cropper.destroy();
            }

            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: "move",
                autoCropArea: 1,
                responsive: true,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false
            });
        };

        reader.readAsDataURL(file);
    });

    function closeCropModalFunc(resetFile = false) {
        cropModal.classList.remove("show");

        if (cropper) {
            cropper.destroy();
            cropper = null;
        }

        if (resetFile) {
            fileInput.value = "";
            croppedImageInput.value = "";
            selectedFile.textContent = "No file selected";
            mainPreviewPhoto.src = originalPreview;

            if (sidebarPhoto) {
                sidebarPhoto.src = originalPreview;
            }
        }
    }

    closeCropModal.addEventListener("click", function () {
        closeCropModalFunc(true);
    });

    cancelCropBtn.addEventListener("click", function () {
        closeCropModalFunc(true);
    });

    cropModal.addEventListener("click", function (e) {
        if (e.target === cropModal) {
            closeCropModalFunc(true);
        }
    });

    applyCropBtn.addEventListener("click", function () {
        if (!cropper) return;

        const canvas = cropper.getCroppedCanvas({
            width: 500,
            height: 500,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: "high"
        });

        if (!canvas) {
            alert("Unable to crop image.");
            return;
        }

        const croppedData = canvas.toDataURL("image/png");
        croppedImageInput.value = croppedData;

        mainPreviewPhoto.src = croppedData;

        if (sidebarPhoto) {
            sidebarPhoto.src = croppedData;
        }

        closeCropModalFunc(false);
    });

    document.getElementById("photoForm").addEventListener("submit", function (e) {
        if (fileInput.files.length > 0 && croppedImageInput.value === "") {
            e.preventDefault();
            alert("Please crop the selected image first before saving.");
        }
    });
});
</script>

</body>
</html>