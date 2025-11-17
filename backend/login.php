<?php
// backend/login.php
ini_set('display_errors', 0); 
ini_set('log_errors', 1);   

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input) || !isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$username = trim($input['username']);
$username = strtolower($username);
$password = $input['password'];

$stmt = $mysqli->prepare("SELECT id, password_hash, role, is_active, student_id, faculty_id FROM login WHERE username = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database prepare failed.']);
    exit;
}
$stmt->bind_param("s", $username); 
$stmt->execute(); 
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401); 
    echo json_encode(['error' => 'User not found.']);
    exit;
}

$row = $res->fetch_assoc();

if ($row['is_active'] == 0 && ($row['role'] === 'student' || $row['role'] === 'faculty') ) {
    http_response_code(403); 
    echo json_encode(['error' => 'Your account is pending admin approval.']);
    exit;
}

if (!password_verify($password, $row['password_hash'])) {
    http_response_code(401); 
    echo json_encode(['error' => 'Invalid password.']);
    exit;
}

session_regenerate_id(true); 
$_SESSION['username'] = $username;
$_SESSION['role'] = $row['role'];

if ($row['role'] === 'student') { $_SESSION['user_id'] = (int)$row['student_id']; } 
else if ($row['role'] === 'faculty') { $_SESSION['user_id'] = (int)$row['faculty_id']; } 
else { $_SESSION['user_id'] = (int)$row['id']; } 


if (($_SESSION['role'] === 'student' || $_SESSION['role'] === 'faculty') && empty($_SESSION['user_id'])) {
    http_response_code(500);
    echo json_encode(['error' => 'User configuration error. Please contact admin.']);
    exit;
}

$name = "User"; 
if ($row['role'] === 'student' && !empty($row['student_id'])) {
    $stmt_name = $mysqli->prepare("SELECT name FROM students WHERE id = ?");
    $stmt_name->bind_param("i", $row['student_id']);
    $stmt_name->execute();
    $name_res = $stmt_name->get_result();
    if($name_row = $name_res->fetch_assoc()) $name = $name_row['name'];
    $stmt_name->close();
} else if ($row['role'] === 'faculty' && !empty($row['faculty_id'])) {
    $stmt_name = $mysqli->prepare("SELECT name FROM faculty WHERE id = ?");
    $stmt_name->bind_param("i", $row['faculty_id']);
    $stmt_name->execute();
    $name_res = $stmt_name->get_result();
    if($name_row = $name_res->fetch_assoc()) $name = $name_row['name'];
    $stmt_name->close();
} else if ($row['role'] === 'admin') {
    $name = "Admin"; // Admin name
}
$_SESSION['name'] = $name;

echo json_encode(['success' => true, 'role' => $row['role']]);

$stmt->close();
$mysqli->close();
?>