<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
    header("Location: teacher.php");
    exit;
}

$class_id = intval($_GET['class_id']);
$message = "";
$message_type = "";

/* TEACHER INFO */
$user_stmt = $conn->prepare("SELECT firstname, lastname, email, profile_photo FROM users WHERE id = ? AND role = 'teacher'");
$user_stmt->bind_param("i", $teacher_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Teacher not found.");
}

$default_photo = "../assets/southern.png";
if (!empty($user['profile_photo']) && file_exists("../assets/uploads/profile/" . $user['profile_photo'])) {
    $photo = "../assets/uploads/profile/" . $user['profile_photo'];
} else {
    $photo = $default_photo;
}

/* GET CLASS INFO */
$class_stmt = $conn->prepare("SELECT * FROM teacher_classes WHERE id = ? AND teacher_id = ?");
$class_stmt->bind_param("ii", $class_id, $teacher_id);
$class_stmt->execute();
$class_result = $class_stmt->get_result();
$class = $class_result->fetch_assoc();

if (!$class) {
    header("Location: teacher.php");
    exit;
}

/* SAVE REVIEW */
if (isset($_POST['save_review'])) {
    $request_id = intval($_POST['request_id']);
    $result = trim($_POST['result']);
    $comment = trim($_POST['comment']);
    $date_signed = date("Y-m-d");
    $status = "Reviewed";

    $update_stmt = $conn->prepare("UPDATE class_requests SET status = ?, result = ?, comment = ?, date_signed = ? WHERE id = ? AND class_id = ?");
    $update_stmt->bind_param("ssssii", $status, $result, $comment, $date_signed, $request_id, $class_id);

    if ($update_stmt->execute()) {
        $message = "Student request updated successfully.";
        $message_type = "success";
    } else {
        $message = "Failed to update request.";
        $message_type = "error";
    }
}

