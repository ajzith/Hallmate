<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$input = json_decode(file_get_contents('php://input'), true);
$login = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($login) || empty($password)) {
    echo json_encode(['error' => 'Missing username or password']);
    exit;
}


$stmt = $mysqli->prepare("SELECT id, username, password, role FROM admin WHERE username = ?");
$stmt->bind_param("s", $login);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Admin login
    $row = $res->fetch_assoc();
    if (!password_verify($password, $row['password'])) {
        echo json_encode(['error'=>'Wrong Password']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['role'] = $row['role'];

    $log = $mysqli->prepare("INSERT INTO login_history (user_id, login_timestamp) VALUES (?, NOW())");
    $log->bind_param("i", $_SESSION['user_id']);
    $log->execute();
    $log->close();

echo json_encode([
        'success'=>true,
        'role'=>$row['role'],
        'redirect'=>'/hallmate/frontend/admin_dashboard.html'
    ]);
    exit;
}

// Fetch user
$stmt = $mysqli->prepare("SELECT id, name, password, role FROM reg WHERE rollno = ?");
$stmt->bind_param("s", $login);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['error'=>'Invalid credentials']);
    exit;
}

$row = $res->fetch_assoc();

// Verify password
if (!password_verify($password, $row['password'])) {
    echo json_encode(['error'=>'Wrong Password']);
    exit;
}

// Update last login
$user_id = (int)$row['id'];
$mysqli->query("UPDATE reg SET last_login_time = NOW() WHERE id = $user_id");

// Insert login history
$log = $mysqli->prepare("INSERT INTO login_history (user_id, login_timestamp) VALUES (?, NOW())");
$log->bind_param("i", $user_id);
$log->execute();
$log->close();


// Set session
session_regenerate_id(true);
$_SESSION['user_id'] = $user_id;
$_SESSION['rollno']  = $login;
$_SESSION['name']    = $row['name'];
$_SESSION['role']    = $row['role'];


echo json_encode([
    'success'=>true,
    'role'=>$row['role'],
    'redirect'=>'/hallmate/frontend/student_dashboard.html'
]);
exit;
