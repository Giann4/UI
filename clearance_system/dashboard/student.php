<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$message = "";

/* STUDENT INFO */
$user_stmt = $conn->prepare("SELECT firstname, lastname, email, course, profile_photo FROM users WHERE id = ?");
$user_stmt->bind_param("i", $student_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Student not found.");
}

$photo = !empty($user['profile_photo'])
    ? "../assets/uploads/profile/" . $user['profile_photo']
    : "../assets/southern.png";

/* SAVE REQUEST */
if (isset($_POST['submit_request'])) {
    $subject = trim($_POST['subject']);
    $class_code = trim($_POST['class_code']);

    if (!empty($subject) && !empty($class_code)) {
        $check_class = $conn->prepare("SELECT id, subject FROM teacher_classes WHERE class_code = ?");
        $check_class->bind_param("s", $class_code);
        $check_class->execute();
        $class_result = $check_class->get_result();

        if ($class_result->num_rows > 0) {
            $class = $class_result->fetch_assoc();
            $class_id = $class['id'];

            if (strtolower($class['subject']) == strtolower($subject)) {
                $check_existing = $conn->prepare("SELECT id FROM class_requests WHERE student_id = ? AND class_id = ?");
                $check_existing->bind_param("ii", $student_id, $class_id);
                $check_existing->execute();
                $existing_result = $check_existing->get_result();

                if ($existing_result->num_rows > 0) {
                    $message = "You already requested this subject.";
                } else {
                    $insert = $conn->prepare("INSERT INTO class_requests (student_id, class_id, subject, status) VALUES (?, ?, ?, 'Requesting')");
                    $insert->bind_param("iis", $student_id, $class_id, $subject);

                    if ($insert->execute()) {
                        $message = "Request submitted successfully.";
                    } else {
                        $message = "Failed to submit request.";
                    }
                }
            } else {
                $message = "Subject does not match the class code.";
            }
        } else {
            $message = "Invalid class code.";
        }
    } else {
        $message = "Please fill in all fields.";
    }
}

/* STUDENT REQUESTS */
$stmt = $conn->prepare("
    SELECT cr.id, cr.subject, cr.status, cr.result, cr.comment, cr.date_signed
    FROM class_requests cr
    WHERE cr.student_id = ?
    ORDER BY cr.id DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$requests = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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

        .box{
            background:#fff;
            border-radius:14px;
            padding:20px;
            margin-bottom:25px;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
        }

        .box h2{
            margin-bottom:15px;
            color:#003b49;
        }

        .message{
            margin-bottom:15px;
            padding:12px;
            border-radius:8px;
            background:#e7f5e7;
            color:#155724;
            font-weight:bold;
        }

        .request-form{
            display:grid;
            grid-template-columns:1fr 1fr auto;
            gap:12px;
            align-items:center;
        }

        .request-form input{
            padding:12px;
            border:1px solid #ccc;
            border-radius:8px;
            font-size:14px;
        }

        .request-form button{
            padding:12px 20px;
            border:none;
            background:#003b49;
            color:#fff;
            border-radius:8px;
            font-weight:bold;
            cursor:pointer;
        }

        .request-form button:hover{
            opacity:0.9;
        }

        .table-title{
            font-size:22px;
            margin-bottom:18px;
            color:#003b49;
            font-weight:bold;
        }

        table{
            width:100%;
            border-collapse:collapse;
        }

        table th, table td{
            border:1px solid #ccc;
            padding:12px 10px;
            text-align:center;
            font-size:14px;
        }

        table th{
            background:#8fbc67;
            color:#000;
        }

        .status-requesting{
            background:#fff3cd;
            color:#856404;
            font-weight:bold;
            padding:6px 12px;
            border-radius:20px;
            display:inline-block;
        }

        .status-reviewed{
            background:#d4edda;
            color:#155724;
            font-weight:bold;
            padding:6px 12px;
            border-radius:20px;
            display:inline-block;
        }

        .result-passed{
            color:green;
            font-weight:bold;
        }

        .result-failed{
            color:red;
            font-weight:bold;
        }

        .result-incomplete{
            color:orange;
            font-weight:bold;
        }

        .no-data{
            text-align:center;
            padding:20px;
            font-weight:bold;
            color:#666;
        }

        @media (max-width: 900px){
            .request-form{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar">
        <img src="<?php echo $photo; ?>" alt="Profile" class="profile-img">
        <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
        <p><?php echo htmlspecialchars($user['email']); ?></p>

        <a href="student.php" class="<?php echo ($current_page == 'student.php') ? 'active' : ''; ?>">Dashboard</a>
        <a href="student_result.php">Result</a>
        <a href="change_password.php">Change Password</a>
        <a href="../auth/logout.php">Log Out</a>
    </div>

    <div class="main-content">
        <div class="top-header">
            SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY
        </div>

        <div class="sub-header">
            STUDENT DASHBOARD
        </div>

        <div class="content">
    <div class="box">
    <h2>Hi, <?php echo htmlspecialchars($user['firstname']); ?> 👋</h2>
    <p>Welcome to your clearance dashboard. Dito mo pwedeng i-request ang subjects at makita ang status ng clearance mo.</p>
    </div>
            <div class="box">
                <h2>Request Clearance Subject</h2>

                <?php if (!empty($message)): ?>
                    <div class="message"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="POST" class="request-form">
                    <input type="text" name="subject" placeholder="Enter Subject" required>
                    <input type="text" name="class_code" placeholder="Enter Class Code" required>
                    <button type="submit" name="submit_request">Request</button>
                </form>
            </div>

            <div class="box">
                <div class="table-title">My Clearance Requests</div>

                <table>
                    <tr>
                        <th>#</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Result</th>
                        <th>Comment</th>
                        <th>Date Signed</th>
                    </tr>

                    <?php
                    $count = 1;
                    if ($requests->num_rows > 0):
                        while ($row = $requests->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo $count++; ?></td>
                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td>
                            <?php if ($row['status'] == 'Requesting'): ?>
                                <span class="status-requesting">Waiting for Approval</span>
                            <?php elseif ($row['status'] == 'Reviewed'): ?>
                                <span class="status-reviewed">Reviewed</span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($row['status']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($row['result'] == 'Passed') {
                                echo '<span class="result-passed">Passed</span>';
                            } elseif ($row['result'] == 'Failed') {
                                echo '<span class="result-failed">Failed</span>';
                            } elseif ($row['result'] == 'Incomplete') {
                                echo '<span class="result-incomplete">Incomplete</span>';
                            } else {
                                echo 'Pending';
                            }
                            ?>
                        </td>
                        <td><?php echo !empty($row['comment']) ? htmlspecialchars($row['comment']) : '---'; ?></td>
                        <td><?php echo !empty($row['date_signed']) ? htmlspecialchars($row['date_signed']) : '---'; ?></td>
                    </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="6" class="no-data">No requests found.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

        </div>
    </div>
</div>

</body>
</html>