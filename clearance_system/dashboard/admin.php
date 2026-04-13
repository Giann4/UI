<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$courseFilter = isset($_GET['course']) ? trim($_GET['course']) : '';
$viewRole = isset($_GET['view']) ? trim($_GET['view']) : 'students';

$sql = "SELECT * FROM users WHERE 1=1";
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

if (!empty($search)) {
    $sql .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
    $searchLike = "%" . $search . "%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "ssss";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$totalStudentsQuery = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='student'");
$totalStudents = $totalStudentsQuery->fetch_assoc()['total'];

$totalTeachersQuery = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='teacher'");
$totalTeachers = $totalTeachersQuery->fetch_assoc()['total'];
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
            background: #f4f7f8;
            color: #1b1b1b;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 270px;
            background: linear-gradient(180deg, #033b46, #022d35);
            color: #fff;
            padding: 26px 18px;
            position: sticky;
            top: 0;
            height: 100vh;
            box-shadow: 4px 0 18px rgba(0,0,0,0.12);
        }

        .profile-box {
            text-align: center;
            padding: 14px 10px 28px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            margin-bottom: 24px;
        }

        .profile-icon {
            width: 88px;
            height: 88px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: #ffffff;
            color: #033b46;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .profile-box h3 {
            font-size: 34px;
            margin-bottom: 4px;
            font-weight: 800;
        }

        .profile-box p {
            font-size: 14px;
            opacity: 0.9;
        }

        .side-btn,
        .dropdown-btn {
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
            background: #fff;
            color: #0e2b30;
            padding: 15px 14px;
            border-radius: 999px;
            font-weight: 700;
            margin-bottom: 14px;
            border: none;
            cursor: pointer;
            transition: 0.25s ease;
            font-size: 16px;
        }

        .side-btn:hover,
        .dropdown-btn:hover,
        .side-btn.active {
            background: #12c96b;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(18, 201, 107, 0.25);
        }

        .dropdown {
            margin-bottom: 14px;
        }

        .dropdown-content {
            display: none;
            padding: 8px 8px 0;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            display: block;
            text-decoration: none;
            background: rgba(255,255,255,0.12);
            color: #fff;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 10px;
            font-size: 14px;
            transition: 0.25s ease;
        }

        .dropdown-content a:hover {
            background: #12c96b;
            color: #fff;
        }

        .main-content {
            flex: 1;
            min-width: 0;
        }

        .top-header {
            background: linear-gradient(135deg, #98c76b, #85b95d);
            color: #111;
            text-align: center;
            padding: 24px 20px;
            font-size: 25px;
            font-weight: 900;
            letter-spacing: 0.5px;
        }

        .sub-header {
            background: #033b46;
            color: #00ff8c;
            text-align: center;
            padding: 14px 20px;
            font-size: 21px;
            font-weight: 900;
            letter-spacing: 0.4px;
        }

        .content-area {
            padding: 28px 24px;
        }

        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .search-form {
            flex: 1;
            min-width: 320px;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-form input[type="text"] {
            flex: 1;
            min-width: 0;
            height: 54px;
            border: 2px solid #d6dee2;
            border-radius: 16px;
            padding: 0 18px;
            font-size: 15px;
            outline: none;
            transition: 0.25s ease;
            background: #fff;
        }

        .search-form input[type="text"]:focus {
            border-color: #12c96b;
            box-shadow: 0 0 0 4px rgba(18, 201, 107, 0.12);
        }

        .search-form button {
            height: 54px;
            padding: 0 24px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(135deg, #0fb761, #0a944d);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            transition: 0.25s ease;
        }

        .search-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(10, 148, 77, 0.22);
        }

        .totals-box {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 12px 16px;
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            font-weight: 800;
            color: #163037;
        }

        .count {
            min-width: 56px;
            height: 42px;
            border-radius: 12px;
            background: #f2f7f4;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            color: #0a944d;
            border: 2px solid #d8ebe0;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.07);
            padding: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        thead th {
            background: linear-gradient(135deg, #eaf4eb, #dfeee2);
            color: #12353b;
            font-size: 15px;
            font-weight: 900;
            padding: 16px 12px;
            text-align: center;
            border-bottom: 2px solid #d8e3dd;
        }

        tbody td {
            padding: 15px 12px;
            text-align: center;
            border-bottom: 1px solid #e8eef0;
            font-size: 15px;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8fcf9;
        }

        .role-badge {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.4px;
        }

        .role-student {
            background: #e6f8ee;
            color: #0a944d;
        }

        .role-teacher {
            background: #e7f1ff;
            color: #1864c7;
        }

        .course-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            background: #f1f3f5;
            color: #374047;
            font-size: 12px;
            font-weight: 800;
        }

        .password-mask {
            color: #6b7479;
            letter-spacing: 2px;
            font-weight: 700;
        }

        .action-group {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 12px;
            color: #fff;
            font-size: 13px;
            font-weight: 800;
            transition: 0.25s ease;
            display: inline-block;
            min-width: 72px;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }

        .view-btn {
            background: #27a8f3;
        }

        .edit-btn {
            background: #f4c542;
            color: #1d1d1d;
        }

        .delete-btn {
            background: #ff5d57;
        }

        .empty-row {
            text-align: center;
            font-weight: 700;
            color: #6b7479;
            padding: 26px 10px;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 220px;
                padding: 20px 14px;
            }

            .profile-box h3 {
                font-size: 28px;
            }

            .top-header {
                font-size: 20px;
            }

            .sub-header {
                font-size: 17px;
            }
        }

        @media (max-width: 700px) {
            .admin-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                width: 100%;
            }

            .search-form {
                min-width: 100%;
            }

            .top-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .totals-box {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <div class="sidebar">
        <div class="profile-box">
            <div class="profile-icon">👤</div>
            <h3>ADMIN</h3>
            <p><?php echo isset($_SESSION['name']) ? $_SESSION['name'] : 'Administrator'; ?></p>
        </div>

        <div class="dropdown">
            <button type="button" class="dropdown-btn" onclick="toggleDropdown()">
                DASHBOARD ⬇
            </button>

            <div id="dropdownContent" class="dropdown-content">
                <a href="admin.php?view=students&course=BSIT%201">BSIT 1</a>
                <a href="admin.php?view=students&course=BSIT%202">BSIT 2</a>
                <a href="admin.php?view=students&course=BSIT%203">BSIT 3</a>
                <a href="admin.php?view=students&course=BSIT%204">BSIT 4</a>
                <a href="admin.php?view=students">All Students</a>
            </div>
        </div>

        <a class="side-btn <?php echo ($viewRole === 'teachers') ? 'active' : ''; ?>" href="admin.php?view=teachers">List of Teacher</a>
        <a class="side-btn <?php echo ($viewRole === 'students' && empty($courseFilter)) ? 'active' : ''; ?>" href="admin.php?view=students">List of Students</a>
        <a class="side-btn" href="#">Reports</a>
        <a class="side-btn" href="#">Change Password</a>
        <a class="side-btn" href="../auth/logout.php">Log Out</a>
    </div>

    <div class="main-content">
        <div class="top-header">SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</div>
        <div class="sub-header">CLEARANCE COLLEGE DEPARTMENT</div>

        <div class="content-area">
            <div class="top-controls">
                <form method="GET" action="admin.php" class="search-form">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewRole); ?>">
                    <?php if (!empty($courseFilter) && $viewRole === 'students'): ?>
                        <input type="hidden" name="course" value="<?php echo htmlspecialchars($courseFilter); ?>">
                    <?php endif; ?>

                    <input
                        type="text"
                        name="search"
                        placeholder="Search by name, email, or contact..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                    <button type="submit">Search</button>
                </form>

                <div class="totals-box">
                    <?php if ($viewRole === 'students'): ?>
                        <span>
                            Total Students<?php echo !empty($courseFilter) ? " (" . htmlspecialchars($courseFilter) . ")" : ""; ?>:
                        </span>
                        <div class="count">
                            <?php
                            if (!empty($courseFilter)) {
                                $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role='student' AND course=?");
                                $countStmt->bind_param("s", $courseFilter);
                                $countStmt->execute();
                                $countRes = $countStmt->get_result()->fetch_assoc();
                                echo $countRes['total'];
                            } else {
                                echo $totalStudents;
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <span>Total Teachers:</span>
                        <div class="count"><?php echo $totalTeachers; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>LIST OF <?php echo ($viewRole === 'students') ? 'STUDENTS' : 'TEACHERS'; ?></th>
                            <th>EMAIL</th>
                            <th>CONTACT</th>
                            <th>PASSWORD</th>
                            <?php if ($viewRole === 'students'): ?>
                                <th>COURSE</th>
                            <?php endif; ?>
                            <th>ROLE</th>
                            <th>User Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $display_id = 1; ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $display_id; ?></td>
                                    <td><?php echo htmlspecialchars($row['lastname'] . ', ' . $row['firstname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                    <td><span class="password-mask">********</span></td>

                                    <?php if ($viewRole === 'students'): ?>
                                        <td>
                                            <span class="course-badge">
                                                <?php echo htmlspecialchars($row['course']); ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <span class="role-badge <?php echo ($row['role'] === 'student') ? 'role-student' : 'role-teacher'; ?>">
                                            <?php echo strtoupper(htmlspecialchars($row['role'])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="action-group">
                                            <a href="view_user.php?id=<?php echo $row['id']; ?>" class="action-btn view-btn">VIEW</a>
                                            <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="action-btn edit-btn">EDIT</a>
                                            <a href="delete_user.php?id=<?php echo $row['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Delete this user?')">DELETE</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php $display_id++; ?>
                            <?php endwhile; ?>
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
</div>

<script>
    function toggleDropdown() {
        document.getElementById("dropdownContent").classList.toggle("show");
    }

    window.addEventListener("click", function(e) {
        const btn = document.querySelector(".dropdown-btn");
        const menu = document.getElementById("dropdownContent");

        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove("show");
        }
    });
</script>

</body>
</html>