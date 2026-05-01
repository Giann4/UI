<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

$user_stmt = $conn->prepare("SELECT firstname, lastname, email, contact_number, course, profile_photo FROM users WHERE id = ?");
$user_stmt->bind_param("i", $student_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Student not found.");
}

$default_photo = "../assets/southern.png";
$photo = $default_photo;

if (!empty($user['profile_photo']) && file_exists("../assets/uploads/profile/" . $user['profile_photo'])) {
    $photo = "../assets/uploads/profile/" . $user['profile_photo'];
}

$left_logo  = "../assets/logo1.png";
$right_logo = "../assets/logo2.png";

$top_header_logo = "../assets/logo2.png";
if (!file_exists($top_header_logo)) {
    $top_header_logo = "../assets/southern.png";
}

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
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Result</title>

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

.course-badge{
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
.send-registrar-btn:hover,
.download-btn:hover,
.print-btn:hover{
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
}

.welcome-box p{
    color:var(--text-soft);
    font-size:15px;
    line-height:1.6;
}

.alert-box{
    border-radius:18px;
    padding:16px 18px;
    margin-bottom:20px;
    font-weight:900;
    border:1px solid var(--panel-border);
    box-shadow:var(--shadow);
    background:var(--panel-bg);
}

.alert-success{
    border-left:7px solid #22c55e;
    color:#16a34a;
}

.alert-error{
    border-left:7px solid #ef4444;
    color:#ef4444;
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
}

.action-buttons{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:flex-end;
}

.send-email-form{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
}

.email-input-wrap{
    height:43px;
    min-width:300px;
    display:flex;
    align-items:center;
    gap:9px;
    padding:0 13px;
    border-radius:12px;
    background:#ffffff;
    border:1px solid var(--panel-border);
    box-shadow:0 10px 22px rgba(15,23,42,.06);
}

.dark-mode .email-input-wrap{
    background:#0f172a;
}

.email-input-wrap span{
    font-size:17px;
}

.email-input-wrap input{
    border:none;
    outline:none;
    width:100%;
    height:100%;
    background:transparent;
    color:var(--text-main);
    font-size:14px;
    font-weight:800;
}

.email-input-wrap input::placeholder{
    color:var(--text-muted);
}

.send-registrar-btn,
.download-btn,
.print-btn{
    border:none;
    min-width:135px;
    height:43px;
    border-radius:12px;
    color:#fff;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
    transition:.22s ease;
    text-decoration:none;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:0 16px;
}

.send-registrar-btn{
    min-width:145px;
    background:linear-gradient(135deg, #16a34a, #22c55e);
    box-shadow:0 10px 22px rgba(34,197,94,.20);
}

.download-btn{
    background:#1677ff;
}

.print-btn{
    background:#ff3131;
}

.document-box{
    margin-top:18px;
    border:1px solid var(--panel-border);
    border-radius:20px;
    padding:24px;
    background:rgba(255,255,255,0.45);
}

.dark-mode .document-box{
    background:rgba(255,255,255,0.03);
}

.inside-doc-header{
    margin:0 0 22px;
}

.inside-doc-header-top{
    display:grid;
    grid-template-columns:90px 1fr 90px;
    align-items:center;
    gap:12px;
}

.inside-logo-box{
    display:flex;
    justify-content:center;
    align-items:center;
}

.inside-logo{
    width:78px;
    height:78px;
    object-fit:contain;
    display:block;
}

.inside-doc-header-text{
    text-align:center;
    line-height:1.15;
}

.inside-doc-header-text h1{
    font-size:22px;
    font-weight:900;
    color:var(--text-main);
    text-transform:uppercase;
    margin:0 0 7px;
    letter-spacing:.4px;
}

.inside-address{
    font-size:12px;
    color:var(--text-soft);
    margin:0;
    line-height:1.35;
}

.inside-double-line{
    margin-top:14px;
}

.inside-double-line span{
    display:block;
    height:4px;
    background:linear-gradient(90deg, #18cf74, #8fbc67);
    margin:4px 0;
    border-radius:999px;
}

.clearance-head{
    text-align:center;
    margin-bottom:20px;
    line-height:1.45;
}

.clearance-head .main{
    font-size:22px;
    font-weight:900;
    color:var(--text-main);
}

.clearance-head .small{
    font-size:14px;
    color:var(--text-soft);
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
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
}

.info-box span{
    font-size:15px;
    color:var(--text-main);
    font-weight:900;
    word-break:break-word;
}

.request-text{
    text-align:center;
    font-size:14px;
    color:var(--text-soft);
    line-height:1.6;
    margin:10px 0 20px;
    padding:0 10px;
}

.table-wrap{
    overflow-x:auto;
    border-radius:16px;
    border:1px solid var(--panel-border);
}

.table-wrap table{
    width:100%;
    min-width:900px;
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
    padding:16px 12px;
    text-align:center;
    border-top:1px solid #e9eef2 !important;
    background:#ffffff !important;
    color:#425768 !important;
    font-size:14px;
}

.table-wrap table tr:nth-child(even) td{
    background:#fbfdfc !important;
}

.status-badge{
    display:inline-block;
    min-width:100px;
    padding:8px 14px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
}

.status-passed{
    background:#dff5e8;
    color:#0a944d;
}

.status-failed{
    background:#ffe0e0;
    color:#d93025;
}

.status-incomplete{
    background:#fff3cd;
    color:#856404;
}

.comment-text{
    font-weight:700;
    color:#425768 !important;
}

.empty-state{
    text-align:center;
    padding:35px 20px !important;
    color:#516574 !important;
    font-weight:900;
}

#printLayout{
    display:none;
}

@media (max-width:1100px){
    .stats-grid,
    .info-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .action-buttons{
        justify-content:flex-start;
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
    .send-registrar-btn,
    .download-btn,
    .print-btn,
    .email-input-wrap,
    .send-email-form{
        width:100%;
    }

    .email-input-wrap{
        min-width:100%;
    }

    .content{
        padding:20px 14px;
    }

    .stats-grid,
    .info-grid{
        grid-template-columns:1fr;
    }

    .action-buttons{
        width:100%;
    }

    .inside-doc-header-top{
        grid-template-columns:1fr;
    }

    .inside-logo-box{
        display:none;
    }

    .inside-doc-header-text h1{
        font-size:18px;
    }

    .document-box{
        padding:16px;
    }
}

/* PRINT */
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
        font-family:Arial, sans-serif;
    }

    .print-top-mini{
        display:flex;
        justify-content:space-between;
        font-size:10px;
        margin-bottom:10px;
    }

    .print-header-top{
        display:grid;
        grid-template-columns:90px 1fr 90px;
        align-items:center;
        gap:12px;
        margin-bottom:10px;
    }

    .print-logo-box{
        display:flex;
        justify-content:center;
        align-items:center;
    }

    .print-logo{
        width:78px;
        height:78px;
        object-fit:contain;
        display:block;
    }

    .print-header-text{
        text-align:center;
        line-height:1.15;
    }

    .print-header-text h1{
        font-size:20px;
        font-weight:900;
        margin:0 0 6px;
        text-transform:uppercase;
        color:#000 !important;
    }

    .print-address{
        font-size:11px;
        color:#000 !important;
    }

    .print-double-line span{
        display:block;
        height:3px;
        background:#2aa66a !important;
        margin:3px 0;
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
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
        <div class="sidebar-top">
            <div>
                <div class="brand-mini">
                    <span class="brand-dot"></span>
                    <span class="brand-text">Student Panel</span>
                </div>

                <div class="profile-card">
                    <div class="profile-ring">
                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Profile" class="profile-img" onerror="this.src='../assets/southern.png';">
                    </div>
                    <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="course-badge">
                        <?php echo !empty($user['course']) ? htmlspecialchars($user['course']) : 'STUDENT'; ?>
                    </div>
                </div>

                <div class="nav-title">Navigation</div>

                <div class="nav-group">
                    <a href="student.php" class="<?php echo ($current_page == 'student.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Dashboard</span>
                    </a>

                    <a href="student_result.php" class="<?php echo ($current_page == 'student_result.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">📄</span>
                        <span class="nav-text">Result</span>
                    </a>

                    <a href="change_password.php" class="<?php echo ($current_page == 'change_password.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">🔒</span>
                        <span class="nav-text">Change Password</span>
                    </a>

                    <a href="all_teachers.php" class="<?php echo ($current_page == 'all_teachers.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">👨‍🏫</span>
                        <span class="nav-text">List of All Teacher's in Southern</span>
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
                    <small>CLEARANCE COLLEGE DEPARTMENT</small>
                </div>
            </div>

            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">🌙 DARK MODE</button>
        </div>

        <div class="content">

            <?php if (isset($_GET['send']) && $_GET['send'] === 'success'): ?>
                <div class="alert-box alert-success">
                    ✅ Clearance result has been successfully sent.
                </div>
            <?php elseif (isset($_GET['send']) && $_GET['send'] === 'failed'): ?>
                <div class="alert-box alert-error">
                    ❌ Failed to send clearance result. Please check Gmail setup or try again.
                </div>
            <?php elseif (isset($_GET['send']) && $_GET['send'] === 'invalid'): ?>
                <div class="alert-box alert-error">
                    ❌ Invalid recipient email address.
                </div>
            <?php elseif (isset($_GET['send']) && $_GET['send'] === 'empty'): ?>
                <div class="alert-box alert-error">
                    ❌ Please enter recipient email address.
                </div>
            <?php endif; ?>

            <div class="welcome-box">
                <h2>Hi, <?php echo htmlspecialchars($user['firstname']); ?> 👋</h2>
                <p>Here is your official clearance result summary. You can review your approved subjects, print this page, download it as an image, or send it to any valid email address.</p>
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

                    <div class="action-buttons">
                        <form action="send_registrar.php" method="POST" class="send-email-form" onsubmit="return confirmSendEmail();">
                            <div class="email-input-wrap">
                                <span>📧</span>
                                <input 
                                    type="email" 
                                    name="recipient_email" 
                                    id="recipientEmail"
                                    placeholder="Enter recipient email"
                                    required
                                >
                            </div>

                            <button type="submit" name="send_clearance" class="send-registrar-btn">
                                SEND EMAIL
                            </button>
                        </form>

                        <button class="download-btn" onclick="downloadAsImage()">DOWNLOAD</button>
                        <button class="print-btn" onclick="window.print()">PRINT</button>
                    </div>
                </div>

                <div class="document-box">
                    <div class="inside-doc-header">
                        <div class="inside-doc-header-top">
                            <div class="inside-logo-box">
                                <img src="<?php echo $left_logo; ?>" alt="Left Logo" class="inside-logo" onerror="this.style.display='none';">
                            </div>

                            <div class="inside-doc-header-text">
                                <h1>SOUTHERN PHILIPPINES INSTITUTE<br>OF SCIENCE AND TECHNOLOGY</h1>
                                <p class="inside-address">Tia Maria Bldg. E. Aguinaldo Highway, Anabu 2A, Imus City, Cavite, 4103</p>
                            </div>

                            <div class="inside-logo-box">
                                <img src="<?php echo $right_logo; ?>" alt="Right Logo" class="inside-logo" onerror="this.style.display='none';">
                            </div>
                        </div>

                        <div class="inside-double-line">
                            <span></span>
                            <span></span>
                        </div>
                    </div>

                    <div class="clearance-head">
                        <div class="main">Student Clearance</div>
                        <div class="small">College Department</div>
                        <div class="small">School Year 2025-2026</div>
                    </div>

                    <div class="info-grid">
                        <div class="info-box">
                            <label>Name</label>
                            <span><?php echo htmlspecialchars($full_name); ?></span>
                        </div>

                        <div class="info-box">
                            <label>Course</label>
                            <span><?php echo htmlspecialchars($user['course'] ?: 'N/A'); ?></span>
                        </div>

                        <div class="info-box">
                            <label>Email</label>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>

                        <div class="info-box">
                            <label>Contact</label>
                            <span><?php echo htmlspecialchars($user['contact_number'] ?: 'N/A'); ?></span>
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
                                        <td class="comment-text"><?php echo htmlspecialchars($row['comment'] ?: 'No comment'); ?></td>
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
</div>

<div id="printLayout">
    <div class="print-paper">
        <div class="print-top-mini">
            <span><?php echo date("n/j/y, g:i A"); ?></span>
            <span>Student Result</span>
        </div>

        <div class="print-header">
            <div class="print-header-top">
                <div class="print-logo-box">
                    <img src="<?php echo $left_logo; ?>" alt="Left Logo" class="print-logo" onerror="this.style.display='none';">
                </div>

                <div class="print-header-text">
                    <h1>SOUTHERN PHILIPPINES INSTITUTE<br>OF SCIENCE AND TECHNOLOGY</h1>
                    <p class="print-address">Tia Maria Bldg. E. Aguinaldo Highway, Anabu 2A, Imus City, Cavite, 4103</p>
                </div>

                <div class="print-logo-box">
                    <img src="<?php echo $right_logo; ?>" alt="Right Logo" class="print-logo" onerror="this.style.display='none';">
                </div>
            </div>

            <div class="print-double-line">
                <span></span>
                <span></span>
            </div>
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
                <strong><?php echo htmlspecialchars($user['course'] ?: 'N/A'); ?></strong>
            </div>
            <div class="print-info-box">
                <span>Email</span>
                <strong><?php echo htmlspecialchars($user['email']); ?></strong>
            </div>
            <div class="print-info-box">
                <span>Contact</span>
                <strong><?php echo htmlspecialchars($user['contact_number'] ?: 'N/A'); ?></strong>
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
                            <td><?php echo htmlspecialchars($row['comment'] ?: 'No comment'); ?></td>
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

function confirmSendEmail() {
    const emailInput = document.getElementById("recipientEmail");
    const email = emailInput.value.trim();

    if (email === "") {
        alert("Please enter recipient email address.");
        emailInput.focus();
        return false;
    }

    return confirm("Send your clearance result to " + email + "?");
}

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
                margin-bottom:10px;
            }
            .print-header-top{
                display:grid;
                grid-template-columns:90px 1fr 90px;
                align-items:center;
                gap:12px;
                margin-bottom:10px;
            }
            .print-logo-box{
                display:flex;
                justify-content:center;
                align-items:center;
            }
            .print-logo{
                width:78px;
                height:78px;
                object-fit:contain;
                display:block;
            }
            .print-header-text{
                text-align:center;
                line-height:1.15;
            }
            .print-header-text h1{
                font-size:20px;
                font-weight:900;
                margin:0 0 6px;
                text-transform:uppercase;
                letter-spacing:0.3px;
            }
            .print-address{
                font-size:11px;
                margin:0;
                line-height:1.3;
            }
            .print-double-line span{
                display:block;
                height:3px;
                background:#2aa66a;
                margin:3px 0;
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
        link.download = 'student_clearance_result.png';
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