<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$courseFilter = isset($_GET['course']) ? trim($_GET['course']) : '';
$viewRole = isset($_GET['view']) ? trim($_GET['view']) : 'students';

if ($viewRole !== 'students' && $viewRole !== 'teachers') {
    $viewRole = 'students';
}

$current_page = basename($_SERVER['PHP_SELF']);

$returnUrl = "admin.php?view=" . urlencode($viewRole);
if ($viewRole === 'students' && !empty($courseFilter)) {
    $returnUrl .= "&course=" . urlencode($courseFilter);
}

$sql = "SELECT * FROM users WHERE is_deleted = 0";
$params = [];
$types = "";

if ($viewRole === 'students') {
    $sql .= " AND role = 'student'";
} elseif ($viewRole === 'teachers') {
    $sql .= " AND role = 'teacher'";
}

if (!empty($courseFilter) && $viewRole === 'students') {
    $sql .= " AND course = ?";
    $params[] = $courseFilter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$totalStudentsQuery = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='student' AND is_deleted = 0");
$totalStudents = $totalStudentsQuery->fetch_assoc()['total'];

$totalTeachersQuery = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='teacher' AND is_deleted = 0");
$totalTeachers = $totalTeachersQuery->fetch_assoc()['total'];

$adminName = isset($_SESSION['name']) && !empty($_SESSION['name']) ? $_SESSION['name'] : 'Administrator';

$default_admin_photo = "../assets/southern.png";
$admin_photo = $default_admin_photo;
$admin_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

if ($admin_id > 0) {
    $admin_stmt = $conn->prepare("SELECT profile_photo FROM admin WHERE id = ?");
    $admin_stmt->bind_param("i", $admin_id);
    $admin_stmt->execute();
    $admin_data = $admin_stmt->get_result()->fetch_assoc();

    if ($admin_data && !empty($admin_data['profile_photo']) && file_exists("../assets/uploads/admin/" . $admin_data['profile_photo'])) {
        $admin_photo = "../assets/uploads/admin/" . $admin_data['profile_photo'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
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
    background:
        linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.05));
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

.side-btn,
.dropdown-btn {
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
    position: relative;
}

.side-btn:hover,
.dropdown-btn:hover {
    background: rgba(255,255,255,0.08);
    transform: translateX(4px);
}

.side-btn.active,
.dropdown-btn.active {
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

.dropdown-arrow {
    font-size: 14px;
}

.dropdown-content {
    display: none;
    padding: 6px 0 8px 40px;
    background: rgba(255,255,255,0.035);
    border-radius: 0 0 16px 16px;
}

.dropdown-content.show {
    display: block;
}

.dropdown-content a {
    display: block;
    text-decoration: none;
    color: #eafff7;
    font-size: 14px;
    font-weight: 800;
    padding: 9px 10px;
    border-radius: 12px;
    margin-bottom: 3px;
    transition: .2s ease;
}

.dropdown-content a:hover {
    background: rgba(18,201,107,0.18);
    transform: translateX(3px);
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
    background: linear-gradient(135deg, #079564 0%, #8fbc67 100%);
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

.top-controls {
    display: grid;
    grid-template-columns: 1fr 370px 370px;
    gap: 22px;
    margin-bottom: 24px;
}

.search-card {
    display: flex;
    gap: 12px;
    align-items: center;
}

.live-search-input {
    width: 100%;
    height: 70px;
    border-radius: 18px;
    border: 1px solid #dfe8ed;
    background: #fff;
    padding: 0 22px;
    font-size: 15px;
    outline: none;
    box-shadow: 0 12px 30px rgba(21, 48, 66, 0.08);
}

.filter-btn {
    width: 70px;
    height: 70px;
    border: none;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(21, 48, 66, 0.08);
    font-size: 22px;
    color: #587080;
}

.stat-card {
    height: 112px;
    border-radius: 20px;
    background: #fff;
    border: 1px solid #e5eef3;
    box-shadow: 0 12px 30px rgba(21, 48, 66, 0.08);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 18px;
}

.stat-icon {
    width: 66px;
    height: 66px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 28px;
    box-shadow: 0 12px 24px rgba(0,0,0,0.16);
}

.stat-green {
    background: linear-gradient(135deg, #13c870, #079564);
}

.stat-blue {
    background: linear-gradient(135deg, #4fa6ff, #1677ff);
}

.stat-card span {
    font-size: 14px;
    color: #456;
    font-weight: 800;
}

.stat-card h2 {
    font-size: 28px;
    color: #243b4a;
    margin: 4px 0;
}

.stat-card p {
    color: #168a54;
    font-size: 13px;
    font-weight: 800;
}

.table-card {
    background: #fff;
    border-radius: 22px;
    padding: 18px;
    box-shadow: 0 18px 42px rgba(21, 48, 66, 0.10);
    border: 1px solid #e5eef3;
}

.table-wrap {
    overflow-x: auto;
    border-radius: 16px;
    border: 1px solid #e4ebef;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1020px;
    background: #fff;
}

thead th {
    background: #eaf5ee;
    color: #163743;
    font-size: 13px;
    font-weight: 900;
    padding: 16px 14px;
    text-align: center;
}

tbody td {
    padding: 15px 14px;
    border-top: 1px solid #e9eef2;
    color: #425768;
    text-align: center;
    font-size: 14px;
    vertical-align: middle;
}

tbody tr:hover td {
    background: #fbfdfc;
}

.role-badge,
.course-badge {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
}

.role-student,
.course-badge {
    background: #dff5e8;
    color: #0a944d;
}

.role-teacher {
    background: #e7f1ff;
    color: #1769c2;
}

.password-mask {
    letter-spacing: 2px;
    color: #566b7a;
    font-weight: 900;
}

.action-group {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}

.action-btn {
    width: 38px;
    height: 38px;
    border-radius: 11px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0;
    font-weight: 900;
    transition: .22s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.view-btn {
    background: #e7f1ff;
    color: #1677ff;
}

.view-btn::before {
    content: "👁";
    font-size: 15px;
}

.edit-btn {
    background: #fff3d8;
    color: #d89b00;
}

.edit-btn::before {
    content: "✏️";
    font-size: 15px;
}

.archive-btn {
    background: #ffe5e5;
    color: #ff3131;
}

.archive-btn::before {
    content: "🗑";
    font-size: 15px;
}

.empty-row {
    padding: 34px 10px;
    color: #6b7479;
    font-weight: 800;
}

/* DARK MODE */
body.dark-mode {
    background: #0f172a;
}

body.dark-mode .top-hero {
    background: linear-gradient(135deg, #033946, #6f9f4f);
}

body.dark-mode .content-area {
    background: #0f172a;
}

body.dark-mode .live-search-input,
body.dark-mode .filter-btn,
body.dark-mode .stat-card,
body.dark-mode .table-card {
    background: #111827;
    border-color: #243244;
    color: #e5e7eb;
}

body.dark-mode .stat-card h2,
body.dark-mode .stat-card span {
    color: #e5e7eb;
}

body.dark-mode table,
body.dark-mode tbody td {
    background: #fff !important;
    color: #425768 !important;
}

body.dark-mode thead th {
    background: #eaf5ee !important;
    color: #163743 !important;
}

@media (max-width: 1200px) {
    .top-controls {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 800px) {
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

    .admin-wrapper {
        flex-direction: column;
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
                        <img src="<?php echo htmlspecialchars($admin_photo); ?>" alt="Admin Profile" class="profile-icon" onerror="this.src='../assets/southern.png';">
                        <span class="online-dot"></span>
                    </div>
                    <h3>Admin</h3>
                    <p><?php echo htmlspecialchars($adminName); ?></p>
                    <div class="admin-badge">🛡 ADMIN PANEL</div>
                </div>

                <div class="menu-label">Main Navigation</div>

                <div class="nav-group">
                    <a class="side-btn <?php echo ($viewRole === 'teachers') ? 'active' : ''; ?>" href="admin.php?view=teachers">
                        <span class="side-icon">👨‍🏫</span>
                        <span class="side-label">List of Teachers</span>
                    </a>

                    <a class="side-btn <?php echo ($viewRole === 'students' && empty($courseFilter)) ? 'active' : ''; ?>" href="admin.php?view=students">
                        <span class="side-icon">👥</span>
                        <span class="side-label">List of Students</span>
                    </a>

                    <a class="side-btn <?php echo ($current_page === 'recently_deleted.php') ? 'active' : ''; ?>" href="recently_deleted.php">
                        <span class="side-icon">🗑</span>
                        <span class="side-label">Recently Deleted</span>
                    </a>

                    <a class="side-btn <?php echo ($current_page === 'admin_teacher_album.php') ? 'active' : ''; ?>" href="admin_teacher_album.php">
                        <span class="side-icon">🖼</span>
                        <span class="side-label">Teacher Album</span>
                    </a>

                    <a class="side-btn <?php echo ($current_page === 'admin_change_password.php') ? 'active' : ''; ?>" href="admin_change_password.php">
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

            <button type="button" class="darkmode-toggle" id="darkModeToggle" onclick="toggleDarkMode()">🌙 DARK MODE</button>
        </div>

        <div class="content-area">
            <div class="top-controls">
                <div class="search-card">
                    <input type="text" id="liveSearch" class="live-search-input" placeholder="🔍 Search users..." autocomplete="off">
                    <button type="button" class="filter-btn">⌁</button>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-green">🎓</div>
                    <div>
                        <span>Total Students</span>
                        <h2><?php echo $totalStudents; ?></h2>
                        <p>Active students</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-blue">👨‍🏫</div>
                    <div>
                        <span>Total Teachers</span>
                        <h2><?php echo $totalTeachers; ?></h2>
                        <p>Active teachers</p>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>NAME</th>
                                <th>EMAIL</th>
                                <th>CONTACT</th>
                                <th>PASSWORD</th>
                                <?php if ($viewRole === 'students'): ?>
                                    <th>COURSE</th>
                                <?php endif; ?>
                                <th>ROLE</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>

                        <tbody id="usersTableBody">
                            <?php if ($result->num_rows > 0): ?>
                                <?php $display_id = 1; ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <?php
                                        $search_text = strtolower(
                                            $display_id . ' ' .
                                            $row['id'] . ' ' .
                                            $row['lastname'] . ' ' .
                                            $row['firstname'] . ' ' .
                                            $row['lastname'] . ', ' . $row['firstname'] . ' ' .
                                            $row['email'] . ' ' .
                                            $row['contact_number'] . ' ' .
                                            $row['role'] . ' ' .
                                            ($row['course'] ?? '')
                                        );
                                    ?>

                                    <tr class="searchable-row" data-search="<?php echo htmlspecialchars($search_text); ?>">
                                        <td><?php echo $display_id; ?></td>
                                        <td><?php echo htmlspecialchars($row['lastname'] . ', ' . $row['firstname']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                        <td><span class="password-mask">••••••••</span></td>

                                        <?php if ($viewRole === 'students'): ?>
                                            <td>
                                                <span class="course-badge"><?php echo htmlspecialchars($row['course']); ?></span>
                                            </td>
                                        <?php endif; ?>

                                        <td>
                                            <span class="role-badge <?php echo ($row['role'] === 'student') ? 'role-student' : 'role-teacher'; ?>">
                                                <?php echo strtoupper(htmlspecialchars($row['role'])); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="action-group">
                                                <?php if ($viewRole === 'students'): ?>
                                                    <a href="view_user.php?id=<?php echo $row['id']; ?>&return=<?php echo urlencode($returnUrl); ?>" class="action-btn view-btn">VIEW</a>
                                                <?php endif; ?>

                                                <a href="edit_user.php?id=<?php echo $row['id']; ?>&return=<?php echo urlencode($returnUrl); ?>" class="action-btn edit-btn">EDIT</a>

                                                <a href="delete_user.php?id=<?php echo $row['id']; ?>&return=<?php echo urlencode($returnUrl); ?>"
                                                   class="action-btn archive-btn"
                                                   onclick="return confirm('Move this user to Recently Deleted?')">
                                                   DELETE
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $display_id++; ?>
                                <?php endwhile; ?>

                                <tr id="noSearchResultRow" style="display:none;">
                                    <td colspan="<?php echo ($viewRole === 'students') ? '8' : '7'; ?>" class="empty-row">
                                        No matching users found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($viewRole === 'students') ? '8' : '7'; ?>" class="empty-row">
                                        No records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function toggleDropdown() {
    document.getElementById("dropdownContent").classList.toggle("show");
}

window.addEventListener("click", function(e) {
    const btn = document.querySelector(".dropdown-btn");
    const menu = document.getElementById("dropdownContent");

    if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove("show");
    }
});

function applyDarkModeState() {
    const isDark = localStorage.getItem('site_darkmode') === 'enabled';
    const btn = document.getElementById('darkModeToggle');

    if (isDark) {
        document.body.classList.add('dark-mode');
        if (btn) btn.innerHTML = '☀️ LIGHT MODE';
    } else {
        document.body.classList.remove('dark-mode');
        if (btn) btn.innerHTML = '🌙 DARK MODE';
    }
}

function toggleDarkMode() {
    const isDark = document.body.classList.contains('dark-mode');

    if (isDark) {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('site_darkmode', 'disabled');
    } else {
        document.body.classList.add('dark-mode');
        localStorage.setItem('site_darkmode', 'enabled');
    }

    applyDarkModeState();
}

document.addEventListener('DOMContentLoaded', function() {
    applyDarkModeState();

    const searchInput = document.getElementById("liveSearch");
    const rows = document.querySelectorAll(".searchable-row");
    const noSearchResultRow = document.getElementById("noSearchResultRow");

    if (searchInput) {
        searchInput.addEventListener("input", function () {
            const value = this.value.toLowerCase().trim();
            let visibleCount = 0;

            rows.forEach(function (row) {
                const searchText = row.getAttribute("data-search") || "";

                if (searchText.includes(value)) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });

            if (noSearchResultRow) {
                noSearchResultRow.style.display = visibleCount === 0 ? "" : "none";
            }
        });
    }
});
</script>

</body>
</html>