/* GET REQUESTS */
$request_stmt = $conn->prepare("
    SELECT 
        cr.id,
        cr.subject,
        cr.status,
        cr.result,
        cr.comment,
        cr.date_signed,
        u.firstname,
        u.lastname,
        u.email,
        u.course
    FROM class_requests cr
    LEFT JOIN users u ON cr.student_id = u.id
    WHERE cr.class_id = ?
    ORDER BY cr.id DESC
");
$request_stmt->bind_param("i", $class_id);
$request_stmt->execute();
$requests = $request_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Requests</title>
    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, sans-serif;
        }

        body{
            background:#d9d9d9;
        }

        .wrapper{
            display:flex;
            min-height:100vh;
        }

        .sidebar{
            width:210px;
            background:#003b49;
            color:white;
            padding:20px 10px;
            text-align:center;
        }

        .profile-img{
            width:90px;
            height:90px;
            border-radius:50%;
            object-fit:cover;
            border:3px solid #fff;
            margin-bottom:12px;
        }

        .sidebar h3{
            font-size:18px;
            margin-bottom:4px;
        }

        .sidebar p{
            font-size:14px;
            margin-bottom:25px;
            word-break:break-word;
        }

        .sidebar a{
            display:block;
            text-decoration:none;
            background:#fff;
            color:#000;
            padding:16px;
            border-radius:30px;
            margin:14px 0;
            font-weight:bold;
            font-size:16px;
            text-align:center;
        }

        .sidebar a.active{
            background:#8fbc67;
        }

        .main-content{
            flex:1;
        }

        .top-header{
            background:#8fbc67;
            text-align:center;
            padding:20px 10px;
            font-size:24px;
            font-weight:bold;
            text-transform:uppercase;
        }

        .sub-header{
            background:#003b49;
            color:#00ff84;
            text-align:center;
            padding:12px 10px;
            font-size:24px;
            font-weight:bold;
            text-transform:uppercase;
        }

        .content{
            padding:25px;
        }

        .info-card,
        .table-card{
            background:#fff;
            border-radius:18px;
            padding:22px;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
            margin-bottom:20px;
        }

        .info-card h2{
            color:#003b49;
            margin-bottom:8px;
        }

        .info-card p{
            color:#555;
            margin-bottom:6px;
        }

        .message{
            padding:14px 16px;
            border-radius:12px;
            margin-bottom:18px;
            font-weight:bold;
        }

        .message.success{
            background:#d4edda;
            color:#155724;
            border:1px solid #b7dfbe;
        }

        .message.error{
            background:#f8d7da;
            color:#721c24;
            border:1px solid #efb7be;
        }

        .table-title{
            font-size:24px;
            color:#003b49;
            font-weight:bold;
            margin-bottom:16px;
        }

        .table-wrap{
            overflow-x:auto;
        }

        table{
            width:100%;
            border-collapse:collapse;
        }

        th, td{
            border:1px solid #d8d8d8;
            padding:12px 10px;
            text-align:center;
            font-size:14px;
        }

        th{
            background:#8fbc67;
            color:#000;
        }

        tr:nth-child(even) td{
            background:#fafafa;
        }

        select, textarea{
            width:100%;
            border:1px solid #ccc;
            border-radius:8px;
            padding:8px 10px;
            font-size:13px;
            outline:none;
        }

        textarea{
            resize:vertical;
            min-height:60px;
        }

        .save-btn{
            background:#003b49;
            color:#fff;
            border:none;
            border-radius:10px;
            padding:10px 14px;
            font-size:13px;
            font-weight:bold;
            cursor:pointer;
            margin-top:8px;
        }

        .status-badge{
            display:inline-block;
            padding:6px 12px;
            border-radius:20px;
            font-size:12px;
            font-weight:bold;
        }

        .requesting{
            background:#fff3cd;
            color:#856404;
        }

        .reviewed{
            background:#d4edda;
            color:#155724;
        }

        .back-btn{
            display:inline-block;
            margin-top:12px;
            text-decoration:none;
            background:#003b49;
            color:#fff;
            padding:10px 16px;
            border-radius:10px;
            font-weight:bold;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar">
        <img src="<?php echo $photo; ?>" alt="Profile" class="profile-img" onerror="this.src='../assets/southern.png';">
        <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
        <p><?php echo htmlspecialchars($user['email']); ?></p>

        <a href="teacher.php">Dashboard</a>
        <a href="teacher_request.php?class_id=<?php echo $class_id; ?>" class="active">List of Request</a>
        <a href="change_password.php">Change Password</a>
        <a href="../auth/logout.php">Log Out</a>
    </div>

    <div class="main-content">
        <div class="top-header">
            SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY
        </div>

        <div class="sub-header">
            CLASS REQUESTS
        </div>

        <div class="content">

            <div class="info-card">
                <h2><?php echo htmlspecialchars($class['subject']); ?></h2>
                <p><strong>Course:</strong> <?php echo htmlspecialchars($class['course']); ?></p>
                <p><strong>Class Code:</strong> <?php echo htmlspecialchars($class['class_code']); ?></p>
                <a href="teacher.php" class="back-btn">← Back to Dashboard</a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-title">Student Requests</div>

                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Result</th>
                            <th>Comment</th>
                            <th>Date Signed</th>
                            <th>Action</th>
                        </tr>

                        <?php
                        $count = 1;
                        if ($requests->num_rows > 0):
                            while ($row = $requests->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $count++; ?></td>
                            <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['course']); ?></td>
                            <td><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td>
                                <?php if ($row['status'] === 'Reviewed'): ?>
                                    <span class="status-badge reviewed">Reviewed</span>
                                <?php else: ?>
                                    <span class="status-badge requesting">Requesting</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($row['result']) ? htmlspecialchars($row['result']) : 'Pending'; ?></td>
                            <td><?php echo !empty($row['comment']) ? htmlspecialchars($row['comment']) : '---'; ?></td>
                            <td><?php echo !empty($row['date_signed']) ? htmlspecialchars($row['date_signed']) : '---'; ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">

                                    <select name="result" required>
                                        <option value="">Select</option>
                                        <option value="Passed">Passed</option>
                                        <option value="Failed">Failed</option>
                                        <option value="Incomplete">Incomplete</option>
                                    </select>

                                    <textarea name="comment" placeholder="Comment" required></textarea>

                                    <button type="submit" name="save_review" class="save-btn">Save</button>
                                </form>
                            </td>
                        </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="10">No student requests found.</td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>