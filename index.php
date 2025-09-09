<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['role'])) {
    header("Location: frontend/login.html");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header("Location: frontend/admin_dashboard.php"); // <-- .php now
    exit;
}

// later if you add faculty/student dashboards:
if ($_SESSION['role'] === 'faculty') {
    header("Location: frontend/faculty_dashboard.php"); // prepare for future
    exit;
}

if ($_SESSION['role'] === 'student') {
    header("Location: frontend/student_dashboard.php"); // prepare for future
    exit;
}
