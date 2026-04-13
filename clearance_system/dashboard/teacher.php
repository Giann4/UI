<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

/* TEACHER INFO */
$user_stmt = $conn->prepare("SELECT firstname, lastname, email, contact_number, profile_photo FROM users WHERE id = ? AND role = 'teacher'");
$user_stmt->bind_param("i", $teacher_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Teacher not found.");
}

$default_photo = "../assets/southern.png";
if (!empty($user['profile_photo']) && file_exists("../assets/uploads/profile/" . $user['profile_photo'])) {
    $photo = "../assets/uploads/profile/" . $user['profile_photo'];
} else {
    $photo = $default_photo;
}

/* RANDOM CLASS CODE FUNCTION */
function generateClassCode($length = 8) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

/* CREATE CLASS */
if (isset($_POST['create_class'])) {
    $subject = trim($_POST['subject']);
    $course = trim($_POST['course']);

    if (!empty($subject) && !empty($course)) {

        do {
            $class_code = generateClassCode(8);
            $check = $conn->prepare("SELECT id FROM teacher_classes WHERE class_code = ?");
            $check->bind_param("s", $class_code);
            $check->execute();
            $check_result = $check->get_result();
        } while ($check_result->num_rows > 0);

        $insert = $conn->prepare("INSERT INTO teacher_classes (teacher_id, subject, course, class_code) VALUES (?, ?, ?, ?)");
        $insert->bind_param("isss", $teacher_id, $subject, $course, $class_code);

        if ($insert->execute()) {
            $message = "Class created successfully. Generated code: " . $class_code;
            $message_type = "success";
        } else {
            $message = "Failed to create class.";
            $message_type = "error";
        }
    } else {
        $message = "Please fill in all fields.";
        $message_type = "error";
    }
}

