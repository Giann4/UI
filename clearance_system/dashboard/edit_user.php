<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("User ID is missing.");
}

$id = intval($_GET['id']);
$success = "";
$error = "";

/* GET USER */
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

/* UPDATE USER */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $role = trim($_POST['role']);
    $course = isset($_POST['course']) ? trim($_POST['course']) : null;
    $new_password = trim($_POST['password']);

    if (empty($firstname) || empty($lastname) || empty($email) || empty($contact_number) || empty($role)) {
        $error = "Please fill in all required fields.";
    } else {
        if ($role !== 'student') {
            $course = NULL;
        }

        if (!empty($new_password)) {
            $hashed_password = md5($new_password);

            $update = $conn->prepare("UPDATE users SET firstname=?, lastname=?, email=?, contact_number=?, role=?, course=?, password=? WHERE id=?");
            $update->bind_param("sssssssi", $firstname, $lastname, $email, $contact_number, $role, $course, $hashed_password, $id);
        } else {
            $update = $conn->prepare("UPDATE users SET firstname=?, lastname=?, email=?, contact_number=?, role=?, course=? WHERE id=?");
            $update->bind_param("ssssssi", $firstname, $lastname, $email, $contact_number, $role, $course, $id);
        }

        if ($update->execute()) {
            $success = "User information updated successfully.";

            $refresh = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $refresh->bind_param("i", $id);
            $refresh->execute();
            $user = $refresh->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update user.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            background:
                linear-gradient(rgba(3, 59, 70, 0.78), rgba(3, 59, 70, 0.78)),
                url("../assets/southern.jpg") no-repeat center center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 16px;
        }

        .edit-container {
            width: 100%;
            max-width: 1100px;
            background: rgba(255, 255, 255, 0.97);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.28);
        }

        .edit-header {
            background: linear-gradient(135deg, #0a944d, #033b46);
            color: #fff;
            padding: 28px 34px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .edit-header h1 {
            font-size: 34px;
            font-weight: 900;
            letter-spacing: 0.4px;
        }

        .edit-header p {
            font-size: 14px;
            opacity: 0.92;
            margin-top: 4px;
        }

        .back-btn {
            text-decoration: none;
            background: #fff;
            color: #033b46;
            padding: 12px 18px;
            border-radius: 14px;
            font-weight: 800;
            transition: 0.25s ease;
            white-space: nowrap;
        }

        .back-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(0,0,0,0.15);
        }

        .edit-body {
            padding: 30px;
        }

        .message {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 14px;
        }

        .message.success {
            background: #e7f8ee;
            color: #0a944d;
            border: 1px solid #bce7ca;
        }

        .message.error {
            background: #ffeaea;
            color: #d93025;
            border: 1px solid #f3b6b3;
        }

        .edit-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 28px;
            align-items: start;
        }

        .profile-card {
            background: linear-gradient(180deg, #f7fbf8, #eef5f1);
            border: 1px solid #dbe7e0;
            border-radius: 22px;
            padding: 28px 22px;
            text-align: center;
        }

        .avatar {
            width: 135px;
            height: 135px;
            border-radius: 50%;
            margin: 0 auto 18px;
            background: linear-gradient(135deg, #0fb761, #033b46);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 54px;
            font-weight: 900;
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }

        .profile-card h2 {
            font-size: 24px;
            color: #12353b;
            margin-bottom: 8px;
        }

        .mini-badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            background: #dff5e8;
            color: #0a944d;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .profile-meta {
            text-align: left;
            margin-top: 14px;
        }

        .profile-meta div {
            background: #fff;
            border: 1px solid #e0ebe5;
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #3c474d;
        }

        .profile-meta strong {
            color: #12353b;
        }

        .form-card {
            background: #fff;
            border: 1px solid #e4ece7;
            border-radius: 22px;
            padding: 28px;
        }

        .form-title {
            font-size: 24px;
            font-weight: 900;
            color: #12353b;
            margin-bottom: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
        }

        .input-group.full {
            grid-column: 1 / -1;
        }

        .input-group label {
            font-size: 14px;
            font-weight: 800;
            color: #12353b;
            margin-bottom: 8px;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            height: 54px;
            border: 2px solid #d8e3dd;
            border-radius: 16px;
            padding: 0 16px;
            font-size: 15px;
            outline: none;
            transition: 0.25s ease;
            background: #fff;
        }

        .input-group input:focus,
        .input-group select:focus {
            border-color: #11ba62;
            box-shadow: 0 0 0 4px rgba(17, 186, 98, 0.12);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 48px;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            color: #5a646a;
            user-select: none;
        }

        .button-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 26px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            border-radius: 15px;
            padding: 14px 22px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            transition: 0.25s ease;
            display: inline-block;
        }

        .btn-cancel {
            background: #eaf0f2;
            color: #12353b;
        }

        .btn-save {
            background: linear-gradient(135deg, #10c766, #06984b);
            color: #fff;
            box-shadow: 0 12px 24px rgba(5, 143, 72, 0.2);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .course-box {
            display: <?php echo ($user['role'] === 'student') ? 'flex' : 'none'; ?>;
            flex-direction: column;
        }

        @media (max-width: 900px) {
            .edit-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .input-group.full {
                grid-column: auto;
            }
        }

        @media (max-width: 600px) {
            .edit-header {
                padding: 24px 20px;
            }

            .edit-header h1 {
                font-size: 28px;
            }

            .edit-body {
                padding: 20px;
            }

            .form-card,
            .profile-card {
                padding: 20px;
            }

            .button-row {
                justify-content: stretch;
            }

            .button-row .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <div class="edit-container">
        <div class="edit-header">
            <div>
                <h1>Edit User</h1>
                <p>Update student or teacher information from the admin dashboard</p>
            </div>
            <a href="admin.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <div class="edit-body">
            <?php if (!empty($success)): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="edit-grid">
                <div class="profile-card">
                    <div class="avatar">
                        <?php echo strtoupper(substr($user['firstname'], 0, 1)); ?>
                    </div>

                    <h2><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h2>

                    <div class="mini-badge">
                        <?php echo strtoupper(htmlspecialchars($user['role'])); ?>
                    </div>

                    <div class="profile-meta">
                        <div><strong>Email:</strong><br><?php echo htmlspecialchars($user['email']); ?></div>
                        <div><strong>Contact:</strong><br><?php echo htmlspecialchars($user['contact_number']); ?></div>
                        <div><strong>User ID:</strong><br>#<?php echo $user['id']; ?></div>
                        <?php if (!empty($user['course'])): ?>
                            <div><strong>Course:</strong><br><?php echo htmlspecialchars($user['course']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-title">User Information</div>

                    <form method="POST">
                        <div class="form-grid">
                            <div class="input-group">
                                <label for="firstname">First Name</label>
                                <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                            </div>

                            <div class="input-group">
                                <label for="lastname">Last Name</label>
                                <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                            </div>

                            <div class="input-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <div class="input-group">
                                <label for="contact_number">Contact Number</label>
                                <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
                            </div>

                            <div class="input-group">
                                <label for="role">Role</label>
                                <select name="role" id="role" onchange="toggleCourseField()" required>
                                    <option value="student" <?php echo ($user['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                                    <option value="teacher" <?php echo ($user['role'] === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                                </select>
                            </div>

                            <div class="input-group course-box" id="courseBox">
                                <label for="course">Course</label>
                                <select name="course" id="course">
                                    <option value="">Select Course</option>
                                    <option value="BSIT 1" <?php echo ($user['course'] === 'BSIT 1') ? 'selected' : ''; ?>>BSIT 1</option>
                                    <option value="BSIT 2" <?php echo ($user['course'] === 'BSIT 2') ? 'selected' : ''; ?>>BSIT 2</option>
                                    <option value="BSIT 3" <?php echo ($user['course'] === 'BSIT 3') ? 'selected' : ''; ?>>BSIT 3</option>
                                    <option value="BSIT 4" <?php echo ($user['course'] === 'BSIT 4') ? 'selected' : ''; ?>>BSIT 4</option>
                                </select>
                            </div>

                            <div class="input-group full">
                                <label for="password">New Password</label>
                                <div class="password-wrap">
                                    <input type="password" id="password" name="password" placeholder="Leave blank if you do not want to change the password">
                                    <span class="toggle-password" onclick="togglePassword()">👁</span>
                                </div>
                            </div>
                        </div>

                        <div class="button-row">
                            <a href="admin.php" class="btn btn-cancel">Cancel</a>
                            <button type="submit" class="btn btn-save">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleCourseField() {
            const role = document.getElementById("role").value;
            const courseBox = document.getElementById("courseBox");
            const course = document.getElementById("course");

            if (role === "student") {
                courseBox.style.display = "flex";
            } else {
                courseBox.style.display = "none";
                course.value = "";
            }
        }

        function togglePassword() {
            const password = document.getElementById("password");
            password.type = password.type === "password" ? "text" : "password";
        }

        document.addEventListener("DOMContentLoaded", function () {
            toggleCourseField();
        });
    </script>

</body>
</html>