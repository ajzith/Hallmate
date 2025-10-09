<?php
include('db.php'); // uses $mysqli

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $role = $_POST['role']; // student or faculty
    $rollno = trim($_POST['username']);
    $name = trim($_POST['name']);
    $batch=trim($_POST['batch']);
    $department = trim($_POST['department']);
    $email= trim($_POST['email']);
    $password = $_POST['password'];

    $username = $_POST['username'];

    // --- Backend validation ---

    // Name must be letteArs and spaces only
    if (!preg_match("/^[A-Za-z\s]{2,50}$/", $name)) {
        echo "<script>alert('Invalid name! Only letters and spaces allowed.'); window.history.back();</script>";
        exit;
    }

    // Faculty username validation
    if($role === 'faculty' && !preg_match("/^[A-Za-z0-9_]{3,50}$/", $username)){
        echo "<script>alert('Invalid username! Only letters, numbers, underscores (3-50 chars) allowed.'); window.history.back();</script>";
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if username exists
    $check =$mysqli->prepare("SELECT id FROM reg WHERE rollno=? OR email=?");

 $check->bind_param("ss", $rollno, $email);
    $check->execute();
    $check->store_result();
    if($check->num_rows > 0){
        echo "<script>alert('Rollno and email already exists!'); window.history.back();</script>";
        exit;
    }
  $check->close();


    // Insert into reg table
    $stmt = $mysqli->prepare("INSERT INTO reg (rollno, name, batch, dept, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $rollno, $name, $batch, $department, $email, $hashedPassword, $role);

    

    // Insert into students or faculty table
    /*if($role === 'student'){
        $batch = trim($_POST['batch']);
        $stmt = $mysqli->prepare("INSERT INTO students (reg_no, name, batch, department, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $username, $name, $batch, $department);
    } else { // faculty
        $stmt = $mysqli->prepare("INSERT INTO faculty (name, email, department, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sss", $name, $username, $department);
    } */

    if($stmt->execute()){
        echo "<script>alert('Registration successful!'); window.location.href='../frontend/login.html';</script>";
    } else {
        echo "<script>alert('Error: Could not register user'); window.history.back();</script>";
    }

    $stmt->close();
    $mysqli->close();
}
?>