/* GET TEACHER CLASSES */
$stmt = $conn->prepare("SELECT * FROM teacher_classes WHERE teacher_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
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
            background:#8fbc67;
        }

        .sidebar a:hover{
            opacity:0.95;
            transform:translateY(-1px);
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

        .message{
            padding:14px 16px;
            border-radius:12px;
            margin-bottom:20px;
            font-weight:bold;
            font-size:14px;
        }

        .message.success{
            background:#d4edda;
            color:#155724;
            border:1px solid #b7dfbe;
        }

        .message.error{
            background:#f8d7da;
            color:#721c24;
            border:1px solid #efb7be;
        }

        .top-actions{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:15px;
            margin-bottom:18px;
            flex-wrap:wrap;
        }

        .section-title{
            color:#003b49;
            font-size:28px;
            font-weight:bold;
        }

        .create-btn{
            background:#003b49;
            color:#fff;
            border:none;
            border-radius:14px;
            padding:14px 22px;
            font-size:15px;
            font-weight:bold;
            cursor:pointer;
            transition:0.2s ease;
        }

        .create-btn:hover{
            background:#002d38;
        }

        .create-form-card{
            display:none;
            background:#fff;
            border-radius:18px;
            padding:22px;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
            margin-bottom:22px;
        }

        .create-form-card.show{
            display:block;
        }

        .create-form-card h3{
            color:#003b49;
            font-size:24px;
            margin-bottom:8px;
        }

        .create-form-card p{
            color:#666;
            font-size:14px;
            margin-bottom:18px;
        }

        .form-grid{
            display:grid;
            grid-template-columns:1fr 1fr auto;
            gap:12px;
            align-items:end;
        }

        .form-group label{
            display:block;
            margin-bottom:8px;
            color:#003b49;
            font-weight:bold;
            font-size:14px;
        }

        .form-group input{
            width:100%;
            height:48px;
            border:1px solid #ccc;
            border-radius:12px;
            padding:0 14px;
            font-size:14px;
            outline:none;
            background:#fafafa;
        }

        .form-group input:focus{
            border-color:#8fbc67;
            background:#fff;
            box-shadow:0 0 0 3px rgba(143,188,103,0.18);
        }

        .save-btn{
            height:48px;
            background:#8fbc67;
            color:#000;
            border:none;
            border-radius:12px;
            padding:0 18px;
            font-size:14px;
            font-weight:bold;
            cursor:pointer;
        }

        .save-btn:hover{
            opacity:0.92;
        }

        .class-grid{
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
            gap:20px;
        }

        .class-card{
            background:#fff;
            border-radius:20px;
            padding:24px;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
            min-height:280px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            transition:0.2s ease;
            border:2px solid transparent;
        }

        .class-card:hover{
            transform:translateY(-3px);
            border-color:#8fbc67;
        }

        .class-top{
            margin-bottom:18px;
        }

        .course-badge{
            display:inline-block;
            background:#e8f4db;
            color:#264d00;
            padding:8px 14px;
            border-radius:999px;
            font-size:13px;
            font-weight:bold;
            margin-bottom:14px;
        }

        .subject-name{
            font-size:30px;
            font-weight:bold;
            color:#003b49;
            margin-bottom:8px;
            text-transform:uppercase;
            word-break:break-word;
        }

        .teacher-name{
            font-size:17px;
            color:#333;
            margin-bottom:14px;
            font-weight:bold;
        }

        .class-code{
            background:#f7f7f7;
            border:1px dashed #bbb;
            border-radius:12px;
            padding:12px;
            text-align:center;
            font-size:14px;
            color:#444;
            margin-bottom:18px;
        }

        .class-code strong{
            color:#003b49;
            font-size:16px;
            letter-spacing:1px;
        }

        .join-btn{
            display:inline-block;
            text-align:center;
            background:#003b49;
            color:#fff;
            text-decoration:none;
            padding:12px 20px;
            border-radius:12px;
            font-weight:bold;
            transition:0.2s ease;
        }

        .join-btn:hover{
            background:#002d38;
        }

        .empty-box{
            background:#fff;
            border-radius:18px;
            padding:40px 20px;
            text-align:center;
            color:#666;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
            font-weight:bold;
        }

        @media (max-width: 1100px){
            .form-grid{
                grid-template-columns:1fr 1fr;
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

            .welcome-box h2,
            .section-title,
            .subject-name{
                font-size:24px;
            }

            .form-grid{
                grid-template-columns:1fr;
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

        <a href="teacher.php" class="active">Dashboard</a>
        <a href="change_password.php">Change Password</a>
        <a href="../auth/logout.php">Log Out</a>
    </div>

    <div class="main-content">
        <div class="top-header">
            SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY
        </div>

        <div class="sub-header">
            TEACHER DASHBOARD
        </div>

        <div class="content">

            <div class="welcome-box">
                <h2>Hi, <?php echo htmlspecialchars($user['firstname']); ?> 👋</h2>
                <p>
                    Manage your class boards here. Create a class, get a random class code automatically, and open each class to review student requests.
                </p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="top-actions">
                <div class="section-title">My Class Boards</div>
                <button class="create-btn" onclick="toggleCreateForm()">+ Create Class</button>
            </div>

            <div class="create-form-card" id="createFormCard">
                <h3>Create New Class</h3>
                <p>Fill in the subject and course. The system will generate a random class code automatically.</p>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" placeholder="Enter subject" required>
                        </div>

                        <div class="form-group">
                            <label>Course</label>
                            <input type="text" name="course" placeholder="Example: BSIT 3" required>
                        </div>

                        <div class="form-group">
                            <button type="submit" name="create_class" class="save-btn">Save Class</button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($classes->num_rows > 0): ?>
                <div class="class-grid">
                    <?php while ($class = $classes->fetch_assoc()): ?>
                        <div class="class-card">
                            <div class="class-top">
                                <div class="course-badge"><?php echo htmlspecialchars($class['course']); ?></div>
                                <div class="subject-name"><?php echo htmlspecialchars($class['subject']); ?></div>
                                <div class="teacher-name"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></div>

                                <div class="class-code">
                                    Random Class Code<br>
                                    <strong><?php echo htmlspecialchars($class['class_code']); ?></strong>
                                </div>
                            </div>

                            <a href="teacher_request.php?class_id=<?php echo $class['id']; ?>" class="join-btn">Open Class</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-box">
                    No class boards yet. Click “Create Class” to add your first subject.
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function toggleCreateForm() {
    const formCard = document.getElementById("createFormCard");
    formCard.classList.toggle("show");
}
</script>

</body>
</html>