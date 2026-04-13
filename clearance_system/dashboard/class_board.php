<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];

/* Kunin ang profile photo ng teacher */
$userStmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ? AND role = 'teacher'");
$userStmt->bind_param("i", $teacher_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();

$photo = !empty($userData['profile_photo'])
    ? "../assets/uploads/profile/" . $userData['profile_photo']
    : "../assets/southern.png";

if (!isset($_GET['class_id'])) {
    die("Class not found.");
}

$class_id = intval($_GET['class_id']);
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$classStmt = $conn->prepare("SELECT * FROM teacher_classes WHERE id = ?");
$classStmt->bind_param("i", $class_id);
$classStmt->execute();
$classData = $classStmt->get_result()->fetch_assoc();

if (!$classData) {
    die("Class not found.");
}

$sql = "
SELECT class_requests.*, users.firstname, users.lastname, users.email, users.course
FROM class_requests
INNER JOIN users ON class_requests.student_id = users.id
WHERE class_requests.class_id = ?
";

$params = [$class_id];
$types = "i";

if (!empty($search)) {
    $sql .= " AND (users.firstname LIKE ? OR users.lastname LIKE ? OR users.email LIKE ?)";
    $searchLike = "%" . $search . "%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "sss";
}

$sql .= " ORDER BY class_requests.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Board</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="teacher-wrapper">
    <div class="teacher-sidebar">
        <div class="teacher-profile">
            <div class="teacher-avatar">
                <img src="<?php echo htmlspecialchars($photo); ?>" alt="Teacher Photo">
            </div>
            <h3><?php echo htmlspecialchars($_SESSION['name']); ?></h3>
        </div>

        <a href="teacher.php" class="side-btn">Dashboard</a>
        <a href="class_board.php?class_id=<?php echo $class_id; ?>" class="side-btn active">List of Request</a>
        <a href="../auth/logout.php" class="side-btn">Log Out</a>
    </div>

    <div class="teacher-main">
        <div class="teacher-header-top">SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</div>
        <div class="teacher-header-bottom">CLEARANCE OF STUDENT’S</div>

        <div class="request-board-box">
            <div class="request-top-bar">
                <form method="GET" class="search-form">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">Search</button>
                </form>

                <div class="class-code-box">
                    <span>Class Code</span>
                    <strong><?php echo htmlspecialchars($classData['class_code']); ?></strong>
                </div>

                <a href="teacher.php" class="save-top-btn">Back</a>
            </div>

            <form action="save_request.php" method="POST">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">

                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Complete Name</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Result</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests->num_rows > 0): ?>
                            <?php while($row = $requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['lastname'] . ', ' . $row['firstname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['course']); ?></td>
                                    <td>
                                        <span class="status-badge"><?php echo htmlspecialchars($row['status']); ?></span>
                                    </td>
                                    <td>
                                        <select name="result[<?php echo $row['id']; ?>]">
                                            <option value="">Select</option>
                                            <option value="Passed" <?php echo ($row['result'] === 'Passed') ? 'selected' : ''; ?>>Passed</option>
                                            <option value="Failed" <?php echo ($row['result'] === 'Failed') ? 'selected' : ''; ?>>Failed</option>
                                            <option value="Incomplete" <?php echo ($row['result'] === 'Incomplete') ? 'selected' : ''; ?>>Incomplete</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="comment[<?php echo $row['id']; ?>]" value="<?php echo htmlspecialchars($row['comment']); ?>">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="save-area">
                    <button type="submit" class="save-top-btn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
