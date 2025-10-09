<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

// Temporary: auto-set admin role for testing
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'admin';
}

require 'db.php'; // Database connection

// Only admin can manage faculty
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

function get_json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    if ($method === 'GET') {
        // Fetch all faculty
        $result = $mysqli->query("SELECT * FROM faculty ORDER BY id DESC");
        $faculty = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'faculty' => $faculty]);
        exit;
    }

    $input = get_json_input();

    if ($method === 'POST') {
        // Add new faculty
        $name = trim($input['name'] ?? '');
        $department = trim($input['department'] ?? '');
        $email = trim($input['email'] ?? '');

        if ($name === '' || $department === '' || $email === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO faculty (name, department, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $department, $email);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $newFaculty = $mysqli->query("SELECT * FROM faculty WHERE id = $new_id")->fetch_assoc();
            echo json_encode(['success' => true, 'faculty' => $newFaculty]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    if ($method === 'PUT') {
        // Update faculty
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $department = trim($input['department'] ?? '');
        $email = trim($input['email'] ?? '');

        if (!$id || $name === '' || $department === '' || $email === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            exit;
        }

        $stmt = $mysqli->prepare("UPDATE faculty SET name = ?, department = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $department, $email, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    if ($method === 'DELETE') {
        // Delete faculty
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing faculty ID']);
            exit;
        }

        $stmt = $mysqli->prepare("DELETE FROM faculty WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        exit;
    }

    // Unsupported method
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
