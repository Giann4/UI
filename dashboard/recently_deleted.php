<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$adminName = isset($_SESSION['name']) && !empty($_SESSION['name']) ? $_SESSION['name'] : 'Administrator';
$roleFilter = isset($_GET['role_filter']) ? trim($_GET['role_filter']) : 'all';

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

/* RESTORE */
if (isset($_GET['restore']) && !empty($_GET['restore'])) {
    $restore_id = intval($_GET['restore']);
    $restore_stmt = $conn->prepare("UPDATE users SET is_deleted = 0, deleted_at = NULL WHERE id = ?");
    $restore_stmt->bind_param("i", $restore_id);
    $restore_stmt->execute();

    header("Location: recently_deleted.php?msg=restored&role_filter=" . urlencode($roleFilter));
    exit;
}

/* PERMANENT DELETE */
if (isset($_GET['permanent_delete']) && !empty($_GET['permanent_delete'])) {
    $delete_id = intval($_GET['permanent_delete']);
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->bind_param("i", $delete_id);
    $delete_stmt->execute();

    header("Location: recently_deleted.php?msg=deleted&role_filter=" . urlencode($roleFilter));
    exit;
}

/* FETCH DELETED USERS WITH ROLE FILTER */
$sql = "SELECT * FROM users WHERE is_deleted = 1";
$params = [];
$types = "";

if ($roleFilter === 'teacher') {
    $sql .= " AND role = ?";
    $params[] = 'teacher';
    $types .= "s";
} elseif ($roleFilter === 'student') {
    $sql .= " AND role = ?";
    $params[] = 'student';
    $types .= "s";
}

$sql .= " ORDER BY deleted_at DESC";

