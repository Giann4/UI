<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

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

$photo = "../assets/southern.png";
if (!empty($user['profile_photo']) && file_exists("../assets/uploads/profile/" . $user['profile_photo'])) {
    $photo = "../assets/uploads/profile/" . $user['profile_photo'];
}

/* TOP HEADER LOGO - PALITAN MO LANG ITO */
$top_header_logo = "../assets/logo2.png";
if (!file_exists($top_header_logo)) {
    $top_header_logo = "../assets/southern.png";
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

/* DELETE CLASS */
if (isset($_POST['delete_class'])) {
    $delete_class_id = intval($_POST['delete_class_id']);

    if ($delete_class_id === $class_id) {
        $conn->begin_transaction();

        try {
            $delete_requests_stmt = $conn->prepare("DELETE FROM class_requests WHERE class_id = ?");
            $delete_requests_stmt->bind_param("i", $class_id);
            $delete_requests_stmt->execute();

            $delete_class_stmt = $conn->prepare("DELETE FROM teacher_classes WHERE id = ? AND teacher_id = ?");
            $delete_class_stmt->bind_param("ii", $class_id, $teacher_id);
            $delete_class_stmt->execute();

            $conn->commit();

            header("Location: teacher.php?deleted=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Failed to delete class.";
            $message_type = "error";
        }
    } else {
        $message = "Invalid class deletion request.";
        $message_type = "error";
    }
}

/* SAVE REVIEW */
if (isset($_POST['save_review'])) {
    $request_id = intval($_POST['request_id']);
    $result = trim($_POST['result']);
    $comment = trim($_POST['comment']);
    $date_signed = date("Y-m-d");
    $status = "Reviewed";

    if (empty($result) || empty($comment)) {
        $message = "Please select a result and add a comment.";
        $message_type = "error";
    } else {
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

$request_rows = [];
$total_requests = 0;
$total_waiting = 0;
$total_reviewed = 0;
$total_passed = 0;

while ($row = $requests->fetch_assoc()) {
    $request_rows[] = $row;
    $total_requests++;

    if ($row['status'] === 'Requesting') {
        $total_waiting++;
    }

    if ($row['status'] === 'Reviewed') {
        $total_reviewed++;
    }

    if ($row['result'] === 'Passed') {
        $total_passed++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Requests</title>

<script>
(function () {
    const savedTheme = localStorage.getItem("site_theme");
    if (savedTheme === "dark") {
        document.documentElement.classList.add("dark-mode");
    }
})();
</script>

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

/* SIDEBAR */
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

/* MAIN */
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

.content{
    padding:30px 34px 40px;
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
.back-btn:hover,
.delete-class-btn:hover,
.save-btn:hover{
    transform:translateY(-2px);
}

.welcome-box,
.stat-card,
.card{
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
    text-transform:uppercase;
}

.welcome-box p{
    color:var(--text-soft);
    font-size:15px;
    line-height:1.6;
}

.stats-grid{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:18px;
    margin-bottom:22px;
}

.stat-card{
    border-radius:18px;
    padding:22px 18px;
    text-align:center;
    position:relative;
    overflow:hidden;
}

.stat-card::before{
    content:"";
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:5px;
    background:linear-gradient(90deg, #063946, #8fbc67);
}

.stat-card h4{
    color:var(--text-soft);
    font-size:14px;
    margin-bottom:10px;
}

.stat-card .number{
    color:var(--text-main);
    font-size:32px;
    font-weight:900;
}

.card{
    border-radius:22px;
    padding:24px 22px 26px;
    margin-bottom:22px;
}

.card-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.card-title h3{
    font-size:28px;
    color:var(--text-main);
    margin-bottom:6px;
}

.card-title p{
    color:var(--text-muted);
    font-size:14px;
    line-height:1.5;
}

.message{
    padding:14px 16px;
    border-radius:14px;
    font-weight:900;
    margin-bottom:16px;
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

.class-info-grid{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:12px;
    margin-bottom:18px;
}

.info-box{
    border:1px solid var(--panel-border);
    border-radius:14px;
    background:#f8fbfc;
    padding:15px;
    text-align:center;
}

.dark-mode .info-box{
    background:rgba(255,255,255,0.035);
}

.info-box label{
    display:block;
    font-size:12px;
    color:var(--text-muted);
    margin-bottom:7px;
    font-weight:900;
    text-transform:uppercase;
}

.info-box span{
    font-size:15px;
    color:var(--text-main);
    font-weight:900;
    word-break:break-word;
}

.action-buttons{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.back-btn,
.delete-class-btn{
    border:none;
    min-height:44px;
    padding:0 18px;
    border-radius:12px;
    color:#fff !important;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
    transition:.22s ease;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}

.back-btn{
    background:#063946;
}

.delete-class-btn{
    background:#dc2626;
}

.table-wrap{
    overflow-x:auto;
    border-radius:16px;
    border:1px solid var(--panel-border);
}

.table-wrap table{
    width:100%;
    min-width:1180px;
    border-collapse:collapse;
    background:#ffffff !important;
}

.table-wrap table th{
    background:#eaf5ee !important;
    color:#163743 !important;
    padding:16px 12px;
    font-size:13px;
    font-weight:900;
    text-align:center;
}

.table-wrap table td{
    padding:14px 12px;
    text-align:center;
    border-top:1px solid #e9eef2 !important;
    background:#ffffff !important;
    color:#425768 !important;
    font-size:14px;
    font-weight:700;
    vertical-align:top;
}

.table-wrap table tr:nth-child(even) td{
    background:#fbfdfc !important;
}

.status-badge{
    display:inline-block;
    min-width:110px;
    padding:8px 14px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}

.status-requesting{
    background:#fff3cd;
    color:#856404 !important;
}

.status-reviewed{
    background:#dff5e8;
    color:#0a944d !important;
}

.result-passed{
    color:#0a944d !important;
    font-weight:900;
}

.result-failed{
    color:#d93025 !important;
    font-weight:900;
}

.result-incomplete{
    color:#b7791f !important;
    font-weight:900;
}

.review-form{
    display:grid;
    gap:8px;
    min-width:190px;
}

.review-form select,
.review-form textarea{
    width:100%;
    border:1px solid #d8e3df;
    border-radius:10px;
    padding:9px 10px;
    font-size:13px;
    outline:none;
    background:#ffffff !important;
    color:#222 !important;
    font-weight:700;
}

.review-form textarea{
    resize:vertical;
    min-height:65px;
}

.review-form select:focus,
.review-form textarea:focus{
    border-color:#18cf74;
    box-shadow:0 0 0 3px rgba(24,207,116,0.12);
}

.save-btn{
    width:100%;
    border:none;
    border-radius:10px;
    padding:10px 14px;
    font-size:13px;
    font-weight:900;
    cursor:pointer;
    color:#fff;
    background:linear-gradient(135deg, #13cf74, #079564);
    transition:.22s ease;
}

.empty-state{
    text-align:center;
    padding:35px 20px !important;
    color:#516574 !important;
    font-weight:900;
}

@media (max-width:1100px){
    .stats-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .class-info-grid{
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

    .theme-toggle-btn,
    .back-btn,
    .delete-class-btn{
        width:100%;
    }

    .content{
        padding:20px 14px;
    }

    .stats-grid{
        grid-template-columns:1fr;
    }

    .card-header{
        flex-direction:column;
    }

    .action-buttons{
        width:100%;
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
                    <span class="brand-text">Teacher Panel</span>
                </div>

                <div class="profile-card">
                    <div class="profile-ring">
                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Profile" class="profile-img" onerror="this.src='../assets/southern.png';">
                    </div>

                    <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>

                    <div class="role-badge">TEACHER</div>
                </div>

                <div class="nav-title">Navigation</div>

                <div class="nav-group">
                    <a href="teacher.php" class="<?php echo ($current_page == 'teacher.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Dashboard</span>
                    </a>

                    <a href="teacher_request.php?class_id=<?php echo intval($class_id); ?>" class="<?php echo ($current_page == 'teacher_request.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">📋</span>
                        <span class="nav-text">List of Request</span>
                    </a>

                    <a href="change_password.php" class="<?php echo ($current_page == 'change_password.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🔒</span>
                        <span class="nav-text">Change Password</span>
                    </a>
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
                    <small>CLASS REQUESTS</small>
                </div>
            </div>

            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">🌙 DARK MODE</button>
        </div>

        <div class="content">

            <div class="welcome-box">
                <h2><?php echo htmlspecialchars($class['subject']); ?></h2>
                <p>
                    Review student clearance requests for this class. Select the result, add a comment, then save the review.
                </p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Requests</h4>
                    <div class="number"><?php echo $total_requests; ?></div>
                </div>

                <div class="stat-card">
                    <h4>Waiting</h4>
                    <div class="number"><?php echo $total_waiting; ?></div>
                </div>

                <div class="stat-card">
                    <h4>Reviewed</h4>
                    <div class="number"><?php echo $total_reviewed; ?></div>
                </div>

                <div class="stat-card">
                    <h4>Passed</h4>
                    <div class="number"><?php echo $total_passed; ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h3>Class Information</h3>
                        <p>Class details and available class actions.</p>
                    </div>

                    <div class="action-buttons">
                        <a href="teacher.php" class="back-btn">← Back to Dashboard</a>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this class? This will also remove all student requests under this class.');">
                            <input type="hidden" name="delete_class_id" value="<?php echo intval($class_id); ?>">
                            <button type="submit" name="delete_class" class="delete-class-btn">Delete Class</button>
                        </form>
                    </div>
                </div>

                <div class="class-info-grid">
                    <div class="info-box">
                        <label>Subject</label>
                        <span><?php echo htmlspecialchars($class['subject']); ?></span>
                    </div>

                    <div class="info-box">
                        <label>Course</label>
                        <span><?php echo htmlspecialchars($class['course']); ?></span>
                    </div>

                    <div class="info-box">
                        <label>Class Code</label>
                        <span><?php echo htmlspecialchars($class['class_code']); ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h3>Student Requests</h3>
                        <p>Track, review, and update student clearance request results.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
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
                        </thead>

                        <tbody>
                            <?php if (count($request_rows) > 0): ?>
                                <?php foreach ($request_rows as $index => $row): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['course']); ?></td>
                                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'Reviewed'): ?>
                                                <span class="status-badge status-reviewed">Reviewed</span>
                                            <?php else: ?>
                                                <span class="status-badge status-requesting">Requesting</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($row['result'] === 'Passed') {
                                                echo '<span class="result-passed">Passed</span>';
                                            } elseif ($row['result'] === 'Failed') {
                                                echo '<span class="result-failed">Failed</span>';
                                            } elseif ($row['result'] === 'Incomplete') {
                                                echo '<span class="result-incomplete">Incomplete</span>';
                                            } else {
                                                echo 'Pending';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo !empty($row['comment']) ? htmlspecialchars($row['comment']) : '---'; ?></td>
                                        <td>
                                            <?php
                                            echo !empty($row['date_signed'])
                                                ? date("F d, Y", strtotime($row['date_signed']))
                                                : '---';
                                            ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="review-form">
                                                <input type="hidden" name="request_id" value="<?php echo intval($row['id']); ?>">

                                                <select name="result" required>
                                                    <option value="">Select Result</option>
                                                    <option value="Passed" <?php echo ($row['result'] === 'Passed') ? 'selected' : ''; ?>>Passed</option>
                                                    <option value="Failed" <?php echo ($row['result'] === 'Failed') ? 'selected' : ''; ?>>Failed</option>
                                                    <option value="Incomplete" <?php echo ($row['result'] === 'Incomplete') ? 'selected' : ''; ?>>Incomplete</option>
                                                </select>

                                                <textarea name="comment" placeholder="Comment" required><?php echo htmlspecialchars($row['comment'] ?? ''); ?></textarea>

                                                <button type="submit" name="save_review" class="save-btn">Save Review</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="empty-state">No student requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
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
});
</script>

</body>
</html>