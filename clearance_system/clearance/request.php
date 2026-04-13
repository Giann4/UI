<?php
session_start();
include("../config/db.php");

if($_POST){
    $dept = $_POST['department'];
    $student = $_SESSION['id'];

    $conn->query("INSERT INTO clearances(student_id,department)
    VALUES('$student','$dept')");
}
?>

<form method="POST">
<select name="department">
<option>Library</option>
<option>Registrar</option>
<option>Guidance</option>
</select>

<button>Request Clearance</button>
</form>