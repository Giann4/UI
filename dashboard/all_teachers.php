<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

$user_stmt = $conn->prepare("SELECT firstname, lastname, email, course, profile_photo FROM users WHERE id = ? AND role = 'student'");
$user_stmt->bind_param("i", $student_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Student not found.");
}

$default_photo = "../assets/southern.png";
$top_logo = "../assets/logo2.png";

$student_photo = $default_photo;
if (!empty($user['profile_photo']) && file_exists("../assets/uploads/profile/" . $user['profile_photo'])) {
    $student_photo = "../assets/uploads/profile/" . $user['profile_photo'];
}

$teachers = [];
$teacher_query = "SELECT id, teacher_name, teacher_photo, teacher_email, teacher_contact, teacher_department 
                  FROM teacher_album 
                  ORDER BY teacher_name ASC";
$teacher_result = $conn->query($teacher_query);

if ($teacher_result && $teacher_result->num_rows > 0) {
    while ($row = $teacher_result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

$total_teachers = count($teachers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>All Teachers</title>

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
    --green:#18cf74;
    --green2:#8fbc67;
    --shadow:0 18px 42px rgba(15,23,42,.09);
}

html.dark-mode{
    --page-bg:#0f172a;
    --panel-bg:#111827;
    --panel-border:#243244;
    --text-main:#f8fafc;
    --text-soft:#cbd5e1;
    --shadow:0 18px 42px rgba(0,0,0,.22);
}

body{
    background:var(--page-bg);
    color:var(--text-main);
    min-height:100vh;
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
}

.nav-icon{
    width:26px;
    text-align:center;
    font-size:18px;
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
        radial-gradient(circle at 8% 30%, rgba(255,255,255,.22), transparent 18%),
        linear-gradient(135deg,#063946 0%,#8fbc67 100%);
    color:#fff;
    padding:28px 34px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    text-transform:uppercase;
}

.top-header-brand{
    display:flex;
    align-items:center;
    gap:16px;
}

.top-logo{
    width:62px;
    height:62px;
    border-radius:50%;
    object-fit:cover;
    background:#fff;
    border:3px solid rgba(255,255,255,0.78);
}

.top-header span{
    display:block;
    font-size:22px;
    font-weight:900;
}

.top-header small{
    display:block;
    margin-top:6px;
    font-size:14px;
    color:#ecfff6;
    font-weight:800;
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
}

.content{
    padding:30px 34px 40px;
}

.welcome-box{
    background:var(--panel-bg);
    border:1px solid var(--panel-border);
    box-shadow:var(--shadow);
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
}

/* STUDENT TEACHER CARD DISPLAY ONLY */
.teacher-section{
    background:#ffffff;
    border-radius:0;
    border:none;
    box-shadow:none;
    padding:0;
    min-height:520px;
}

.dark-mode .teacher-section{
    background:transparent;
}

.teacher-grid{
    display:grid;
    grid-template-columns:repeat(5, 1fr);
    gap:34px;
    align-items:start;
}

.teacher-card{
    position:relative;
    min-height:380px; /* dating 315px / 385px */
    background:#ffffff;
    border-radius:18px;
    overflow:hidden;
    border:1px solid #edf3f1;
    box-shadow:0 14px 32px rgba(15,23,42,.13);
    text-align:center;
    transition:.22s ease;
}

.teacher-card:hover{
    transform:translateY(-6px);
    box-shadow:0 18px 34px rgba(15,23,42,.18);
}

.dark-mode .teacher-card{
    background:#111827;
    border-color:#243244;
}

.teacher-card::before{
    content:"";
    position:absolute;
    top:0;
    left:0;
    right:0;
    height:100px;
    background:linear-gradient(135deg, #e8f7de 0%, #e4f4e8 55%, #dff1ff 100%);
    clip-path:polygon(0 0,100% 0,100% 70%,0 92%);
}

.teacher-card:nth-child(2)::before{
    background:linear-gradient(135deg, #eef9e9 0%, #e5f1ff 100%);
}

.teacher-card:nth-child(3)::before{
    background:linear-gradient(135deg, #e8fff5 0%, #e6f4ff 100%);
}

.teacher-card:nth-child(4)::before{
    background:linear-gradient(135deg, #f1e9ff 0%, #eef7ff 100%);
}

.teacher-card:nth-child(5)::before{
    background:linear-gradient(135deg, #fff0e7 0%, #ffe4dc 100%);
}

.teacher-photo-wrap{
    width:112px;
    height:112px;
    border-radius:50%;
    padding:4px;
    background:#ffffff;
    border:3px solid #cceec3;
    position:relative;
    z-index:2;
    margin:25px 0 18px 42px;
    box-shadow:0 10px 22px rgba(0,0,0,.18);
}

.teacher-photo{
    width:100%;
    height:100%;
    border-radius:50%;
    object-fit:cover;
    display:block;
}

.teacher-dept-badge{
    position:absolute;
    top:17px;
    right:14px;
    z-index:3;
    padding:8px 14px;
    border-radius:999px;
    background:#e4f9df;
    border:1px solid #bce8b2;
    color:#18b56a;
    font-size:11px;
    font-weight:900;
    text-transform:uppercase;
    max-width:130px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.teacher-card h3{
    position:relative;
    z-index:2;
    min-height:64px;
    margin:18px 14px 20px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#0d2b42;
    font-size:23px;
    line-height:1.12;
    letter-spacing:.4px;
    font-weight:900;
    text-transform:uppercase;
}

.dark-mode .teacher-card h3{
    color:#f8fafc;
}

.teacher-meta{
    position:relative;
    z-index:2;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    color:#486171;
    font-size:14px;
    margin:11px 12px;
    word-break:break-word;
}

.dark-mode .teacher-meta{
    color:#cbd5e1;
}

.teacher-meta.email::before{
    content:"✉";
    color:#2991c8;
    font-size:14px;
}

.teacher-meta.contact::before{
    content:"☎";
    color:#2991c8;
    font-size:14px;
}

.teacher-footer{
    margin-top:32px;
    min-height:48px;
    display:flex;
    justify-content:center;
    align-items:center;
    position:relative;
}

.pagination{
    display:flex;
    align-items:center;
    gap:13px;
    background:#ffffff;
    border-radius:12px;
    padding:8px 15px;
    box-shadow:0 10px 22px rgba(15,23,42,.08);
}

.dark-mode .pagination{
    background:#111827;
    border:1px solid #243244;
}

.page-arrow{
    border:none;
    background:transparent;
    color:#9badb8;
    font-size:24px;
    cursor:pointer;
    line-height:1;
}

.page-number{
    width:32px;
    height:32px;
    border-radius:8px;
    background:linear-gradient(135deg,#17cf75,#12b86b);
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:14px;
    font-weight:900;
}

.teacher-count{
    position:absolute;
    right:0;
    color:var(--text-soft);
    font-size:12px;
    font-weight:700;
}

.empty-box{
    background:#f8fbfc;
    border:1px dashed var(--panel-border);
    border-radius:20px;
    padding:34px;
    text-align:center;
    color:var(--text-soft);
    font-weight:900;
}

@media (max-width:1400px){
    .teacher-grid{
        grid-template-columns:repeat(4, 1fr);
    }
}

@media (max-width:1100px){
    .teacher-grid{
        grid-template-columns:repeat(3, 1fr);
    }
}

@media (max-width:900px){
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
        flex-direction:column;
        text-align:center;
        justify-content:center;
        padding:24px 18px;
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

    .teacher-grid{
        grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
        gap:22px;
    }

    .teacher-footer{
        flex-direction:column;
        gap:12px;
    }

    .teacher-count{
        position:static;
    }
}
</style>
</head>

<body>

<div class="wrapper">

    <aside class="sidebar">
        <div class="sidebar-top">
            <div>
                <div class="brand-mini">
                    <span class="brand-dot"></span>
                    <span class="brand-text">Student Panel</span>
                </div>

                <div class="profile-card">
                    <div class="profile-ring">
                        <img src="<?php echo htmlspecialchars($student_photo); ?>" alt="Student Photo" class="profile-img" onerror="this.src='../assets/southern.png';">
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
    </aside>

    <main class="main-content">
        <div class="top-header">
            <div class="top-header-brand">
                <img src="<?php echo htmlspecialchars($top_logo); ?>" class="top-logo" alt="Logo" onerror="this.src='../assets/southern.png';">

                <div>
                    <span>SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</span>
                    <small>LIST OF ALL TEACHERS</small>
                </div>
            </div>

            <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">🌙 DARK MODE</button>
        </div>

        <div class="content">
            <div class="welcome-box">
                <h2>All Teachers in Southern</h2>
                <p>Here is the complete list of teachers added by the administrator.</p>
            </div>

            <div class="teacher-section">
                <?php if (!empty($teachers)): ?>
                    <div class="teacher-grid">
                        <?php foreach ($teachers as $teacher): ?>
                            <?php
                                if (!empty($teacher['teacher_photo']) && file_exists("../assets/uploads/teacher_album/" . $teacher['teacher_photo'])) {
                                    $teacher_photo = "../assets/uploads/teacher_album/" . $teacher['teacher_photo'];
                                } else {
                                    $teacher_photo = "../assets/southern.png";
                                }

                                $department = !empty($teacher['teacher_department']) ? $teacher['teacher_department'] : 'Department';
                                $email = !empty($teacher['teacher_email']) ? $teacher['teacher_email'] : 'No email available';
                                $contact = !empty($teacher['teacher_contact']) ? $teacher['teacher_contact'] : 'No contact available';
                            ?>

                            <div class="teacher-card">
                                <div class="teacher-dept-badge"><?php echo htmlspecialchars($department); ?></div>

                                <div class="teacher-photo-wrap">
                                    <img src="<?php echo htmlspecialchars($teacher_photo); ?>" alt="Teacher Photo" class="teacher-photo" onerror="this.src='../assets/southern.png';">
                                </div>

                                <h3><?php echo htmlspecialchars($teacher['teacher_name']); ?></h3>

                                <div class="teacher-meta email">
                                    <?php echo htmlspecialchars($email); ?>
                                </div>

                                <div class="teacher-meta contact">
                                    <?php echo htmlspecialchars($contact); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="teacher-footer">
                    </div>
                <?php else: ?>
                    <div class="empty-box">
                        No teachers found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

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