<?php
session_start();
include("../config/db.php");

$id = $_SESSION['id'];
$res = $conn->query("SELECT * FROM clearances WHERE student_id='$id'");

while($row = $res->fetch_assoc()){
    echo $row['department'] . " - " . $row['status'] . "<br>";
}