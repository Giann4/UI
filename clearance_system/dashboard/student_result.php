<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

/* STUDENT INFO */
$user_stmt = $conn->prepare("SELECT firstname, lastname, email, contact_number, course, profile_photo FROM users WHERE id = ?");
$user_stmt->bind_param("i", $student_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Student not found.");
}

$default_photo = "../assets/southern.png";
if (!empty($user['profile_photo']) && file_exists("../assets/uploads/profile/" . $user['profile_photo'])) {
    $photo = "../assets/uploads/profile/" . $user['profile_photo'];
} else {
    $photo = $default_photo;
}

/* RESULT QUERY */
$stmt = $conn->prepare("
    SELECT 
        cr.subject,
        cr.result,
        cr.comment,
        cr.date_signed,
        CONCAT(u.lastname, ', ', u.firstname) AS instructor_name
    FROM class_requests cr
    LEFT JOIN teacher_classes tc ON cr.class_id = tc.id
    LEFT JOIN users u ON tc.teacher_id = u.id
    WHERE cr.student_id = ? AND cr.status = 'Reviewed'
    ORDER BY cr.id DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$total_subjects = 0;
$total_passed = 0;
$total_failed = 0;
$total_incomplete = 0;

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $total_subjects++;

    if ($row['result'] === 'Passed') {
        $total_passed++;
    } elseif ($row['result'] === 'Failed') {
        $total_failed++;
    } elseif ($row['result'] === 'Incomplete') {
        $total_incomplete++;
    }
}

