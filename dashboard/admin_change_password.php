<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$message = "";
$message_type = "";
$current_page = basename($_SERVER['PHP_SELF']);

$admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$admin_name = isset($_SESSION['name']) && !empty($_SESSION['name']) ? $_SESSION['name'] : 'Administrator';
$admin_email = "admin@gmail.com";
$default_photo = "../assets/southern.png";
$upload_dir = "../assets/uploads/admin/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* GET ADMIN INFO */
if ($admin_id > 0) {
    $admin_stmt = $conn->prepare("SELECT id, name, email, password, profile_photo FROM admin WHERE id = ?");
    $admin_stmt->bind_param("i", $admin_id);
    $admin_stmt->execute();
    $admin = $admin_stmt->get_result()->fetch_assoc();

    if ($admin) {
        $admin_name = $admin['name'];
        $admin_email = $admin['email'];
    } else {
        die("Admin not found.");
    }
} else {
    die("Admin session not found.");
}

/* UPLOAD / SAVE CROPPED ADMIN PHOTO */
if (isset($_POST['upload_photo'])) {
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
                    $new_file_name = "admin_" . $admin_id . "_" . time() . "." . $file_ext;
                    $target_file = $upload_dir . $new_file_name;

                    if (file_put_contents($target_file, $decoded_image) !== false) {
                        if (!empty($admin['profile_photo'])) {
                            $old_file = $upload_dir . $admin['profile_photo'];
                            if (file_exists($old_file)) {
                                @unlink($old_file);
                            }
                        }

                        $update_photo = $conn->prepare("UPDATE admin SET profile_photo = ? WHERE id = ?");
                        $update_photo->bind_param("si", $new_file_name, $admin_id);

                        if ($update_photo->execute()) {
                            $message = "Admin profile photo uploaded successfully.";
                            $message_type = "success";
                            $admin['profile_photo'] = $new_file_name;
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
    } else {
        $message = "Please choose and crop an image first.";
        $message_type = "error";
    }
}

/* CHANGE PASSWORD */
if (isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    $has_length = strlen($new_password) >= 12;
    $has_uppercase = preg_match('/[A-Z]/', $new_password);
    $has_number = preg_match('/[0-9]/', $new_password);
    $has_special = preg_match('/[\W_]/', $new_password);

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all password fields.";
        $message_type = "error";
    } elseif (!$has_length || !$has_uppercase || !$has_number || !$has_special) {
        $message = "New password must meet all password requirements.";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirm password do not match.";
        $message_type = "error";
    } else {
        $stored_password = $admin['password'];
        $password_matched = false;

        if ($stored_password === md5($current_password)) {
            $password_matched = true;
        } elseif (password_verify($current_password, $stored_password)) {
            $password_matched = true;
        } elseif ($stored_password === $current_password) {
            $password_matched = true;
        }

        if (!$password_matched) {
            $message = "Current password is incorrect.";
            $message_type = "error";
        } else {
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $update_stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_hashed_password, $admin_id);

            if ($update_stmt->execute()) {
                $message = "Admin password changed successfully.";
                $message_type = "success";
                $admin['password'] = $new_hashed_password;
            } else {
                $message = "Failed to update admin password.";
                $message_type = "error";
            }
        }
    }
}

/* ADMIN PHOTO PATH */
if (!empty($admin['profile_photo']) && file_exists($upload_dir . $admin['profile_photo'])) {
    $admin_photo = $upload_dir . $admin['profile_photo'];
} else {
    $admin_photo = $default_photo;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Change Password</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">

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

.page-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    align-items: start;
}

.card,
.profile-panel-card {
    background: #111827;
    border: 1px solid #243244;
    border-radius: 22px;
    padding: 26px;
    box-shadow: 0 18px 42px rgba(0,0,0,0.22);
}

.card h2 {
    font-size: 30px;
    color: #ffffff;
    margin-bottom: 8px;
}

.card-sub {
    color: #94a3b8;
    margin-bottom: 22px;
    font-size: 15px;
    font-weight: 700;
}

.message {
    margin-bottom: 18px;
    padding: 14px 18px;
    border-radius: 14px;
    font-weight: 800;
    font-size: 14px;
}

.message.success {
    background: rgba(18,201,107,0.12);
    color: #6ee7b7;
    border: 1px solid rgba(18,201,107,0.25);
}

.message.error {
    background: rgba(239,68,68,0.12);
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.25);
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 900;
    color: #dbeafe;
    margin-bottom: 8px;
}

.required {
    color: #ff5d57;
}

.input-wrap {
    position: relative;
}

