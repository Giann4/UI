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

/* GET ADMIN INFO */
if ($admin_id > 0) {
    $admin_stmt = $conn->prepare("SELECT id, name, email, password FROM admin WHERE id = ?");
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
    } elseif (strlen($new_password) < 4) {
        $message = "New password must be at least 4 characters.";
        $message_type = "error";
    } else {
        $stored_password = $admin['password'];
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Change Password</title>
    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, Helvetica, sans-serif;
        }

        body{
            background:
                radial-gradient(circle at top left, rgba(18, 201, 107, 0.08), transparent 28%),
                radial-gradient(circle at bottom right, rgba(3, 59, 70, 0.10), transparent 30%),
                #edf2f4;
            color:#1b1b1b;
        }

        .admin-wrapper{
            display:flex;
            min-height:100vh;
        }

        .sidebar{
            width:270px;
            background:rgba(3, 59, 70, 0.88);
            color:#fff;
            padding:24px 16px;
            position:sticky;
            top:0;
            height:100vh;
            box-shadow:6px 0 20px rgba(0,0,0,0.14);
            border-right:1px solid rgba(255,255,255,0.10);
            backdrop-filter:blur(14px);
            -webkit-backdrop-filter:blur(14px);
            display:flex;
            flex-direction:column;
            justify-content:space-between;
        }

        .profile-box{
            text-align:center;
            padding:14px 10px 24px;
            border:1px solid rgba(255,255,255,0.10);
            background:rgba(255,255,255,0.06);
            border-radius:24px;
            margin-bottom:22px;
            box-shadow:0 10px 24px rgba(0,0,0,0.12);
            backdrop-filter:blur(10px);
            -webkit-backdrop-filter:blur(10px);
        }

        .profile-icon-wrap{
            width:100px;
            height:100px;
            margin:0 auto 14px;
            border-radius:50%;
            padding:4px;
            background:linear-gradient(135deg, rgba(216,242,228,0.9), rgba(143,188,103,0.9));
            box-shadow:0 10px 24px rgba(0,0,0,0.18);
        }

        .profile-icon{
            width:100%;
            height:100%;
            border-radius:50%;
            background:#ffffff;
            color:#4d2c82;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:42px;
            border:3px solid #fff;
        }

        .profile-box h3{
            font-size:34px;
            margin-bottom:4px;
            font-weight:900;
            letter-spacing:1px;
            line-height:1;
        }

        .profile-box p{
            font-size:14px;
            color:#d9eef2;
            margin-bottom:10px;
            word-break:break-word;
        }

        .admin-badge{
            display:inline-block;
            padding:8px 14px;
            border-radius:999px;
            background:linear-gradient(135deg, #10c96b, #2de07f);
            color:#fff;
            font-size:12px;
            font-weight:800;
            letter-spacing:0.5px;
            box-shadow:0 6px 16px rgba(18, 201, 107, 0.22);
        }

        .menu-label{
            font-size:12px;
            text-transform:uppercase;
            letter-spacing:1px;
            color:#b8d5da;
            font-weight:800;
            margin:0 6px 12px;
        }

        .side-btn{
            display:block;
            width:100%;
            text-align:center;
            text-decoration:none;
            background:rgba(255,255,255,0.14);
            color:#ffffff;
            padding:15px 14px;
            border-radius:999px;
            font-weight:800;
            margin-bottom:14px;
            border:1px solid rgba(255,255,255,0.12);
            cursor:pointer;
            transition:0.25s ease;
            font-size:16px;
            box-shadow:0 6px 14px rgba(0,0,0,0.10);
        }

        .side-btn:hover{
            transform:translateY(-2px);
            background:rgba(255,255,255,0.22);
        }

        .side-btn.active{
            background:linear-gradient(135deg, rgba(16, 201, 107, 0.92), rgba(45, 224, 127, 0.88));
            color:#ffffff;
            box-shadow:0 10px 20px rgba(18, 201, 107, 0.25);
        }

        .main-content{
            flex:1;
            min-width:0;
        }

        .top-header{
            background:linear-gradient(135deg, #98c76b, #85b95d);
            color:#111;
            text-align:center;
            padding:24px 20px;
            font-size:25px;
            font-weight:900;
            letter-spacing:0.5px;
        }

        .sub-header{
            background:#033b46;
            color:#00ff8c;
            text-align:center;
            padding:14px 20px;
            font-size:21px;
            font-weight:900;
            letter-spacing:0.4px;
        }

        .content-area{
            padding:28px 24px;
        }

        .card{
            max-width:900px;
            background:#fff;
            border-radius:24px;
            box-shadow:0 12px 30px rgba(0,0,0,0.07);
            padding:26px;
        }

        .card h2{
            font-size:34px;
            color:#033b46;
            margin-bottom:8px;
        }

        .card p{
            color:#56666c;
            margin-bottom:22px;
            font-size:15px;
        }

        .message{
            margin-bottom:18px;
            padding:14px 16px;
            border-radius:14px;
            font-weight:800;
            font-size:14px;
        }

        .message.success{
            background:#e9fff1;
            color:#0d7f40;
            border:1px solid #a9e1bf;
        }

        .message.error{
            background:#ffeaea;
            color:#b10000;
            border:1px solid #ffb8b8;
        }

        .form-group{
            margin-bottom:18px;
        }

        .form-group label{
            display:block;
            font-size:14px;
            font-weight:800;
            color:#1d3c43;
            margin-bottom:8px;
        }

        .required{
            color:#ff4d4f;
        }

        .input-wrap{
            position:relative;
        }

        .form-group input{
            width:100%;
            height:56px;
            padding:0 80px 0 16px;
            border:1.5px solid #d6dee2;
            border-radius:14px;
            font-size:15px;
            outline:none;
            transition:0.25s ease;
        }

        .form-group input:focus{
            border-color:#12c96b;
            box-shadow:0 0 0 4px rgba(18, 201, 107, 0.12);
        }

        .toggle-password{
            position:absolute;
            right:10px;
            top:50%;
            transform:translateY(-50%);
            border:none;
            border-radius:10px;
            background:#edf3f5;
            color:#26464d;
            padding:8px 12px;
            font-size:12px;
            font-weight:800;
            cursor:pointer;
        }

        .save-btn{
            border:none;
            border-radius:14px;
            background:linear-gradient(135deg, #0fb761, #0a944d);
            color:#fff;
            font-weight:800;
            padding:15px 24px;
            cursor:pointer;
            min-width:200px;
            font-size:15px;
            box-shadow:0 10px 20px rgba(10, 148, 77, 0.18);
        }

        .save-btn:hover{
            opacity:0.95;
            transform:translateY(-1px);
        }

        .logout-btn{
            background:rgba(255,255,255,0.08) !important;
        }

        @media (max-width: 900px){
            .admin-wrapper{
                flex-direction:column;
            }

            .sidebar{
                width:100%;
                height:auto;
                position:relative;
            }

            .card{
                max-width:100%;
            }

            .top-header{
                font-size:20px;
            }

            .sub-header{
                font-size:17px;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <div class="sidebar">
        <div>
            <div class="profile-box">
                <div class="profile-icon-wrap">
                    <div class="profile-icon">👤</div>
                </div>
                <h3>ADMIN</h3>
                <p><?php echo htmlspecialchars($admin_name); ?></p>
                <div class="admin-badge">ADMIN PANEL</div>
            </div>

            <div class="menu-label">Navigation</div>

            <a class="side-btn" href="admin.php?view=students">Dashboard</a>
            <a class="side-btn" href="admin.php?view=teachers">List of Teacher</a>
            <a class="side-btn" href="admin_teacher_album.php">Teacher Album</a>
            <a class="side-btn">Reports</a>
            <a class="side-btn active" href="admin_change_password.php">Change Password</a>
        </div>

        <div>
            <a class="side-btn logout-btn" href="../auth/logout.php">Log Out</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</div>
        <div class="sub-header">ADMIN CHANGE PASSWORD</div>

        <div class="content-area">
            <div class="card">
                <h2>Change Your Password</h2>
                <p>Enter your current password and set a new one for your admin account.</p>

                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

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
        </div>
    </div>
</div>

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
</script>

</body>
</html>