if (!empty($params)) {
    $deleted_stmt = $conn->prepare($sql);
    $deleted_stmt->bind_param($types, ...$params);
    $deleted_stmt->execute();
    $deleted_users = $deleted_stmt->get_result();
} else {
    $deleted_users = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recently Deleted</title>

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

.admin-wrapper {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR SAME ADMIN UI */
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

.content-area {
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
}

body.light-mode .page-title h2 {
    color: #102a33;
}

body.light-mode .page-title p {
    color: #516574;
}

.filter-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-btn {
    text-decoration: none;
    min-width: 95px;
    height: 48px;
    padding: 0 18px;
    border-radius: 14px;
    background: #111827;
    color: #e5e7eb;
    border: 1px solid #243244;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 900;
    transition: .22s ease;
}

.filter-btn:hover {
    transform: translateY(-2px);
}

.filter-btn.active {
    background: linear-gradient(135deg, #13cf74, #079564);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 10px 20px rgba(18,201,107,0.22);
}

.search-card {
    width: 100%;
    max-width: 600px;
    margin-bottom: 24px;
}

.search-card input {
    width: 100%;
    height: 58px;
    border-radius: 16px;
    border: 1px solid #dfe8ed;
    background: #ffffff;
    color: #102a33;
    padding: 0 20px;
    font-size: 15px;
    outline: none;
    box-shadow: 0 12px 30px rgba(21, 48, 66, 0.08);
}

.search-card input::placeholder {
    color: #7b8b97;
}

.table-card {
    background: #ffffff;
    border-radius: 22px;
    padding: 18px;
    box-shadow: 0 18px 42px rgba(21, 48, 66, 0.10);
    border: none;
}

.table-wrap {
    overflow-x: auto;
    border-radius: 16px;
    border: none;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
    background: #ffffff;
}

thead th {
    background: #eaf5ee;
    color: #163743;
    font-size: 13px;
    font-weight: 900;
    padding: 17px 14px;
    text-align: center;
}

tbody td {
    padding: 16px 14px;
    border-top: 1px solid #e9eef2;
    color: #425768;
    background: #ffffff;
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
    min-width: 78px;
    padding: 7px 13px;
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

.action-group {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}

.action-btn {
    min-width: 96px;
    height: 39px;
    padding: 0 14px;
    border-radius: 10px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 900;
    transition: .22s ease;
    color: #fff;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.restore-btn {
    background: linear-gradient(135deg, #13cf74, #079564);
}

.permanent-btn {
    background: linear-gradient(135deg, #ff5d57, #ef4444);
}

.empty-row {
    padding: 34px 10px;
    color: #516574;
    font-weight: 800;
}

.table-footer {
    padding: 15px 6px 0;
    color: #516574;
    font-size: 13px;
    font-weight: 700;
}

/* LIGHT MODE */
body.light-mode .content-area {
    background: #f4f7fb;
}

body.light-mode .filter-btn {
    background: #fff;
    border-color: #e5eef3;
    color: #102a33;
}

body.light-mode .filter-btn.active {
    background: linear-gradient(135deg, #13cf74, #079564);
    color: #fff;
}

body.light-mode .darkmode-toggle {
    background: #fff;
    color: #063946;
}

/* DARK PAGE BUT CLEAN WHITE TABLE */
body:not(.light-mode) .table-card {
    background: #ffffff;
    border: none;
}

body:not(.light-mode) .table-wrap {
    border: none;
}

body:not(.light-mode) table {
    background: #ffffff;
}

body:not(.light-mode) thead th {
    background: #eaf5ee;
    color: #163743;
}

body:not(.light-mode) tbody td {
    background: #ffffff;
    color: #425768;
    border-top: 1px solid #e9eef2;
}

body:not(.light-mode) tbody tr:hover td {
    background: #fbfdfc;
}

body:not(.light-mode) .table-footer,
body:not(.light-mode) .empty-row {
    color: #516574;
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

    .filter-group {
        width: 100%;
    }

    .filter-btn {
        flex: 1;
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

                <div class="menu-label">Navigation</div>

                <div class="nav-group">
                    <a class="side-btn" href="admin.php?view=teachers">
                        <span class="side-icon">👨‍🏫</span>
                        <span class="side-label">List of Teachers</span>
                    </a>

                    <a class="side-btn" href="admin.php?view=students">
                        <span class="side-icon">👥</span>
                        <span class="side-label">List of Students</span>
                    </a>

                    <a class="side-btn active" href="recently_deleted.php">
                        <span class="side-icon">🗑</span>
                        <span class="side-label">Recently Deleted</span>
                    </a>

                    <a class="side-btn" href="admin_teacher_album.php">
                        <span class="side-icon">🖼</span>
                        <span class="side-label">Teacher Album</span>
                    </a>

                    <a class="side-btn" href="admin_change_password.php">
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

            <button type="button" class="darkmode-toggle" id="darkModeToggle" onclick="toggleDarkMode()">☀️ LIGHT MODE</button>
        </div>

        <div class="content-area">
            <div class="page-title-row">
                <div class="page-title">
                    <h2>RECENTLY DELETED USERS</h2>
                    <p>Manage users that have been deleted from the system. You can restore or permanently delete them.</p>
                </div>

                <div class="filter-group">
                    <a href="recently_deleted.php?role_filter=all" class="filter-btn <?php echo ($roleFilter === 'all') ? 'active' : ''; ?>">ALL</a>
                    <a href="recently_deleted.php?role_filter=teacher" class="filter-btn <?php echo ($roleFilter === 'teacher') ? 'active' : ''; ?>">TEACHER</a>
                    <a href="recently_deleted.php?role_filter=student" class="filter-btn <?php echo ($roleFilter === 'student') ? 'active' : ''; ?>">STUDENT</a>
                </div>
            </div>

            <div class="search-card">
                <input 
                    type="text" 
                    id="liveSearch" 
                    placeholder="🔍 Search by name, email, contact, role, course, or ID..."
                    autocomplete="off"
                >
            </div>

            <div class="table-card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>FULL NAME</th>
                                <th>EMAIL</th>
                                <th>CONTACT</th>
                                <th>ROLE</th>
                                <th>COURSE</th>
                                <th>DELETED AT</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>

                        <tbody id="deletedUsersTable">
                            <?php if ($deleted_users && $deleted_users->num_rows > 0): ?>
                                <?php while ($row = $deleted_users->fetch_assoc()): ?>
                                    <?php
                                        $search_text = strtolower(
                                            $row['id'] . ' ' .
                                            $row['lastname'] . ' ' .
                                            $row['firstname'] . ' ' .
                                            $row['lastname'] . ', ' . $row['firstname'] . ' ' .
                                            $row['email'] . ' ' .
                                            $row['contact_number'] . ' ' .
                                            $row['role'] . ' ' .
                                            ($row['course'] ?? '') . ' ' .
                                            $row['deleted_at']
                                        );
                                    ?>
                                    <tr class="searchable-row" data-search="<?php echo htmlspecialchars($search_text); ?>">
                                        <td><?php echo $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['lastname'] . ', ' . $row['firstname']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                        <td>
                                            <span class="role-badge <?php echo ($row['role'] === 'student') ? 'role-student' : 'role-teacher'; ?>">
                                                <?php echo strtoupper(htmlspecialchars($row['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['course'])): ?>
                                                <span class="course-badge"><?php echo htmlspecialchars($row['course']); ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['deleted_at']); ?></td>
                                        <td>
                                            <div class="action-group">
                                                <a href="recently_deleted.php?restore=<?php echo $row['id']; ?>&role_filter=<?php echo urlencode($roleFilter); ?>" class="action-btn restore-btn" onclick="return confirm('Restore this user?')">RESTORE</a>
                                                <a href="recently_deleted.php?permanent_delete=<?php echo $row['id']; ?>&role_filter=<?php echo urlencode($roleFilter); ?>" class="action-btn permanent-btn" onclick="return confirm('Permanently delete this user?')">DELETE</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr id="noDataRow">
                                    <td colspan="8" class="empty-row">No recently deleted users found.</td>
                                </tr>
                            <?php endif; ?>

                            <tr id="noSearchResultRow" style="display: none;">
                                <td colspan="8" class="empty-row">No matching users found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    Showing recently deleted users
                </div>
            </div>
        </div>
    </main>
</div>

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

    const searchInput = document.getElementById("liveSearch");
    const rows = document.querySelectorAll(".searchable-row");
    const noSearchResultRow = document.getElementById("noSearchResultRow");
    const noDataRow = document.getElementById("noDataRow");

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
                noSearchResultRow.style.display = (rows.length > 0 && visibleCount === 0) ? "" : "none";
            }

            if (noDataRow) {
                noDataRow.style.display = rows.length === 0 ? "" : "none";
            }
        });
    }
});
</script>

</body>
</html>