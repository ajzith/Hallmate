<?php
// backend/login.php  (replace existing)
ini_set('display_errors', 0);       // don't print PHP warnings as HTML
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global exception/error handler -> return JSON
set_exception_handler(function($e){
    http_response_code(500);
    error_log("Uncaught exception in login.php: " . $e->getMessage());
    echo json_encode(['error' => 'Server error. Check logs.']);
    exit;
});

set_error_handler(function($errno, $errstr, $errfile, $errline){
    // convert PHP warnings/notices to exceptions so they become JSON
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? $input['password'] : '';

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username or password']);
    exit;
}

$stmt = $mysqli->prepare("SELECT id, password_hash, role FROM login WHERE username = ?");
if (!$stmt) {
    http_response_code(500);
    error_log("DB prepare failed in login.php: " . $mysqli->error);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    // return JSON; do not print HTML
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    $stmt->close();
    $mysqli->close();
    exit;
}

$row = $res->fetch_assoc();
$hash = $row['password_hash'];

if (password_verify($password, $hash)) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $row['role'];

    echo json_encode(['success' => true, 'role' => $row['role']]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
}

$stmt->close();
$mysqli->close();
exit;