.form-group input {
    width: 100%;
    height: 56px;
    padding: 0 80px 0 16px;
    border: 1px solid #334155;
    border-radius: 14px;
    font-size: 15px;
    outline: none;
    transition: 0.25s ease;
    background: #0f172a;
    color: #f8fafc;
}

.form-group input:focus {
    border-color: #13cf74;
    box-shadow: 0 0 0 4px rgba(18, 201, 107, 0.12);
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    border-radius: 10px;
    background: #334155;
    color: #f8fafc;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}

.save-btn {
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, #13cf74, #079564);
    color: #fff;
    font-weight: 900;
    padding: 15px 24px;
    cursor: pointer;
    min-width: 200px;
    font-size: 15px;
    box-shadow: 0 10px 20px rgba(18,201,107,0.22);
    transition: .22s ease;
    margin-top: 6px;
}

.save-btn:hover {
    transform: translateY(-2px);
}

.upload-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 12px;
}

.upload-btn {
    display: inline-block;
    background: #334155;
    color: #f8fafc;
    border: none;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
}

.hidden-file {
    display: none;
}

.big-photo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid #8fbc67;
    margin: 0 auto 16px;
    display: block;
    background: #fff;
}

.selected-file {
    margin-top: 8px;
    font-size: 13px;
    color: #cbd5e1;
    word-break: break-word;
    min-height: 20px;
    text-align: center;
}

.upload-submit-wrap {
    display: flex;
    justify-content: center;
    margin-top: 14px;
}

.upload-submit {
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, #b6dd72, #8fbc67);
    color: #163328;
    font-weight: 900;
    padding: 12px 18px;
    cursor: pointer;
    font-size: 14px;
    min-width: 140px;
}

.info-block {
    margin-top: 18px;
    text-align: center;
}

.info-label {
    color: #94a3b8;
    font-size: 13px;
    font-weight: 900;
    margin-bottom: 6px;
    text-transform: uppercase;
}

.info-value {
    font-size: 18px;
    font-weight: 900;
    color: #f8fafc;
    word-break: break-word;
}

/* RIGHT SIDE PASSWORD REQUIREMENTS */
.helper-box {
    margin-top: 22px;
    background: linear-gradient(135deg, #13cf74, #079564);
    border: 1px solid rgba(255,255,255,0.22);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 14px 30px rgba(18,201,107,0.25);
}

.helper-box h4 {
    color: #ffffff;
    font-size: 16px;
    font-weight: 900;
    margin-bottom: 12px;
}

.password-side-box ul {
    list-style: none;
    display: grid;
    gap: 10px;
    margin-top: 12px;
}

.password-side-box li {
    color: #ffffff;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.4;
}

.password-side-box li::before {
    content: "○";
    margin-right: 9px;
    color: #eafff7;
    font-weight: 900;
}

.password-side-box li.valid::before {
    content: "✓";
    color: #ffffff;
}

.password-side-box li.valid {
    color: #ffffff;
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

body.light-mode .card,
body.light-mode .profile-panel-card {
    background: #ffffff;
    border-color: #e5eef3;
    box-shadow: 0 18px 42px rgba(21,48,66,0.10);
}

body.light-mode .card h2,
body.light-mode .info-value {
    color: #102a33;
}

body.light-mode .card-sub,
body.light-mode .info-label,
body.light-mode .selected-file {
    color: #516574;
}

body.light-mode .form-group label {
    color: #102a33;
}

body.light-mode .form-group input {
    background: #ffffff;
    color: #102a33;
    border-color: #dfe8ed;
}

body.light-mode .toggle-password,
body.light-mode .upload-btn {
    background: #e5eef3;
    color: #102a33;
}

body.light-mode .helper-box {
    background: linear-gradient(135deg, #13cf74, #8fbc67);
    border-color: #bbf7d0;
}

body.light-mode .helper-box h4,
body.light-mode .password-side-box li {
    color: #ffffff;
}

body.light-mode .darkmode-toggle {
    background: #ffffff;
    color: #063946;
}

body.light-mode .message.success {
    background: #eafaf1;
    color: #047857;
    border-color: #bbf7d0;
}

body.light-mode .message.error {
    background: #ffeaea;
    color: #b10000;
    border-color: #ffb8b8;
}

/* CROP MODAL */
.crop-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.75);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.crop-modal.show {
    display: flex;
}

.crop-modal-box {
    width: 100%;
    max-width: 820px;
    background: #0c3f45;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 24px;
    padding: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.30);
}

.crop-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
}

.crop-modal-title {
    color: #fff;
    font-size: 22px;
    font-weight: bold;
}

.crop-close {
    background: transparent;
    border: none;
    color: #fff;
    font-size: 32px;
    line-height: 1;
    cursor: pointer;
}

