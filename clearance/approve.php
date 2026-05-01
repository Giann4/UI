<?php
include("../config/db.php");

if(isset($_GET['id'])){
    $id = $_GET['id'];
    $conn->query("UPDATE clearances SET status='approved' WHERE id='$id'");
}

$res = $conn->query("SELECT * FROM clearances");

while($row = $res->fetch_assoc()){
    echo $row['department'] . " - " . $row['status'];
    echo " <a href='?id=".$row['id']."'>Approve</a><br>";
}