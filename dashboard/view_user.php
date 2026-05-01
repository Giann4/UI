<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Student ID not found.");
}

$student_id = intval($_GET['id']);

$returnUrl = isset($_GET['return']) && !empty($_GET['return'])
    ? $_GET['return']
    : 'admin.php?view=students';

/* STUDENT INFO */
$user_stmt = $conn->prepare("SELECT firstname, lastname, email, contact_number, course, profile_photo FROM users WHERE id = ? AND role = 'student'");
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
<title>Admin - Student Result View</title>

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

.wrapper {
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
    display: block;
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
    font-size: 20px;
    font-weight: 900;
    margin-bottom: 5px;
    text-transform: uppercase;
    line-height: 1.15;
}

.profile-box p {
    color: #23e986;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 14px;
    word-break: break-word;
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

.content {
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
    max-width: 820px;
    line-height: 1.5;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat-card {
    background: #111827;
    border: 1px solid #243244;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 14px 30px rgba(0,0,0,0.18);
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-icon {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #13cf74, #079564);
    font-size: 23px;
    flex-shrink: 0;
}

.stat-card h4 {
    color: #cbd5e1;
    font-size: 13px;
    margin-bottom: 7px;
    font-weight: 900;
}

.stat-card .number {
    font-size: 30px;
    font-weight: 900;
    color: #ffffff;
    line-height: 1;
}

.card {
    background: #111827;
    border: 1px solid #243244;
    border-radius: 22px;
    padding: 24px;
    box-shadow: 0 18px 42px rgba(0,0,0,0.22);
    margin-bottom: 22px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}

.card-title h3 {
    color: #ffffff;
    font-size: 26px;
    margin-bottom: 6px;
    font-weight: 900;
}

.card-title p {
    color: #94a3b8;
    font-size: 14px;
    font-weight: 700;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.print-btn,
.download-btn,
.back-btn {
    color: #fff;
    border: none;
    padding: 12px 22px;
    font-weight: 900;
    border-radius: 12px;
    cursor: pointer;
    font-size: 14px;
    transition: 0.22s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 112px;
}

.back-btn {
    background: linear-gradient(135deg, #13cf74, #079564);
}

.download-btn {
    background: linear-gradient(135deg, #3ea7ff, #1677ff);
}

.print-btn {
    background: linear-gradient(135deg, #ff5d57, #ef4444);
}

.print-btn:hover,
.download-btn:hover,
.back-btn:hover {
    transform: translateY(-2px);
}

.clearance-paper {
    background: #ffffff;
    color: #102a33;
    border-radius: 18px;
    padding: 22px;
    box-shadow: inset 0 0 0 1px #e5eef3;
}

.clearance-head {
    text-align: center;
    margin-bottom: 18px;
    line-height: 1.4;
}

.clearance-head .main {
    font-size: 23px;
    font-weight: 900;
    color: #063946;
}

.clearance-head .small {
    font-size: 14px;
    color: #516574;
    font-weight: 700;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}

.info-box {
    border: 1px solid #dfe8ed;
    border-radius: 14px;
    background: #f8fbfc;
    padding: 14px;
    text-align: center;
}

.info-box label {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 6px;
    font-weight: 900;
}

.info-box span {
    font-size: 15px;
    color: #102a33;
    font-weight: 900;
    word-break: break-word;
}

.request-text {
    text-align: center;
    font-size: 14px;
    color: #425768;
    line-height: 1.6;
    margin: 8px 0 18px;
    padding: 0 10px;
}

.table-wrap {
    overflow-x: auto;
    border-radius: 16px;
    border: 1px solid #e5eef3;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
    background: #fff;
}

table th {
    background: #eaf5ee;
    color: #163743;
    padding: 15px 12px;
    font-size: 13px;
    font-weight: 900;
    text-align: center;
}

table td {
    padding: 15px 12px;
    text-align: center;
    border-top: 1px solid #e9eef2;
    background: #ffffff;
    color: #425768;
    font-size: 14px;
}

table tr:hover td {
    background: #fbfdfc;
}

.status-badge {
    display: inline-block;
    min-width: 100px;
    padding: 8px 14px;
    border-radius: 999px;
    font-weight: 900;
    font-size: 12px;
}

.status-passed {
    background: #dff5e8;
    color: #0a944d;
}

.status-failed {
    background: #fee2e2;
    color: #b91c1c;
}

.status-incomplete {
    background: #fff3cd;
    color: #856404;
}

.comment-text {
    font-weight: 700;
    color: #334155;
}

.empty-state {
    text-align: center;
    padding: 35px 20px;
    color: #64748b;
    font-weight: 900;
}

#printLayout {
    display: none;
}

/* LIGHT MODE */
body.light-mode .content {
    background: #f4f7fb;
}

body.light-mode .page-title h2 {
    color: #102a33;
}

body.light-mode .page-title p {
    color: #516574;
}

body.light-mode .stat-card,
body.light-mode .card {
    background: #ffffff;
    border-color: #e5eef3;
    box-shadow: 0 18px 42px rgba(21,48,66,0.10);
}

body.light-mode .stat-card h4,
body.light-mode .card-title p {
    color: #516574;
}

body.light-mode .stat-card .number,
body.light-mode .card-title h3 {
    color: #102a33;
}

body.light-mode .darkmode-toggle {
    background: #ffffff;
    color: #063946;
}

@media (max-width: 1150px) {
    .stats-grid,
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 900px) {
    .wrapper {
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

    .content {
        padding: 16px;
    }

    .stats-grid,
    .info-grid {
        grid-template-columns: 1fr;
    }

    .action-buttons {
        width: 100%;
    }

    .print-btn,
    .download-btn,
    .back-btn {
        width: 100%;
    }
}

/* PRINT */
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    body {
        background: #fff !important;
    }

    body * {
        visibility: hidden;
    }

    #printLayout,
    #printLayout * {
        visibility: visible;
    }

    #printLayout {
        display: block !important;
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: #fff;
    }

    .print-paper {
        width: 100%;
        max-width: 760px;
        margin: 0 auto;
        color: #000;
        padding: 0;
        font-family: Arial, sans-serif;
    }

    .print-top-mini {
        display: flex;
        justify-content: space-between;
        font-size: 10px;
        margin-bottom: 8px;
    }

    .print-header {
        text-align: center;
        margin-bottom: 8px;
    }

    .print-header h1 {
        font-size: 18px;
        font-weight: 900;
        line-height: 1.2;
        margin: 0 0 8px;
        text-transform: uppercase;
    }

    .print-line {
        border-top: 1.5px solid #000;
        margin: 6px 0;
    }

    .print-header h2 {
        font-size: 14px;
        font-weight: 800;
        margin: 0;
        text-transform: uppercase;
    }

    .print-subhead-left {
        margin: 12px 0 8px;
    }

    .print-subhead-left h3 {
        font-size: 14px;
        margin: 0 0 2px;
        font-weight: 800;
    }

    .print-subhead-left p {
        font-size: 11px;
        margin: 0;
    }

    .print-center-title {
        text-align: center;
        margin: 8px 0 12px;
    }

    .print-center-title h2 {
        font-size: 16px;
        margin: 0 0 4px;
        font-weight: 800;
    }

    .print-center-title p {
        margin: 0;
        font-size: 11px;
        line-height: 1.4;
    }

    .print-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 12px;
    }

    .print-info-box {
        border: 1px solid #000;
        text-align: center;
        padding: 10px 8px;
        min-height: 54px;
    }

    .print-info-box span {
        display: block;
        font-size: 10px;
        margin-bottom: 4px;
        color: #444;
        font-weight: 700;
    }

    .print-info-box strong {
        font-size: 12px;
        font-weight: 800;
    }

    .print-message {
        text-align: center;
        font-size: 11px;
        line-height: 1.5;
        margin: 10px auto 14px;
        max-width: 92%;
    }

    .print-result-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }

    .print-result-table th,
    .print-result-table td {
        border: 1px solid #000;
        padding: 5px 4px;
        text-align: center;
        vertical-align: middle;
        background: #fff !important;
        color: #000 !important;
    }

    .print-result-table th {
        font-weight: 800;
        background: #f3f3f3 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
</head>

<body>

<div class="wrapper">
    <aside class="sidebar">
        <div class="sidebar-shell">
            <div>
                <div class="brand-mini">
                    <div class="brand-icon">🎓</div>
                    <div class="brand-text">ADMIN PANEL</div>
                </div>

                <div class="profile-box">
                    <div class="profile-icon-wrap">
                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Profile" class="profile-icon" onerror="this.src='../assets/southern.png';">
                        <span class="online-dot"></span>
                    </div>
                    <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="admin-badge">📄 STUDENT VIEW</div>
                </div>

                <div class="menu-label">Navigation</div>

                <div class="nav-group">
                    <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="side-btn">
                        <span class="side-icon">⬅️</span>
                        <span class="side-label">Back</span>
                    </a>

                    <a href="#" class="side-btn active">
                        <span class="side-icon">📄</span>
                        <span class="side-label">Student Result Copy</span>
                    </a>
                </div>
            </div>

            <a href="../auth/logout.php" class="side-btn logout-btn">
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

        <div class="content">
            <div class="page-title-row">
                <div class="page-title">
                    <h2>Admin View - <?php echo htmlspecialchars($user['firstname']); ?> 👋</h2>
                    <p>
                        This is the copied clearance result view of the selected student. The admin can review, print, and keep a record of the student clearance result.
                    </p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div>
                        <h4>Total Reviewed Subjects</h4>
                        <div class="number"><?php echo $total_subjects; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div>
                        <h4>Passed</h4>
                        <div class="number"><?php echo $total_passed; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div>
                        <h4>Failed</h4>
                        <div class="number"><?php echo $total_failed; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div>
                        <h4>Incomplete</h4>
                        <div class="number"><?php echo $total_incomplete; ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h3>Student Clearance Result</h3>
                        <p>Printable academic clearance record - Admin Copy</p>
                    </div>

                    <div class="action-buttons">
                        <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="back-btn">BACK</a>
                        <button class="download-btn" onclick="downloadAsImage()">DOWNLOAD</button>
                        <button class="print-btn" onclick="window.print()">PRINT</button>
                    </div>
                </div>

                <div class="clearance-paper">
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
    </main>
</div>

<!-- PRINT-ONLY LAYOUT -->
<div id="printLayout">
    <div class="print-paper">
        <div class="print-top-mini">
            <span><?php echo date("n/j/y, g:i A"); ?></span>
            <span>Admin Copy - Student Result</span>
        </div>

        <div class="print-header">
            <h1>SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</h1>
            <div class="print-line"></div>
            <h2>CLEARANCE COLLEGE DEPARTMENT</h2>
        </div>

        <div class="print-subhead-left">
            <h3>Student Clearance Result</h3>
            <p>Printable academic clearance record - Admin Copy</p>
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

<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

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

document.addEventListener("DOMContentLoaded", function () {
    applyDarkModeState();
});

function downloadAsImage() {
    const oldTemp = document.getElementById('tempDownloadWrapper');
    if (oldTemp) {
        oldTemp.remove();
    }

    const tempWrapper = document.createElement('div');
    tempWrapper.id = 'tempDownloadWrapper';
    tempWrapper.style.position = 'absolute';
    tempWrapper.style.left = '-99999px';
    tempWrapper.style.top = '0';
    tempWrapper.style.width = '900px';
    tempWrapper.style.background = '#ffffff';
    tempWrapper.style.padding = '20px';
    tempWrapper.style.zIndex = '-1';

    tempWrapper.innerHTML = `
        <style>
            .print-paper{
                width:100%;
                max-width:760px;
                margin:0 auto;
                color:#000;
                padding:0;
                background:#fff;
                font-family:Arial, sans-serif;
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
            }
        </style>
        ${document.getElementById('printLayout').innerHTML}
    `;

    document.body.appendChild(tempWrapper);

    const target = tempWrapper.querySelector('.print-paper');

    html2canvas(target, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff'
    }).then(function(canvas) {
        const link = document.createElement('a');
        link.download = 'admin_student_clearance_result.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        tempWrapper.remove();
    }).catch(function(error) {
        console.error(error);
        alert('Failed to download image.');
        tempWrapper.remove();
    });
}
</script>

</body>
</html>