.crop-container {
    width: 100%;
    max-height: 500px;
    overflow: hidden;
    border-radius: 18px;
    background: #102f34;
}

.crop-container img {
    display: block;
    max-width: 100%;
}

.crop-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 16px;
    flex-wrap: wrap;
}

.crop-cancel-btn,
.crop-apply-btn {
    border: none;
    border-radius: 12px;
    padding: 11px 16px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

.crop-cancel-btn {
    background: rgba(255,255,255,0.10);
    color: #fff;
}

.crop-apply-btn {
    background: #a9d466;
    color: #163328;
}

@media (max-width: 950px) {
    .page-grid {
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
                        <img src="<?php echo htmlspecialchars($admin_photo); ?>" alt="Admin Profile" class="profile-icon" id="sidebarAdminPhoto" onerror="this.src='../assets/southern.png';">
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

                    <a class="side-btn" href="admin_teacher_album.php">
                        <span class="side-icon">🖼</span>
                        <span class="side-label">Teacher Album</span>
                    </a>

                    <a class="side-btn active" href="admin_change_password.php">
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
                    <h2>ADMIN CHANGE PASSWORD</h2>
                    <p>Update your admin password and profile photo securely.</p>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="page-grid">
                <div class="card">
                    <h2>Change Your Password</h2>
                    <div class="card-sub">Enter your current password and set a stronger new password for your admin account.</div>

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

                        <button type="submit" name="change_password" class="save-btn">Save New Password</button>
                    </form>
                </div>

                <div class="profile-panel-card">
                    <form method="POST" enctype="multipart/form-data" id="adminPhotoForm">
                        <div class="upload-row">
                            <label for="fileInput" class="upload-btn">UPLOAD</label>
                        </div>

                        <input type="file" id="fileInput" name="profile_photo" class="hidden-file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                        <input type="hidden" name="cropped_image" id="croppedImageInput">

                        <img src="<?php echo htmlspecialchars($admin_photo); ?>" alt="Admin Profile" class="big-photo" id="mainPreviewPhoto" onerror="this.src='../assets/southern.png';">

                        <div id="selectedFile" class="selected-file">No file selected</div>

                        <div class="upload-submit-wrap">
                            <button type="submit" name="upload_photo" class="upload-submit">Save Photo</button>
                        </div>
                    </form>

                    <div class="info-block">
                        <div class="info-label">Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($admin_name); ?></div>
                    </div>

                    <div class="info-block">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($admin_email); ?></div>
                    </div>

                    <div class="helper-box password-side-box">
                        <h4>🛡 PASSWORD REQUIREMENTS</h4>
                        <ul>
                            <li id="sideReqLength">At least 12 characters long</li>
                            <li id="sideReqUppercase">At least 1 uppercase letter</li>
                            <li id="sideReqNumber">At least 1 number</li>
                            <li id="sideReqSpecial">At least 1 special character</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="crop-modal" id="cropModal">
    <div class="crop-modal-box">
        <div class="crop-modal-header">
            <div class="crop-modal-title">Crop Admin Photo</div>
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

document.addEventListener("DOMContentLoaded", function () {
    applyDarkModeState();

    const newPasswordInput = document.getElementById("new_password");
    const sideReqLength = document.getElementById("sideReqLength");
    const sideReqUppercase = document.getElementById("sideReqUppercase");
    const sideReqNumber = document.getElementById("sideReqNumber");
    const sideReqSpecial = document.getElementById("sideReqSpecial");

    function setRequirementState(element, isValid) {
        if (element) {
            element.classList.toggle("valid", isValid);
        }
    }

    if (newPasswordInput) {
        newPasswordInput.addEventListener("input", function () {
            const value = this.value;

            setRequirementState(sideReqLength, value.length >= 12);
            setRequirementState(sideReqUppercase, /[A-Z]/.test(value));
            setRequirementState(sideReqNumber, /[0-9]/.test(value));
            setRequirementState(sideReqSpecial, /[\W_]/.test(value));
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
    const sidebarAdminPhoto = document.getElementById("sidebarAdminPhoto");
    const croppedImageInput = document.getElementById("croppedImageInput");
    const adminPhotoForm = document.getElementById("adminPhotoForm");

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
            sidebarAdminPhoto.src = originalPreview;
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
        sidebarAdminPhoto.src = croppedData;

        closeCropModalFunc(false);
    });

    adminPhotoForm.addEventListener("submit", function (e) {
        if (fileInput.files.length > 0 && croppedImageInput.value === "") {
            e.preventDefault();
            alert("Please crop the selected image first before saving.");
        }
    });
});
</script>

</body>
</html>