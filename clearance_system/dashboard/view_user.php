<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("User ID not found.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

$fullName = $user['lastname'] . ', ' . $user['firstname'];

if ($user['role'] === 'teacher') {
    $positionRole = "Instructor";
} else {
    $positionRole = strtoupper($user['role']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            background:
                linear-gradient(rgba(3, 59, 70, 0.82), rgba(3, 59, 70, 0.82)),
                url("../assets/southern.jpg") no-repeat center center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 16px;
        }

        .view-page {
            width: 100%;
            max-width: 1080px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.28);
        }

        .view-header {
            background: linear-gradient(135deg, #0a944d, #033b46);
            color: #fff;
            padding: 28px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .view-header-left h1 {
            font-size: 34px;
            font-weight: 900;
            margin-bottom: 6px;
        }

        .view-header-left p {
            font-size: 14px;
            opacity: 0.92;
        }

        .back-link {
            text-decoration: none;
            background: #fff;
            color: #033b46;
            padding: 12px 18px;
            border-radius: 14px;
            font-weight: 800;
            transition: 0.25s ease;
        }

        .back-link:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(0,0,0,0.15);
        }

        .view-content {
            padding: 28px;
        }

        .view-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 26px;
        }

        .user-card {
            background: linear-gradient(180deg, #f7fbf8, #eef5f1);
            border: 1px solid #dbe7e0;
            border-radius: 24px;
            padding: 28px 20px;
            text-align: center;
        }

        .avatar-circle {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            margin: 0 auto 18px;
            background: linear-gradient(135deg, #10c766, #033b46);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 54px;
            font-weight: 900;
            box-shadow: 0 12px 24px rgba(0,0,0,0.14);
        }

        .user-card h2 {
            font-size: 25px;
            color: #12353b;
            margin-bottom: 8px;
        }

        .role-badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            background: #dff5e8;
            color: #0a944d;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .side-info {
            text-align: left;
            margin-top: 10px;
        }

        .side-info div {
            background: #fff;
            border: 1px solid #e0ebe5;
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #3c474d;
            line-height: 1.5;
        }

        .side-info strong {
            color: #12353b;
        }

        .details-card {
            background: #fff;
            border: 1px solid #e4ece7;
            border-radius: 24px;
            padding: 28px;
        }

        .details-title {
            font-size: 24px;
            font-weight: 900;
            color: #12353b;
            margin-bottom: 22px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .info-row {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 16px;
            align-items: center;
        }

        .info-row label {
            font-size: 15px;
            font-weight: 800;
            color: #12353b;
        }

        .info-value {
            min-height: 56px;
            background: #f8fbf9;
            border: 2px solid #d8e3dd;
            border-radius: 16px;
            display: flex;
            align-items: center;
            padding: 0 18px;
            font-size: 15px;
            color: #26353a;
            font-weight: 700;
            word-break: break-word;
        }

        .password-mask {
            letter-spacing: 2px;
            color: #6a757a;
        }

        .course-badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            background: #eef3f6;
            color: #364247;
            font-size: 12px;
            font-weight: 800;
        }

        .action-row {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            text-decoration: none;
            border-radius: 15px;
            padding: 14px 22px;
            font-size: 15px;
            font-weight: 800;
            transition: 0.25s ease;
            display: inline-block;
        }

        .edit-btn {
            background: linear-gradient(135deg, #10c766, #06984b);
            color: #fff;
            box-shadow: 0 12px 24px rgba(5, 143, 72, 0.2);
        }

        .back-btn {
            background: #eaf0f2;
            color: #12353b;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 920px) {
            .view-layout {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 18px 10px;
            }

            .view-header {
                padding: 22px 18px;
            }

            .view-header-left h1 {
                font-size: 28px;
            }

            .view-content {
                padding: 18px;
            }

            .details-card,
            .user-card {
                padding: 20px;
            }

            .action-row {
                justify-content: stretch;
            }

            .action-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="view-page">
    <div class="view-header">
        <div class="view-header-left">
            <h1>User Information</h1>
            <p>View complete user details from the admin dashboard</p>
        </div>

        <a href="admin.php?view=<?php echo ($user['role'] === 'teacher') ? 'teachers' : 'students'; ?>" class="back-link">
            ← Back
        </a>
    </div>

    <div class="view-content">
        <div class="view-layout">

            <div class="user-card">
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($user['firstname'], 0, 1)); ?>
                </div>

                <h2><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h2>

                <div class="role-badge">
                    <?php echo htmlspecialchars($positionRole); ?>
                </div>

                <div class="side-info">
                    <div>
                        <strong>User ID:</strong><br>
                        #<?php echo $user['id']; ?>
                    </div>

                    <div>
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </div>

                    <div>
                        <strong>Contact Number:</strong><br>
                        <?php echo htmlspecialchars($user['contact_number']); ?>
                    </div>

                    <?php if (!empty($user['course'])): ?>
                    <div>
                        <strong>Course:</strong><br>
                        <?php echo htmlspecialchars($user['course']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="details-card">
                <div class="details-title">Profile Details</div>

                <div class="info-grid">
                    <div class="info-row">
                        <label>Full Name</label>
                        <div class="info-value"><?php echo htmlspecialchars($fullName); ?></div>
                    </div>

                    <div class="info-row">
                        <label>Email</label>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>

                    <div class="info-row">
                        <label>Contact Number</label>
                        <div class="info-value"><?php echo htmlspecialchars($user['contact_number']); ?></div>
                    </div>

                    <div class="info-row">
                        <label>Position / Role</label>
                        <div class="info-value"><?php echo htmlspecialchars($positionRole); ?></div>
                    </div>

                    <?php if ($user['role'] === 'student'): ?>
                    <div class="info-row">
                        <label>Course</label>
                        <div class="info-value">
                            <span class="course-badge"><?php echo htmlspecialchars($user['course']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <label>Password</label>
                        <div class="info-value password-mask">********</div>
                    </div>
                </div>

                <div class="action-row">
                    <a href="admin.php?view=<?php echo ($user['role'] === 'teacher') ? 'teachers' : 'students'; ?>" class="action-btn back-btn">Back</a>
                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="action-btn edit-btn">Edit User</a>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>