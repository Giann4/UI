<?php
session_start();
include("../config/db.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "../vendor/autoload.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student' || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = intval($_SESSION['user_id']);

/* CHECK RECIPIENT EMAIL FROM STUDENT RESULT PAGE */
if (!isset($_POST['recipient_email']) || empty(trim($_POST['recipient_email']))) {
    header("Location: student_result.php?send=empty");
    exit;
}

$recipient_email = trim($_POST['recipient_email']);

if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
    header("Location: student_result.php?send=invalid");
    exit;
}

/* EMAIL SETTINGS */
$sender_email = "markparedes54321@gmail.com";
$sender_app_password = "jmhy tbhz wiou mzma";

/* GET STUDENT INFO */
$user_stmt = $conn->prepare("
    SELECT firstname, lastname, email, contact_number, course
    FROM users
    WHERE id = ? AND role = 'student'
");
$user_stmt->bind_param("i", $student_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: student_result.php?send=failed");
    exit;
}

/* GET CLEARANCE RESULT */
$result_stmt = $conn->prepare("
    SELECT 
        cr.subject,
        cr.result,
        cr.comment,
        cr.date_signed,
        CONCAT(u.lastname, ', ', u.firstname) AS instructor_name
    FROM class_requests cr
    LEFT JOIN teacher_classes tc ON cr.class_id = tc.id
    LEFT JOIN users u ON tc.teacher_id = u.id
    WHERE cr.student_id = ? 
    AND cr.status = 'Reviewed'
    ORDER BY cr.id DESC
");
$result_stmt->bind_param("i", $student_id);
$result_stmt->execute();
$results = $result_stmt->get_result();

$total = 0;
$passed = 0;
$failed = 0;
$incomplete = 0;
$table_rows = "";

while ($row = $results->fetch_assoc()) {
    $total++;

    if ($row['result'] === 'Passed') $passed++;
    if ($row['result'] === 'Failed') $failed++;
    if ($row['result'] === 'Incomplete') $incomplete++;

    $subject = htmlspecialchars($row['subject']);
    $instructor = htmlspecialchars($row['instructor_name'] ?: 'N/A');
    $comment = htmlspecialchars($row['comment'] ?: 'No comment');
    $status = htmlspecialchars($row['result']);
    $date = !empty($row['date_signed']) ? date("F d, Y", strtotime($row['date_signed'])) : 'N/A';

    $table_rows .= "
        <tr>
            <td>{$total}</td>
            <td>{$subject}</td>
            <td>{$instructor}</td>
            <td>{$comment}</td>
            <td><strong>{$status}</strong></td>
            <td>{$date}</td>
        </tr>
    ";
}

if ($total <= 0) {
    header("Location: student_result.php?send=failed");
    exit;
}

$student_name = strtoupper($user['lastname'] . ", " . $user['firstname']);
$course = htmlspecialchars($user['course'] ?: 'N/A');
$email = htmlspecialchars($user['email']);
$contact = htmlspecialchars($user['contact_number'] ?: 'N/A');

$email_subject = "Student Clearance Result - " . $student_name;

$email_body = "
<html>
<head>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f8fafc;
        color: #111827;
        padding: 20px;
    }
    .box {
        max-width: 900px;
        margin: auto;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        padding: 25px;
    }
    h2 {
        color: #064e3b;
        text-align: center;
        margin-bottom: 5px;
    }
    .sub {
        text-align: center;
        font-weight: bold;
        color: #475569;
        margin-top: 0;
    }
    .line {
        border-top: 3px solid #22c55e;
        margin: 18px 0;
    }
    .summary {
        background: #f0fdf4;
        border-left: 5px solid #22c55e;
        padding: 15px;
        border-radius: 10px;
        margin: 15px 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        margin-top: 15px;
    }
    th {
        background: #dcfce7;
    }
    th, td {
        border: 1px solid #d1d5db;
        padding: 8px;
        text-align: center;
    }
    .note {
        font-size: 12px;
        color: #64748b;
    }
</style>
</head>
<body>
<div class='box'>
    <h2>SOUTHERN PHILIPPINES INSTITUTE OF SCIENCE AND TECHNOLOGY</h2>
    <p class='sub'>CLEARANCE COLLEGE DEPARTMENT</p>
    <div class='line'></div>

    <h3>Student Clearance Result</h3>

    <p>Good day,</p>
    <p>Please see below the official clearance result of the student.</p>

    <p><strong>Student Name:</strong> {$student_name}</p>
    <p><strong>Course:</strong> {$course}</p>
    <p><strong>Email:</strong> {$email}</p>
    <p><strong>Contact:</strong> {$contact}</p>

    <div class='summary'>
        <strong>Summary:</strong><br>
        Total Reviewed Subjects: {$total}<br>
        Passed: {$passed}<br>
        Failed: {$failed}<br>
        Incomplete: {$incomplete}
    </div>

    <table>
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
            {$table_rows}
        </tbody>
    </table>

    <p style='margin-top:20px;'>Thank you.</p>
    <p class='note'><strong>This is an automated email from the Online Clearance System. Please do not reply.</strong></p>
</div>
</body>
</html>
";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $sender_email;
    $mail->Password = $sender_app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom($sender_email, "Online Clearance System");
    $mail->addAddress($recipient_email);

    $mail->isHTML(true);
    $mail->Subject = $email_subject;
    $mail->Body = $email_body;

    $mail->send();

    /* EMAIL LOG */
    $log_stmt = $conn->prepare("
        INSERT INTO registrar_email_logs 
        (student_id, registrar_email, email_subject, status)
        VALUES (?, ?, ?, 'Sent')
    ");
    $log_stmt->bind_param("iss", $student_id, $recipient_email, $email_subject);
    $log_stmt->execute();

    header("Location: student_result.php?send=success");
    exit;

} catch (Exception $e) {
    header("Location: student_result.php?send=failed");
    exit;
}
?>