$full_name = $user['lastname'] . ', ' . $user['firstname'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result</title>
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
            transition:0.2s ease;
        }

        .sidebar a.active{
            background:#76b3de;
        }

        .sidebar a:hover{
            transform:translateY(-1px);
            opacity:0.95;
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
            letter-spacing:0.5px;
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

        .welcome-box{
            background:#fff;
            border-radius:16px;
            padding:22px;
            margin-bottom:20px;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
        }

        .welcome-box h2{
            color:#003b49;
            margin-bottom:8px;
            font-size:28px;
        }

        .welcome-box p{
            color:#444;
            font-size:15px;
            line-height:1.5;
        }

        .stats-grid{
            display:grid;
            grid-template-columns:repeat(4, 1fr);
            gap:16px;
            margin-bottom:20px;
        }

        .stat-card{
            background:#fff;
            border-radius:16px;
            padding:20px;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
            text-align:center;
        }

        .stat-card h4{
            color:#003b49;
            font-size:15px;
            margin-bottom:10px;
        }

        .stat-card .number{
            font-size:28px;
            font-weight:bold;
        }

        .card{
            background:#fff;
            border-radius:18px;
            padding:20px;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
            margin-bottom:20px;
        }

        .card-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:15px;
            margin-bottom:18px;
            flex-wrap:wrap;
        }

        .card-title h3{
            color:#003b49;
            font-size:28px;
            margin-bottom:6px;
        }

        .card-title p{
            color:#555;
            font-size:14px;
        }

        .print-btn{
            background:#ff3131;
            color:#fff;
            border:none;
            padding:12px 28px;
            font-weight:bold;
            border-radius:12px;
            cursor:pointer;
            font-size:15px;
            box-shadow:0 4px 10px rgba(0,0,0,0.15);
        }

        .print-btn:hover{
            opacity:0.92;
        }

        .clearance-head{
            text-align:center;
            margin-bottom:18px;
            line-height:1.4;
        }

        .clearance-head .small{
            font-size:14px;
            color:#333;
        }

        .clearance-head .main{
            font-size:22px;
            font-weight:bold;
            color:#003b49;
        }

        .info-grid{
            display:grid;
            grid-template-columns:repeat(4, 1fr);
            gap:12px;
            margin-bottom:18px;
        }

        .info-box{
            border:1px solid #d4d4d4;
            border-radius:12px;
            background:#f8f8f8;
            padding:14px;
            text-align:center;
        }

        .info-box label{
            display:block;
            font-size:13px;
            color:#666;
            margin-bottom:6px;
            font-weight:bold;
        }

        .info-box span{
            font-size:17px;
            color:#111;
            font-weight:bold;
            word-break:break-word;
        }

        .request-text{
            text-align:center;
            font-size:14px;
            color:#333;
            line-height:1.6;
            margin:8px 0 18px;
            padding:0 10px;
        }

        .table-wrap{
            overflow-x:auto;
            border-radius:14px;
        }

        table{
            width:100%;
            border-collapse:collapse;
            overflow:hidden;
            border-radius:14px;
        }

        table th{
            background:#8fbc67;
            color:#000;
            padding:14px 10px;
            font-size:14px;
            border:1px solid #cfcfcf;
        }

        table td{
            padding:14px 10px;
            text-align:center;
            border:1px solid #d8d8d8;
            background:#fff;
            font-size:14px;
        }

        table tr:nth-child(even) td{
            background:#fafafa;
        }

        .status-badge{
            display:inline-block;
            min-width:100px;
            padding:8px 14px;
            border-radius:20px;
            font-weight:bold;
            font-size:13px;
        }

        .status-passed{
            background:#d4edda;
            color:#155724;
        }

        .status-failed{
            background:#f8d7da;
            color:#721c24;
        }

        .status-incomplete{
            background:#fff3cd;
            color:#856404;
        }

        .comment-text{
            font-weight:600;
            color:#333;
        }

        .empty-state{
            text-align:center;
            padding:35px 20px;
            color:#666;
            font-weight:bold;
        }

        /* PRINT-ONLY LAYOUT */
        #printLayout{
            display:none;
        }

        @media (max-width: 1100px){
            .stats-grid,
            .info-grid{
                grid-template-columns:repeat(2, 1fr);
            }
        }

        @media (max-width: 700px){
            .wrapper{
                flex-direction:column;
            }

            .sidebar{
                width:100%;
            }

            .content{
                padding:15px;
            }

            .stats-grid,
            .info-grid{
                grid-template-columns:1fr;
            }

            .card-title h3{
                font-size:22px;
            }

            .welcome-box h2{
                font-size:24px;
            }
        }

        @media print{
            @page{
                size:A4 portrait;
                margin:10mm;
            }

            body{
                background:#fff !important;
            }

            body *{
                visibility:hidden;
            }

            #printLayout,
            #printLayout *{
                visibility:visible;
            }

            #printLayout{
                display:block !important;
                position:absolute;
                left:0;
                top:0;
                width:100%;
                background:#fff;
            }

            .print-paper{
                width:100%;
                max-width:760px;
                margin:0 auto;
                color:#000;
                padding:0;
            }

            .print-top-mini{
                display:flex;
                justify-content:space-between;
                font-size:10px;
                margin-bottom:8px;
            }

            .print-header{
                text-align:center;
                margin-bottom:8px;
            }

            .print-header h1{
                font-size:18px;
                font-weight:900;
                line-height:1.2;
                margin:0 0 8px;
                text-transform:uppercase;
            }

            .print-line{
                border-top:1.5px solid #000;
                margin:6px 0;
            }

            .print-header h2{
                font-size:14px;
                font-weight:800;
                margin:0;
                text-transform:uppercase;
            }

            .print-subhead-left{
                margin:12px 0 8px;
            }

            .print-subhead-left h3{
                font-size:14px;
                margin:0 0 2px;
                font-weight:800;
            }

            .print-subhead-left p{
                font-size:11px;
                margin:0;
            }

            .print-center-title{
                text-align:center;
                margin:8px 0 12px;
            }

            .print-center-title h2{
                font-size:16px;
                margin:0 0 4px;
                font-weight:800;
            }

            .print-center-title p{
                margin:0;
                font-size:11px;
                line-height:1.4;
            }

            .print-info-grid{
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:8px;
                margin-bottom:12px;
            }

            .print-info-box{
                border:1px solid #000;
                text-align:center;
                padding:10px 8px;
                min-height:54px;
            }

            .print-info-box span{
                display:block;
                font-size:10px;
                margin-bottom:4px;
                color:#444;
                font-weight:700;
            }

            .print-info-box strong{
                font-size:12px;
                font-weight:800;
            }

            .print-message{
                text-align:center;
                font-size:11px;
                line-height:1.5;
                margin:10px auto 14px;
                max-width:92%;
            }

            .print-result-table{
                width:100%;
                border-collapse:collapse;
                font-size:10px;
            }

            .print-result-table th,
            .print-result-table td{
                border:1px solid #000;
                padding:5px 4px;
                text-align:center;
                vertical-align:middle;
                background:#fff !important;
                color:#000 !important;
            }

            .print-result-table th{
                font-weight:800;
                background:#f3f3f3 !important;
                -webkit-print-color-adjust:exact;
                print-color-adjust:exact;
            }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar">
        <img src="<?php echo $photo; ?>" alt="Profile" class="profile-img" onerror="this.src='../assets/southern.png';">
        <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
        <p><?php echo htmlspecialchars($user['email']); ?></p>

        <a href="student.php">Dashboard</a>
        <a href="student_result.php" class="active">Result</a>
        <a href="change_password.php">Change Password</a>
        <a href="../auth/logout.php">Log Out</a>
    </div>

    <div class="main-content">
        <div class="top-header">
            SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY
        </div>

        <div class="sub-header">
            CLEARANCE COLLEGE DEPARTMENT
        </div>

        <div class="content">

            <div class="welcome-box">
                <h2>Hi, <?php echo htmlspecialchars($user['firstname']); ?> 👋</h2>
                <p>
                    Here is your official clearance result summary. You can review your approved subjects and print this page anytime.
                </p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Reviewed Subjects</h4>
                    <div class="number"><?php echo $total_subjects; ?></div>
                </div>

                <div class="stat-card">
                    <h4>Passed</h4>
                    <div class="number"><?php echo $total_passed; ?></div>
                </div>

                <div class="stat-card">
                    <h4>Failed</h4>
                    <div class="number"><?php echo $total_failed; ?></div>
                </div>

                <div class="stat-card">
                    <h4>Incomplete</h4>
                    <div class="number"><?php echo $total_incomplete; ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h3>Student Clearance Result</h3>
                        <p>Printable academic clearance record</p>
                    </div>

                    <button class="print-btn" onclick="window.print()">PRINT</button>
                </div>

                <div class="clearance-head">
                    <div class="main">Student Clearance</div>
                    <div class="small">College Department</div>
                    <div class="small">School Year 2025-2026</div>
                </div>

                <div class="info-grid">
                    <div class="info-box">
                        <label>Name</label>
                        <span><?php echo htmlspecialchars($user['lastname'] . ', ' . $user['firstname']); ?></span>
                    </div>

                    <div class="info-box">
                        <label>Course</label>
                        <span><?php echo htmlspecialchars($user['course']); ?></span>
                    </div>

                    <div class="info-box">
                        <label>Email</label>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>

                    <div class="info-box">
                        <label>Contact</label>
                        <span><?php echo htmlspecialchars($user['contact_number']); ?></span>
                    </div>
                </div>

                <div class="request-text">
                    Good day. I would like to respectfully request clearance for this semester.
                    I have completed all required academic responsibilities. If there are remaining
                    requirements or concerns, please let me know so I can comply immediately.
                </div>

                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>#</th>
                            <th>Subject</th>
                            <th>Instructor</th>
                            <th>Comment</th>
                            <th>Status</th>
                            <th>Date Signed</th>
                        </tr>

                        <?php if (count($rows) > 0): ?>
                            <?php foreach ($rows as $index => $row): ?>
                                <?php
                                $badgeClass = 'status-passed';
                                if ($row['result'] === 'Failed') {
                                    $badgeClass = 'status-failed';
                                } elseif ($row['result'] === 'Incomplete') {
                                    $badgeClass = 'status-incomplete';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($row['instructor_name'] ?: 'N/A'); ?></td>
                                    <td class="comment-text"><?php echo htmlspecialchars($row['comment']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($row['result']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        echo !empty($row['date_signed'])
                                            ? date("F d, Y", strtotime($row['date_signed']))
                                            : 'N/A';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">No reviewed results yet.</td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- PRINT-ONLY LAYOUT -->
<div id="printLayout">
    <div class="print-paper">
        <div class="print-top-mini">
            <span><?php echo date("n/j/y, g:i A"); ?></span>
            <span>Student Result</span>
        </div>

        <div class="print-header">
            <h1>SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</h1>
            <div class="print-line"></div>
            <h2>CLEARANCE COLLEGE DEPARTMENT</h2>
        </div>

        <div class="print-subhead-left">
            <h3>Student Clearance Result</h3>
            <p>Printable academic clearance record</p>
        </div>

        <div class="print-center-title">
            <h2>Student Clearance</h2>
            <p>College Department</p>
            <p>School Year 2025-2026</p>
        </div>

        <div class="print-info-grid">
            <div class="print-info-box">
                <span>Name</span>
                <strong><?php echo htmlspecialchars($full_name); ?></strong>
            </div>
            <div class="print-info-box">
                <span>Course</span>
                <strong><?php echo htmlspecialchars($user['course']); ?></strong>
            </div>
            <div class="print-info-box">
                <span>Email</span>
                <strong><?php echo htmlspecialchars($user['email']); ?></strong>
            </div>
            <div class="print-info-box">
                <span>Contact</span>
                <strong><?php echo htmlspecialchars($user['contact_number']); ?></strong>
            </div>
        </div>

        <div class="print-message">
            Good day. I would like to respectfully request clearance for this semester. I have completed all required academic responsibilities. If there are remaining requirements or concerns, please let me know so I can comply immediately.
        </div>

        <table class="print-result-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>Instructor</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Date Signed</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) > 0): ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($row['subject']); ?></td>
                            <td><?php echo htmlspecialchars($row['instructor_name'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['comment']); ?></td>
                            <td><?php echo htmlspecialchars($row['result']); ?></td>
                            <td>
                                <?php
                                echo !empty($row['date_signed'])
                                    ? date("F d, Y", strtotime($row['date_signed']))
                                    : 'N/A';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No reviewed results yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>